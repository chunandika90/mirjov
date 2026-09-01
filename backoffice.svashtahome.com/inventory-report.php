<?php
/**
 * Laporan sebaran stok per lokasi — gudang sendiri maupun gudang vendor
 * (virtual, dari fitur "kirim ke stock vendor" di PO). Read-only.
 */
$pageTitle = 'Laporan Inventory';
$activeMenu = 'inventory_report';
require __DIR__ . '/includes/header.php';
require_module_access('laporan');

$pdo = db();
$orgId = $org['organization_id'];

// ===== Stok Produk — folder per kategori (products.category), drill-down ke item =====
const INV_FOLDER_COLORS = ['#7c6ae8', '#5b8def', '#e8a13a', '#3ab77c', '#e85d75', '#2fb8c4', '#c47ae8', '#e8c93a'];
function inv_folder_color(string $name): string
{
    return INV_FOLDER_COLORS[crc32($name) % count(INV_FOLDER_COLORS)];
}

$activeCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
$activeWarehouse = isset($_GET['warehouse']) ? (int) $_GET['warehouse'] : 0;

// User non-Owner yang lokasinya dibatasin (lihat Master User) cuma boleh liat inventory
// lokasi dia sendiri — paksa filter-nya, gak peduli ?warehouse= di URL diapa-apain.
$myWarehouseId = user_location_restriction();
if ($myWarehouseId !== null) $activeWarehouse = $myWarehouseId;

// Lokasi (gudang sendiri + gudang vendor) yang ada stok produknya, buat filter chip di atas.
$locationStmt = $pdo->prepare(
    "SELECT w.id, w.name, w.vendor_id, v.name AS vendor_name, SUM(sl.qty_remaining) AS total_qty
     FROM stock_ledger sl JOIN warehouses w ON w.id = sl.warehouse_id LEFT JOIN contacts v ON v.id = w.vendor_id
     WHERE sl.organization_id=? AND sl.direction='in' AND sl.product_id IS NOT NULL
     GROUP BY w.id HAVING total_qty > 0
     ORDER BY w.vendor_id IS NULL DESC, w.name"
);
$locationStmt->execute([$orgId]);
$locations = $locationStmt->fetchAll();

$folderWhere = "sl.organization_id=? AND sl.direction='in' AND sl.product_id IS NOT NULL";
$folderParams = [$orgId];
if ($activeWarehouse) { $folderWhere .= " AND sl.warehouse_id=?"; $folderParams[] = $activeWarehouse; }

$folderStmt = $pdo->prepare(
    "SELECT COALESCE(NULLIF(p.category,''),'Tanpa Kategori') AS category,
       COUNT(DISTINCT p.id) AS item_count, SUM(sl.qty_remaining) AS total_qty, SUM(sl.qty_remaining * sl.unit_cost) AS total_value
     FROM stock_ledger sl JOIN products p ON p.id = sl.product_id
     WHERE $folderWhere
     GROUP BY category HAVING total_qty > 0
     ORDER BY category"
);
$folderStmt->execute($folderParams);
$folders = $folderStmt->fetchAll();

$grandTotalQty = array_sum(array_column($folders, 'total_qty'));
$grandTotalValue = array_sum(array_column($folders, 'total_value'));

$categoryItems = [];
if ($activeCategory !== null) {
    $itemWhere = "sl.organization_id=? AND sl.direction='in' AND sl.product_id IS NOT NULL AND COALESCE(NULLIF(p.category,''),'Tanpa Kategori') = ?";
    $itemParams = [$orgId, $activeCategory];
    if ($activeWarehouse) { $itemWhere .= " AND sl.warehouse_id=?"; $itemParams[] = $activeWarehouse; }
    $itemStmt = $pdo->prepare(
        "SELECT p.id, p.name, p.unit, p.photo_path,
           (SELECT pp.file_path FROM product_photos pp WHERE pp.product_id = p.id ORDER BY pp.sort_order, pp.id LIMIT 1) AS gallery_photo,
           SUM(sl.qty_remaining) AS total_qty, SUM(sl.qty_remaining * sl.unit_cost) AS total_value
         FROM stock_ledger sl JOIN products p ON p.id = sl.product_id
         WHERE $itemWhere
         GROUP BY p.id HAVING total_qty > 0
         ORDER BY p.name"
    );
    $itemStmt->execute($itemParams);
    $categoryItems = $itemStmt->fetchAll();
}

