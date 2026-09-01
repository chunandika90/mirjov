<?php
$pageTitle = 'Form Surat Jalan';
$activeMenu = 'manufaktur_surat_jalan';
require __DIR__ . '/includes/header.php';
require_module_access('manufaktur_surat_jalan');
require_once __DIR__ . '/../backoffice-shared/stock.php';

$pdo = db();
$flash = null;
if (!empty($_GET['stock_warning'])) {
    $flash = ['error', 'Tersimpan, tapi ada stok yang gak cukup dikurangin: ' . $_GET['stock_warning']];
}

// User non-Owner yang lokasinya dibatasin (lihat Master User) cuma boleh liat/bikin Surat
// Jalan buat gudang asal dia sendiri — NULL berarti gak dibatasin (Owner atau gak di-assign).
$myWarehouseId = user_location_restriction();
$myWarehouseName = null;
if ($myWarehouseId !== null) {
    $mwStmt = $pdo->prepare('SELECT name FROM warehouses WHERE id=? AND organization_id=?');
    $mwStmt->execute([$myWarehouseId, $org['organization_id']]);
    $myWarehouseName = $mwStmt->fetchColumn() ?: null;
}

/** Ambil angka pertama dari qty bebas-teks (cth. "52 mtr" -> 52, "? mtr" -> null karena gak kebaca). */
function sj_parse_qty(string $qtyStr): ?float
{
    if (preg_match('/-?\d+(\.\d+)?/', $qtyStr, $m)) return (float) $m[0];
    return null;
}

function sj_default_warehouse_id(PDO $pdo, int $orgId): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM warehouses WHERE organization_id=? AND is_default=1 AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$orgId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

/**
 * Kurangin stok FIFO buat tiap baris SJ yang barusan disimpan — dipanggil CUMA pas
 * dokumen pertama kali dibuat (bukan pas diedit, biar gak dobel kurang tiap disimpan ulang).
 * Kalau stok gak cukup atau qty-nya gak kebaca angka, di-skip aja (gak gagalin simpan SJ-nya),
 * tapi dikumpulin jadi warning buat ditampilin.
 *
 * Kalau tujuannya "Antar Gudang" (transfer), stok yang keluar dari gudang asal OTOMATIS
 * masuk lagi ke gudang tujuan (pakai harga rata-rata dari FIFO yang barusan kepakai) —
 * jadi total stok organisasi tetap sama, cuma pindah lokasi.
 */
function sj_consume_stock_for_header(PDO $pdo, int $orgId, int $headerId, ?int $warehouseId, string $tujuanType, ?int $tujuanWarehouseId): array
{
    $warnings = [];
    $whId = $warehouseId ?: sj_default_warehouse_id($pdo, $orgId);
    if (!$whId) return ['Gudang belum ditentukan, stok gak dikurangin.'];

    $stmt = $pdo->prepare('SELECT id, product_id, product_name_snapshot, qty FROM manufaktur_surat_jalan_lines WHERE surat_jalan_id=?');
    $stmt->execute([$headerId]);
    foreach ($stmt->fetchAll() as $il) {
        if (!$il['product_id']) continue;
        $qtyNum = sj_parse_qty((string) ($il['qty'] ?? ''));
        if ($qtyNum === null || $qtyNum <= 0) continue;
        try {
            $unitCost = fifo_consume_stock($orgId, $whId, (int) $il['product_id'], $qtyNum, 'manufaktur_surat_jalan', $headerId);

            if ($tujuanType === 'gudang' && $tujuanWarehouseId) {
                $pdo->prepare(
                    'INSERT INTO stock_ledger (organization_id, warehouse_id, product_id, direction, qty, qty_remaining, unit_cost, ref_type, ref_id) VALUES (?,?,?,"in",?,?,?,"manufaktur_surat_jalan",?)'
                )->execute([$orgId, $tujuanWarehouseId, (int) $il['product_id'], $qtyNum, $qtyNum, $unitCost, $headerId]);
            }
        } catch (Throwable $e) {
            $warnings[] = $il['product_name_snapshot'] . ' (' . $e->getMessage() . ')';
        }
    }
    return $warnings;
}

const SJ_STATUS_LABELS = ['draft' => 'Draft', 'dikirim' => 'Dikirim', 'diterima' => 'Diterima', 'void' => 'Void'];

/** Nomor dokumen: {nomor 4 digit}/SJ/MJ/{bulan romawi}/{tahun 2 digit} — sesuai format cetakan Surat Jalan yang sudah dipakai manual selama ini. */
function sj_roman_month(int $month): string
{
    $map = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    return $map[$month] ?? (string) $month;
}

function next_manufaktur_sj_number(PDO $pdo, int $organizationId): string
{
    $year = (int) date('Y');
    $month = (int) date('n');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT last_number FROM doc_counters WHERE organization_id=? AND doc_type=? AND year=? FOR UPDATE');
        $stmt->execute([$organizationId, 'SJ', $year]);
        $row = $stmt->fetch();
        if ($row) {
            $next = (int) $row['last_number'] + 1;
            $pdo->prepare('UPDATE doc_counters SET last_number=? WHERE organization_id=? AND doc_type=? AND year=?')
                ->execute([$next, $organizationId, 'SJ', $year]);
        } else {
            $next = 1;
            $pdo->prepare('INSERT INTO doc_counters (organization_id, doc_type, year, last_number) VALUES (?,?,?,?)')
                ->execute([$organizationId, 'SJ', $year, $next]);
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTransaction) $pdo->rollBack();
        throw $e;
    }
    return sprintf('%04d/SJ/MJ/%s/%s', $next, sj_roman_month($month), date('y'));
}

function touch_sj_header(PDO $pdo, int $headerId, int $userId): void
{
    $pdo->prepare('UPDATE manufaktur_surat_jalan SET updated_by=?, updated_at=NOW() WHERE id=?')->execute([$userId, $headerId]);
}

