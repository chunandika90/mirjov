<?php
$pageTitle = 'Form Product Series';
$activeMenu = 'manufaktur_po';
require __DIR__ . '/includes/header.php';
require_module_access('manufaktur_po');
require_once __DIR__ . '/../backoffice-shared/image_upload.php';

$pdo = db();
$flash = null;

/**
 * Nomor dokumen: PO/{tahun}/{bulan 2 digit}/{nomor 4 digit} — reuse tabel doc_counters.
 * doc_type-nya 'MPO' (bukan 'PO') SENGAJA biar gak nyatu sama counter modul PO lama —
 * kolom doc_type collation-nya case-insensitive (utf8mb4_0900_ai_ci), jadi kalau dulu
 * dipake 'PO' doang, itu ke-anggep SAMA sama 'po' punya modul lama (purchase_orders.php),
 * counternya kebagi berdua tanpa sengaja.
 */
function next_manufaktur_po_number(PDO $pdo, int $organizationId): string
{
    $year = (int) date('Y');
    $month = (int) date('n');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT last_number FROM doc_counters WHERE organization_id=? AND doc_type=? AND year=? FOR UPDATE');
        $stmt->execute([$organizationId, 'MPO', $year]);
        $row = $stmt->fetch();
        if ($row) {
            $next = (int) $row['last_number'] + 1;
            $pdo->prepare('UPDATE doc_counters SET last_number=? WHERE organization_id=? AND doc_type=? AND year=?')
                ->execute([$next, $organizationId, 'MPO', $year]);
        } else {
            $next = 1;
            $pdo->prepare('INSERT INTO doc_counters (organization_id, doc_type, year, last_number) VALUES (?,?,?,?)')
                ->execute([$organizationId, 'MPO', $year, $next]);
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTransaction) $pdo->rollBack();
        throw $e;
    }
    return sprintf('PO/%d/%02d/%04d', $year, $month, $next);
}

function touch_mpo_header(PDO $pdo, int $headerId, int $userId): void
{
    $pdo->prepare('UPDATE manufaktur_po SET updated_by=?, updated_at=NOW() WHERE id=?')->execute([$userId, $headerId]);
}