function inv_qs(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return htmlspecialchars('inventory-report.php' . ($params ? '?' . http_build_query($params) : ''));
}

// ===== List semua produk (flat, buat di-export) — qty & rata-rata harga/unit dari stock_ledger =====
$allWhere = "sl.organization_id=? AND sl.direction='in' AND sl.product_id IS NOT NULL";
$allParams = [$orgId];
if ($activeWarehouse) { $allWhere .= " AND sl.warehouse_id=?"; $allParams[] = $activeWarehouse; }
if ($activeCategory !== null) { $allWhere .= " AND COALESCE(NULLIF(p.category,''),'Tanpa Kategori')=?"; $allParams[] = $activeCategory; }
$allProductsStmt = $pdo->prepare(
    "SELECT p.id, COALESCE(NULLIF(p.category,''),'Tanpa Kategori') AS category, p.name, p.unit,
       SUM(sl.qty_remaining) AS total_qty, SUM(sl.qty_remaining * sl.unit_cost) AS total_value
     FROM stock_ledger sl JOIN products p ON p.id = sl.product_id
     WHERE $allWhere
     GROUP BY p.id HAVING total_qty > 0
     ORDER BY category, p.name"
);
$allProductsStmt->execute($allParams);
$allProducts = $allProductsStmt->fetchAll();
foreach ($allProducts as &$ap) {
    $ap['unit_cost'] = $ap['total_qty'] > 0 ? $ap['total_value'] / $ap['total_qty'] : 0;
}
unset($ap);
$allProductsTotalQty = array_sum(array_column($allProducts, 'total_qty'));
$allProductsTotalValue = array_sum(array_column($allProducts, 'total_value'));
?>

<style>
  .inv-page-head { margin-bottom:18px; }
  .inv-page-head h2 { margin:0 0 4px; font-size:20px; }
  .inv-page-head p { margin:0; font-size:13px; color:var(--ink-muted); }
  .inv-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:18px; box-shadow:var(--shadow-card); }
  .inv-section-head { margin-bottom:14px; }
  .inv-section-head h3 { margin:0; font-size:14px; }

  table.inv-box-table { width:100%; border-collapse:collapse; }
  table.inv-box-table th, table.inv-box-table td { border:1px solid var(--border); padding:9px 12px; text-align:left; font-size:12.5px; }
  table.inv-box-table th { background:oklch(0.97 0.003 90); font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); }
  table.inv-box-table td.num { text-align:right; font-weight:600; }
  table.inv-box-table tbody tr:nth-child(even) { background:oklch(0.985 0.003 90); }

  .inv-summary-bar { display:flex; gap:16px; margin-bottom:16px; }
  .inv-summary-bar .card { flex:1; padding:14px 18px; }
  .inv-summary-bar .card .k { display:block; font-size:10.5px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:4px; }
  .inv-summary-bar .card .v { font-size:18px; font-weight:700; }

  .inv-breadcrumb { font-size:13px; margin-bottom:14px; }
  .inv-breadcrumb a { text-decoration:none; }

  .inv-location-row { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
  .inv-location-chip { display:flex; align-items:center; gap:6px; text-decoration:none; color:inherit; border:1px solid var(--border); border-radius:20px; padding:6px 14px 6px 12px; background:var(--surface); font-size:12.5px; }
  .inv-location-chip:hover { box-shadow:var(--shadow-card); }
  .inv-location-chip.active { background:var(--accent); color:#fff; border-color:var(--accent); }
  .inv-location-chip .qty { font-weight:700; }

  .inv-search-row { margin-bottom:16px; }
  .inv-search-row input { width:100%; max-width:340px; padding:9px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; box-sizing:border-box; }

  .inv-folder-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:14px; }
  .inv-folder-tile { display:block; text-decoration:none; color:inherit; border:1px solid var(--border); border-radius:10px; padding:14px; background:var(--surface); transition:box-shadow .15s; }
  .inv-folder-tile:hover { box-shadow:var(--shadow-card); }
  .inv-folder-icon { width:38px; height:32px; border-radius:4px 8px 8px 8px; margin-bottom:10px; position:relative; }
  .inv-folder-icon::before { content:''; position:absolute; top:-6px; left:0; width:18px; height:6px; border-radius:4px 4px 0 0; background:inherit; }
  .inv-folder-name { font-size:12.5px; font-weight:700; margin-bottom:6px; word-break:break-word; }
  .inv-folder-qty { font-size:15px; font-weight:700; }
  .inv-folder-value { font-size:11px; color:var(--ink-muted); margin-top:2px; }

  .inv-item-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:14px; }
  .inv-item-tile { display:block; text-decoration:none; color:inherit; border:1px solid var(--border); border-radius:10px; overflow:hidden; background:var(--surface); }
  .inv-item-tile:hover { box-shadow:var(--shadow-card); }
  .inv-item-photo { width:100%; aspect-ratio:1/1; background:oklch(0.97 0.003 90) center/cover no-repeat; display:flex; align-items:center; justify-content:center; color:var(--ink-muted); font-size:11px; }
  .inv-item-body { padding:10px 12px; }
  .inv-item-name { font-size:12.5px; font-weight:600; margin-bottom:4px; word-break:break-word; }
  .inv-item-qty { font-size:13px; font-weight:700; }
  .inv-item-value { font-size:10.5px; color:var(--ink-muted); }