function find_or_create_sj_kepada(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare("SELECT id FROM contacts WHERE organization_id=? AND name=? LIMIT 1");
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO contacts (organization_id, type, name) VALUES (?, 'customer', ?)")->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

function find_or_create_sj_warehouse(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM warehouses WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare('INSERT INTO warehouses (organization_id, name) VALUES (?,?)')->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

function find_or_create_sj_product(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO products (organization_id, name, unit) VALUES (?,?,'pcs')")->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

function save_sj_lines(PDO $pdo, int $headerId, array $lines): int
{
    $stmt = $pdo->prepare('INSERT INTO manufaktur_surat_jalan_lines (surat_jalan_id, product_id, product_name_snapshot, item_code, qty, kemasan, baik, lengkap, keterangan, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
    global $org;
    $count = 0;
    foreach ($lines as $i => $line) {
        $name = trim($line['product_name'] ?? '');
        if ($name === '') continue;
        $productId = find_or_create_sj_product($pdo, $org['organization_id'], $name);
        $itemCode = trim($line['item_code'] ?? '') ?: null;
        $qty = trim($line['qty'] ?? '') ?: null;
        $kemasan = trim($line['kemasan'] ?? '') ?: null;
        $baik = isset($line['baik']) ? 1 : 0;
        $lengkap = isset($line['lengkap']) ? 1 : 0;
        $keterangan = trim($line['keterangan'] ?? '') ?: null;
        $stmt->execute([$headerId, $productId, $name, $itemCode, $qty, $kemasan, $baik, $lengkap, $keterangan, $count]);
        $count++;
    }
    return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_manufaktur_sj') {
            require_module_access('manufaktur_surat_jalan', 'can_create');
            $tanggal = $_POST['tanggal'] ?? '';
            $tujuanType = ($_POST['tujuan_type'] ?? 'customer') === 'gudang' ? 'gudang' : 'customer';
            $kepadaName = trim($_POST['kepada_name'] ?? '');
            $tujuanWarehouseId = (int) ($_POST['tujuan_warehouse_id'] ?? 0) ?: null;
            $warehouseName = trim($_POST['warehouse_name'] ?? '');
            // Jaga-jaga field readonly di-bypass lewat devtools — paksa balik ke lokasi
            // yang di-assign kalau user-nya dibatasin.
            $myWid = user_location_restriction();
            if ($myWid !== null) {
                $mwStmt = $pdo->prepare('SELECT name FROM warehouses WHERE id=? AND organization_id=?');
                $mwStmt->execute([$myWid, $org['organization_id']]);
                $warehouseName = $mwStmt->fetchColumn() ?: $warehouseName;
            }
            $nomorQuotation = trim($_POST['nomor_quotation'] ?? '') ?: null;
            $nomorOrderPo = trim($_POST['nomor_order_po'] ?? '') ?: null;
            $nomorPolisi = trim($_POST['nomor_polisi'] ?? '') ?: null;
            $driverName = trim($_POST['driver_name'] ?? '') ?: null;
            $keterangan = trim($_POST['keterangan'] ?? '') ?: null;
            $lines = $_POST['lines'] ?? [];

            if (!$tanggal) throw new RuntimeException('Tanggal wajib diisi.');
            if (!$lines) throw new RuntimeException('Minimal 1 baris barang.');

            $pdo->beginTransaction();
            try {
                $warehouseId = $warehouseName !== '' ? find_or_create_sj_warehouse($pdo, $org['organization_id'], $warehouseName) : null;

                if ($tujuanType === 'gudang') {
                    if (!$tujuanWarehouseId) throw new RuntimeException('Gudang tujuan wajib dipilih.');
                    if ($warehouseId && $tujuanWarehouseId === $warehouseId) throw new RuntimeException('Gudang tujuan gak boleh sama dengan gudang asal.');
                    $twStmt = $pdo->prepare('SELECT name FROM warehouses WHERE id=? AND organization_id=?');
                    $twStmt->execute([$tujuanWarehouseId, $org['organization_id']]);
                    $tujuanWarehouseRow = $twStmt->fetch();
                    if (!$tujuanWarehouseRow) throw new RuntimeException('Gudang tujuan gak valid.');
                    $kepadaId = null;
                    $kepadaSnapshot = $tujuanWarehouseRow['name'];
                } else {
                    if ($kepadaName === '') throw new RuntimeException('Kepada wajib diisi.');
                    $kepadaId = find_or_create_sj_kepada($pdo, $org['organization_id'], $kepadaName);
                    $kepadaSnapshot = $kepadaName;
                    $tujuanWarehouseId = null;
                }

                $docNumber = next_manufaktur_sj_number($pdo, $org['organization_id']);
                $pdo->prepare('INSERT INTO manufaktur_surat_jalan (organization_id, doc_number, tanggal, nomor_quotation, nomor_order_po, nomor_polisi, kepada_id, kepada_snapshot, tujuan_type, tujuan_warehouse_id, warehouse_id, warehouse_snapshot, driver_name, keterangan, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$org['organization_id'], $docNumber, $tanggal, $nomorQuotation, $nomorOrderPo, $nomorPolisi, $kepadaId, $kepadaSnapshot, $tujuanType, $tujuanWarehouseId, $warehouseId, $warehouseName ?: null, $driverName, $keterangan, $user['id']]);
                $headerId = (int) $pdo->lastInsertId();

                $lineCount = save_sj_lines($pdo, $headerId, $lines);
                if (!$lineCount) throw new RuntimeException('Minimal 1 baris barang yang valid.');

                $pdo->commit();

                // Kurangin stok SETELAH commit (biar transaksi SJ tetap sukses walau ada baris yang stoknya kurang).
                $stockWarnings = sj_consume_stock_for_header($pdo, $org['organization_id'], $headerId, $warehouseId, $tujuanType, $tujuanWarehouseId);
                $redirectUrl = 'manufaktur-surat-jalan.php?id=' . $headerId;
                if ($stockWarnings) $redirectUrl .= '&stock_warning=' . urlencode(implode('; ', $stockWarnings));
                header('Location: ' . $redirectUrl);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'update_manufaktur_sj_full') {
            require_module_access('manufaktur_surat_jalan', 'can_edit');
            $headerId = (int) ($_POST['manufaktur_sj_id'] ?? 0);
            $check = $pdo->prepare('SELECT id, warehouse_id FROM manufaktur_surat_jalan WHERE id=? AND organization_id=?');
            $check->execute([$headerId, $org['organization_id']]);
            $checkRow = $check->fetch();
            if (!$checkRow) throw new RuntimeException('Dokumen tidak ditemukan.');
            $myWid = user_location_restriction();
            if ($myWid !== null && (int) $checkRow['warehouse_id'] !== $myWid) throw new RuntimeException('Dokumen ini bukan di lokasi kamu.');

            $tanggal = $_POST['tanggal'] ?? '';
            $tujuanType = ($_POST['tujuan_type'] ?? 'customer') === 'gudang' ? 'gudang' : 'customer';
            $kepadaName = trim($_POST['kepada_name'] ?? '');
            $tujuanWarehouseId = (int) ($_POST['tujuan_warehouse_id'] ?? 0) ?: null;
            $warehouseName = trim($_POST['warehouse_name'] ?? '');
            if ($myWid !== null) {
                $mwStmt = $pdo->prepare('SELECT name FROM warehouses WHERE id=? AND organization_id=?');
                $mwStmt->execute([$myWid, $org['organization_id']]);
                $warehouseName = $mwStmt->fetchColumn() ?: $warehouseName;
            }
            $nomorQuotation = trim($_POST['nomor_quotation'] ?? '') ?: null;
            $nomorOrderPo = trim($_POST['nomor_order_po'] ?? '') ?: null;
            $nomorPolisi = trim($_POST['nomor_polisi'] ?? '') ?: null;
            $driverName = trim($_POST['driver_name'] ?? '') ?: null;
            $keterangan = trim($_POST['keterangan'] ?? '') ?: null;
            $lines = $_POST['lines'] ?? [];

            if (!$tanggal) throw new RuntimeException('Tanggal wajib diisi.');
            if (!$lines) throw new RuntimeException('Minimal 1 baris barang.');

            $pdo->beginTransaction();
            try {
                $warehouseId = $warehouseName !== '' ? find_or_create_sj_warehouse($pdo, $org['organization_id'], $warehouseName) : null;

                if ($tujuanType === 'gudang') {
                    if (!$tujuanWarehouseId) throw new RuntimeException('Gudang tujuan wajib dipilih.');
                    $twStmt = $pdo->prepare('SELECT name FROM warehouses WHERE id=? AND organization_id=?');
                    $twStmt->execute([$tujuanWarehouseId, $org['organization_id']]);
                    $tujuanWarehouseRow = $twStmt->fetch();
                    if (!$tujuanWarehouseRow) throw new RuntimeException('Gudang tujuan gak valid.');
                    $kepadaId = null;
                    $kepadaSnapshot = $tujuanWarehouseRow['name'];
                } else {
                    if ($kepadaName === '') throw new RuntimeException('Kepada wajib diisi.');
                    $kepadaId = find_or_create_sj_kepada($pdo, $org['organization_id'], $kepadaName);
                    $kepadaSnapshot = $kepadaName;
                    $tujuanWarehouseId = null;
                }

                $pdo->prepare('UPDATE manufaktur_surat_jalan SET tanggal=?, nomor_quotation=?, nomor_order_po=?, nomor_polisi=?, kepada_id=?, kepada_snapshot=?, tujuan_type=?, tujuan_warehouse_id=?, warehouse_id=?, warehouse_snapshot=?, driver_name=?, keterangan=? WHERE id=?')
                    ->execute([$tanggal, $nomorQuotation, $nomorOrderPo, $nomorPolisi, $kepadaId, $kepadaSnapshot, $tujuanType, $tujuanWarehouseId, $warehouseId, $warehouseName ?: null, $driverName, $keterangan, $headerId]);

                $pdo->prepare('DELETE FROM manufaktur_surat_jalan_lines WHERE surat_jalan_id=?')->execute([$headerId]);
                save_sj_lines($pdo, $headerId, $lines);

                $pdo->commit();
                touch_sj_header($pdo, $headerId, $user['id']);
                header('Location: manufaktur-surat-jalan.php?id=' . $headerId);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'update_status_sj') {
            require_module_access('manufaktur_surat_jalan', 'can_edit');
            $id = (int) ($_POST['manufaktur_sj_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (array_key_exists($status, SJ_STATUS_LABELS)) {
                $pdo->prepare('UPDATE manufaktur_surat_jalan SET status=? WHERE id=? AND organization_id=?')->execute([$status, $id, $org['organization_id']]);
                touch_sj_header($pdo, $id, $user['id']);
                $flash = ['ok', 'Status diperbarui.'];
            }
        } elseif ($action === 'delete_manufaktur_sj') {
            require_module_access('manufaktur_surat_jalan', 'can_delete');
            $id = (int) ($_POST['manufaktur_sj_id'] ?? 0);
            $pdo->prepare('UPDATE manufaktur_surat_jalan SET deleted_by=?, deleted_at=NOW() WHERE id=? AND organization_id=?')
                ->execute([$user['id'], $id, $org['organization_id']]);
            $flash = ['ok', 'Surat Jalan ditandai dihapus (void).'];
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }
}

$kepadaList = $pdo->prepare("SELECT id, name FROM contacts WHERE organization_id=? AND type IN ('customer','both') ORDER BY name");
$kepadaList->execute([$org['organization_id']]);
$kepadaList = $kepadaList->fetchAll();

$warehouseList = $pdo->prepare('SELECT id, name FROM warehouses WHERE organization_id=? AND deleted_at IS NULL ORDER BY name');
$warehouseList->execute([$org['organization_id']]);
$warehouseList = $warehouseList->fetchAll();

$productsList = $pdo->prepare('SELECT id, name FROM products WHERE organization_id=? ORDER BY name');
$productsList->execute([$org['organization_id']]);
$productsList = $productsList->fetchAll();

$isNewForm = isset($_GET['new']);
$editId = (int) ($_GET['edit'] ?? 0);
$isEditMode = $editId > 0;
$editHeader = null;
$editLines = [];
if ($isEditMode) {
    $eStmt = $pdo->prepare('SELECT * FROM manufaktur_surat_jalan WHERE id=? AND organization_id=?');
    $eStmt->execute([$editId, $org['organization_id']]);
    $editHeader = $eStmt->fetch() ?: null;
    if ($editHeader) {
        $elStmt = $pdo->prepare('SELECT * FROM manufaktur_surat_jalan_lines WHERE surat_jalan_id=? ORDER BY sort_order, id');
        $elStmt->execute([$editId]);
        $editLines = $elStmt->fetchAll();
    } else {
        $isEditMode = false;
    }
}

if (!$isNewForm && !$isEditMode) {
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

    // User non-Owner yang dibatasin ke 1 lokasi cuma boleh liat Surat Jalan yang gudang
    // asal-nya (warehouse_id) sama kayak lokasi dia terdaftar ($myWarehouseId di atas).
    $railWhere = "h.organization_id=? AND DATE_FORMAT(h.created_at,'%Y-%m')=?";
    $railParams = [$org['organization_id'], $month];
    if ($myWarehouseId !== null) { $railWhere .= " AND h.warehouse_id=?"; $railParams[] = $myWarehouseId; }
    $railStmt = $pdo->prepare("SELECT h.* FROM manufaktur_surat_jalan h WHERE $railWhere ORDER BY h.created_at DESC");
    $railStmt->execute($railParams);
    $railItems = $railStmt->fetchAll();

    $selectedId = (int) ($_GET['id'] ?? ($railItems[0]['id'] ?? 0));
    $selected = null;
    $selectedLines = [];
    foreach ($railItems as $r) { if ((int) $r['id'] === $selectedId) { $selected = $r; break; } }
    if (!$selected && $selectedId) {
        $sWhere = 'id=? AND organization_id=?';
        $sParams = [$selectedId, $org['organization_id']];
        if ($myWarehouseId !== null) { $sWhere .= ' AND warehouse_id=?'; $sParams[] = $myWarehouseId; }
        $sStmt = $pdo->prepare("SELECT * FROM manufaktur_surat_jalan WHERE $sWhere");
        $sStmt->execute($sParams);
        $selected = $sStmt->fetch() ?: null;
    }
    if ($selected) {
        $userNameStmt = $pdo->prepare('SELECT name FROM users WHERE id=?');
        $fetchUserName = function ($userId) use ($userNameStmt) {
            if (!$userId) return null;
            $userNameStmt->execute([$userId]);
            return $userNameStmt->fetch()['name'] ?? null;
        };
        $headerCreatedByName = $fetchUserName($selected['created_by']);
        $headerUpdatedByName = $fetchUserName($selected['updated_by']);
        $headerDeletedByName = $fetchUserName($selected['deleted_by']);

        $lStmt = $pdo->prepare('SELECT * FROM manufaktur_surat_jalan_lines WHERE surat_jalan_id=? ORDER BY sort_order, id');
        $lStmt->execute([$selected['id']]);
        $selectedLines = $lStmt->fetchAll();
    }
}
?>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<?php if ($isNewForm || $isEditMode): ?>
  <style>
    .sj-page-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; }
    .sj-page-head h2 { margin:0 0 4px; font-size:20px; }
    .sj-page-head p { margin:0; font-size:13px; color:var(--ink-muted); }
    .sj-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:18px; box-shadow:var(--shadow-card); }
    .sj-section-head { margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; }
    .sj-section-head h3 { margin:0 0 2px; font-size:14px; }
    .sj-section-head p { margin:0; font-size:12px; color:var(--ink-muted); }
    .sj-submit-row { display:flex; justify-content:flex-end; gap:10px; }

    table.sj-box-table { width:100%; border-collapse:collapse; table-layout:fixed; }
    table.sj-box-table td { border:1px solid var(--border); padding:0; vertical-align:top; }
    table.sj-box-table td.lbl { width:22%; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); background:oklch(0.97 0.003 90); padding:7px 9px; }
    table.sj-box-table td.field-cell { padding:2px 4px; }
    table.sj-box-table input, table.sj-box-table textarea { width:100%; border:none; background:transparent; padding:6px 6px; font-size:13px; box-sizing:border-box; font-family:inherit; }
    table.sj-box-table input:focus, table.sj-box-table textarea:focus { outline:2px solid var(--accent); outline-offset:-2px; border-radius:3px; }

    table.sj-line-table { width:100%; border-collapse:collapse; }
    table.sj-line-table th, table.sj-line-table td { border:1px solid var(--border); padding:0; vertical-align:middle; }
    table.sj-line-table th { background:oklch(0.97 0.003 90); font-size:10px; font-weight:700; text-transform:uppercase; padding:7px 6px; text-align:center; }
    table.sj-line-table td input[type=text] { width:100%; border:none; background:transparent; padding:7px 6px; font-size:12.5px; box-sizing:border-box; }
    table.sj-line-table td input:focus { outline:2px solid var(--accent); outline-offset:-2px; }
    table.sj-line-table td.chk-cell { text-align:center; }
    table.sj-line-table td.no-cell { text-align:center; font-size:12px; color:var(--ink-muted); width:32px; }
    table.sj-line-table td.rm-cell { text-align:center; width:36px; }
    .sj-add-row-btn { margin-top:10px; }
    .sj-keterangan-box { margin-top:12px; padding:12px 16px; border:1px solid var(--border); border-radius:10px; background:oklch(0.98 0.003 90); font-size:13px; white-space:pre-wrap; }
    .sj-keterangan-box .k { display:block; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:6px; }
  </style>

  <div class="sj-page-head">
    <div>
      <h2><?= $isEditMode ? 'Edit Surat Jalan' : 'Buat Surat Jalan' ?></h2>
      <p>Dikirimkan barang-barang sesuai daftar di bawah, ditandatangani gudang, driver &amp; penerima.</p>
    </div>
    <a class="btn btn-sm btn-ghost" href="manufaktur-surat-jalan.php<?= $isEditMode ? '?id=' . $editId : '' ?>">Batal</a>
  </div>

  <form method="post" id="sj-form">
    <?= csrf_field() ?>
    <?php if ($isEditMode): ?>
      <input type="hidden" name="action" value="update_manufaktur_sj_full">
      <input type="hidden" name="manufaktur_sj_id" value="<?= $editHeader['id'] ?>">
    <?php else: ?>
      <input type="hidden" name="action" value="save_manufaktur_sj">
    <?php endif; ?>

    <div class="sj-section">
      <div class="sj-section-head"><h3>Informasi Umum</h3></div>
      <table class="sj-box-table">
        <?php $editTujuanType = $isEditMode ? ($editHeader['tujuan_type'] ?? 'customer') : 'customer'; ?>
        <tr>
          <td class="lbl">Tanggal</td><td class="field-cell"><input type="date" name="tanggal" value="<?= $isEditMode ? htmlspecialchars($editHeader['tanggal']) : date('Y-m-d') ?>" required></td>
          <td class="lbl">Nomor Order/PO</td><td class="field-cell"><input type="text" name="nomor_order_po" value="<?= $isEditMode ? htmlspecialchars($editHeader['nomor_order_po'] ?? '') : '' ?>"></td>
        </tr>
        <tr>
          <td class="lbl" id="sj-kepada-label">Kepada</td>
          <td class="field-cell"><input type="text" id="sj-kepada-input" name="kepada_name" value="<?= $isEditMode && $editTujuanType === 'customer' ? htmlspecialchars($editHeader['kepada_snapshot'] ?? '') : '' ?>" placeholder="cth. MJJ" autocomplete="off" <?= $editTujuanType === 'gudang' ? 'style="display:none;"' : 'required' ?>></td>
          <td class="lbl">Driver</td><td class="field-cell"><input type="text" name="driver_name" value="<?= $isEditMode ? htmlspecialchars($editHeader['driver_name'] ?? '') : '' ?>"></td>
        </tr>
        <tr>
          <td class="lbl">Nomor Quotation</td><td class="field-cell"><input type="text" name="nomor_quotation" value="<?= $isEditMode ? htmlspecialchars($editHeader['nomor_quotation'] ?? '') : '' ?>"></td>
          <td class="lbl"></td><td class="field-cell"></td>
        </tr>
        <tr>
          <td class="lbl">Nomor Polisi</td><td class="field-cell"><input type="text" name="nomor_polisi" value="<?= $isEditMode ? htmlspecialchars($editHeader['nomor_polisi'] ?? '') : '' ?>" placeholder="cth. F 8233 HV"></td>
          <td class="lbl">Group Tujuan</td>
          <td class="field-cell">
            <select name="tujuan_type" id="sj-tujuan-type">
              <option value="customer" <?= $editTujuanType === 'customer' ? 'selected' : '' ?>>Customer</option>
              <option value="gudang" <?= $editTujuanType === 'gudang' ? 'selected' : '' ?>>Antar Gudang (Transfer)</option>
            </select>
          </td>
        </tr>
        <tr>
          <td class="lbl">Gudang Asal</td><td class="field-cell"><input type="text" id="sj-warehouse-input" name="warehouse_name" value="<?= $myWarehouseId !== null ? htmlspecialchars($myWarehouseName ?? '') : ($isEditMode ? htmlspecialchars($editHeader['warehouse_snapshot'] ?? '') : '') ?>" placeholder="cth. MJT" autocomplete="off" <?= $myWarehouseId !== null ? 'readonly style="background:oklch(0.97 0.003 90);"' : '' ?>></td>
          <td class="lbl">Lokasi Tujuan</td>
          <td class="field-cell">
            <select name="tujuan_warehouse_id" id="sj-tujuan-warehouse-select" <?= $editTujuanType === 'gudang' ? 'required' : 'style="display:none;"' ?>>
              <option value="">— Pilih gudang tujuan —</option>
              <?php foreach ($warehouseList as $w): ?>
                <option value="<?= $w['id'] ?>" <?= $isEditMode && (int) ($editHeader['tujuan_warehouse_id'] ?? 0) === (int) $w['id'] ? 'selected' : '' ?>><?= htmlspecialchars($w['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr>
          <td class="lbl">Keterangan</td>
          <td class="field-cell" colspan="3"><textarea name="keterangan" rows="2" placeholder="Catatan tambahan buat surat jalan ini..."><?= $isEditMode ? htmlspecialchars($editHeader['keterangan'] ?? '') : '' ?></textarea></td>
        </tr>
      </table>
    </div>

    <div class="sj-section">
      <div class="sj-section-head"><h3>Dikirimkan Barang-Barang Sebagai Berikut</h3></div>
      <table class="sj-line-table" id="sj-line-table">
        <thead>
          <tr>
            <th style="width:32px;">No</th>
            <th>Nama Barang</th>
            <th style="width:90px;">Kode Barang</th>
            <th style="width:70px;">Qty</th>
            <th style="width:90px;">Jumlah Label</th>
            <th style="width:56px;">Baik</th>
            <th style="width:56px;">Lengkap</th>
            <th>Keterangan</th>
            <th style="width:36px;"></th>
          </tr>
        </thead>
        <tbody id="sj-line-tbody">
          <?php
          $rowsToRender = $isEditMode ? $editLines : [];
          $rowIdx = 0;
          foreach ($rowsToRender as $ln):
          ?>
            <tr>
              <td class="no-cell"><?= $rowIdx + 1 ?></td>
              <td><input type="text" class="sj-combo-product" name="lines[<?= $rowIdx ?>][product_name]" value="<?= htmlspecialchars($ln['product_name_snapshot']) ?>" autocomplete="off"></td>
              <td><input type="text" name="lines[<?= $rowIdx ?>][item_code]" value="<?= htmlspecialchars($ln['item_code'] ?? '') ?>"></td>
              <td><input type="text" name="lines[<?= $rowIdx ?>][qty]" value="<?= htmlspecialchars($ln['qty'] ?? '') ?>"></td>
              <td><input type="number" min="1" step="1" name="lines[<?= $rowIdx ?>][kemasan]" value="<?= htmlspecialchars($ln['kemasan'] ?? '') ?>" placeholder="cth. 5"></td>
              <td class="chk-cell"><input type="checkbox" name="lines[<?= $rowIdx ?>][baik]" <?= $ln['baik'] ? 'checked' : '' ?>></td>
              <td class="chk-cell"><input type="checkbox" name="lines[<?= $rowIdx ?>][lengkap]" <?= $ln['lengkap'] ? 'checked' : '' ?>></td>
              <td><input type="text" name="lines[<?= $rowIdx ?>][keterangan]" value="<?= htmlspecialchars($ln['keterangan'] ?? '') ?>"></td>
              <td class="rm-cell"><button type="button" class="btn btn-sm btn-ghost sj-remove-row" style="padding:2px 8px;">✕</button></td>
            </tr>
          <?php $rowIdx++; endforeach; ?>
        </tbody>
      </table>
      <button type="button" class="btn btn-sm sj-add-row-btn" id="sj-add-row-btn">+ Tambah Baris</button>
    </div>

    <div class="sj-submit-row">
      <a class="btn btn-ghost" href="manufaktur-surat-jalan.php<?= $isEditMode ? '?id=' . $editId : '' ?>">Batal</a>
      <button type="submit" class="btn"><?= $isEditMode ? 'Simpan Perubahan' : 'Simpan Surat Jalan' ?></button>
    </div>
  </form>

  <script>
  var SJ_KEPADA_NAMES = <?= json_encode(array_column($kepadaList, 'name')) ?>;
  var SJ_WAREHOUSE_NAMES = <?= json_encode(array_column($warehouseList, 'name')) ?>;
  var SJ_PRODUCT_NAMES = <?= json_encode(array_column($productsList, 'name')) ?>;

  document.addEventListener('DOMContentLoaded', function () {
    initCombobox(document.getElementById('sj-kepada-input'), SJ_KEPADA_NAMES);
    initCombobox(document.getElementById('sj-warehouse-input'), SJ_WAREHOUSE_NAMES);

    var tujuanTypeSelect = document.getElementById('sj-tujuan-type');
    var kepadaInput = document.getElementById('sj-kepada-input');
    var tujuanWarehouseSelect = document.getElementById('sj-tujuan-warehouse-select');

    function syncTujuanFields() {
      if (tujuanTypeSelect.value === 'gudang') {
        kepadaInput.style.display = 'none';
        kepadaInput.required = false;
        tujuanWarehouseSelect.style.display = '';
        tujuanWarehouseSelect.required = true;
      } else {
        kepadaInput.style.display = '';
        kepadaInput.required = true;
        tujuanWarehouseSelect.style.display = 'none';
        tujuanWarehouseSelect.required = false;
      }
    }
    tujuanTypeSelect.addEventListener('change', syncTujuanFields);
    syncTujuanFields();

    var tbody = document.getElementById('sj-line-tbody');
    var rowIndex = tbody.querySelectorAll('tr').length;

    function renumber() {
      tbody.querySelectorAll('tr').forEach(function (tr, i) {
        tr.querySelector('.no-cell').textContent = i + 1;
      });
    }

    function bindRow(tr) {
      var combo = tr.querySelector('.sj-combo-product');
      if (combo) initCombobox(combo, SJ_PRODUCT_NAMES);
      tr.querySelector('.sj-remove-row').addEventListener('click', function () {
        tr.remove();
        renumber();
      });
    }

    function addRow() {
      var i = rowIndex++;
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="no-cell"></td>' +
        '<td><input type="text" class="sj-combo-product" name="lines[' + i + '][product_name]" autocomplete="off"></td>' +
        '<td><input type="text" name="lines[' + i + '][item_code]"></td>' +
        '<td><input type="text" name="lines[' + i + '][qty]"></td>' +
        '<td><input type="number" min="1" step="1" name="lines[' + i + '][kemasan]" placeholder="cth. 5"></td>' +
        '<td class="chk-cell"><input type="checkbox" name="lines[' + i + '][baik]" checked></td>' +
        '<td class="chk-cell"><input type="checkbox" name="lines[' + i + '][lengkap]" checked></td>' +
        '<td><input type="text" name="lines[' + i + '][keterangan]"></td>' +
        '<td class="rm-cell"><button type="button" class="btn btn-sm btn-ghost sj-remove-row" style="padding:2px 8px;">✕</button></td>';
      tbody.appendChild(tr);
      bindRow(tr);
      renumber();
    }

    tbody.querySelectorAll('tr').forEach(bindRow);
    document.getElementById('sj-add-row-btn').addEventListener('click', addRow);

    document.getElementById('sj-form').addEventListener('submit', function (e) {
      if (tujuanTypeSelect.value === 'gudang') {
        if (!tujuanWarehouseSelect.value) {
          e.preventDefault();
          alert('Lokasi Tujuan (gudang) wajib dipilih dulu.');
          tujuanWarehouseSelect.focus();
        }
      } else if (!kepadaInput.value.trim()) {
        e.preventDefault();
        alert('Kepada wajib diisi dulu.');
        kepadaInput.focus();
      }
    });

    <?php if (!$isEditMode): ?>
    for (var __i = 0; __i < 3; __i++) addRow();
    <?php endif; ?>
  });
  </script>

<?php else: ?>
  <style>
    .txn-rail-item.sj-rail-void { background:var(--danger-bg, #fde2e2) !important; }
    .txn-rail-item.sj-rail-void .doc { color:var(--danger, #b91c1c) !important; }
    #sj-rail-list .txn-rail-item { padding:12px; }
    #sj-rail-list .txn-rail-item .sub { margin-top:4px; }
    .txn-rail .txn-rail-month .today-btn { margin:0; }
    #sj-rail-search-wrap { padding:0 0 10px; }
    .sj-info-table { margin-top:16px; display:grid; grid-template-columns: repeat(4, 1fr); border:1px solid var(--border); border-radius:10px; overflow:hidden; }
    @media (max-width: 780px) { .sj-info-table { grid-template-columns: repeat(2, 1fr); } }
    .sj-info-table .cell { padding:12px 16px; border-right:1px solid var(--border); border-top:1px solid var(--border); background:oklch(0.98 0.003 90); }
    .sj-info-table .cell:nth-child(-n+4) { border-top:none; }
    .sj-info-table .cell:nth-child(4n) { border-right:none; }
    .sj-info-table .k { display:block; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:4px; }
    .sj-void-banner { margin-top:12px; background:var(--danger-bg, #fde2e2); color:var(--danger, #b91c1c); border:1px solid var(--danger, #b91c1c); border-radius:8px; padding:10px 14px; font-size:13px; }
    .sj-audit-log { margin-top:12px; border:1px solid var(--border); border-radius:8px; overflow:hidden; font-size:11.5px; }
    .sj-audit-log .row { display:flex; padding:7px 12px; border-top:1px solid var(--border); background:oklch(0.98 0.003 90); }
    .sj-audit-log .row:first-child { border-top:none; }
    .sj-audit-log .k { width:60px; flex-shrink:0; font-weight:600; text-transform:uppercase; font-size:10px; letter-spacing:.02em; color:var(--ink-muted); align-self:center; }
    table.sj-detail-table { width:100%; border-collapse:collapse; margin-top:16px; font-size:12.5px; }
    table.sj-detail-table th, table.sj-detail-table td { border:1px solid var(--border); padding:7px 8px; text-align:left; }
    table.sj-detail-table th { background:oklch(0.97 0.003 90); font-size:10px; text-transform:uppercase; }
    table.sj-detail-table td.chk-cell, table.sj-detail-table th.chk-cell { text-align:center; }
  </style>

  <div class="txn-shell">
    <div class="txn-rail">
      <div class="txn-rail-month">
        <a href="manufaktur-surat-jalan.php?month=<?= $prevMonth ?>">‹</a>
        <span><?= htmlspecialchars(date('F Y', strtotime($month . '-01'))) ?></span>
        <a href="manufaktur-surat-jalan.php?month=<?= $nextMonth ?>">›</a>
        <a class="today-btn" href="manufaktur-surat-jalan.php">Bulan Ini</a>
      </div>
      <div id="sj-rail-search-wrap">
        <input type="text" id="sj-rail-search" placeholder="Cari nomor dokumen / kepada..." style="width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:12.5px; box-sizing:border-box;">
      </div>
      <div class="txn-rail-list" id="sj-rail-list">
        <?php foreach ($railItems as $r): ?>
          <?php
          $isVoided = $r['deleted_at'] || $r['status'] === 'void';
          $searchBlob = mb_strtolower($r['doc_number'] . ' ' . ($r['kepada_snapshot'] ?? ''));
          ?>
          <a class="txn-rail-item <?= (int) $r['id'] === $selectedId ? 'active' : '' ?> <?= $isVoided ? 'sj-rail-void' : '' ?>" data-search="<?= htmlspecialchars($searchBlob) ?>" href="manufaktur-surat-jalan.php?month=<?= $month ?>&id=<?= $r['id'] ?>">
            <div class="doc" style="font-weight:700;"><?= htmlspecialchars($r['doc_number']) ?><?= $isVoided ? ' 🚫' : '' ?></div>
            <div class="sub"><?= htmlspecialchars(date('d M Y', strtotime($r['tanggal']))) ?> · <?= htmlspecialchars($r['kepada_snapshot'] ?? '—') ?></div>
            <div class="sub" style="margin-top:2px;"><span class="pill pill-<?= $r['status'] ?>"><?= strtoupper(SJ_STATUS_LABELS[$r['status']] ?? $r['status']) ?></span></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$railItems): ?><div style="padding:14px; font-size:12px; color:var(--ink-muted);">Gak ada Surat Jalan bulan ini.</div><?php endif; ?>
      </div>
      <?php if (has_access('manufaktur_surat_jalan', 'can_create')): ?>
        <div style="padding:10px;"><a class="btn btn-sm" style="width:100%;" href="manufaktur-surat-jalan.php?new=1">+ Buat Surat Jalan</a></div>
      <?php endif; ?>
    </div>

    <div class="txn-detail">
      <?php if (!$selected): ?>
        <div class="card txn-empty">Pilih dokumen di kiri, atau buat yang baru.</div>
      <?php else: ?>
        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <h2 style="margin:0; font-size:20px;"><?= htmlspecialchars($selected['doc_number']) ?> <span class="pill pill-<?= $selected['status'] ?>"><?= strtoupper(SJ_STATUS_LABELS[$selected['status']] ?? $selected['status']) ?></span></h2>
            <div class="txn-detail-actions">
              <a class="btn btn-sm btn-ghost" href="manufaktur-surat-jalan-print.php?id=<?= $selected['id'] ?>" target="_blank">🖨 Print</a>
              <a class="btn btn-sm btn-ghost" href="manufaktur-surat-jalan-label-print.php?id=<?= $selected['id'] ?>" target="_blank">🏷 Print Label</a>
              <?php if (has_access('manufaktur_surat_jalan', 'can_edit')): ?>
                <a class="btn btn-sm btn-ghost" href="manufaktur-surat-jalan.php?edit=<?= $selected['id'] ?>">✎ Edit</a>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_status_sj">
                  <input type="hidden" name="manufaktur_sj_id" value="<?= $selected['id'] ?>">
                  <select name="status" onchange="this.form.submit();" style="padding:6px 10px; border:1px solid var(--border); border-radius:4px; font-size:12px;">
                    <?php foreach (SJ_STATUS_LABELS as $sk => $sl): ?>
                      <option value="<?= $sk ?>" <?= $selected['status'] === $sk ? 'selected' : '' ?>><?= strtoupper($sl) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              <?php endif; ?>
              <?php if (has_access('manufaktur_surat_jalan', 'can_delete')): ?>
                <button class="btn btn-sm btn-ghost" type="button" onclick="if(confirm('Hapus dokumen ini?')) __submitDeleteForm('delete_manufaktur_sj', {manufaktur_sj_id: <?= $selected['id'] ?>})">Hapus</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="sj-info-table">
            <?php if (($selected['tujuan_type'] ?? 'customer') === 'gudang'): ?>
              <div class="cell"><span class="k">Tujuan</span>Transfer Antar Gudang</div>
              <div class="cell"><span class="k">Gudang Asal</span><?= $selected['warehouse_snapshot'] ? htmlspecialchars($selected['warehouse_snapshot']) : '—' ?></div>
              <div class="cell"><span class="k">Gudang Tujuan</span><?= htmlspecialchars($selected['kepada_snapshot'] ?? '—') ?></div>
            <?php else: ?>
              <div class="cell"><span class="k">Kepada</span><?= htmlspecialchars($selected['kepada_snapshot'] ?? '—') ?></div>
              <div class="cell"><span class="k">Gudang</span><?= $selected['warehouse_snapshot'] ? htmlspecialchars($selected['warehouse_snapshot']) : '—' ?></div>
            <?php endif; ?>
            <div class="cell"><span class="k">Tanggal</span><?= htmlspecialchars(date('d M Y', strtotime($selected['tanggal']))) ?></div>
            <div class="cell"><span class="k">Nomor Polisi</span><?= $selected['nomor_polisi'] ? htmlspecialchars($selected['nomor_polisi']) : '—' ?></div>
            <div class="cell"><span class="k">Driver</span><?= $selected['driver_name'] ? htmlspecialchars($selected['driver_name']) : '—' ?></div>
            <div class="cell"><span class="k">Nomor Quotation</span><?= $selected['nomor_quotation'] ? htmlspecialchars($selected['nomor_quotation']) : '—' ?></div>
            <div class="cell"><span class="k">Nomor Order/PO</span><?= $selected['nomor_order_po'] ? htmlspecialchars($selected['nomor_order_po']) : '—' ?></div>
          </div>

          <?php if (!empty($selected['keterangan'])): ?>
            <div class="sj-keterangan-box"><span class="k">Keterangan</span><?= nl2br(htmlspecialchars($selected['keterangan'])) ?></div>
          <?php endif; ?>

          <?php if ($selected['deleted_at']): ?>
            <div class="sj-void-banner">🚫 Dokumen ini sudah <strong>dihapus (void)</strong> oleh <strong><?= htmlspecialchars($headerDeletedByName ?? '—') ?></strong> pada <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['deleted_at']))) ?></div>
          <?php endif; ?>

          <div class="sj-audit-log">
            <div class="row"><span class="k">Dibuat</span><span><strong><?= htmlspecialchars($headerCreatedByName ?? '—') ?></strong> · <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['created_at']))) ?></span></div>
            <?php if ($selected['updated_at']): ?>
              <div class="row"><span class="k">Diedit</span><span><strong><?= htmlspecialchars($headerUpdatedByName ?? '—') ?></strong> · <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['updated_at']))) ?></span></div>
            <?php endif; ?>
          </div>

          <table class="sj-detail-table">
            <thead><tr><th style="width:32px;">No</th><th>Nama Barang</th><th style="width:90px;">Kode Barang</th><th style="width:70px;">Qty</th><th style="width:90px;">Jumlah Label</th><th class="chk-cell" style="width:50px;">Baik</th><th class="chk-cell" style="width:60px;">Lengkap</th><th>Keterangan</th></tr></thead>
            <tbody>
              <?php foreach ($selectedLines as $i => $ln): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><?= htmlspecialchars($ln['product_name_snapshot']) ?></td>
                  <td><?= $ln['item_code'] ? htmlspecialchars($ln['item_code']) : '—' ?></td>
                  <td><?= htmlspecialchars($ln['qty'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($ln['kemasan'] ?? '—') ?></td>
                  <td class="chk-cell"><?= $ln['baik'] ? '✓' : '—' ?></td>
                  <td class="chk-cell"><?= $ln['lengkap'] ? '✓' : '—' ?></td>
                  <td><?= $ln['keterangan'] ? htmlspecialchars($ln['keterangan']) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$selectedLines): ?><tr><td colspan="8" style="text-align:center; color:var(--ink-muted);">Belum ada barang.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var sjRailSearch = document.getElementById('sj-rail-search');
    if (sjRailSearch) {
      sjRailSearch.addEventListener('input', function () {
        var q = sjRailSearch.value.trim().toLowerCase();
        document.querySelectorAll('#sj-rail-list .txn-rail-item').forEach(function (item) {
          var hay = item.getAttribute('data-search') || '';
          item.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
        });
      });
    }
  });
  </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
