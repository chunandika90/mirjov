<?php
$pageTitle = 'Form Penawaran Harga';
$activeMenu = 'manufaktur_penawaran';
require __DIR__ . '/includes/header.php';
require_module_access('manufaktur_penawaran');
require_once __DIR__ . '/../backoffice-shared/doc_number.php';
require_once __DIR__ . '/../backoffice-shared/image_upload.php';

$pdo = db();
$flash = null;

/** Catet "terakhir diubah oleh/kapan" di level dokumen — dipanggil di tiap aksi yang nyentuh dokumen ini. */
function touch_manufaktur_header(PDO $pdo, int $headerId, int $userId): void
{
    $pdo->prepare('UPDATE manufaktur_penawaran SET updated_by=?, updated_at=NOW() WHERE id=?')->execute([$userId, $headerId]);
}

const MP_PRICE_TYPE_LABELS = ['harga_frame' => 'Biaya Frame / Konstruksi', 'harga_qc' => 'Biaya QC', 'harga_finishing' => 'Biaya Finishing', 'harga_komponen' => 'Biaya Komponen', 'harga_packaging' => 'Biaya Packaging / Pengemasan', 'harga_dll' => 'Biaya Tambahan / Lain-lain', 'harga_unit' => 'Harga Unit (lama)'];

/** Urutan tetap + deskripsi spesifikasi tiap kategori biaya — dipakai di cetakan biar sama persis kayak Form PO+Penawaran contoh (MJ/MMT). */
const MP_PRICE_CATEGORY_SPEC = [
    'harga_frame' => 'Material utama & pembuatan struktur',
    'harga_qc' => 'QC barang produksi',
    'harga_finishing' => 'Pengecatan / laminasi / polishing',
    'harga_komponen' => 'Hardware / adjuster / rel / dll',
    'harga_packaging' => 'Bubble wrap, wooden crate, karton',
    'harga_dll' => 'Pengiriman / Instalasi / aksesoris',
];

/**
 * Nomor dokumen khusus Manufaktur: PH/{tahun}/{bulan 2 digit}/{nomor 4 digit} —
 * beda dari pola next_doc_number() umum (yang pakai prefix organisasi), jadi ditulis
 * terpisah di sini biar gak ngerubah format dokumen lain (Penawaran biasa, Invoice, dst).
 * Reuse tabel doc_counters yang sama (counter per organisasi+doc_type+tahun).
 */
function next_manufaktur_ph_number(PDO $pdo, int $organizationId): string
{
    $year = (int) date('Y');
    $month = (int) date('n');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT last_number FROM doc_counters WHERE organization_id=? AND doc_type=? AND year=? FOR UPDATE');
        $stmt->execute([$organizationId, 'PH', $year]);
        $row = $stmt->fetch();
        if ($row) {
            $next = (int) $row['last_number'] + 1;
            $pdo->prepare('UPDATE doc_counters SET last_number=? WHERE organization_id=? AND doc_type=? AND year=?')
                ->execute([$next, $organizationId, 'PH', $year]);
        } else {
            $next = 1;
            $pdo->prepare('INSERT INTO doc_counters (organization_id, doc_type, year, last_number) VALUES (?,?,?,?)')
                ->execute([$organizationId, 'PH', $year, $next]);
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTransaction) $pdo->rollBack();
        throw $e;
    }
    return sprintf('PH/%d/%02d/%04d', $year, $month, $next);
}

/**
 * Nomor transaksi buat tiap update Harga/Timeline/Remark dari tim manufaktur —
 * dibikin SEKALI aja per baris (pas pertama kali disimpan), biar bisa dijadiin
 * referensi/report tersendiri nanti tanpa nempel ke nomor dokumen Penawaran-nya.
 */
function next_manufaktur_update_no(PDO $pdo, int $organizationId): string
{
    $year = (int) date('Y');
    $month = (int) date('n');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT last_number FROM doc_counters WHERE organization_id=? AND doc_type=? AND year=? FOR UPDATE');
        $stmt->execute([$organizationId, 'UPD', $year]);
        $row = $stmt->fetch();
        if ($row) {
            $next = (int) $row['last_number'] + 1;
            $pdo->prepare('UPDATE doc_counters SET last_number=? WHERE organization_id=? AND doc_type=? AND year=?')
                ->execute([$next, $organizationId, 'UPD', $year]);
        } else {
            $next = 1;
            $pdo->prepare('INSERT INTO doc_counters (organization_id, doc_type, year, last_number) VALUES (?,?,?,?)')
                ->execute([$organizationId, 'UPD', $year, $next]);
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTransaction) $pdo->rollBack();
        throw $e;
    }
    return sprintf('UPD/%d/%02d/%04d', $year, $month, $next);
}