</style>

<div class="inv-page-head">
  <h2>Laporan Inventory</h2>
  <p>Sebaran stok sekarang — termasuk yang lagi "dititip" di gudang vendor (bukan cuma gudang Svashta sendiri).</p>
</div>

<div class="inv-location-row">
  <?php if ($myWarehouseId === null): ?>
    <a class="inv-location-chip <?= !$activeWarehouse ? 'active' : '' ?>" href="<?= inv_qs(['warehouse' => null]) ?>">Semua Lokasi</a>
  <?php endif; ?>
  <?php foreach ($locations as $loc): ?>
    <?php if ($myWarehouseId !== null && (int) $loc['id'] !== $myWarehouseId) continue; ?>
    <a class="inv-location-chip <?= $activeWarehouse === (int) $loc['id'] ? 'active' : '' ?>" href="<?= inv_qs(['warehouse' => $loc['id']]) ?>">
      <?= $loc['vendor_id'] ? 'VENDOR: ' . htmlspecialchars($loc['vendor_name'] ?? '?') : htmlspecialchars($loc['name']) ?>
      <span class="qty">· <?= rtrim(rtrim(number_format((float) $loc['total_qty'], 2, ',', '.'), '0'), ',') ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="inv-search-row">
  <input type="text" id="inv-search" placeholder="Cari kategori / nama barang...">
</div>

<div class="inv-summary-bar">
  <div class="card"><span class="k">Total Gudang</span><span class="v"><?= $activeWarehouse ? 1 : count($locations) ?></span></div>
  <div class="card"><span class="k">Total Kategori</span><span class="v"><?= count($folders) ?></span></div>
  <div class="card"><span class="k">Total Qty</span><span class="v"><?= rtrim(rtrim(number_format($grandTotalQty, 2, ',', '.'), '0'), ',') ?></span></div>
  <div class="card"><span class="k">Total Nilai (HPP)</span><span class="v">Rp <?= number_format($grandTotalValue, 0, ',', '.') ?></span></div>
</div>

