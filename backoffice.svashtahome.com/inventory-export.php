<?php
require_once __DIR__ . '/../backoffice-shared/auth.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
require_once __DIR__ . '/../backoffice-shared/image_upload.php';
require_login();
$org = require_org();
require_module_access('laporan');

$pdo = db();
$orgId = $org['organization_id'];
$activeWarehouse = isset($_GET['warehouse']) ? (int) $_GET['warehouse'] : 0;
$activeCategory = isset($_GET['category']) ? trim((string) $_GET['category']) : null;

$where = "sl.organization_id=? AND sl.direction='in' AND sl.product_id IS NOT NULL";
$params = [$orgId];
if ($activeWarehouse) { $where .= " AND sl.warehouse_id=?"; $params[] = $activeWarehouse; }
if ($activeCategory !== null) { $where .= " AND COALESCE(NULLIF(p.category,''),'Tanpa Kategori')=?"; $params[] = $activeCategory; }

$stmt = $pdo->prepare(
    "SELECT p.id, COALESCE(NULLIF(p.category,''),'Tanpa Kategori') AS category, sc.name AS subcategory,
       p.name, p.unit, p.photo_path, w.name AS warehouse_name,
       (SELECT pp.file_path FROM product_photos pp WHERE pp.product_id = p.id ORDER BY pp.sort_order, pp.id LIMIT 1) AS gallery_photo,
       SUM(sl.qty_remaining) AS total_qty, SUM(sl.qty_remaining * sl.unit_cost) AS total_value
     FROM stock_ledger sl
     JOIN products p ON p.id = sl.product_id
     JOIN warehouses w ON w.id = sl.warehouse_id
     LEFT JOIN product_subcategories sc ON sc.id = p.subcategory_id
     WHERE $where
     GROUP BY p.id, sl.warehouse_id HAVING total_qty > 0
     ORDER BY warehouse_name, category, p.name"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Semua export jadi .xlsx asli (gak ada mode CSV lagi) — kolom lengkap + foto tiap produk (kalau ada).
require_once __DIR__ . '/../backoffice-shared/xlsx_writer.php';

$xlsx = new MinimalXlsxWriter([14, 16, 16, 34, 8, 8, 14, 15, 22]);
$xlsx->addHeaderRow(['Lokasi', 'Kategori', 'Sub Kategori', 'Nama Produk', 'Unit', 'Qty', 'Harga per Unit', 'Total Nilai', 'Foto']);

$totalQty = 0;
$totalValue = 0;
foreach ($rows as $r) {
    $qty = (float) $r['total_qty'];
    $value = (float) $r['total_value'];
    $unitCost = $qty > 0 ? $value / $qty : 0;

    $photoRel = $r['gallery_photo'] ?: $r['photo_path'];
    $photoLocal = $photoRel ? webroot_dir() . '/' . $photoRel : null;

    $xlsx->addDataRow([
        ['type' => 's', 'value' => $r['warehouse_name']],
        ['type' => 's', 'value' => $r['category']],
        ['type' => 's', 'value' => $r['subcategory'] ?: '—'],
        ['type' => 's', 'value' => $r['name']],
        ['type' => 's', 'value' => $r['unit']],
        ['type' => 'n', 'value' => $qty],
        ['type' => 'n', 'value' => round($unitCost, 2)],
        ['type' => 'n', 'value' => $value],
        ['type' => 's', 'value' => ''], // kolom foto — teksnya kosong, gambarnya nempel di atas cell ini
    ], $photoLocal && is_file($photoLocal) ? $photoLocal : null, 8, 140);

    $totalQty += $qty;
    $totalValue += $value;
}
$xlsx->addDataRow([
    ['type' => 's', 'value' => ''],
    ['type' => 's', 'value' => ''],
    ['type' => 's', 'value' => ''],
    ['type' => 's', 'value' => 'TOTAL'],
    ['type' => 's', 'value' => ''],
    ['type' => 'n', 'value' => $totalQty],
    ['type' => 's', 'value' => ''],
    ['type' => 'n', 'value' => $totalValue],
    ['type' => 's', 'value' => ''],
]);

$xlsx->output('inventory-' . date('Y-m-d') . '.xlsx');