// Dropdown searchable (combobox) di form ini: ketik nama yang udah ada = dipakai lagi,
// ketik nama baru = otomatis dibikinkan record master-nya. Pola sama kayak combo-* di products.php.
function find_or_create_manufaktur_vendor(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare("SELECT id FROM contacts WHERE organization_id=? AND name=? AND type IN ('vendor','both') LIMIT 1");
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO contacts (organization_id, type, name) VALUES (?, 'vendor', ?)")->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

function find_or_create_manufaktur_project(PDO $pdo, int $orgId, string $name, int $userId): int
{
    $stmt = $pdo->prepare('SELECT id FROM projects WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare('INSERT INTO projects (organization_id, name, created_by) VALUES (?,?,?)')->execute([$orgId, $name, $userId]);
    return (int) $pdo->lastInsertId();
}

function find_or_create_manufaktur_product(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO products (organization_id, name, unit) VALUES (?,?,'pcs')")->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

function find_or_create_manufaktur_finishing(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM product_finishings WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare('INSERT INTO product_finishings (organization_id, name) VALUES (?,?)')->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

function find_or_create_manufaktur_material(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM materials WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare('INSERT INTO materials (organization_id, name) VALUES (?,?)')->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_manufaktur_penawaran') {
            require_module_access('manufaktur_penawaran', 'can_create');
            $tanggal = $_POST['tanggal'] ?? '';
            $keterangan = trim($_POST['keterangan'] ?? '') ?: null;
            $vendorName = trim($_POST['vendor_name'] ?? '');
            $projectName = trim($_POST['project_name'] ?? '');
            $poNumber = trim($_POST['po_number'] ?? '') ?: null;
            $dpTerms = trim($_POST['dp_terms'] ?? '') ?: null;
            $lines = $_POST['lines'] ?? [];
            $filesLines = $_FILES['lines'] ?? [];
            if (!$tanggal) throw new RuntimeException('Tanggal wajib diisi.');
            if ($vendorName === '') throw new RuntimeException('Vendor wajib diisi.');
            if (!$lines) throw new RuntimeException('Minimal 1 baris barang.');

            $pdo->beginTransaction();
            try {
                $vendorId = find_or_create_manufaktur_vendor($pdo, $org['organization_id'], $vendorName);
                $projectId = $projectName !== '' ? find_or_create_manufaktur_project($pdo, $org['organization_id'], $projectName, $user['id']) : null;

                $docNumber = next_manufaktur_ph_number($pdo, $org['organization_id']);
                $pdo->prepare('INSERT INTO manufaktur_penawaran (organization_id, doc_number, po_number, dp_terms, tanggal, keterangan, vendor_id, project_id, created_by) VALUES (?,?,?,?,?,?,?,?,?)')
                    ->execute([$org['organization_id'], $docNumber, $poNumber, $dpTerms, $tanggal, $keterangan, $vendorId, $projectId, $user['id']]);
                $headerId = (int) $pdo->lastInsertId();

                $lineStmt = $pdo->prepare(
                    'INSERT INTO manufaktur_penawaran_lines (manufaktur_penawaran_id, product_id, product_name_snapshot, item_code, size_mm, finishing_id, finishing_snapshot, qty, material_id, material_snapshot, material2_id, material2_snapshot, wood, texture_topcoat, deadline_mj, keterangan_mj)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $attachmentStmt = $pdo->prepare('INSERT INTO manufaktur_penawaran_line_attachments (line_id, file_path, original_name, uploaded_by) VALUES (?,?,?,?)');

                $lineCount = 0;
                foreach ($lines as $i => $line) {
                    $productName = trim($line['product_name'] ?? '');
                    if ($productName === '') continue;
                    $productId = find_or_create_manufaktur_product($pdo, $org['organization_id'], $productName);

                    $itemCode = trim($line['item_code'] ?? '') ?: null;
                    $sizeMm = trim($line['size_mm'] ?? '') ?: null;
                    $wood = trim($line['wood'] ?? '') ?: null;
                    $textureTopcoat = trim($line['texture_topcoat'] ?? '') ?: null;

                    $finishingName = trim($line['finishing_name'] ?? '');
                    $finishingId = $finishingName !== '' ? find_or_create_manufaktur_finishing($pdo, $org['organization_id'], $finishingName) : null;

                    $materialName = trim($line['material_name'] ?? '');
                    $materialId = $materialName !== '' ? find_or_create_manufaktur_material($pdo, $org['organization_id'], $materialName) : null;

                    $material2Name = trim($line['material2_name'] ?? '');
                    $material2Id = $material2Name !== '' ? find_or_create_manufaktur_material($pdo, $org['organization_id'], $material2Name) : null;

                    $qty = (float) ($line['qty'] ?? 1) ?: 1;
                    $deadline = trim($line['deadline_mj'] ?? '') ?: null;
                    $ketMj = trim($line['keterangan_mj'] ?? '') ?: null;

                    $lineStmt->execute([$headerId, $productId, $productName, $itemCode, $sizeMm, $finishingId, $finishingName ?: null, $qty, $materialId, $materialName ?: null, $material2Id, $material2Name ?: null, $wood, $textureTopcoat, $deadline, $ketMj]);
                    $lineId = (int) $pdo->lastInsertId();
                    $lineCount++;

                    foreach (normalize_manufaktur_line_files($filesLines, (int) $i) as $file) {
                        $saved = save_manufaktur_line_attachment($file);
                        $attachmentStmt->execute([$lineId, $saved['file_path'], $saved['original_name'], $user['id']]);
                    }
                }
                if (!$lineCount) throw new RuntimeException('Minimal 1 baris barang yang valid.');

                $pdo->commit();
                header('Location: manufaktur-penawaran.php?id=' . $headerId);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'update_line_pricing') {
            require_module_access('manufaktur_penawaran', 'can_edit');
            $lineId = (int) ($_POST['line_id'] ?? 0);
            $headerId = (int) ($_POST['manufaktur_penawaran_id'] ?? 0);
            $timeline = trim($_POST['timeline_pabrik'] ?? '') ?: null;
            $remark = trim($_POST['remark_pabrik'] ?? '') ?: null;
            $priceTypes = $_POST['price_type'] ?? [];
            $priceValues = $_POST['price_value'] ?? [];
            $produksiFiles = $_FILES['produksi_attachments'] ?? [];
            // Kalau form ini digabung sama Detail Final (baris terakhir), field2 ini ikut kekirim sekalian.
            $includeFinalDetail = isset($_POST['include_final_detail']);
            $poNumber = trim($_POST['po_number'] ?? '') ?: null;
            $dpTerms = trim($_POST['dp_terms'] ?? '') ?: null;
            $wording = trim($_POST['wording_pelunasan'] ?? '') ?: null;
            $bankName = trim($_POST['bank_name'] ?? '') ?: null;
            $bankNorek = trim($_POST['bank_norek'] ?? '') ?: null;
            $bankAn = trim($_POST['bank_an'] ?? '') ?: null;

            $check = $pdo->prepare('SELECT l.id, l.update_transaction_no FROM manufaktur_penawaran_lines l JOIN manufaktur_penawaran h ON h.id=l.manufaktur_penawaran_id WHERE l.id=? AND h.organization_id=?');
            $check->execute([$lineId, $org['organization_id']]);
            $lineRow = $check->fetch();
            if (!$lineRow) throw new RuntimeException('Baris barang tidak ditemukan.');

            $pdo->beginTransaction();
            try {
                $txNo = $lineRow['update_transaction_no'] ?: next_manufaktur_update_no($pdo, $org['organization_id']);
                $pdo->prepare('UPDATE manufaktur_penawaran_lines SET timeline_pabrik=?, remark_pabrik=?, updated_by_pabrik=?, updated_at_pabrik=NOW(), update_transaction_no=? WHERE id=?')
                    ->execute([$timeline, $remark, $user['id'], $txNo, $lineId]);

                $pdo->prepare('DELETE FROM manufaktur_penawaran_line_prices WHERE line_id=?')->execute([$lineId]);
                $priceStmt = $pdo->prepare('INSERT INTO manufaktur_penawaran_line_prices (line_id, price_type, price_value, created_by) VALUES (?,?,?,?)');
                foreach ($priceTypes as $i => $type) {
                    if (!array_key_exists($type, MP_PRICE_TYPE_LABELS)) continue;
                    $value = (float) ($priceValues[$i] ?? 0);
                    if ($value <= 0) continue;
                    $priceStmt->execute([$lineId, $type, $value, $user['id']]);
                }

                if (!empty($produksiFiles['name'])) {
                    $attachmentStmt = $pdo->prepare("INSERT INTO manufaktur_penawaran_line_attachments (line_id, file_path, original_name, uploaded_by, source) VALUES (?,?,?,?,'produksi')");
                    foreach ($produksiFiles['name'] as $j => $name) {
                        if (($produksiFiles['error'][$j] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                        $file = ['name' => $name, 'type' => $produksiFiles['type'][$j], 'tmp_name' => $produksiFiles['tmp_name'][$j], 'error' => $produksiFiles['error'][$j], 'size' => $produksiFiles['size'][$j]];
                        $saved = save_manufaktur_line_attachment($file);
                        $attachmentStmt->execute([$lineId, $saved['file_path'], $saved['original_name'], $user['id']]);
                    }
                }

                if ($includeFinalDetail) {
                    $pdo->prepare('UPDATE manufaktur_penawaran SET po_number=?, dp_terms=?, wording_pelunasan=?, bank_name=?, bank_norek=?, bank_an=?, detail_updated_by=?, detail_updated_at=NOW() WHERE id=? AND organization_id=?')
                        ->execute([$poNumber, $dpTerms, $wording, $bankName, $bankNorek, $bankAn, $user['id'], $headerId, $org['organization_id']]);
                }

                $pdo->commit();
                touch_manufaktur_header($pdo, $headerId, $user['id']);
                $flash = ['ok', 'Harga, timeline & detail produksi disimpan.'];
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            header('Location: manufaktur-penawaran.php?id=' . $headerId);
            exit;
        } elseif ($action === 'update_manufaktur_detail') {
            require_module_access('manufaktur_penawaran', 'can_edit');
            $id = (int) ($_POST['manufaktur_penawaran_id'] ?? 0);
            $wording = trim($_POST['wording_pelunasan'] ?? '') ?: null;
            $bankName = trim($_POST['bank_name'] ?? '') ?: null;
            $bankNorek = trim($_POST['bank_norek'] ?? '') ?: null;
            $bankAn = trim($_POST['bank_an'] ?? '') ?: null;
            $poNumber = trim($_POST['po_number'] ?? '') ?: null;
            $dpTerms = trim($_POST['dp_terms'] ?? '') ?: null;
            $pdo->prepare('UPDATE manufaktur_penawaran SET wording_pelunasan=?, bank_name=?, bank_norek=?, bank_an=?, po_number=?, dp_terms=?, detail_updated_by=?, detail_updated_at=NOW() WHERE id=? AND organization_id=?')
                ->execute([$wording, $bankName, $bankNorek, $bankAn, $poNumber, $dpTerms, $user['id'], $id, $org['organization_id']]);
            touch_manufaktur_header($pdo, $id, $user['id']);
            $flash = ['ok', 'Detail Form Penawaran Harga disimpan.'];
            header('Location: manufaktur-penawaran.php?id=' . $id);
            exit;
        } elseif ($action === 'update_status') {
            require_module_access('manufaktur_penawaran', 'can_edit');
            $id = (int) ($_POST['manufaktur_penawaran_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (in_array($status, ['draft', 'diajukan', 'diproses', 'selesai', 'void'], true)) {
                $pdo->prepare('UPDATE manufaktur_penawaran SET status=? WHERE id=? AND organization_id=?')->execute([$status, $id, $org['organization_id']]);
                touch_manufaktur_header($pdo, $id, $user['id']);
                $flash = ['ok', 'Status diperbarui.'];
            }
        } elseif ($action === 'update_line_mj') {
            require_module_access('manufaktur_penawaran', 'can_edit');
            $lineId = (int) ($_POST['line_id'] ?? 0);
            $headerId = (int) ($_POST['manufaktur_penawaran_id'] ?? 0);
            $check = $pdo->prepare('SELECT l.id FROM manufaktur_penawaran_lines l JOIN manufaktur_penawaran h ON h.id=l.manufaktur_penawaran_id WHERE l.id=? AND h.organization_id=?');
            $check->execute([$lineId, $org['organization_id']]);
            if (!$check->fetch()) throw new RuntimeException('Baris barang tidak ditemukan.');

            $productName = trim($_POST['product_name'] ?? '');
            if ($productName === '') throw new RuntimeException('Nama barang wajib diisi.');
            $itemCode = trim($_POST['item_code'] ?? '') ?: null;
            $sizeMm = trim($_POST['size_mm'] ?? '') ?: null;
            $wood = trim($_POST['wood'] ?? '') ?: null;
            $textureTopcoat = trim($_POST['texture_topcoat'] ?? '') ?: null;
            $finishingName = trim($_POST['finishing_name'] ?? '');
            $materialName = trim($_POST['material_name'] ?? '');
            $material2Name = trim($_POST['material2_name'] ?? '');
            $qty = (float) ($_POST['qty'] ?? 1) ?: 1;
            $deadline = trim($_POST['deadline_mj'] ?? '') ?: null;
            $ketMj = trim($_POST['keterangan_mj'] ?? '') ?: null;
            $newFiles = $_FILES['attachments'] ?? [];

            $pdo->beginTransaction();
            try {
                $productId = find_or_create_manufaktur_product($pdo, $org['organization_id'], $productName);
                $finishingId = $finishingName !== '' ? find_or_create_manufaktur_finishing($pdo, $org['organization_id'], $finishingName) : null;
                $materialId = $materialName !== '' ? find_or_create_manufaktur_material($pdo, $org['organization_id'], $materialName) : null;
                $material2Id = $material2Name !== '' ? find_or_create_manufaktur_material($pdo, $org['organization_id'], $material2Name) : null;

                $pdo->prepare('UPDATE manufaktur_penawaran_lines SET product_id=?, product_name_snapshot=?, item_code=?, size_mm=?, finishing_id=?, finishing_snapshot=?, qty=?, material_id=?, material_snapshot=?, material2_id=?, material2_snapshot=?, wood=?, texture_topcoat=?, deadline_mj=?, keterangan_mj=? WHERE id=?')
                    ->execute([$productId, $productName, $itemCode, $sizeMm, $finishingId, $finishingName ?: null, $qty, $materialId, $materialName ?: null, $material2Id, $material2Name ?: null, $wood, $textureTopcoat, $deadline, $ketMj, $lineId]);

                if (!empty($newFiles['name'])) {
                    $attachmentStmt = $pdo->prepare("INSERT INTO manufaktur_penawaran_line_attachments (line_id, file_path, original_name, uploaded_by, source) VALUES (?,?,?,?,'mj')");
                    foreach ($newFiles['name'] as $j => $name) {
                        if (($newFiles['error'][$j] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                        $file = ['name' => $name, 'type' => $newFiles['type'][$j], 'tmp_name' => $newFiles['tmp_name'][$j], 'error' => $newFiles['error'][$j], 'size' => $newFiles['size'][$j]];
                        $saved = save_manufaktur_line_attachment($file);
                        $attachmentStmt->execute([$lineId, $saved['file_path'], $saved['original_name'], $user['id']]);
                    }
                }
                $pdo->commit();
                touch_manufaktur_header($pdo, $headerId, $user['id']);
                $flash = ['ok', 'Detail barang diperbarui.'];
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
            header('Location: manufaktur-penawaran.php?id=' . $headerId);
            exit;
        } elseif ($action === 'delete_manufaktur_penawaran') {
            require_module_access('manufaktur_penawaran', 'can_delete');
            $id = (int) ($_POST['manufaktur_penawaran_id'] ?? 0);
            // Soft delete — data TETAP disimpan (foto/harga/dll), cuma ditandain dihapus.
            // Gak dipakai di laporan nanti, tapi tetep muncul di list biar ada jejaknya.
            $pdo->prepare('UPDATE manufaktur_penawaran SET deleted_by=?, deleted_at=NOW() WHERE id=? AND organization_id=?')
                ->execute([$user['id'], $id, $org['organization_id']]);
            $flash = ['ok', 'Form Penawaran Harga ditandai dihapus (void).'];
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }
}

$vendors = $pdo->prepare("SELECT id, name FROM contacts WHERE organization_id=? AND type IN ('vendor','both') ORDER BY name");
$vendors->execute([$org['organization_id']]);
$vendors = $vendors->fetchAll();

$projectsList = $pdo->prepare('SELECT id, name FROM projects WHERE organization_id=? ORDER BY name');
$projectsList->execute([$org['organization_id']]);
$projectsList = $projectsList->fetchAll();

$productsList = $pdo->prepare('SELECT id, name, material, size FROM products WHERE organization_id=? ORDER BY name');
$productsList->execute([$org['organization_id']]);
$productsForPicker = [];
foreach ($productsList->fetchAll() as $p) {
    $productsForPicker[$p['id']] = ['name' => $p['name'], 'material' => $p['material'], 'size' => $p['size']];
}

$finishingsList = $pdo->prepare('SELECT id, name FROM product_finishings WHERE organization_id=? ORDER BY name');
$finishingsList->execute([$org['organization_id']]);
$finishingsList = $finishingsList->fetchAll();

$materialsList = $pdo->prepare('SELECT id, name FROM materials WHERE organization_id=? ORDER BY name');
$materialsList->execute([$org['organization_id']]);
$materialsList = $materialsList->fetchAll();

$isNewForm = isset($_GET['new']);
// Sementara dibuka buat siapapun yang punya akses edit modul ini (belum dibatasin ke role
// Pabrik doang) — biar gampang ditest dulu. Ketatin lagi belakangan pas role Pabrik udah settle.
$canProduction = has_access('manufaktur_penawaran', 'can_edit');
$editLine = (int) ($_GET['edit_line'] ?? 0);
$editMjLine = (int) ($_GET['edit_mj_line'] ?? 0);

if (!$isNewForm) {
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

    $railStmt = $pdo->prepare(
        "SELECT h.*, c.name AS vendor_name, p.name AS project_name FROM manufaktur_penawaran h
         JOIN contacts c ON c.id=h.vendor_id LEFT JOIN projects p ON p.id=h.project_id
         WHERE h.organization_id=? AND DATE_FORMAT(h.created_at,'%Y-%m')=? ORDER BY h.created_at DESC"
    );
    $railStmt->execute([$org['organization_id'], $month]);
    $railItems = $railStmt->fetchAll();

    $selectedId = (int) ($_GET['id'] ?? ($railItems[0]['id'] ?? 0));
    $selected = null;
    $selectedLines = [];
    foreach ($railItems as $r) { if ((int) $r['id'] === $selectedId) { $selected = $r; break; } }
    if (!$selected && $selectedId) {
        $sStmt = $pdo->prepare('SELECT h.*, c.name AS vendor_name FROM manufaktur_penawaran h JOIN contacts c ON c.id=h.vendor_id WHERE h.id=? AND h.organization_id=?');
        $sStmt->execute([$selectedId, $org['organization_id']]);
        $selected = $sStmt->fetch() ?: null;
    }
    if ($selected) {
        $userNameStmt = $pdo->prepare('SELECT name FROM users WHERE id=?');
        $fetchUserName = function ($userId) use ($userNameStmt) {
            if (!$userId) return null;
            $userNameStmt->execute([$userId]);
            return $userNameStmt->fetch()['name'] ?? null;
        };
        $detailUpdatedByName = $fetchUserName($selected['detail_updated_by']);
        $headerCreatedByName = $fetchUserName($selected['created_by']);
        $headerUpdatedByName = $fetchUserName($selected['updated_by']);
        $headerDeletedByName = $fetchUserName($selected['deleted_by']);
        $lStmt = $pdo->prepare(
            'SELECT l.*, up.name AS updated_by_pabrik_name FROM manufaktur_penawaran_lines l
             LEFT JOIN users up ON up.id=l.updated_by_pabrik
             WHERE l.manufaktur_penawaran_id=?'
        );
        $lStmt->execute([$selected['id']]);
        $selectedLines = $lStmt->fetchAll();

        $attStmt = $pdo->prepare("SELECT * FROM manufaktur_penawaran_line_attachments WHERE line_id=? AND source='mj'");
        $attProduksiStmt = $pdo->prepare("SELECT * FROM manufaktur_penawaran_line_attachments WHERE line_id=? AND source='produksi'");
        $priceStmt = $pdo->prepare('SELECT * FROM manufaktur_penawaran_line_prices WHERE line_id=?');
        foreach ($selectedLines as &$sl) {
            $attStmt->execute([$sl['id']]);
            $sl['attachments'] = $attStmt->fetchAll();
            $attProduksiStmt->execute([$sl['id']]);
            $sl['attachments_produksi'] = $attProduksiStmt->fetchAll();
            $priceStmt->execute([$sl['id']]);
            $sl['prices'] = $priceStmt->fetchAll();
        }
        unset($sl);

        $projName = null;
        if ($selected['project_id']) {
            $pjStmt = $pdo->prepare('SELECT name FROM projects WHERE id=?');
            $pjStmt->execute([$selected['project_id']]);
            $projName = $pjStmt->fetch()['name'] ?? null;
        }
    }
}
?>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<?php if ($isNewForm): ?>
  <!-- ===================== FORM FULL PAGE: BUAT PENAWARAN HARGA MANUFAKTUR ===================== -->
  <style>
    .mp-page-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; }
    .mp-page-head h2 { margin:0 0 4px; font-size:20px; }
    .mp-page-head p { margin:0; font-size:13px; color:var(--ink-muted); }
    .mp-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:18px; box-shadow:var(--shadow-card); }
    .mp-section-head { margin-bottom:16px; }
    .mp-section-head h3 { margin:0 0 2px; font-size:14px; }
    .mp-section-head p { margin:0; font-size:12px; color:var(--ink-muted); }
    .mp-form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; }
    .mp-section label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:6px; }
    .mp-section input[type=text], .mp-section input[type=date], .mp-section input[type=number], .mp-section input[type=file] { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-size:13px; }
    .mp-section input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-bg); }
    .mp-line-split { display:grid; grid-template-columns: 2fr 1fr; gap:24px; }
    @media (max-width: 680px) { .mp-line-split { grid-template-columns: 1fr; } }
    .mp-line-split .field-stack { display:flex; flex-direction:column; gap:16px; }
    .mp-submit-row { display:flex; justify-content:flex-end; gap:10px; margin-top:4px; }

    /* Tabel kotak-kotak — sengaja disamain sama layout cetakan (spec-table di manufaktur-penawaran-print.php) biar pas diisi udah kebayang hasil cetaknya. */
    table.mp-box-table { width:100%; border-collapse:collapse; table-layout:fixed; }
    table.mp-box-table td { border:1px solid var(--border); padding:0; vertical-align:top; }
    table.mp-box-table td.lbl { width:24%; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); background:oklch(0.97 0.003 90); padding:7px 9px; }
    table.mp-box-table td.field-cell { padding:2px 4px; }
    table.mp-box-table input, table.mp-box-table textarea, table.mp-box-table select { width:100%; border:none; background:transparent; padding:6px 6px; font-size:13px; box-sizing:border-box; font-family:inherit; }
    table.mp-box-table input:focus, table.mp-box-table textarea:focus { outline:2px solid var(--accent); outline-offset:-2px; border-radius:3px; }
    table.mp-box-table textarea { resize:vertical; min-height:100%; height:100%; }
    table.mp-box-table td.field-cell.file-cell { padding:6px 9px; }
    table.mp-box-table input[type=file] { padding:4px 0; }
  </style>

  <div class="mp-page-head">
    <div>
      <h2>Buat Form Penawaran Harga</h2>
      <p>Diisi tim produksi (MJ) — harga &amp; timeline diisi belakangan oleh tim manufaktur.</p>
    </div>
    <a class="btn btn-sm btn-ghost" href="manufaktur-penawaran.php">Batal</a>
  </div>

  <form method="post" id="mp-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_manufaktur_penawaran">

    <div class="mp-section">
      <div class="mp-section-head"><h3>Informasi Umum</h3><p>Tanggal, vendor, project, No. Form PO &amp; ketentuan DP.</p></div>
      <table class="mp-box-table">
        <tr>
          <td class="lbl">Tanggal</td><td class="field-cell"><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></td>
          <td class="lbl">No. Form Purchase Order</td><td class="field-cell"><input type="text" name="po_number" placeholder="cth. 0001-MJ-VIII-26"></td>
        </tr>
        <tr>
          <td class="lbl">Vendor</td><td class="field-cell"><input type="text" name="vendor_name" id="mp-vendor-input" placeholder="Cari atau ketik nama vendor baru..." autocomplete="off" required></td>
          <td class="lbl">Ketentuan DP</td><td class="field-cell"><input type="text" name="dp_terms" value="DP 50%" placeholder="cth. DP 50%"></td>
        </tr>
        <tr>
          <td class="lbl">Project / Cust.</td><td class="field-cell"><input type="text" name="project_name" id="mp-project-input" placeholder="Cari atau ketik nama project baru..." autocomplete="off"></td>
          <td class="lbl">Keterangan</td><td class="field-cell"><input type="text" name="keterangan" placeholder="Opsional"></td>
        </tr>
      </table>
    </div>

    <div class="mp-section">
      <div class="mp-section-head"><h3>1. Informasi &amp; Spesifikasi Barang (MJ)</h3></div>
      <table class="mp-box-table">
        <tr>
          <td class="lbl">1. Nama Barang</td><td class="field-cell"><input type="text" class="mp-combo-product" name="lines[0][product_name]" placeholder="Cari atau ketik barang baru..." autocomplete="off"></td>
          <td class="lbl">Kode Barang</td><td class="field-cell"><input type="text" name="lines[0][item_code]" placeholder="Opsional"></td>
        </tr>
        <tr>
          <td class="lbl">2. Ukuran (mm)</td><td class="field-cell"><input type="text" name="lines[0][size_mm]" placeholder="cth. 600x600x670"></td>
          <td class="lbl">Tekstur + Top Coat</td><td class="field-cell"><input type="text" name="lines[0][texture_topcoat]" placeholder="cth. Open Halus + G10"></td>
        </tr>
        <tr>
          <td class="lbl">3. Finishing (Opsi)</td><td class="field-cell"><input type="text" class="mp-combo-finishing" name="lines[0][finishing_name]" placeholder="Cari atau ketik finishing baru..." autocomplete="off"></td>
          <td class="lbl" rowspan="6">9. Remark / Catatan Tambahan</td>
          <td class="field-cell" rowspan="6"><textarea name="lines[0][keterangan_mj]" placeholder="Catatan tambahan" style="min-height:150px;"></textarea></td>
        </tr>
        <tr><td class="lbl">4. Jumlah (Qty)</td><td class="field-cell"><input type="number" name="lines[0][qty]" value="1" min="0.01" step="0.01"></td></tr>
        <tr><td class="lbl">5. Material 1</td><td class="field-cell"><input type="text" class="mp-combo-material" name="lines[0][material_name]" placeholder="Cari atau ketik material baru..." autocomplete="off"></td></tr>
        <tr><td class="lbl">6. Material 2</td><td class="field-cell"><input type="text" class="mp-combo-material2" name="lines[0][material2_name]" placeholder="Opsional" autocomplete="off"></td></tr>
        <tr><td class="lbl">7. Wood</td><td class="field-cell"><input type="text" name="lines[0][wood]" placeholder="cth. TPK"></td></tr>
        <tr><td class="lbl">8. Deadline</td><td class="field-cell"><input type="date" name="lines[0][deadline_mj]"></td></tr>
        <tr>
          <td class="lbl">9. Gambar Kerja</td>
          <td class="field-cell file-cell" colspan="3"><input type="file" name="lines[0][attachments][]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf"></td>
        </tr>
      </table>
    </div>

    <div class="mp-section" style="background:var(--amber-bg, #fdf3e2); opacity:.75;">
      <div class="mp-section-head"><h3>💰 Harga, Timeline &amp; Remark — Tim Manufaktur</h3><p>Section ini baru bisa diisi <strong>setelah</strong> dokumen ini disimpan (tim manufaktur isi lewat halaman detail dokumen). Ditampilkan di sini cuma buat gambaran.</p></div>
      <div class="mp-form-grid">
        <div><label>Timeline</label><input type="date" disabled></div>
        <div><label>Remark</label><input type="text" placeholder="Diisi belakangan" disabled></div>
        <div><label>Harga (Frame / Finishing / Packaging / Lain)</label><input type="text" placeholder="Diisi belakangan" disabled></div>
      </div>
    </div>

    <div class="mp-submit-row">
      <a class="btn btn-ghost" href="manufaktur-penawaran.php">Batal</a>
      <button type="submit" class="btn">Simpan Form Penawaran Harga</button>
    </div>
  </form>

  <script>
  var MP_VENDOR_NAMES = <?= json_encode(array_column($vendors, 'name')) ?>;
  var MP_PROJECT_NAMES = <?= json_encode(array_column($projectsList, 'name')) ?>;
  var MP_PRODUCT_NAMES = <?= json_encode(array_column($productsForPicker, 'name')) ?>;
  var MP_FINISHING_NAMES = <?= json_encode(array_column($finishingsList, 'name')) ?>;
  var MP_MATERIAL_NAMES = <?= json_encode(array_column($materialsList, 'name')) ?>;

  document.addEventListener('DOMContentLoaded', function () {
    initCombobox(document.getElementById('mp-vendor-input'), MP_VENDOR_NAMES);
    initCombobox(document.getElementById('mp-project-input'), MP_PROJECT_NAMES);
    initCombobox(document.querySelector('.mp-combo-product'), MP_PRODUCT_NAMES);
    initCombobox(document.querySelector('.mp-combo-finishing'), MP_FINISHING_NAMES);
    initCombobox(document.querySelector('.mp-combo-material'), MP_MATERIAL_NAMES);
    initCombobox(document.querySelector('.mp-combo-material2'), MP_MATERIAL_NAMES);
  });
  </script>

<?php else: ?>
  <!-- ===================== LIST: RAIL + DETAIL ===================== -->
  <style>
    .txn-rail-item.mp-rail-void { background:var(--danger-bg, #fde2e2) !important; }
    .txn-rail-item.mp-rail-void .doc { color:var(--danger, #b91c1c) !important; }
    #mp-rail-list .txn-rail-item { padding:12px; }
    #mp-rail-list .txn-rail-item .sub { margin-top:4px; }
    #mp-rail-list .txn-rail-item .sub:last-child { margin-top:6px; }
    .txn-rail .txn-rail-month { flex-wrap:wrap; }
    .txn-rail .txn-rail-month .today-btn { margin:0; }
    #mp-rail-search-wrap { padding:0 0 10px; }

    table.mp-box-table { width:100%; border-collapse:collapse; table-layout:fixed; }
    table.mp-box-table td { border:1px solid var(--border); padding:0; vertical-align:top; }
    table.mp-box-table td.lbl { width:24%; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); background:oklch(0.97 0.003 90); padding:7px 9px; }
    table.mp-box-table td.field-cell { padding:2px 4px; }
    table.mp-box-table input, table.mp-box-table textarea, table.mp-box-table select { width:100%; border:none; background:transparent; padding:6px 6px; font-size:13px; box-sizing:border-box; font-family:inherit; }
    table.mp-box-table input:focus, table.mp-box-table textarea:focus { outline:2px solid var(--accent); outline-offset:-2px; border-radius:3px; }
    table.mp-box-table textarea { resize:vertical; min-height:100%; height:100%; }
    table.mp-box-table td.field-cell.file-cell { padding:6px 9px; }
    table.mp-box-table input[type=file] { padding:4px 0; }
  </style>
  <div class="txn-shell">
    <div class="txn-rail">
      <div class="txn-rail-month">
        <a href="manufaktur-penawaran.php?month=<?= $prevMonth ?>">‹</a>
        <span><?= htmlspecialchars(date('F Y', strtotime($month . '-01'))) ?></span>
        <a href="manufaktur-penawaran.php?month=<?= $nextMonth ?>">›</a>
        <a class="today-btn" href="manufaktur-penawaran.php">Bulan Ini</a>
      </div>
      <div id="mp-rail-search-wrap">
        <input type="text" id="mp-rail-search" placeholder="Cari nomor dokumen / vendor / project..." style="width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:12.5px; box-sizing:border-box;">
      </div>
      <div class="txn-rail-list" id="mp-rail-list">
        <?php foreach ($railItems as $r): ?>
          <?php
          $isVoided = $r['deleted_at'] || $r['status'] === 'void';
          $searchBlob = mb_strtolower($r['doc_number'] . ' ' . $r['vendor_name'] . ' ' . ($r['project_name'] ?? ''));
          ?>
          <a class="txn-rail-item <?= (int) $r['id'] === $selectedId ? 'active' : '' ?> <?= $isVoided ? 'mp-rail-void' : '' ?>" data-search="<?= htmlspecialchars($searchBlob) ?>" href="manufaktur-penawaran.php?month=<?= $month ?>&id=<?= $r['id'] ?>">
            <div class="doc" style="font-weight:700;"><?= htmlspecialchars($r['doc_number']) ?><?= $isVoided ? ' 🚫' : '' ?></div>
            <div class="sub"><?= htmlspecialchars(date('d M Y', strtotime($r['tanggal']))) ?> · <?= htmlspecialchars($r['vendor_name']) ?></div>
            <?php if ($r['project_name']): ?><div class="sub" style="margin-top:2px;"><?= htmlspecialchars($r['project_name']) ?></div><?php endif; ?>
            <div class="sub" style="margin-top:2px;"><span class="pill pill-<?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$railItems): ?><div style="padding:14px; font-size:12px; color:var(--ink-muted);">Gak ada Form Penawaran Harga bulan ini.</div><?php endif; ?>
      </div>
      <?php if (has_access('manufaktur_penawaran', 'can_create')): ?>
        <div style="padding:10px;"><a class="btn btn-sm" style="width:100%;" href="manufaktur-penawaran.php?new=1">+ Buat Form Penawaran Harga</a></div>
      <?php endif; ?>
    </div>

    <div class="txn-detail">
      <?php if (!$selected): ?>
        <div class="card txn-empty">Pilih dokumen di kiri, atau buat yang baru.</div>
      <?php else: ?>
        <style>
          .mp-detail-header-card { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; }
          .mp-detail-header-card h2 { margin:0; font-size:20px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
          .mp-info-table { margin-top:16px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); border:1px solid var(--border); border-radius:10px; overflow:hidden; }
          .mp-void-banner { margin-top:12px; background:var(--danger-bg, #fde2e2); color:var(--danger, #b91c1c); border:1px solid var(--danger, #b91c1c); border-radius:8px; padding:10px 14px; font-size:13px; }
          .mp-audit-log { margin-top:12px; border:1px solid var(--border); border-radius:8px; overflow:hidden; font-size:11.5px; }
          .mp-audit-log .row { display:flex; padding:7px 12px; border-top:1px solid var(--border); background:oklch(0.98 0.003 90); }
          .mp-audit-log .row:first-child { border-top:none; }
          .mp-audit-log .k { width:60px; flex-shrink:0; font-weight:600; text-transform:uppercase; font-size:10px; letter-spacing:.02em; color:var(--ink-muted); align-self:center; }
          .mp-audit-log .v { color:var(--ink); }
          .mp-info-table .cell { padding:12px 16px; border-right:1px solid var(--border); border-top:1px solid var(--border); background:oklch(0.98 0.003 90); }
          .mp-info-table .cell:nth-child(-n+4) { border-top:none; }
          .mp-info-table .cell:last-child, .mp-info-table .cell:nth-child(4n) { border-right:none; }
          .mp-info-table .k { display:block; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:4px; }
          .mp-info-table .v { font-size:13px; color:var(--ink); }
          .mp-detail-line { border:1px solid var(--border); border-radius:10px; margin-top:16px; overflow:hidden; }
          .mp-detail-line-body { display:flex; flex-direction:column; }
          @media (max-width: 780px) { .mp-info-table { grid-template-columns: repeat(2, 1fr); } .mp-info-table .cell:nth-child(-n+4) { border-top:1px solid var(--border); } .mp-info-table .cell:nth-child(1), .mp-info-table .cell:nth-child(2) { border-top:none; } .mp-info-table .cell:nth-child(4n) { border-right:1px solid var(--border); } .mp-info-table .cell:nth-child(2n) { border-right:none; } }
          .mp-detail-line-info { padding:18px; border-bottom:1px solid var(--border); }
          .mp-detail-line-info h4 { margin:0 0 12px; font-size:14px; }
          .mp-kv-grid { display:grid; grid-template-columns: repeat(2, 1fr); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
          .mp-kv-grid .cell { padding:10px 14px; border-right:1px solid var(--border); border-top:1px solid var(--border); background:oklch(0.98 0.003 90); font-size:12px; }
          .mp-kv-grid .cell:nth-child(-n+2) { border-top:none; }
          .mp-kv-grid .cell:nth-child(2n) { border-right:none; }
          .mp-kv-grid .lbl { display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:2px; }
          .mp-keterangan-row { border:1px solid var(--border); border-radius:8px; padding:10px 14px; margin-top:10px; background:oklch(0.98 0.003 90); font-size:12px; }
          .mp-attachments { margin-top:14px; }
          .mp-attachments .lbl { display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:6px; }
          .mp-thumb-row { display:flex; flex-wrap:wrap; gap:8px; }
          .mp-thumb { width:64px; height:64px; border-radius:8px; border:1px solid var(--border); object-fit:cover; cursor:pointer; background:#fff; }
          .mp-pdf-chip { width:84px; display:flex; flex-direction:column; align-items:center; gap:5px; text-decoration:none; border:1px solid var(--border); border-radius:8px; padding:8px 6px; background:#fff; }
          .mp-pdf-chip .mp-pdf-badge { font-size:9px; font-weight:700; letter-spacing:.03em; background:#dc2626; color:#fff; padding:2px 7px; border-radius:4px; }
          .mp-pdf-chip .mp-pdf-fname { font-size:10px; text-align:center; color:var(--ink); word-break:break-word; line-height:1.25; max-height:2.5em; overflow:hidden; }
          .mp-price-panel { padding:18px; background:var(--amber-bg, #fdf3e2); }
          .mp-price-panel h4 { margin:0 0 14px; font-size:13px; display:flex; align-items:center; gap:6px; }
          .mp-price-row { display:grid; grid-template-columns: 1.4fr 1fr auto; gap:8px; margin-bottom:8px; align-items:center; }
          .mp-price-row select, .mp-price-row input { padding:7px 8px; border:1px solid var(--border); border-radius:6px; width:100%; box-sizing:border-box; }
          .mp-price-readonly-row { display:flex; justify-content:space-between; font-size:12px; padding:4px 0; border-bottom:1px dashed var(--border); }
          .mp-price-readonly-row:last-child { border-bottom:none; }
          .mp-price-total-row { display:flex; justify-content:space-between; font-size:13px; font-weight:700; padding:10px 0 4px; margin-top:6px; border-top:1px solid var(--border); }
          .mp-saved-log { display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:11px; color:var(--ink-muted); background:rgba(0,0,0,.04); border-radius:6px; padding:8px 10px; margin-bottom:12px; }
          .mp-saved-log-text { flex:1; }
          .mp-saved-log .btn { flex-shrink:0; }
          .mp-price-readonly-block { background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:8px; padding:12px 14px; }
          .mp-detail-line-info .mp-line-split { display:grid; grid-template-columns: 2fr 1fr; gap:20px; }
          @media (max-width: 680px) { .mp-detail-line-info .mp-line-split { grid-template-columns: 1fr; } }
          .mp-detail-line-info .field-stack { display:flex; flex-direction:column; gap:14px; }
          .mp-detail-line-info .field-stack label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:6px; }
          .mp-detail-line-info .field-stack input { width:100%; padding:9px 11px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-size:13px; }
        </style>

        <div class="card">
          <div class="mp-detail-header-card">
            <h2><?= htmlspecialchars($selected['doc_number']) ?> <span class="pill pill-<?= $selected['status'] ?>"><?= strtoupper($selected['status']) ?></span></h2>
            <div class="txn-detail-actions">
              <a class="btn btn-sm btn-ghost" href="manufaktur-penawaran-print.php?id=<?= $selected['id'] ?>" target="_blank">Print</a>
              <a class="btn btn-sm btn-ghost" href="manufaktur-penawaran-pdf.php?id=<?= $selected['id'] ?>" target="_blank">📎 PDF Gabungan (+lampiran)</a>
              <?php if (has_access('manufaktur_penawaran', 'can_edit')): ?>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="manufaktur_penawaran_id" value="<?= $selected['id'] ?>">
                  <select name="status" onchange="this.form.submit();" style="padding:6px 10px; border:1px solid var(--border); border-radius:4px; font-size:12px;">
                    <?php foreach (['draft', 'diajukan', 'diproses', 'selesai', 'void'] as $s): ?>
                      <option value="<?= $s ?>" <?= $selected['status'] === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              <?php endif; ?>
              <?php if (has_access('manufaktur_penawaran', 'can_edit')): foreach ($selectedLines as $el): ?>
                <a class="btn btn-sm btn-ghost" href="manufaktur-penawaran.php?id=<?= $selected['id'] ?>&edit_mj_line=<?= $el['id'] ?>#line-<?= $el['id'] ?>">✎ Edit <?= count($selectedLines) > 1 ? htmlspecialchars($el['product_name_snapshot']) : 'Barang (MJ)' ?></a>
              <?php endforeach; endif; ?>
              <?php if (has_access('manufaktur_penawaran', 'can_delete')): ?>
                <button class="btn btn-sm btn-ghost" type="button" onclick="if(confirm('Hapus dokumen ini?')) __submitDeleteForm('delete_manufaktur_penawaran', {manufaktur_penawaran_id: <?= $selected['id'] ?>})">Hapus</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="mp-info-table">
            <div class="cell"><span class="k">Vendor</span><span class="v"><?= htmlspecialchars($selected['vendor_name']) ?></span></div>
            <div class="cell"><span class="k">Project</span><span class="v"><?= $projName ? htmlspecialchars($projName) : '—' ?></span></div>
            <div class="cell"><span class="k">Tanggal</span><span class="v"><?= htmlspecialchars(date('d M Y', strtotime($selected['tanggal']))) ?></span></div>
            <div class="cell"><span class="k">No. Form PO</span><span class="v"><?= $selected['po_number'] ? htmlspecialchars($selected['po_number']) : '—' ?></span></div>
            <div class="cell"><span class="k">Ketentuan DP</span><span class="v"><?= $selected['dp_terms'] ? htmlspecialchars($selected['dp_terms']) : '—' ?></span></div>
            <div class="cell"><span class="k">Keterangan</span><span class="v"><?= $selected['keterangan'] ? htmlspecialchars($selected['keterangan']) : '—' ?></span></div>
          </div>

          <?php if ($selected['deleted_at']): ?>
            <div class="mp-void-banner">🚫 Dokumen ini sudah <strong>dihapus (void)</strong> oleh <strong><?= htmlspecialchars($headerDeletedByName ?? '—') ?></strong> pada <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['deleted_at']))) ?></div>
          <?php endif; ?>

          <div class="mp-audit-log">
            <div class="row"><span class="k">Dibuat</span><span class="v"><strong><?= htmlspecialchars($headerCreatedByName ?? '—') ?></strong> · <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['created_at']))) ?></span></div>
            <?php if ($selected['updated_at']): ?>
              <div class="row"><span class="k">Diedit</span><span class="v"><strong><?= htmlspecialchars($headerUpdatedByName ?? '—') ?></strong> · <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['updated_at']))) ?></span></div>
            <?php endif; ?>
          </div>
        </div>

        <?php foreach ($selectedLines as $lineIdx => $l): $isLastLine = $lineIdx === array_key_last($selectedLines); ?>
          <div class="card mp-detail-line" id="line-<?= $l['id'] ?>">
            <div class="mp-detail-line-body">
              <div class="mp-detail-line-info">
                <?php if ($editMjLine === (int) $l['id']): ?>
                  <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_line_mj">
                    <input type="hidden" name="line_id" value="<?= $l['id'] ?>">
                    <input type="hidden" name="manufaktur_penawaran_id" value="<?= $selected['id'] ?>">
                    <table class="mp-box-table">
                      <tr>
                        <td class="lbl">1. Nama Barang</td><td class="field-cell"><input type="text" class="mp-combo-product-edit" name="product_name" value="<?= htmlspecialchars($l['product_name_snapshot']) ?>" autocomplete="off"></td>
                        <td class="lbl">Kode Barang</td><td class="field-cell"><input type="text" name="item_code" value="<?= htmlspecialchars($l['item_code'] ?? '') ?>"></td>
                      </tr>
                      <tr>
                        <td class="lbl">2. Ukuran (mm)</td><td class="field-cell"><input type="text" name="size_mm" value="<?= htmlspecialchars($l['size_mm'] ?? '') ?>"></td>
                        <td class="lbl">Tekstur + Top Coat</td><td class="field-cell"><input type="text" name="texture_topcoat" value="<?= htmlspecialchars($l['texture_topcoat'] ?? '') ?>"></td>
                      </tr>
                      <tr>
                        <td class="lbl">3. Finishing (Opsi)</td><td class="field-cell"><input type="text" class="mp-combo-finishing-edit" name="finishing_name" value="<?= htmlspecialchars($l['finishing_snapshot'] ?? '') ?>" autocomplete="off"></td>
                        <td class="lbl" rowspan="6">9. Remark / Catatan Tambahan</td>
                        <td class="field-cell" rowspan="6"><textarea name="keterangan_mj" style="min-height:150px;"><?= htmlspecialchars($l['keterangan_mj'] ?? '') ?></textarea></td>
                      </tr>
                      <tr><td class="lbl">4. Jumlah (Qty)</td><td class="field-cell"><input type="number" name="qty" value="<?= $l['qty'] ?>" min="0.01" step="0.01"></td></tr>
                      <tr><td class="lbl">5. Material 1</td><td class="field-cell"><input type="text" class="mp-combo-material-edit" name="material_name" value="<?= htmlspecialchars($l['material_snapshot'] ?? '') ?>" autocomplete="off"></td></tr>
                      <tr><td class="lbl">6. Material 2</td><td class="field-cell"><input type="text" class="mp-combo-material2-edit" name="material2_name" value="<?= htmlspecialchars($l['material2_snapshot'] ?? '') ?>" autocomplete="off"></td></tr>
                      <tr><td class="lbl">7. Wood</td><td class="field-cell"><input type="text" name="wood" value="<?= htmlspecialchars($l['wood'] ?? '') ?>"></td></tr>
                      <tr><td class="lbl">8. Deadline</td><td class="field-cell"><input type="date" name="deadline_mj" value="<?= htmlspecialchars($l['deadline_mj'] ?? '') ?>"></td></tr>
                      <tr>
                        <td class="lbl">9. Gambar Kerja</td>
                        <td class="field-cell file-cell" colspan="3"><input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf"></td>
                      </tr>
                    </table>
                    <div style="margin-top:14px; display:flex; gap:8px;">
                      <button type="submit" class="btn btn-sm">Simpan</button>
                      <a class="btn btn-sm btn-ghost" href="manufaktur-penawaran.php?id=<?= $selected['id'] ?>#line-<?= $l['id'] ?>">Batal</a>
                    </div>
                  </form>
                <?php else: ?>
                  <h4><?= htmlspecialchars($l['product_name_snapshot']) ?></h4>
                  <div class="mp-kv-grid">
                    <div class="cell"><span class="lbl">Kode Barang</span><?= $l['item_code'] ? htmlspecialchars($l['item_code']) : '—' ?></div>
                    <div class="cell"><span class="lbl">Ukuran (mm)</span><?= $l['size_mm'] ? htmlspecialchars($l['size_mm']) : '—' ?></div>
                    <div class="cell"><span class="lbl">Finishing</span><?= $l['finishing_snapshot'] ? htmlspecialchars($l['finishing_snapshot']) : '—' ?></div>
                    <div class="cell"><span class="lbl">Tekstur + Top Coat</span><?= $l['texture_topcoat'] ? htmlspecialchars($l['texture_topcoat']) : '—' ?></div>
                    <div class="cell"><span class="lbl">Material 1</span><?= $l['material_snapshot'] ? htmlspecialchars($l['material_snapshot']) : '—' ?></div>
                    <div class="cell"><span class="lbl">Material 2</span><?= $l['material2_snapshot'] ? htmlspecialchars($l['material2_snapshot']) : '—' ?></div>
                    <div class="cell"><span class="lbl">Wood</span><?= $l['wood'] ? htmlspecialchars($l['wood']) : '—' ?></div>
                    <div class="cell"><span class="lbl">Qty</span><?= rtrim(rtrim(number_format((float) $l['qty'], 2, ',', '.'), '0'), ',') ?></div>
                    <div class="cell"><span class="lbl">Deadline</span><?= $l['deadline_mj'] ? htmlspecialchars(date('d M Y', strtotime($l['deadline_mj']))) : '—' ?></div>
                  </div>
                  <?php if ($l['keterangan_mj']): ?><div class="mp-keterangan-row"><span class="lbl" style="display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:2px;">Keterangan</span><?= htmlspecialchars($l['keterangan_mj']) ?></div><?php endif; ?>
                  <?php if ($l['attachments']): ?>
                    <div class="mp-attachments">
                      <span class="lbl">Gambar Kerja</span>
                      <div class="mp-thumb-row">
                        <?php foreach ($l['attachments'] as $a):
                          $ext = strtolower(pathinfo($a['file_path'], PATHINFO_EXTENSION));
                          $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
                        ?>
                          <?php if ($isImage): ?>
                            <img src="<?= htmlspecialchars($a['file_path']) ?>" alt="<?= htmlspecialchars($a['original_name']) ?>" class="mp-thumb" onclick="mpPreviewImage('<?= htmlspecialchars($a['file_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['original_name'], ENT_QUOTES) ?>')">
                          <?php else: ?>
                            <a href="<?= htmlspecialchars($a['file_path']) ?>" target="_blank" class="mp-pdf-chip"><span class="mp-pdf-badge">PDF</span><span class="mp-pdf-fname"><?= htmlspecialchars($a['original_name']) ?></span></a>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

              <?php
              $alreadySaved = !empty($l['updated_at_pabrik']) || ($isLastLine && $selected['detail_updated_at']);
              $showEditForm = $canProduction && (!$alreadySaved || $editLine === (int) $l['id']);
              ?>
              <div class="mp-price-panel">
                <h4>💰 Harga, Timeline, Remark &amp; Detail Final — Tim Manufaktur</h4>
                <?php if ($alreadySaved): ?>
                  <div class="mp-saved-log">
                    <div class="mp-saved-log-text">
                      <?php if ($l['update_transaction_no']): ?>No. Transaksi: <strong><?= htmlspecialchars($l['update_transaction_no']) ?></strong><br><?php endif; ?>
                      Disimpan oleh <strong><?= htmlspecialchars($l['updated_by_pabrik_name'] ?? $detailUpdatedByName ?? '—') ?></strong> pada <?= htmlspecialchars(date('d M Y, H:i', strtotime($l['updated_at_pabrik'] ?? $selected['detail_updated_at']))) ?>
                    </div>
                    <?php if ($canProduction && !$showEditForm): ?>
                      <a class="btn btn-sm btn-ghost" href="manufaktur-penawaran.php?id=<?= $selected['id'] ?>&edit_line=<?= $l['id'] ?>#line-<?= $l['id'] ?>">✎ Edit</a>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if ($showEditForm): ?>
                  <form method="post" class="mp-pricing-form" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_line_pricing">
                    <input type="hidden" name="line_id" value="<?= $l['id'] ?>">
                    <input type="hidden" name="manufaktur_penawaran_id" value="<?= $selected['id'] ?>">
                    <?php if ($isLastLine): ?><input type="hidden" name="include_final_detail" value="1"><?php endif; ?>
                    <div class="mp-price-rows">
                      <?php if ($l['prices']): foreach ($l['prices'] as $pr): ?>
                        <div class="mp-price-row">
                          <select name="price_type[]">
                            <?php foreach (MP_PRICE_TYPE_LABELS as $pk => $plabel): ?>
                              <option value="<?= $pk ?>" <?= $pr['price_type'] === $pk ? 'selected' : '' ?>><?= $plabel ?></option>
                            <?php endforeach; ?>
                          </select>
                          <input type="text" inputmode="numeric" class="rupiah-input mp-price-value" name="price_value[]" value="<?= (int) $pr['price_value'] ?>" placeholder="Rp">
                          <button type="button" class="btn btn-sm btn-ghost mp-remove-price-row">✕</button>
                        </div>
                      <?php endforeach; endif; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-ghost mp-add-price-row">+ Tambah Harga</button>
                    <div class="mp-price-total-row"><span>Total Harga</span><strong class="mp-price-total">Rp 0</strong></div>
                    <div style="margin-top:12px;">
                      <span class="mp-field-label" style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:6px;">Timeline</span>
                      <input type="date" name="timeline_pabrik" value="<?= htmlspecialchars($l['timeline_pabrik'] ?? '') ?>" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
                    </div>
                    <div style="margin-top:12px;">
                      <span class="mp-field-label" style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:6px;">Remark</span>
                      <input type="text" name="remark_pabrik" value="<?= htmlspecialchars($l['remark_pabrik'] ?? '') ?>" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box;">
                    </div>
                    <div style="margin-top:12px;">
                      <span class="mp-field-label" style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:6px;">Lampiran Tim Manufaktur (bisa lebih dari 1)</span>
                      <input type="file" name="produksi_attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" style="width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; background:#fff;">
                      <?php if ($l['attachments_produksi']): ?>
                        <div class="mp-thumb-row" style="margin-top:8px;">
                          <?php foreach ($l['attachments_produksi'] as $a):
                            $ext = strtolower(pathinfo($a['file_path'], PATHINFO_EXTENSION));
                            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
                          ?>
                            <?php if ($isImage): ?>
                              <img src="<?= htmlspecialchars($a['file_path']) ?>" alt="<?= htmlspecialchars($a['original_name']) ?>" class="mp-thumb" onclick="mpPreviewImage('<?= htmlspecialchars($a['file_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['original_name'], ENT_QUOTES) ?>')">
                            <?php else: ?>
                              <a href="<?= htmlspecialchars($a['file_path']) ?>" target="_blank" class="mp-pdf-chip"><span class="mp-pdf-badge">PDF</span><span class="mp-pdf-fname"><?= htmlspecialchars($a['original_name']) ?></span></a>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>

                    <?php if ($isLastLine): ?>
                      <h4 style="margin:16px 0 10px; font-size:13px; display:flex; align-items:center; gap:6px; border-top:1px dashed rgba(0,0,0,.15); padding-top:14px;">📝 Detail Final Penawaran (Wording &amp; Rekening)</h4>
                      <div class="mp-final-form-grid">
                        <div><label>No. Form Purchase Order</label><input type="text" name="po_number" value="<?= htmlspecialchars($selected['po_number'] ?? '') ?>" placeholder="cth. 0001-MJ-VIII-26"></div>
                        <div><label>Ketentuan DP</label><input type="text" name="dp_terms" value="<?= htmlspecialchars($selected['dp_terms'] ?? 'DP 50%') ?>" placeholder="cth. DP 50%"></div>
                        <div style="grid-column:1/-1;"><label>Wording / Syarat Pembayaran &amp; Pengiriman</label><textarea name="wording_pelunasan" placeholder="Contoh: Setelah pengerjaan selesai akan kami videokan dan pelunasan dilakukan setelah video dikirim..."><?= htmlspecialchars($selected['wording_pelunasan'] ?? '') ?></textarea></div>
                        <div><label>Nama Bank</label><input type="text" name="bank_name" value="<?= htmlspecialchars($selected['bank_name'] ?? '') ?>" placeholder="cth. SeaBank"></div>
                        <div><label>No. Rekening</label><input type="text" name="bank_norek" value="<?= htmlspecialchars($selected['bank_norek'] ?? '') ?>"></div>
                        <div><label>Atas Nama</label><input type="text" name="bank_an" value="<?= htmlspecialchars($selected['bank_an'] ?? '') ?>"></div>
                      </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-sm" style="margin-top:14px; width:100%;">Simpan Semua</button>
                  </form>
                <?php else: ?>
                  <?php if ($l['prices']): foreach ($l['prices'] as $pr): ?>
                    <div class="mp-price-readonly-row"><span><?= MP_PRICE_TYPE_LABELS[$pr['price_type']] ?? $pr['price_type'] ?></span><strong>Rp <?= number_format((float) $pr['price_value'], 0, ',', '.') ?></strong></div>
                  <?php endforeach; ?>
                    <div class="mp-price-total-row"><span>Total Harga</span><strong>Rp <?= number_format(array_sum(array_column($l['prices'], 'price_value')), 0, ',', '.') ?></strong></div>
                  <?php else: ?>
                    <div style="font-size:12px; color:var(--ink-muted);">Belum diisi tim manufaktur.</div>
                  <?php endif; ?>
                  <div class="mp-price-readonly-row"><span>Timeline</span><strong><?= $l['timeline_pabrik'] ? htmlspecialchars(date('d M Y', strtotime($l['timeline_pabrik']))) : '—' ?></strong></div>
                  <div class="mp-price-readonly-row"><span>Remark</span><strong><?= $l['remark_pabrik'] ? htmlspecialchars($l['remark_pabrik']) : '—' ?></strong></div>
                  <?php if ($l['attachments_produksi']): ?>
                    <div class="mp-thumb-row" style="margin-top:8px;">
                      <?php foreach ($l['attachments_produksi'] as $a):
                        $ext = strtolower(pathinfo($a['file_path'], PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
                      ?>
                        <?php if ($isImage): ?>
                          <img src="<?= htmlspecialchars($a['file_path']) ?>" alt="<?= htmlspecialchars($a['original_name']) ?>" class="mp-thumb" onclick="mpPreviewImage('<?= htmlspecialchars($a['file_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['original_name'], ENT_QUOTES) ?>')">
                        <?php else: ?>
                          <a href="<?= htmlspecialchars($a['file_path']) ?>" target="_blank" class="mp-pdf-chip"><span class="mp-pdf-badge">PDF</span><span class="mp-pdf-fname"><?= htmlspecialchars($a['original_name']) ?></span></a>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <?php if ($isLastLine): ?>
                    <h4 style="margin:16px 0 10px; font-size:13px; display:flex; align-items:center; gap:6px; border-top:1px dashed rgba(0,0,0,.15); padding-top:14px;">📝 Detail Final Penawaran (Wording &amp; Rekening)</h4>
                    <div style="font-size:12.5px; margin-bottom:10px;">No. Form PO: <strong><?= $selected['po_number'] ? htmlspecialchars($selected['po_number']) : '—' ?></strong> · Ketentuan DP: <strong><?= $selected['dp_terms'] ? htmlspecialchars($selected['dp_terms']) : '—' ?></strong></div>
                    <div style="font-size:12.5px; white-space:pre-wrap; margin-bottom:10px;"><?= $selected['wording_pelunasan'] ? htmlspecialchars($selected['wording_pelunasan']) : '<span style="color:var(--ink-muted);">Belum diisi.</span>' ?></div>
                    <div style="font-size:12.5px; margin-bottom:14px;">Bank: <strong><?= $selected['bank_name'] ? htmlspecialchars($selected['bank_name']) : '—' ?></strong> · Norek: <strong><?= $selected['bank_norek'] ? htmlspecialchars($selected['bank_norek']) : '—' ?></strong> · A/N: <strong><?= $selected['bank_an'] ? htmlspecialchars($selected['bank_an']) : '—' ?></strong></div>
                  <?php endif; ?>
                <?php endif; ?>
                <?php if ($isLastLine): ?>
                  <div style="margin-top:14px;"><a class="btn btn-sm" href="manufaktur-penawaran-print-detail.php?id=<?= $selected['id'] ?>" target="_blank">🖨 Print Form Penawaran Harga (Detail)</a></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <style>
          .mp-final-form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:14px; }
          .mp-final-form-grid label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:6px; }
          .mp-final-form-grid input, .mp-final-form-grid textarea { width:100%; padding:9px 11px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-size:13px; font-family:inherit; }
          .mp-final-form-grid textarea { resize:vertical; min-height:70px; }
        </style>
      <?php endif; ?>
    </div>
  </div>

  <div class="modal-scrim" id="mp-image-preview-modal">
    <div class="modal-card" style="max-width:min(720px, 92vw);">
      <div class="modal-head"><h3 id="mp-image-preview-title">Preview</h3><button class="modal-close" data-close-modal="mp-image-preview-modal">&times;</button></div>
      <div class="modal-body" style="text-align:center;">
        <img id="mp-image-preview-img" src="" alt="" style="max-width:100%; max-height:70vh; border-radius:8px;">
      </div>
    </div>
  </div>

  <script>
  function mpPreviewImage(src, name) {
    document.getElementById('mp-image-preview-img').src = src;
    document.getElementById('mp-image-preview-title').textContent = name;
    document.getElementById('mp-image-preview-modal').classList.add('open');
  }
  var MP_PRODUCT_NAMES_D = <?= json_encode(array_column($productsForPicker, 'name')) ?>;
  var MP_FINISHING_NAMES_D = <?= json_encode(array_column($finishingsList, 'name')) ?>;
  var MP_MATERIAL_NAMES_D = <?= json_encode(array_column($materialsList, 'name')) ?>;

  document.addEventListener('DOMContentLoaded', function () {

  var mpRailSearch = document.getElementById('mp-rail-search');
  if (mpRailSearch) {
    mpRailSearch.addEventListener('input', function () {
      var q = mpRailSearch.value.trim().toLowerCase();
      document.querySelectorAll('#mp-rail-list .txn-rail-item').forEach(function (item) {
        var hay = item.getAttribute('data-search') || '';
        item.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }

  var comboProduct = document.querySelector('.mp-combo-product-edit');
  var comboFinishing = document.querySelector('.mp-combo-finishing-edit');
  var comboMaterial = document.querySelector('.mp-combo-material-edit');
  var comboMaterial2 = document.querySelector('.mp-combo-material2-edit');
  if (comboProduct) initCombobox(comboProduct, MP_PRODUCT_NAMES_D);
  if (comboFinishing) initCombobox(comboFinishing, MP_FINISHING_NAMES_D);
  if (comboMaterial) initCombobox(comboMaterial, MP_MATERIAL_NAMES_D);
  if (comboMaterial2) initCombobox(comboMaterial2, MP_MATERIAL_NAMES_D);

  function mpRecalcTotal(form) {
    var total = 0;
    form.querySelectorAll('.mp-price-value').forEach(function (el) {
      total += parseInt(el.value.replace(/[^\d]/g, ''), 10) || 0;
    });
    var totalEl = form.querySelector('.mp-price-total');
    if (totalEl) totalEl.textContent = 'Rp ' + total.toLocaleString('id-ID');
  }

  document.querySelectorAll('.mp-pricing-form').forEach(function (form) {
    form.querySelectorAll('.mp-price-value').forEach(function (el) {
      if (window.initRupiahInput) initRupiahInput(el);
      el.addEventListener('input', function () { mpRecalcTotal(form); });
    });
    form.querySelectorAll('.mp-remove-price-row').forEach(function (btn) {
      btn.addEventListener('click', function () {
        btn.closest('.mp-price-row').remove();
        mpRecalcTotal(form);
      });
    });
    mpRecalcTotal(form);
  });

  document.querySelectorAll('.mp-add-price-row').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = btn.closest('.mp-pricing-form');
      var wrap = form.querySelector('.mp-price-rows');
      var row = document.createElement('div');
      row.className = 'mp-price-row';
      row.innerHTML =
        '<select name="price_type[]">' +
        <?= json_encode(implode('', array_map(fn($k, $v) => "<option value=\"$k\">$v</option>", array_keys(MP_PRICE_TYPE_LABELS), MP_PRICE_TYPE_LABELS))) ?> +
        '</select>' +
        '<input type="text" inputmode="numeric" class="rupiah-input mp-price-value" name="price_value[]" value="0" placeholder="Rp">' +
        '<button type="button" class="btn btn-sm btn-ghost mp-remove-price-row">✕</button>';
      wrap.appendChild(row);
      var newInput = row.querySelector('.mp-price-value');
      if (window.initRupiahInput) initRupiahInput(newInput);
      newInput.addEventListener('input', function () { mpRecalcTotal(form); });
      row.querySelector('.mp-remove-price-row').addEventListener('click', function () {
        row.remove();
        mpRecalcTotal(form);
      });
      mpRecalcTotal(form);
    });
  });

  });
  </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