<div class="inv-section">
  <div class="inv-section-head" style="display:flex; justify-content:space-between; align-items:center;">
    <h3>Stok Produk</h3>
  </div>

  <?php if ($activeCategory === null): ?>
    <div class="inv-folder-grid" id="inv-folder-grid">
      <?php foreach ($folders as $f): $color = inv_folder_color($f['category']); ?>
        <a class="inv-folder-tile" data-search="<?= htmlspecialchars(mb_strtolower($f['category'])) ?>" href="<?= inv_qs(['category' => $f['category']]) ?>">
          <div class="inv-folder-icon" style="background:<?= $color ?>;"></div>
          <div class="inv-folder-name"><?= htmlspecialchars($f['category']) ?></div>
          <div class="inv-folder-qty"><?= rtrim(rtrim(number_format((float) $f['total_qty'], 2, ',', '.'), '0'), ',') ?></div>
          <div class="inv-folder-value">Rp <?= number_format((float) $f['total_value'], 0, ',', '.') ?> · <?= $f['item_count'] ?> item</div>
        </a>
      <?php endforeach; ?>
      <?php if (!$folders): ?><div style="grid-column:1/-1; text-align:center; color:var(--ink-muted); padding:20px;">Belum ada stok produk.</div><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="inv-breadcrumb"><a href="<?= inv_qs(['category' => null]) ?>">Stok Produk</a> / <strong><?= htmlspecialchars($activeCategory) ?></strong></div>
    <div class="inv-item-grid" id="inv-item-grid">
      <?php foreach ($categoryItems as $it): ?>
        <a class="inv-item-tile" data-search="<?= htmlspecialchars(mb_strtolower($it['name'])) ?>" href="manufaktur-product-info.php?id=<?= $it['id'] ?>">
          <div class="inv-item-photo">
            <?php $itPhoto = $it['gallery_photo'] ?: $it['photo_path']; ?>
            <?php if ($itPhoto): ?><img src="<?= htmlspecialchars($itPhoto) ?>" alt="" style="width:100%; height:100%; object-fit:cover;"><?php else: ?>Tanpa foto<?php endif; ?>
          </div>
          <div class="inv-item-body">
            <div class="inv-item-name"><?= htmlspecialchars($it['name']) ?></div>
            <div class="inv-item-qty"><?= rtrim(rtrim(number_format((float) $it['total_qty'], 2, ',', '.'), '0'), ',') ?></div>
            <div class="inv-item-value">Rp <?= number_format((float) $it['total_value'], 0, ',', '.') ?></div>
          </div>
        </a>
      <?php endforeach; ?>
      <?php if (!$categoryItems): ?><div style="grid-column:1/-1; text-align:center; color:var(--ink-muted); padding:20px;">Gak ada item di kategori ini.</div><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="inv-section">
  <div class="inv-section-head" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
    <h3>Semua Produk<?= $activeCategory !== null ? ' — ' . htmlspecialchars($activeCategory) : '' ?></h3>
    <div style="display:flex; gap:8px;">
      <a class="btn btn-sm btn-ghost" href="inventory-export.php?<?= http_build_query(array_filter(['warehouse' => $activeWarehouse ?: null, 'category' => $activeCategory])) ?>">⬇ Export ke Excel</a>
      <a class="btn btn-sm" href="manufaktur-saldo-awal.php?new=1">+ Input Saldo Awal</a>
    </div>
  </div>
  <p style="font-size:11.5px; color:var(--ink-muted); margin:-6px 0 12px;">"Export ke Excel" jadi file <code>.xlsx</code> asli lengkap foto tiap produk. Kalau mau edit Qty-nya terus upload ulang buat update stok, file yang sama ini bisa langsung dipakai di halaman Input Saldo Awal.</p>
  <table class="inv-box-table">
    <thead><tr><th>Kategori</th><th>Nama Produk</th><th class="num" style="width:90px;">Qty</th><th class="num" style="width:130px;">Harga/Unit</th><th class="num" style="width:140px;">Total Nilai</th></tr></thead>
    <tbody>
      <?php foreach ($allProducts as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['category']) ?></td>
          <td><a href="manufaktur-product-info.php?id=<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></a></td>
          <td class="num"><?= rtrim(rtrim(number_format((float) $r['total_qty'], 2, ',', '.'), '0'), ',') ?></td>
          <td class="num"><?= number_format((float) $r['unit_cost'], 0, ',', '.') ?></td>
          <td class="num"><?= number_format((float) $r['total_value'], 0, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$allProducts): ?><tr><td colspan="5" style="text-align:center; color:var(--ink-muted);">Belum ada stok produk.</td></tr><?php endif; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:700; background:oklch(0.97 0.003 90);">
        <td colspan="2">Total</td>
        <td class="num"><?= rtrim(rtrim(number_format($allProductsTotalQty, 2, ',', '.'), '0'), ',') ?></td>
        <td></td>
        <td class="num"><?= number_format($allProductsTotalValue, 0, ',', '.') ?></td>
      </tr>
    </tfoot>
  </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var invSearch = document.getElementById('inv-search');
  if (invSearch) {
    invSearch.addEventListener('input', function () {
      var q = invSearch.value.trim().toLowerCase();
      document.querySelectorAll('#inv-folder-grid [data-search], #inv-item-grid [data-search]').forEach(function (el) {
        var hay = el.getAttribute('data-search') || '';
        el.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
