<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$pageTitle = 'Product Info';
$activeMenu = 'inventory_report';
$embedMode = isset($_GET['embed']) && $_GET['embed'] === '1';
require __DIR__ . '/includes/header.php';
require_module_access('manufaktur_surat_jalan');
require_once __DIR__ . '/../backoffice-shared/image_upload.php';
require_once __DIR__ . '/../backoffice-shared/stock.php';

$pdo = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_product_info') {
            $id = (int) ($_POST['product_id'] ?? 0);
            $check = $pdo->prepare('SELECT * FROM products WHERE id=? AND organization_id=?');
            $check->execute([$id, $org['organization_id']]);
            $before = $check->fetch();
            if (!$before) throw new RuntimeException('Produk tidak ditemukan.');

            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException('Nama produk wajib diisi.');
            $category = trim($_POST['category'] ?? '') ?: null;
            $barcode = trim($_POST['barcode'] ?? '') ?: null;
            $material = trim($_POST['material'] ?? '') ?: null;
            $itemType = trim($_POST['item_type'] ?? '') ?: null;
            $size = trim($_POST['size'] ?? '') ?: null;
            $finishing = trim($_POST['finishing'] ?? '') ?: null;
            $description = trim($_POST['description'] ?? '') ?: null;
            $unit = trim($_POST['unit'] ?? '') ?: 'pcs';
            $purchasePrice = trim($_POST['purchase_price'] ?? '') !== '' ? (float) $_POST['purchase_price'] : null;
            $salePrice = trim($_POST['sale_price'] ?? '') !== '' ? (float) $_POST['sale_price'] : null;

            $after = [
                'name' => $name, 'category' => $category, 'barcode' => $barcode, 'material' => $material,
                'item_type' => $itemType, 'size' => $size, 'finishing' => $finishing, 'description' => $description,
                'unit' => $unit, 'purchase_price' => $purchasePrice, 'sale_price' => $salePrice,
            ];
            $fieldLabels = [
                'name' => 'Nama Produk', 'category' => 'Kategori', 'barcode' => 'Barcode', 'material' => 'Material',
                'item_type' => 'Item Type', 'size' => 'Size', 'finishing' => 'Finishing', 'description' => 'Deskripsi',
                'unit' => 'Unit', 'purchase_price' => 'Harga Beli', 'sale_price' => 'Harga Jual',
            ];
            $diffLines = [];
            foreach ($after as $field => $newVal) {
                $oldVal = $before[$field];
                $oldCmp = $oldVal === null ? '' : (string) $oldVal;
                $newCmp = $newVal === null ? '' : (string) $newVal;
                if ($oldCmp !== $newCmp) {
                    $diffLines[] = $fieldLabels[$field] . ': "' . ($oldVal ?? '—') . '" -> "' . ($newVal ?? '—') . '"';
                }
            }
            if ($diffLines) {
                $pdo->prepare('INSERT INTO product_audit_log (product_id, changed_by, summary) VALUES (?,?,?)')
                    ->execute([$id, $user['id'], implode("\n", $diffLines)]);
            }

            $pdo->prepare('UPDATE products SET name=?, category=?, barcode=?, material=?, item_type=?, size=?, finishing=?, description=?, unit=?, purchase_price=?, sale_price=? WHERE id=? AND organization_id=?')
                ->execute([$name, $category, $barcode, $material, $itemType, $size, $finishing, $description, $unit, $purchasePrice, $salePrice, $id, $org['organization_id']]);

            if (!empty($_FILES['new_photos']['name'][0])) {
                $maxOrder = $pdo->prepare('SELECT COALESCE(MAX(sort_order),-1) m FROM product_photos WHERE product_id=?');
                $maxOrder->execute([$id]);
                $sort = (int) $maxOrder->fetch()['m'] + 1;
                $photoStmt = $pdo->prepare('INSERT INTO product_photos (product_id, file_path, sort_order) VALUES (?,?,?)');
                foreach ($_FILES['new_photos']['name'] as $j => $name0) {
                    if (($_FILES['new_photos']['error'][$j] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                    $file = ['name' => $name0, 'type' => $_FILES['new_photos']['type'][$j], 'tmp_name' => $_FILES['new_photos']['tmp_name'][$j], 'error' => $_FILES['new_photos']['error'][$j], 'size' => $_FILES['new_photos']['size'][$j]];
                    $path = save_resized_product_photo($file);
                    $photoStmt->execute([$id, $path, $sort]);
                    $sort++;
                }
            }

            header('Location: manufaktur-product-info.php?id=' . $id . ($embedMode ? '&embed=1' : ''));
            exit;
        } elseif ($action === 'delete_photo') {
            $photoId = (int) ($_POST['photo_id'] ?? 0);
            $id = (int) ($_POST['product_id'] ?? 0);
            $pStmt = $pdo->prepare('SELECT pp.file_path FROM product_photos pp JOIN products p ON p.id=pp.product_id WHERE pp.id=? AND p.organization_id=?');
            $pStmt->execute([$photoId, $org['organization_id']]);
            $row = $pStmt->fetch();
            if ($row) {
                $pdo->prepare('DELETE FROM product_photos WHERE id=?')->execute([$photoId]);
                delete_product_photo($row['file_path']);
            }
            header('Location: manufaktur-product-info.php?id=' . $id . '&edit=1' . ($embedMode ? '&embed=1' : ''));
            exit;
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM products WHERE id=? AND organization_id=?');
$stmt->execute([$id, $org['organization_id']]);
$p = $stmt->fetch();
if (!$p) { http_response_code(404); exit('Produk tidak ditemukan.'); }

$photos = [];
if ($p['photo_path']) $photos[] = ['id' => 0, 'file_path' => $p['photo_path']];
$photoStmt = $pdo->prepare('SELECT id, file_path FROM product_photos WHERE product_id=? ORDER BY sort_order, id');
$photoStmt->execute([$id]);
foreach ($photoStmt->fetchAll() as $ph) $photos[] = $ph;

$whStmt = $pdo->prepare(
    "SELECT w.id, w.name, w.vendor_id, c.name AS vendor_name, SUM(sl.qty_remaining) AS qty
     FROM stock_ledger sl JOIN warehouses w ON w.id=sl.warehouse_id LEFT JOIN contacts c ON c.id=w.vendor_id
     WHERE sl.organization_id=? AND sl.direction='in' AND sl.product_id=?
     GROUP BY w.id HAVING qty > 0 ORDER BY w.vendor_id IS NULL DESC, w.name"
);
$whStmt->execute([$org['organization_id'], $id]);
$whStock = $whStmt->fetchAll();
$totalQty = array_sum(array_column($whStock, 'qty'));

// ===== Kartu Stok — tiap baris stock_ledger buat produk ini, ditelusur balik ke dokumen asalnya =====
const SL_REF_TYPE_LABELS = [
    'opening_balance' => ['label' => 'Saldo Awal', 'href' => 'manufaktur-saldo-awal.php?id=%d'],
    'manufaktur_surat_jalan' => ['label' => 'Surat Jalan', 'href' => 'manufaktur-surat-jalan.php?id=%d'],
    'goods_receipt' => ['label' => 'Penerimaan Barang', 'href' => 'goods-receipts.php?id=%d'],
    'delivery_order' => ['label' => 'Delivery Order', 'href' => 'delivery-orders.php?id=%d'],
    'spk_material' => ['label' => 'SPK (Material)', 'href' => 'spk.php?id=%d'],
];
function sl_ref_source(string $refType, int $refId): array
{
    $meta = SL_REF_TYPE_LABELS[$refType] ?? ['label' => $refType, 'href' => null];
    return ['label' => $meta['label'], 'href' => $meta['href'] ? sprintf($meta['href'], $refId) : null];
}

// Diambil KRONOLOGIS (lama->baru) dulu buat ngitung saldo berjalan per lokasi,
// baru dikelompokkan per gudang dan dibalik urutannya (terbaru di atas) buat ditampilin.
$ledgerStmt = $pdo->prepare(
    "SELECT sl.*, w.name AS warehouse_name, w.vendor_id, c.name AS vendor_name
     FROM stock_ledger sl JOIN warehouses w ON w.id=sl.warehouse_id LEFT JOIN contacts c ON c.id=w.vendor_id
     WHERE sl.organization_id=? AND sl.product_id=?
     ORDER BY sl.warehouse_id, sl.created_at ASC, sl.id ASC"
);
$ledgerStmt->execute([$org['organization_id'], $id]);
$stockLedgerFlat = $ledgerStmt->fetchAll();

$stockLedgerByWarehouse = [];
$runningByWarehouse = [];
foreach ($stockLedgerFlat as $sl) {
    $whId = (int) $sl['warehouse_id'];
    if (!isset($stockLedgerByWarehouse[$whId])) {
        $stockLedgerByWarehouse[$whId] = [
            'label' => $sl['vendor_id'] ? 'VENDOR: ' . ($sl['vendor_name'] ?? '?') : $sl['warehouse_name'],
            'rows' => [],
        ];
        $runningByWarehouse[$whId] = 0.0;
    }
    $runningByWarehouse[$whId] += $sl['direction'] === 'in' ? (float) $sl['qty'] : -(float) $sl['qty'];
    $sl['running'] = $runningByWarehouse[$whId];
    $stockLedgerByWarehouse[$whId]['rows'][] = $sl;
}
// Terbaru di atas dalam tiap grup lokasi.
foreach ($stockLedgerByWarehouse as &$grp) { $grp['rows'] = array_reverse($grp['rows']); }
unset($grp);

$auditStmt = $pdo->prepare(
    "SELECT pal.*, u.name AS user_name FROM product_audit_log pal LEFT JOIN users u ON u.id = pal.changed_by
     WHERE pal.product_id=? ORDER BY pal.created_at DESC"
);
$auditStmt->execute([$id]);
$auditLog = $auditStmt->fetchAll();

$isEdit = isset($_GET['edit']);
?>
<style>
  .pinfo-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; gap:12px; flex-wrap:wrap; }
  .pinfo-head h2 { margin:0 0 4px; font-size:20px; }
  .pinfo-head p { margin:0; font-size:13px; color:var(--ink-muted); }
  .pinfo-layout { display:grid; grid-template-columns: 340px 1fr; gap:20px; align-items:start; }
  @media (max-width: 820px) { .pinfo-layout { grid-template-columns: 1fr; } }
  .pinfo-left-col { display:flex; flex-direction:column; }

  .pinfo-audit-list { display:flex; flex-direction:column; gap:12px; }
  .pinfo-kartustok-loc { margin-bottom:16px; }
  .pinfo-kartustok-loc:last-child { margin-bottom:0; }
  .pinfo-kartustok-loc-head { font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:6px; padding-bottom:4px; border-bottom:1px solid var(--border); }
  .pinfo-audit-entry { border:1px solid var(--border); border-radius:8px; padding:10px 12px; background:oklch(0.98 0.003 90); }
  .pinfo-audit-meta { font-size:11.5px; color:var(--ink-muted); margin-bottom:6px; }
  .pinfo-audit-box { width:100%; border:1px solid var(--border); border-radius:6px; padding:8px 10px; font-size:12px; font-family:ui-monospace, Consolas, monospace; background:#fff; resize:vertical; box-sizing:border-box; color:var(--ink); }

  .pinfo-gallery { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:16px; box-shadow:var(--shadow-card); margin-bottom:20px; }
  .pinfo-main-photo { width:100%; aspect-ratio:1/1; border-radius:10px; border:1px solid var(--border); background:oklch(0.97 0.003 90) center/cover no-repeat; display:flex; align-items:center; justify-content:center; color:var(--ink-muted); font-size:12px; cursor:pointer; overflow:hidden; }
  .pinfo-main-photo img { width:100%; height:100%; object-fit:cover; }
  .pinfo-thumbs { display:flex; gap:8px; margin-top:10px; flex-wrap:wrap; }
  .pinfo-thumb { width:52px; height:52px; border-radius:8px; border:2px solid transparent; object-fit:cover; cursor:pointer; background:oklch(0.97 0.003 90); }
  .pinfo-thumb.active { border-color:var(--accent); }
  .pinfo-thumb-del { position:relative; }
  .pinfo-thumb-del button { position:absolute; top:-6px; right:-6px; width:18px; height:18px; border-radius:50%; background:var(--danger,#b91c1c); color:#fff; border:none; font-size:11px; line-height:1; cursor:pointer; }

  .pinfo-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:18px; box-shadow:var(--shadow-card); }
  .pinfo-section h3 { margin:0 0 14px; font-size:14px; }

  table.pinfo-box-table { width:100%; border-collapse:collapse; table-layout:fixed; }
  table.pinfo-box-table td { border:1px solid var(--border); padding:0; vertical-align:top; }
  table.pinfo-box-table td.lbl { width:26%; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); background:oklch(0.97 0.003 90); padding:9px 10px; }
  table.pinfo-box-table td.field-cell { padding:9px 10px; font-size:13px; }
  table.pinfo-box-table td.field-cell input, table.pinfo-box-table td.field-cell textarea { width:100%; border:none; background:transparent; padding:0; font-size:13px; box-sizing:border-box; font-family:inherit; }
  table.pinfo-box-table td.field-cell input:focus, table.pinfo-box-table td.field-cell textarea:focus { outline:2px solid var(--accent); outline-offset:1px; }

  table.pinfo-wh-table { width:100%; border-collapse:collapse; font-size:12.5px; }
  table.pinfo-wh-table th, table.pinfo-wh-table td { border:1px solid var(--border); padding:8px 10px; text-align:left; }
  table.pinfo-wh-table th { background:oklch(0.97 0.003 90); font-size:10px; text-transform:uppercase; }
  table.pinfo-wh-table td.num { text-align:right; font-weight:600; }
  table.pinfo-wh-table tr.total-row td { font-weight:700; border-top:2px solid var(--border); }
</style>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="pinfo-head">
  <div>
    <h2><?= htmlspecialchars($p['name']) ?></h2>
    <p><?= $p['category'] ? htmlspecialchars($p['category']) : 'Tanpa kategori' ?> · Total stok: <strong><?= rtrim(rtrim(number_format($totalQty, 2, ',', '.'), '0'), ',') ?> <?= htmlspecialchars($p['unit']) ?></strong></p>
  </div>
  <div style="display:flex; gap:8px;">
    <?php if (!$embedMode): ?><a class="btn btn-sm btn-ghost" href="inventory-report.php">← Kembali</a><?php endif; ?>
    <?php $embedQs = $embedMode ? '&embed=1' : ''; ?>
    <?php if ($isEdit): ?>
      <a class="btn btn-sm btn-ghost" href="manufaktur-product-info.php?id=<?= $id . $embedQs ?>">Batal Edit</a>
    <?php else: ?>
      <a class="btn btn-sm" href="manufaktur-product-info.php?id=<?= $id ?>&edit=1<?= $embedQs ?>">✎ Edit</a>
    <?php endif; ?>
  </div>
</div>

<form method="post" enctype="multipart/form-data" id="pinfo-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save_product_info">
  <input type="hidden" name="product_id" value="<?= $id ?>">

  <div class="pinfo-layout">
    <div class="pinfo-left-col">
    <div class="pinfo-gallery">
      <div class="pinfo-main-photo" id="pinfo-main-photo">
        <?php if ($photos): ?>
          <img id="pinfo-main-img" src="<?= htmlspecialchars($photos[0]['file_path']) ?>" alt="">
        <?php else: ?>
          <span id="pinfo-main-empty">Belum ada foto</span>
        <?php endif; ?>
      </div>
      <?php if (count($photos) > 1): ?>
        <div class="pinfo-thumbs">
          <?php foreach ($photos as $i => $ph): ?>
            <?php if ($isEdit && $ph['id']): ?>
              <span class="pinfo-thumb-del">
                <img class="pinfo-thumb <?= $i === 0 ? 'active' : '' ?>" src="<?= htmlspecialchars($ph['file_path']) ?>" data-src="<?= htmlspecialchars($ph['file_path']) ?>">
                <button type="button" onclick="if(confirm('Hapus foto ini?')) __submitDeleteForm('delete_photo', {photo_id: <?= $ph['id'] ?>, product_id: <?= $id ?>})">✕</button>
              </span>
            <?php else: ?>
              <img class="pinfo-thumb <?= $i === 0 ? 'active' : '' ?>" src="<?= htmlspecialchars($ph['file_path']) ?>" data-src="<?= htmlspecialchars($ph['file_path']) ?>">
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($isEdit): ?>
        <div style="margin-top:12px;">
          <label style="display:block; font-size:11px; font-weight:600; text-transform:uppercase; color:var(--ink-muted); margin-bottom:6px;">Tambah Foto (bisa lebih dari 1)</label>
          <input type="file" name="new_photos[]" multiple accept=".jpg,.jpeg,.png,.webp" style="width:100%;">
        </div>
      <?php endif; ?>
    </div>

      <div class="pinfo-section" style="margin-bottom:20px;">
        <h3>Stok per Lokasi</h3>
        <table class="pinfo-wh-table">
          <thead><tr><th>Lokasi</th><th class="num" style="width:120px;">Qty</th></tr></thead>
          <tbody>
            <?php foreach ($whStock as $w): ?>
              <tr>
                <td><?= $w['vendor_id'] ? 'VENDOR: ' . htmlspecialchars($w['vendor_name'] ?? '?') : htmlspecialchars($w['name']) ?></td>
                <td class="num"><?= rtrim(rtrim(number_format((float) $w['qty'], 2, ',', '.'), '0'), ',') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$whStock): ?><tr><td colspan="2" style="text-align:center; color:var(--ink-muted);">Belum ada stok.</td></tr><?php endif; ?>
            <tr class="total-row"><td>Total</td><td class="num"><?= rtrim(rtrim(number_format($totalQty, 2, ',', '.'), '0'), ',') ?></td></tr>
          </tbody>
        </table>
      </div>

      <div class="pinfo-section">
        <h3>Riwayat Perubahan</h3>
        <?php if (!$auditLog): ?>
          <div style="font-size:12.5px; color:var(--ink-muted);">Belum ada perubahan tercatat.</div>
        <?php else: ?>
          <div class="pinfo-audit-list">
            <?php foreach ($auditLog as $log): ?>
              <div class="pinfo-audit-entry">
                <div class="pinfo-audit-meta"><strong><?= htmlspecialchars($log['user_name'] ?? '—') ?></strong> · <?= htmlspecialchars(date('d M Y, H:i', strtotime($log['created_at']))) ?></div>
                <textarea class="pinfo-audit-box" readonly rows="<?= min(8, substr_count($log['summary'], "\n") + 1) ?>"><?= htmlspecialchars($log['summary']) ?></textarea>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="pinfo-section">
        <h3>Spesifikasi</h3>
        <table class="pinfo-box-table">
          <tr><td class="lbl">Nama Produk</td><td class="field-cell"><?php if ($isEdit): ?><input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required><?php else: ?><?= htmlspecialchars($p['name']) ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Kategori</td><td class="field-cell"><?php if ($isEdit): ?><input type="text" name="category" value="<?= htmlspecialchars($p['category'] ?? '') ?>" placeholder="cth. Furniture, Busa/Foam, Connector Sofa"><?php else: ?><?= $p['category'] ? htmlspecialchars($p['category']) : '—' ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Barcode</td><td class="field-cell"><?php if ($isEdit): ?><input type="text" name="barcode" value="<?= htmlspecialchars($p['barcode'] ?? '') ?>"><?php else: ?><?= $p['barcode'] ? htmlspecialchars($p['barcode']) : '—' ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Material</td><td class="field-cell"><?php if ($isEdit): ?><input type="text" name="material" value="<?= htmlspecialchars($p['material'] ?? '') ?>"><?php else: ?><?= $p['material'] ? htmlspecialchars($p['material']) : '—' ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Item Type</td><td class="field-cell"><?php if ($isEdit): ?><input type="text" name="item_type" value="<?= htmlspecialchars($p['item_type'] ?? '') ?>"><?php else: ?><?= $p['item_type'] ? htmlspecialchars($p['item_type']) : '—' ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Size</td><td class="field-cell"><?php if ($isEdit): ?><input type="text" name="size" value="<?= htmlspecialchars($p['size'] ?? '') ?>"><?php else: ?><?= $p['size'] ? htmlspecialchars($p['size']) : '—' ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Finishing</td><td class="field-cell"><?php if ($isEdit): ?><input type="text" name="finishing" value="<?= htmlspecialchars($p['finishing'] ?? '') ?>"><?php else: ?><?= $p['finishing'] ? htmlspecialchars($p['finishing']) : '—' ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Unit</td><td class="field-cell"><?php if ($isEdit): ?><input type="text" name="unit" value="<?= htmlspecialchars($p['unit']) ?>"><?php else: ?><?= htmlspecialchars($p['unit']) ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Deskripsi</td><td class="field-cell"><?php if ($isEdit): ?><textarea name="description" rows="3"><?= htmlspecialchars($p['description'] ?? '') ?></textarea><?php else: ?><?= $p['description'] ? nl2br(htmlspecialchars($p['description'])) : '—' ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Harga Beli</td><td class="field-cell"><?php if ($isEdit): ?><input type="number" step="0.01" name="purchase_price" value="<?= htmlspecialchars($p['purchase_price'] ?? '') ?>"><?php else: ?><?= $p['purchase_price'] !== null ? 'Rp ' . number_format((float) $p['purchase_price'], 0, ',', '.') : '—' ?><?php endif; ?></td></tr>
          <tr><td class="lbl">Harga Jual</td><td class="field-cell"><?php if ($isEdit): ?><input type="number" step="0.01" name="sale_price" value="<?= htmlspecialchars($p['sale_price'] ?? '') ?>"><?php else: ?><?= $p['sale_price'] !== null ? 'Rp ' . number_format((float) $p['sale_price'], 0, ',', '.') : '—' ?><?php endif; ?></td></tr>
        </table>
        <?php if ($isEdit): ?><button type="submit" class="btn btn-sm" style="margin-top:14px;">Simpan Perubahan</button><?php endif; ?>
      </div>

      <div class="pinfo-section">
        <h3>Kartu Stok</h3>
        <p style="font-size:11.5px; color:var(--ink-muted); margin:0 0 12px;">Dipisah per lokasi, tiap baris nunjukin saldo berjalan (qty saat itu) sekalian ditelusur balik ke dokumen asalnya.</p>
        <?php if (!$stockLedgerByWarehouse): ?>
          <div style="font-size:12px; color:var(--ink-muted);">Belum ada pergerakan stok.</div>
        <?php endif; ?>
        <?php foreach ($stockLedgerByWarehouse as $whId => $grp): ?>
          <div class="pinfo-kartustok-loc">
            <div class="pinfo-kartustok-loc-head"><?= htmlspecialchars($grp['label']) ?></div>
            <table class="pinfo-wh-table">
              <thead><tr><th>Tanggal</th><th>Arah</th><th class="num" style="width:60px;">Qty</th><th class="num" style="width:80px;">Saldo</th><th>Dari Dokumen</th></tr></thead>
              <tbody>
                <?php foreach ($grp['rows'] as $sl): $src = sl_ref_source($sl['ref_type'], (int) $sl['ref_id']); ?>
                  <tr>
                    <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($sl['created_at']))) ?></td>
                    <td><?= $sl['direction'] === 'in' ? '<span style="color:#1a7f37; font-weight:700;">MASUK</span>' : '<span style="color:#b91c1c; font-weight:700;">KELUAR</span>' ?></td>
                    <td class="num"><?= rtrim(rtrim(number_format((float) $sl['qty'], 2, ',', '.'), '0'), ',') ?></td>
                    <td class="num"><strong><?= rtrim(rtrim(number_format((float) $sl['running'], 2, ',', '.'), '0'), ',') ?></strong></td>
                    <td><?php if ($src['href']): ?><a href="<?= htmlspecialchars($src['href']) ?>"><?= htmlspecialchars($src['label']) ?></a><?php else: ?><?= htmlspecialchars($src['label']) ?><?php endif; ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.pinfo-thumb').forEach(function (thumb) {
    thumb.addEventListener('click', function () {
      document.getElementById('pinfo-main-img').src = thumb.getAttribute('data-src');
      document.querySelectorAll('.pinfo-thumb').forEach(function (t) { t.classList.remove('active'); });
      thumb.classList.add('active');
    });
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