function find_or_create_mpo_vendor(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare("SELECT id FROM contacts WHERE organization_id=? AND name=? AND type IN ('vendor','both') LIMIT 1");
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO contacts (organization_id, type, name) VALUES (?, 'vendor', ?)")->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

function find_or_create_mpo_project(PDO $pdo, int $orgId, string $name, int $userId): int
{
    $stmt = $pdo->prepare('SELECT id FROM projects WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare('INSERT INTO projects (organization_id, name, created_by) VALUES (?,?,?)')->execute([$orgId, $name, $userId]);
    return (int) $pdo->lastInsertId();
}

function find_or_create_mpo_product(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO products (organization_id, name, unit) VALUES (?,?,'pcs')")->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_manufaktur_po') {
            require_module_access('manufaktur_po', 'can_create');
            $tanggal = $_POST['tanggal'] ?? '';
            $poNumberVendor = trim($_POST['po_number_vendor'] ?? '') ?: null;
            $vendorName = trim($_POST['vendor_name'] ?? '');
            $projectName = trim($_POST['project_name'] ?? '');
            $pemesan = trim($_POST['pemesan'] ?? '') ?: null;
            $waktuProduksi = trim($_POST['waktu_produksi'] ?? '') ?: null;
            $keterangan = trim($_POST['keterangan'] ?? '') ?: null;
            $productName = trim($_POST['product_name'] ?? '');
            $series = trim($_POST['series'] ?? '') ?: null;
            $sizeMm = trim($_POST['size_mm'] ?? '') ?: null;
            $qty = (float) ($_POST['qty'] ?? 1) ?: 1;
            $itemCode = trim($_POST['item_code'] ?? '') ?: null;
            $remarks = trim($_POST['remarks'] ?? '') ?: null;
            $compNames = $_POST['comp_name'] ?? [];
            $compPembuat = $_POST['comp_pembuat'] ?? [];
            $compCode = $_POST['comp_code'] ?? [];
            $compMaterial = $_POST['comp_material'] ?? [];
            $compPhotos = $_FILES['comp_photo'] ?? [];
            $files = $_FILES['attachments'] ?? [];

            if (!$tanggal) throw new RuntimeException('Tanggal wajib diisi.');
            if ($vendorName === '') throw new RuntimeException('Vendor wajib diisi.');
            if ($productName === '') throw new RuntimeException('Produk wajib diisi.');

            $pdo->beginTransaction();
            try {
                $vendorId = find_or_create_mpo_vendor($pdo, $org['organization_id'], $vendorName);
                $projectId = $projectName !== '' ? find_or_create_mpo_project($pdo, $org['organization_id'], $projectName, $user['id']) : null;
                $productId = find_or_create_mpo_product($pdo, $org['organization_id'], $productName);

                $docNumber = next_manufaktur_po_number($pdo, $org['organization_id']);
                $pdo->prepare('INSERT INTO manufaktur_po (organization_id, doc_number, po_number_vendor, tanggal, vendor_id, project_id, pemesan, waktu_produksi, keterangan, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$org['organization_id'], $docNumber, $poNumberVendor, $tanggal, $vendorId, $projectId, $pemesan, $waktuProduksi, $keterangan, $user['id']]);
                $headerId = (int) $pdo->lastInsertId();

                $pdo->prepare('INSERT INTO manufaktur_po_lines (manufaktur_po_id, product_id, product_name_snapshot, series, size_mm, qty, item_code, remarks) VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([$headerId, $productId, $productName, $series, $sizeMm, $qty, $itemCode, $remarks]);
                $lineId = (int) $pdo->lastInsertId();

                $compStmt = $pdo->prepare('INSERT INTO manufaktur_po_line_components (line_id, component_name, pembuat, code, material, photo_path) VALUES (?,?,?,?,?,?)');
                foreach ($compNames as $i => $cName) {
                    $cName = trim($cName);
                    if ($cName === '') continue;
                    $photoPath = null;
                    if (($compPhotos['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $cFile = ['name' => $compPhotos['name'][$i], 'type' => $compPhotos['type'][$i], 'tmp_name' => $compPhotos['tmp_name'][$i], 'error' => $compPhotos['error'][$i], 'size' => $compPhotos['size'][$i]];
                        $photoPath = save_manufaktur_line_attachment($cFile)['file_path'];
                    }
                    $compStmt->execute([$lineId, $cName, trim($compPembuat[$i] ?? '') ?: null, trim($compCode[$i] ?? '') ?: null, trim($compMaterial[$i] ?? '') ?: null, $photoPath]);
                }

                if (!empty($files['name'])) {
                    $attStmt = $pdo->prepare("INSERT INTO manufaktur_po_line_attachments (line_id, file_path, original_name, uploaded_by, source) VALUES (?,?,?,?,'mj')");
                    foreach ($files['name'] as $j => $name) {
                        if (($files['error'][$j] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                        $file = ['name' => $name, 'type' => $files['type'][$j], 'tmp_name' => $files['tmp_name'][$j], 'error' => $files['error'][$j], 'size' => $files['size'][$j]];
                        $saved = save_manufaktur_line_attachment($file);
                        $attStmt->execute([$lineId, $saved['file_path'], $saved['original_name'], $user['id']]);
                    }
                }

                $pdo->commit();
                header('Location: manufaktur-po.php?id=' . $headerId);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'update_manufaktur_po_full') {
            require_module_access('manufaktur_po', 'can_edit');
            $headerId = (int) ($_POST['manufaktur_po_id'] ?? 0);
            $lineId = (int) ($_POST['line_id'] ?? 0);
            $check = $pdo->prepare('SELECT l.id FROM manufaktur_po_lines l JOIN manufaktur_po h ON h.id=l.manufaktur_po_id WHERE l.id=? AND h.id=? AND h.organization_id=?');
            $check->execute([$lineId, $headerId, $org['organization_id']]);
            if (!$check->fetch()) throw new RuntimeException('Dokumen tidak ditemukan.');

            $tanggal = $_POST['tanggal'] ?? '';
            $poNumberVendor = trim($_POST['po_number_vendor'] ?? '') ?: null;
            $vendorName = trim($_POST['vendor_name'] ?? '');
            $projectName = trim($_POST['project_name'] ?? '');
            $pemesan = trim($_POST['pemesan'] ?? '') ?: null;
            $waktuProduksi = trim($_POST['waktu_produksi'] ?? '') ?: null;
            $keterangan = trim($_POST['keterangan'] ?? '') ?: null;
            $productName = trim($_POST['product_name'] ?? '');
            $series = trim($_POST['series'] ?? '') ?: null;
            $sizeMm = trim($_POST['size_mm'] ?? '') ?: null;
            $qty = (float) ($_POST['qty'] ?? 1) ?: 1;
            $itemCode = trim($_POST['item_code'] ?? '') ?: null;
            $remarks = trim($_POST['remarks'] ?? '') ?: null;
            $compNames = $_POST['comp_name'] ?? [];
            $compPembuat = $_POST['comp_pembuat'] ?? [];
            $compCode = $_POST['comp_code'] ?? [];
            $compMaterial = $_POST['comp_material'] ?? [];
            $compExistingPhoto = $_POST['comp_existing_photo'] ?? [];
            $compPhotos = $_FILES['comp_photo'] ?? [];
            $files = $_FILES['attachments'] ?? [];

            if (!$tanggal) throw new RuntimeException('Tanggal wajib diisi.');
            if ($vendorName === '') throw new RuntimeException('Vendor wajib diisi.');
            if ($productName === '') throw new RuntimeException('Produk wajib diisi.');

            $pdo->beginTransaction();
            try {
                $vendorId = find_or_create_mpo_vendor($pdo, $org['organization_id'], $vendorName);
                $projectId = $projectName !== '' ? find_or_create_mpo_project($pdo, $org['organization_id'], $projectName, $user['id']) : null;
                $productId = find_or_create_mpo_product($pdo, $org['organization_id'], $productName);

                $pdo->prepare('UPDATE manufaktur_po SET po_number_vendor=?, tanggal=?, vendor_id=?, project_id=?, pemesan=?, waktu_produksi=?, keterangan=? WHERE id=?')
                    ->execute([$poNumberVendor, $tanggal, $vendorId, $projectId, $pemesan, $waktuProduksi, $keterangan, $headerId]);

                $pdo->prepare('UPDATE manufaktur_po_lines SET product_id=?, product_name_snapshot=?, series=?, size_mm=?, qty=?, item_code=?, remarks=? WHERE id=?')
                    ->execute([$productId, $productName, $series, $sizeMm, $qty, $itemCode, $remarks, $lineId]);

                $pdo->prepare('DELETE FROM manufaktur_po_line_components WHERE line_id=?')->execute([$lineId]);
                $compStmt = $pdo->prepare('INSERT INTO manufaktur_po_line_components (line_id, component_name, pembuat, code, material, photo_path) VALUES (?,?,?,?,?,?)');
                foreach ($compNames as $i => $cName) {
                    $cName = trim($cName);
                    if ($cName === '') continue;
                    $photoPath = trim($compExistingPhoto[$i] ?? '') ?: null;
                    if (($compPhotos['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                        $cFile = ['name' => $compPhotos['name'][$i], 'type' => $compPhotos['type'][$i], 'tmp_name' => $compPhotos['tmp_name'][$i], 'error' => $compPhotos['error'][$i], 'size' => $compPhotos['size'][$i]];
                        $photoPath = save_manufaktur_line_attachment($cFile)['file_path'];
                    }
                    $compStmt->execute([$lineId, $cName, trim($compPembuat[$i] ?? '') ?: null, trim($compCode[$i] ?? '') ?: null, trim($compMaterial[$i] ?? '') ?: null, $photoPath]);
                }

                if (!empty($files['name'])) {
                    $attStmt = $pdo->prepare("INSERT INTO manufaktur_po_line_attachments (line_id, file_path, original_name, uploaded_by, source) VALUES (?,?,?,?,'mj')");
                    foreach ($files['name'] as $j => $name) {
                        if (($files['error'][$j] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                        $file = ['name' => $name, 'type' => $files['type'][$j], 'tmp_name' => $files['tmp_name'][$j], 'error' => $files['error'][$j], 'size' => $files['size'][$j]];
                        $saved = save_manufaktur_line_attachment($file);
                        $attStmt->execute([$lineId, $saved['file_path'], $saved['original_name'], $user['id']]);
                    }
                }

                $pdo->commit();
                touch_mpo_header($pdo, $headerId, $user['id']);
                header('Location: manufaktur-po.php?id=' . $headerId);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'update_status_po') {
            require_module_access('manufaktur_po', 'can_edit');
            $id = (int) ($_POST['manufaktur_po_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            if (in_array($status, ['draft', 'diajukan', 'diproses', 'selesai', 'void'], true)) {
                $pdo->prepare('UPDATE manufaktur_po SET status=? WHERE id=? AND organization_id=?')->execute([$status, $id, $org['organization_id']]);
                touch_mpo_header($pdo, $id, $user['id']);
                $flash = ['ok', 'Status diperbarui.'];
            }
        } elseif ($action === 'delete_manufaktur_po') {
            require_module_access('manufaktur_po', 'can_delete');
            $id = (int) ($_POST['manufaktur_po_id'] ?? 0);
            $pdo->prepare('UPDATE manufaktur_po SET deleted_by=?, deleted_at=NOW() WHERE id=? AND organization_id=?')
                ->execute([$user['id'], $id, $org['organization_id']]);
            $flash = ['ok', 'Form Product Series ditandai dihapus (void).'];
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

$productsList = $pdo->prepare('SELECT id, name FROM products WHERE organization_id=? ORDER BY name');
$productsList->execute([$org['organization_id']]);
$productsList = $productsList->fetchAll();

$isNewForm = isset($_GET['new']);
$editId = (int) ($_GET['edit'] ?? 0);
$isEditMode = $editId > 0;
$editHeader = null;
$editLine = null;
if ($isEditMode) {
    $eStmt = $pdo->prepare('SELECT * FROM manufaktur_po WHERE id=? AND organization_id=?');
    $eStmt->execute([$editId, $org['organization_id']]);
    $editHeader = $eStmt->fetch() ?: null;
    if ($editHeader) {
        $evStmt = $pdo->prepare('SELECT name FROM contacts WHERE id=?');
        $evStmt->execute([$editHeader['vendor_id']]);
        $editHeader['vendor_name'] = $evStmt->fetch()['name'] ?? '';
        $editHeader['project_name'] = '';
        if ($editHeader['project_id']) {
            $epStmt = $pdo->prepare('SELECT name FROM projects WHERE id=?');
            $epStmt->execute([$editHeader['project_id']]);
            $editHeader['project_name'] = $epStmt->fetch()['name'] ?? '';
        }
        $elStmt = $pdo->prepare('SELECT * FROM manufaktur_po_lines WHERE manufaktur_po_id=? LIMIT 1');
        $elStmt->execute([$editId]);
        $editLine = $elStmt->fetch() ?: null;
        if ($editLine) {
            $ecStmt = $pdo->prepare('SELECT * FROM manufaktur_po_line_components WHERE line_id=?');
            $ecStmt->execute([$editLine['id']]);
            $editLine['components'] = $ecStmt->fetchAll();
            $eaStmt = $pdo->prepare('SELECT * FROM manufaktur_po_line_attachments WHERE line_id=?');
            $eaStmt->execute([$editLine['id']]);
            $editLine['attachments'] = $eaStmt->fetchAll();
        }
    } else {
        $isEditMode = false;
    }
}

if (!$isNewForm && !$isEditMode) {
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

    $railStmt = $pdo->prepare(
        "SELECT h.*, c.name AS vendor_name, p.name AS project_name FROM manufaktur_po h
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
        $sStmt = $pdo->prepare('SELECT h.*, c.name AS vendor_name, p.name AS project_name FROM manufaktur_po h JOIN contacts c ON c.id=h.vendor_id LEFT JOIN projects p ON p.id=h.project_id WHERE h.id=? AND h.organization_id=?');
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
        $headerCreatedByName = $fetchUserName($selected['created_by']);
        $headerUpdatedByName = $fetchUserName($selected['updated_by']);
        $headerDeletedByName = $fetchUserName($selected['deleted_by']);

        $lStmt = $pdo->prepare('SELECT * FROM manufaktur_po_lines WHERE manufaktur_po_id=?');
        $lStmt->execute([$selected['id']]);
        $selectedLines = $lStmt->fetchAll();

        $compStmt = $pdo->prepare('SELECT * FROM manufaktur_po_line_components WHERE line_id=?');
        $attStmt = $pdo->prepare('SELECT * FROM manufaktur_po_line_attachments WHERE line_id=?');
        foreach ($selectedLines as &$sl) {
            $compStmt->execute([$sl['id']]);
            $sl['components'] = $compStmt->fetchAll();
            $attStmt->execute([$sl['id']]);
            $sl['attachments'] = $attStmt->fetchAll();
        }
        unset($sl);
    }
}
?>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<?php if ($isNewForm || $isEditMode): ?>
  <style>
    .mpo-page-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; }
    .mpo-page-head h2 { margin:0 0 4px; font-size:20px; }
    .mpo-page-head p { margin:0; font-size:13px; color:var(--ink-muted); }
    .mpo-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:18px; box-shadow:var(--shadow-card); }
    .mpo-section-head { margin-bottom:16px; }
    .mpo-section-head h3 { margin:0 0 2px; font-size:14px; }
    .mpo-section-head p { margin:0; font-size:12px; color:var(--ink-muted); }
    .mpo-form-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px; }
    .mpo-section label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:6px; }
    .mpo-section input[type=text], .mpo-section input[type=date], .mpo-section input[type=number], .mpo-section input[type=file] { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; box-sizing:border-box; font-size:13px; }
    .mpo-comp-list { display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 260px)); gap:14px; margin-bottom:14px; }
    .mpo-comp-row { border:1px solid var(--border); border-radius:10px; padding:12px; background:oklch(0.985 0.003 90); }
    .mpo-comp-row-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
    .mpo-comp-row-head span { font-size:11px; font-weight:700; text-transform:uppercase; color:var(--ink-muted); }
    .mpo-comp-row .field-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px; }
    .mpo-comp-row input { width:100%; padding:7px 9px; border:1px solid var(--border); border-radius:6px; box-sizing:border-box; font-size:12px; margin-bottom:8px; }
    .mpo-comp-row label { display:block; font-size:9.5px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:3px; }
    .mpo-submit-row { display:flex; justify-content:flex-end; gap:10px; }
    .mpo-line-split { display:grid; grid-template-columns: 2fr 1fr; gap:20px; }
    @media (max-width: 680px) { .mpo-line-split { grid-template-columns: 1fr; } }
    .mpo-line-split .field-stack { display:flex; flex-direction:column; gap:16px; }

    /* Tabel kotak-kotak — sama gayanya kayak Form Penawaran Harga biar konsisten se-modul manufaktur. */
    table.mpo-box-table { width:100%; border-collapse:collapse; table-layout:fixed; }
    table.mpo-box-table td { border:1px solid var(--border); padding:0; vertical-align:top; }
    table.mpo-box-table td.lbl { width:22%; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); background:oklch(0.97 0.003 90); padding:7px 9px; }
    table.mpo-box-table td.field-cell { padding:2px 4px; }
    table.mpo-box-table input, table.mpo-box-table textarea { width:100%; border:none; background:transparent; padding:6px 6px; font-size:13px; box-sizing:border-box; font-family:inherit; }
    table.mpo-box-table input:focus, table.mpo-box-table textarea:focus { outline:2px solid var(--accent); outline-offset:-2px; border-radius:3px; }
    table.mpo-box-table textarea { resize:vertical; min-height:100%; height:100%; }
    table.mpo-box-table td.field-cell.file-cell { padding:6px 9px; }
    table.mpo-box-table input[type=file] { padding:4px 0; }
  </style>

  <div class="mpo-page-head">
    <div>
      <h2><?= $isEditMode ? 'Edit Form Product Series' : 'Buat Form Product Series' ?></h2>
      <p>Diisi tim produksi (MJ).</p>
    </div>
    <a class="btn btn-sm btn-ghost" href="manufaktur-po.php<?= $isEditMode ? '?id=' . $editId : '' ?>">Batal</a>
  </div>

  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($isEditMode): ?>
      <input type="hidden" name="action" value="update_manufaktur_po_full">
      <input type="hidden" name="manufaktur_po_id" value="<?= $editHeader['id'] ?>">
      <input type="hidden" name="line_id" value="<?= $editLine['id'] ?? 0 ?>">
    <?php else: ?>
      <input type="hidden" name="action" value="save_manufaktur_po">
    <?php endif; ?>

    <div class="mpo-section">
      <div class="mpo-section-head"><h3>Informasi Umum</h3><p>Tanggal, vendor, project, dan nomor PO dari sisi vendor (kalau ada).</p></div>
      <table class="mpo-box-table">
        <tr>
          <td class="lbl">Tanggal</td><td class="field-cell"><input type="date" name="tanggal" value="<?= $isEditMode ? htmlspecialchars($editHeader['tanggal']) : date('Y-m-d') ?>" required></td>
          <td class="lbl">No. PO Vendor</td><td class="field-cell"><input type="text" name="po_number_vendor" value="<?= $isEditMode ? htmlspecialchars($editHeader['po_number_vendor'] ?? '') : '' ?>" placeholder="cth. PURCHASE ORDER NO.00001/VIII/MMT/2026"></td>
        </tr>
        <tr>
          <td class="lbl">Vendor</td><td class="field-cell"><input type="text" name="vendor_name" id="mpo-vendor-input" value="<?= $isEditMode ? htmlspecialchars($editHeader['vendor_name']) : '' ?>" placeholder="Cari atau ketik nama vendor baru..." autocomplete="off" required></td>
          <td class="lbl">Pemesan</td><td class="field-cell"><input type="text" name="pemesan" value="<?= $isEditMode ? htmlspecialchars($editHeader['pemesan'] ?? '') : '' ?>"></td>
        </tr>
        <tr>
          <td class="lbl">Project</td><td class="field-cell"><input type="text" name="project_name" id="mpo-project-input" value="<?= $isEditMode ? htmlspecialchars($editHeader['project_name']) : '' ?>" placeholder="Cari atau ketik nama project baru..." autocomplete="off"></td>
          <td class="lbl">Waktu Produksi</td><td class="field-cell"><input type="text" name="waktu_produksi" value="<?= $isEditMode ? htmlspecialchars($editHeader['waktu_produksi'] ?? '') : '' ?>" placeholder="cth. 3 Bulan"></td>
        </tr>
        <tr>
          <td class="lbl">Keterangan</td><td class="field-cell" colspan="3"><input type="text" name="keterangan" value="<?= $isEditMode ? htmlspecialchars($editHeader['keterangan'] ?? '') : '' ?>"></td>
        </tr>
      </table>
    </div>

    <div class="mpo-section">
      <div class="mpo-section-head"><h3>Detail Barang</h3></div>
      <table class="mpo-box-table">
        <tr>
          <td class="lbl">Kode Barang / Produk</td><td class="field-cell"><input type="text" class="mpo-combo-product" name="product_name" value="<?= $isEditMode ? htmlspecialchars($editLine['product_name_snapshot'] ?? '') : '' ?>" placeholder="Cari atau ketik barang baru..." autocomplete="off"></td>
          <td class="lbl">Item Code</td><td class="field-cell"><input type="text" name="item_code" value="<?= $isEditMode ? htmlspecialchars($editLine['item_code'] ?? '') : '' ?>"></td>
        </tr>
        <tr>
          <td class="lbl">Series</td><td class="field-cell"><input type="text" name="series" value="<?= $isEditMode ? htmlspecialchars($editLine['series'] ?? '') : '' ?>" placeholder="cth. VILLA 3 BR BALI"></td>
          <td class="lbl">Jumlah (Qty)</td><td class="field-cell"><input type="number" name="qty" value="<?= $isEditMode ? htmlspecialchars($editLine['qty'] ?? '1') : '1' ?>" min="0.01" step="0.01"></td>
        </tr>
        <tr>
          <td class="lbl">Size (mm)</td><td class="field-cell"><input type="text" name="size_mm" value="<?= $isEditMode ? htmlspecialchars($editLine['size_mm'] ?? '') : '' ?>" placeholder="cth. 640x600x670"></td>
          <td class="lbl">Remarks</td><td class="field-cell"><input type="text" name="remarks" value="<?= $isEditMode ? htmlspecialchars($editLine['remarks'] ?? '') : '' ?>"></td>
        </tr>
        <?php if ($isEditMode && !empty($editLine['attachments'])): ?>
        <tr>
          <td class="lbl">Gambar Kerja Saat Ini</td>
          <td class="field-cell file-cell" colspan="3">
            <div class="mp-thumb-row">
              <?php foreach ($editLine['attachments'] as $a):
                $ext = strtolower(pathinfo($a['file_path'], PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
              ?>
                <?php if ($isImage): ?>
                  <img src="<?= htmlspecialchars($a['file_path']) ?>" alt="" class="mp-thumb" style="width:56px; height:56px;">
                <?php else: ?>
                  <span class="mp-pdf-chip"><span class="mp-pdf-badge">PDF</span><span class="mp-pdf-fname"><?= htmlspecialchars($a['original_name']) ?></span></span>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </td>
        </tr>
        <?php endif; ?>
        <tr>
          <td class="lbl"><?= $isEditMode ? 'Tambah Gambar Kerja' : 'Gambar Kerja / Attachment' ?></td>
          <td class="field-cell file-cell" colspan="3"><input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf"></td>
        </tr>
      </table>
    </div>

    <div class="mpo-section">
      <div class="mpo-section-head" style="display:flex; justify-content:space-between; align-items:center;">
        <div><h3>Detail Komponen</h3><p>Frame, Fabric, Cushion, Foam, Connector, Kaki, dll — tambah sesuai kebutuhan produk.</p></div>
        <button type="button" class="btn btn-sm" id="mpo-add-comp-btn">+ Tambah Komponen</button>
      </div>
      <div id="mpo-comp-list" class="mpo-comp-list">
        <?php if ($isEditMode): foreach (($editLine['components'] ?? []) as $ci => $c): ?>
          <div class="mpo-comp-row">
            <div class="mpo-comp-row-head"><span>Komponen #<?= $ci + 1 ?></span><button type="button" class="btn btn-sm btn-ghost" style="padding:2px 8px;" onclick="this.closest('.mpo-comp-row').remove()">✕</button></div>
            <label>Nama Komponen</label><input type="text" name="comp_name[<?= $ci ?>]" value="<?= htmlspecialchars($c['component_name']) ?>">
            <div class="field-row-2">
              <div><label>Pembuat</label><input type="text" name="comp_pembuat[<?= $ci ?>]" value="<?= htmlspecialchars($c['pembuat'] ?? '') ?>"></div>
              <div><label>Code</label><input type="text" name="comp_code[<?= $ci ?>]" value="<?= htmlspecialchars($c['code'] ?? '') ?>"></div>
            </div>
            <label>Material</label><input type="text" name="comp_material[<?= $ci ?>]" value="<?= htmlspecialchars($c['material'] ?? '') ?>">
            <?php if ($c['photo_path']): ?><img src="<?= htmlspecialchars($c['photo_path']) ?>" alt="" style="width:44px; height:44px; object-fit:cover; border-radius:6px; margin-bottom:6px; display:block;"><?php endif; ?>
            <input type="hidden" name="comp_existing_photo[<?= $ci ?>]" value="<?= htmlspecialchars($c['photo_path'] ?? '') ?>">
            <label>Ganti Foto/Swatch (opsional)</label><input type="file" name="comp_photo[<?= $ci ?>]" accept=".jpg,.jpeg,.png,.webp" style="margin-bottom:0;">
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="mpo-submit-row">
      <a class="btn btn-ghost" href="manufaktur-po.php<?= $isEditMode ? '?id=' . $editId : '' ?>">Batal</a>
      <button type="submit" class="btn"><?= $isEditMode ? 'Simpan Perubahan' : 'Simpan Form Product Series' ?></button>
    </div>
  </form>

  <script>
  var MPO_VENDOR_NAMES = <?= json_encode(array_column($vendors, 'name')) ?>;
  var MPO_PROJECT_NAMES = <?= json_encode(array_column($projectsList, 'name')) ?>;
  var MPO_PRODUCT_NAMES = <?= json_encode(array_column($productsList, 'name')) ?>;

  document.addEventListener('DOMContentLoaded', function () {
    initCombobox(document.getElementById('mpo-vendor-input'), MPO_VENDOR_NAMES);
    initCombobox(document.getElementById('mpo-project-input'), MPO_PROJECT_NAMES);
    initCombobox(document.querySelector('.mpo-combo-product'), MPO_PRODUCT_NAMES);

    var compList = document.getElementById('mpo-comp-list');
    var compIndex = compList.querySelectorAll('.mpo-comp-row').length;
    function addCompRow() {
      var i = compIndex++;
      var row = document.createElement('div');
      row.className = 'mpo-comp-row';
      row.innerHTML =
        '<div class="mpo-comp-row-head"><span>Komponen #' + (i + 1) + '</span><button type="button" class="btn btn-sm btn-ghost" style="padding:2px 8px;" onclick="this.closest(\'.mpo-comp-row\').remove()">✕</button></div>' +
        '<label>Nama Komponen</label><input type="text" name="comp_name[' + i + ']" placeholder="cth. Frame, Fabric Body, Kaki">' +
        '<div class="field-row-2"><div><label>Pembuat</label><input type="text" name="comp_pembuat[' + i + ']"></div><div><label>Code</label><input type="text" name="comp_code[' + i + ']"></div></div>' +
        '<label>Material</label><input type="text" name="comp_material[' + i + ']">' +
        '<label>Foto/Swatch</label><input type="file" name="comp_photo[' + i + ']" accept=".jpg,.jpeg,.png,.webp" style="margin-bottom:0;">';
      compList.appendChild(row);
    }
    document.getElementById('mpo-add-comp-btn').addEventListener('click', addCompRow);
    <?php if (!$isEditMode): ?>
    addCompRow();
    addCompRow();
    <?php endif; ?>
  });
  </script>

<?php else: ?>
  <style>
    .txn-rail-item.mpo-rail-void { background:var(--danger-bg, #fde2e2) !important; }
    .txn-rail-item.mpo-rail-void .doc { color:var(--danger, #b91c1c) !important; }
    #mpo-rail-list .txn-rail-item { padding:12px; }
    #mpo-rail-list .txn-rail-item .sub { margin-top:4px; }
    .txn-rail .txn-rail-month .today-btn { margin:0; }
    #mpo-rail-search-wrap { padding:0 0 10px; }
    .mpo-info-table { margin-top:16px; display:grid; grid-template-columns: repeat(4, 1fr); border:1px solid var(--border); border-radius:10px; overflow:hidden; }
    @media (max-width: 780px) { .mpo-info-table { grid-template-columns: repeat(2, 1fr); } }
    .mpo-info-table .cell { padding:12px 16px; border-right:1px solid var(--border); border-top:1px solid var(--border); background:oklch(0.98 0.003 90); }
    .mpo-info-table .cell:nth-child(-n+4) { border-top:none; }
    .mpo-info-table .cell:nth-child(4n) { border-right:none; }
    .mpo-info-table .k { display:block; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:4px; }
    .mpo-void-banner { margin-top:12px; background:var(--danger-bg, #fde2e2); color:var(--danger, #b91c1c); border:1px solid var(--danger, #b91c1c); border-radius:8px; padding:10px 14px; font-size:13px; }
    .mpo-audit-log { margin-top:12px; border:1px solid var(--border); border-radius:8px; overflow:hidden; font-size:11.5px; }
    .mpo-audit-log .row { display:flex; padding:7px 12px; border-top:1px solid var(--border); background:oklch(0.98 0.003 90); }
    .mpo-audit-log .row:first-child { border-top:none; }
    .mpo-audit-log .k { width:60px; flex-shrink:0; font-weight:600; text-transform:uppercase; font-size:10px; letter-spacing:.02em; color:var(--ink-muted); align-self:center; }
    .mpo-detail-line { border:1px solid var(--border); border-radius:10px; margin-top:16px; overflow:hidden; }
    .mpo-detail-line-body { display:block; }
    .mpo-detail-line-info { padding:18px; }
    .mpo-detail-line-info h4 { margin:0 0 12px; font-size:14px; }
    .mpo-kv-grid { display:grid; grid-template-columns: repeat(2, 1fr); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
    .mpo-kv-grid .cell { padding:10px 14px; border-right:1px solid var(--border); border-top:1px solid var(--border); background:oklch(0.98 0.003 90); font-size:12px; }
    .mpo-kv-grid .cell:nth-child(-n+2) { border-top:none; }
    .mpo-kv-grid .cell:nth-child(2n) { border-right:none; }
    .mpo-kv-grid .lbl { display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:2px; }
    .mpo-comp-table { width:100%; border-collapse:collapse; margin-top:14px; font-size:12px; }
    .mpo-comp-table th, .mpo-comp-table td { border:1px solid var(--border); padding:6px 8px; text-align:left; }
    .mpo-comp-table th { background:oklch(0.97 0.003 90); font-size:10px; text-transform:uppercase; }
  </style>

  <div class="txn-shell">
    <div class="txn-rail">
      <div class="txn-rail-month">
        <a href="manufaktur-po.php?month=<?= $prevMonth ?>">‹</a>
        <span><?= htmlspecialchars(date('F Y', strtotime($month . '-01'))) ?></span>
        <a href="manufaktur-po.php?month=<?= $nextMonth ?>">›</a>
        <a class="today-btn" href="manufaktur-po.php">Bulan Ini</a>
      </div>
      <div id="mpo-rail-search-wrap">
        <input type="text" id="mpo-rail-search" placeholder="Cari nomor dokumen / vendor / project..." style="width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:12.5px; box-sizing:border-box;">
      </div>
      <div class="txn-rail-list" id="mpo-rail-list">
        <?php foreach ($railItems as $r): ?>
          <?php
          $isVoided = $r['deleted_at'] || $r['status'] === 'void';
          $searchBlob = mb_strtolower($r['doc_number'] . ' ' . $r['vendor_name'] . ' ' . ($r['project_name'] ?? ''));
          ?>
          <a class="txn-rail-item <?= (int) $r['id'] === $selectedId ? 'active' : '' ?> <?= $isVoided ? 'mpo-rail-void' : '' ?>" data-search="<?= htmlspecialchars($searchBlob) ?>" href="manufaktur-po.php?month=<?= $month ?>&id=<?= $r['id'] ?>">
            <div class="doc" style="font-weight:700;"><?= htmlspecialchars($r['doc_number']) ?><?= $isVoided ? ' 🚫' : '' ?></div>
            <div class="sub"><?= htmlspecialchars(date('d M Y', strtotime($r['tanggal']))) ?> · <?= htmlspecialchars($r['vendor_name']) ?></div>
            <?php if ($r['project_name']): ?><div class="sub" style="margin-top:2px;"><?= htmlspecialchars($r['project_name']) ?></div><?php endif; ?>
            <div class="sub" style="margin-top:2px;"><span class="pill pill-<?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$railItems): ?><div style="padding:14px; font-size:12px; color:var(--ink-muted);">Gak ada Form Product Series bulan ini.</div><?php endif; ?>
      </div>
      <?php if (has_access('manufaktur_po', 'can_create')): ?>
        <div style="padding:10px;"><a class="btn btn-sm" style="width:100%;" href="manufaktur-po.php?new=1">+ Buat Form Product Series</a></div>
      <?php endif; ?>
    </div>

    <div class="txn-detail">
      <?php if (!$selected): ?>
        <div class="card txn-empty">Pilih dokumen di kiri, atau buat yang baru.</div>
      <?php else: ?>
        <div class="card">
          <div class="mp-detail-header-card" style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <h2 style="margin:0; font-size:20px;"><?= htmlspecialchars($selected['doc_number']) ?> <span class="pill pill-<?= $selected['status'] ?>"><?= strtoupper($selected['status']) ?></span></h2>
            <div class="txn-detail-actions">
              <a class="btn btn-sm btn-ghost" href="manufaktur-po-print.php?id=<?= $selected['id'] ?>" target="_blank">Print</a>
              <a class="btn btn-sm btn-ghost" href="manufaktur-po-pdf.php?id=<?= $selected['id'] ?>" target="_blank">📎 PDF Gabungan (+lampiran)</a>
              <?php if (has_access('manufaktur_po', 'can_edit')): ?>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_status_po">
                  <input type="hidden" name="manufaktur_po_id" value="<?= $selected['id'] ?>">
                  <select name="status" onchange="this.form.submit();" style="padding:6px 10px; border:1px solid var(--border); border-radius:4px; font-size:12px;">
                    <?php foreach (['draft', 'diajukan', 'diproses', 'selesai', 'void'] as $s): ?>
                      <option value="<?= $s ?>" <?= $selected['status'] === $s ? 'selected' : '' ?>><?= strtoupper($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              <?php endif; ?>
              <?php if (has_access('manufaktur_po', 'can_delete')): ?>
                <button class="btn btn-sm btn-ghost" type="button" onclick="if(confirm('Hapus dokumen ini?')) __submitDeleteForm('delete_manufaktur_po', {manufaktur_po_id: <?= $selected['id'] ?>})">Hapus</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="mpo-info-table">
            <div class="cell"><span class="k">Vendor</span><?= htmlspecialchars($selected['vendor_name']) ?></div>
            <div class="cell"><span class="k">Project</span><?= $selected['project_name'] ? htmlspecialchars($selected['project_name']) : '—' ?></div>
            <div class="cell"><span class="k">Tanggal</span><?= htmlspecialchars(date('d M Y', strtotime($selected['tanggal']))) ?></div>
            <div class="cell"><span class="k">No. PO Vendor</span><?= $selected['po_number_vendor'] ? htmlspecialchars($selected['po_number_vendor']) : '—' ?></div>
            <div class="cell"><span class="k">Pemesan</span><?= $selected['pemesan'] ? htmlspecialchars($selected['pemesan']) : '—' ?></div>
            <div class="cell"><span class="k">Waktu Produksi</span><?= $selected['waktu_produksi'] ? htmlspecialchars($selected['waktu_produksi']) : '—' ?></div>
            <div class="cell"><span class="k">Keterangan</span><?= $selected['keterangan'] ? htmlspecialchars($selected['keterangan']) : '—' ?></div>
          </div>

          <?php if ($selected['deleted_at']): ?>
            <div class="mpo-void-banner">🚫 Dokumen ini sudah <strong>dihapus (void)</strong> oleh <strong><?= htmlspecialchars($headerDeletedByName ?? '—') ?></strong> pada <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['deleted_at']))) ?></div>
          <?php endif; ?>

          <div class="mpo-audit-log">
            <div class="row"><span class="k">Dibuat</span><span><strong><?= htmlspecialchars($headerCreatedByName ?? '—') ?></strong> · <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['created_at']))) ?></span></div>
            <?php if ($selected['updated_at']): ?>
              <div class="row"><span class="k">Diedit</span><span><strong><?= htmlspecialchars($headerUpdatedByName ?? '—') ?></strong> · <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['updated_at']))) ?></span></div>
            <?php endif; ?>
          </div>
        </div>

        <?php foreach ($selectedLines as $l): ?>
          <div class="card mpo-detail-line">
            <div class="mpo-detail-line-body">
              <div class="mpo-detail-line-info">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                  <h4><?= htmlspecialchars($l['product_name_snapshot']) ?></h4>
                  <?php if (has_access('manufaktur_po', 'can_edit')): ?>
                    <a class="btn btn-sm btn-ghost" href="manufaktur-po.php?edit=<?= $selected['id'] ?>">✎ Edit</a>
                  <?php endif; ?>
                </div>
                <div class="mpo-kv-grid">
                  <div class="cell"><span class="lbl">Series</span><?= $l['series'] ? htmlspecialchars($l['series']) : '—' ?></div>
                  <div class="cell"><span class="lbl">Size (mm)</span><?= $l['size_mm'] ? htmlspecialchars($l['size_mm']) : '—' ?></div>
                  <div class="cell"><span class="lbl">Qty</span><?= rtrim(rtrim(number_format((float) $l['qty'], 2, ',', '.'), '0'), ',') ?></div>
                  <div class="cell"><span class="lbl">Item Code</span><?= $l['item_code'] ? htmlspecialchars($l['item_code']) : '—' ?></div>
                </div>
                <?php if ($l['remarks']): ?><p style="font-size:12px; margin:12px 0 0;"><strong style="font-size:10px; text-transform:uppercase; color:var(--ink-muted);">Remarks:</strong> <?= htmlspecialchars($l['remarks']) ?></p><?php endif; ?>

                <?php if ($l['components']): ?>
                  <table class="mpo-comp-table">
                    <thead><tr><th style="width:56px;">Foto</th><th>Komponen</th><th>Pembuat</th><th>Code</th><th>Material</th></tr></thead>
                    <tbody>
                      <?php foreach ($l['components'] as $c): ?>
                        <tr>
                          <td>
                            <?php if ($c['photo_path']): ?>
                              <img src="<?= htmlspecialchars($c['photo_path']) ?>" alt="" class="mp-thumb" style="width:40px; height:40px;" onclick="mpoPreviewImage('<?= htmlspecialchars($c['photo_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($c['component_name'], ENT_QUOTES) ?>')">
                            <?php else: ?>—<?php endif; ?>
                          </td>
                          <td><?= htmlspecialchars($c['component_name']) ?></td>
                          <td><?= $c['pembuat'] ? htmlspecialchars($c['pembuat']) : '—' ?></td>
                          <td><?= $c['code'] ? htmlspecialchars($c['code']) : '—' ?></td>
                          <td><?= $c['material'] ? htmlspecialchars($c['material']) : '—' ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>

                <?php if ($l['attachments']): ?>
                  <div class="mp-attachments" style="margin-top:14px;">
                    <span class="lbl" style="display:block; font-size:10px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:6px;">Gambar Kerja</span>
                    <div class="mp-thumb-row">
                      <?php foreach ($l['attachments'] as $a):
                        $ext = strtolower(pathinfo($a['file_path'], PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
                      ?>
                        <?php if ($isImage): ?>
                          <img src="<?= htmlspecialchars($a['file_path']) ?>" alt="<?= htmlspecialchars($a['original_name']) ?>" class="mp-thumb" onclick="mpoPreviewImage('<?= htmlspecialchars($a['file_path'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['original_name'], ENT_QUOTES) ?>')">
                        <?php else: ?>
                          <a href="<?= htmlspecialchars($a['file_path']) ?>" target="_blank" class="mp-pdf-chip"><span class="mp-pdf-badge">PDF</span><span class="mp-pdf-fname"><?= htmlspecialchars($a['original_name']) ?></span></a>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="modal-scrim" id="mpo-image-preview-modal">
    <div class="modal-card" style="max-width:min(720px, 92vw);">
      <div class="modal-head"><h3 id="mpo-image-preview-title">Preview</h3><button class="modal-close" data-close-modal="mpo-image-preview-modal">&times;</button></div>
      <div class="modal-body" style="text-align:center;">
        <img id="mpo-image-preview-img" src="" alt="" style="max-width:100%; max-height:70vh; border-radius:8px;">
      </div>
    </div>
  </div>

  <script>
  function mpoPreviewImage(src, name) {
    document.getElementById('mpo-image-preview-img').src = src;
    document.getElementById('mpo-image-preview-title').textContent = name;
    document.getElementById('mpo-image-preview-modal').classList.add('open');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var mpoRailSearch = document.getElementById('mpo-rail-search');
    if (mpoRailSearch) {
      mpoRailSearch.addEventListener('input', function () {
        var q = mpoRailSearch.value.trim().toLowerCase();
        document.querySelectorAll('#mpo-rail-list .txn-rail-item').forEach(function (item) {
          var hay = item.getAttribute('data-search') || '';
          item.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
        });
      });
    }

  });
  </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
