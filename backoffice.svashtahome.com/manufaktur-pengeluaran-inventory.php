<?php
$pageTitle = 'Pengeluaran Inventory';
$activeMenu = 'manufaktur_pengeluaran_inventory';
require __DIR__ . '/includes/header.php';
require_module_access('manufaktur_surat_jalan');

$pdo = db();

$month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
$prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

$stmt = $pdo->prepare(
    "SELECT sl.*, p.name AS product_name, w.name AS warehouse_name, sj.doc_number AS sj_doc_number, sj.id AS sj_id
     FROM stock_ledger sl
     LEFT JOIN products p ON p.id = sl.product_id
     LEFT JOIN warehouses w ON w.id = sl.warehouse_id
     LEFT JOIN manufaktur_surat_jalan sj ON sj.id = sl.ref_id AND sl.ref_type='manufaktur_surat_jalan'
     WHERE sl.organization_id=? AND sl.direction='out' AND sl.ref_type='manufaktur_surat_jalan'
       AND DATE_FORMAT(sl.created_at,'%Y-%m')=?
     ORDER BY sl.created_at DESC"
);
$stmt->execute([$org['organization_id'], $month]);
$rows = $stmt->fetchAll();

$totalQty = array_sum(array_column($rows, 'qty'));
$totalValue = array_sum(array_map(fn($r) => (float) $r['qty'] * (float) $r['unit_cost'], $rows));
?>
<style>
  .pi-toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
  .pi-month-nav { display:flex; align-items:center; gap:10px; font-size:14px; font-weight:600; }
  .pi-month-nav a { text-decoration:none; padding:4px 10px; border:1px solid var(--border); border-radius:6px; }
  .pi-summary { display:flex; gap:16px; margin-bottom:16px; }
  .pi-summary .card { flex:1; padding:14px 18px; }
  .pi-summary .card .k { display:block; font-size:10.5px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:4px; }
  .pi-summary .card .v { font-size:18px; font-weight:700; }
  table.pi-table { width:100%; border-collapse:collapse; font-size:12.5px; }
  table.pi-table th, table.pi-table td { border:1px solid var(--border); padding:8px 10px; text-align:left; }
  table.pi-table th { background:oklch(0.97 0.003 90); font-size:10px; text-transform:uppercase; }
  table.pi-table td.num { text-align:right; }
</style>

<div class="pi-toolbar">
  <div class="pi-month-nav">
    <a href="manufaktur-pengeluaran-inventory.php?month=<?= $prevMonth ?>">‹</a>
    <span><?= htmlspecialchars(date('F Y', strtotime($month . '-01'))) ?></span>
    <a href="manufaktur-pengeluaran-inventory.php?month=<?= $nextMonth ?>">›</a>
    <a class="btn btn-sm btn-ghost" href="manufaktur-pengeluaran-inventory.php">Bulan Ini</a>
  </div>
</div>

<div class="pi-summary">
  <div class="card"><span class="k">Total Baris Pengeluaran</span><span class="v"><?= count($rows) ?></span></div>
  <div class="card"><span class="k">Total Qty Keluar</span><span class="v"><?= rtrim(rtrim(number_format($totalQty, 2, ',', '.'), '0'), ',') ?></span></div>
  <div class="card"><span class="k">Total Nilai (HPP)</span><span class="v">Rp <?= number_format($totalValue, 0, ',', '.') ?></span></div>
</div>

<div class="card">
  <table class="pi-table">
    <thead>
      <tr>
        <th style="width:110px;">Tanggal</th>
        <th>Barang</th>
        <th style="width:120px;">Gudang</th>
        <th style="width:80px;">Qty</th>
        <th style="width:120px;">HPP / Unit</th>
        <th style="width:130px;">Nilai</th>
        <th style="width:140px;">Dari Surat Jalan</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($r['created_at']))) ?></td>
          <td><?= htmlspecialchars($r['product_name'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['warehouse_name'] ?? '—') ?></td>
          <td class="num"><?= rtrim(rtrim(number_format((float) $r['qty'], 2, ',', '.'), '0'), ',') ?></td>
          <td class="num">Rp <?= number_format((float) $r['unit_cost'], 0, ',', '.') ?></td>
          <td class="num">Rp <?= number_format((float) $r['qty'] * (float) $r['unit_cost'], 0, ',', '.') ?></td>
          <td><?php if ($r['sj_doc_number']): ?><a href="manufaktur-surat-jalan.php?id=<?= $r['sj_id'] ?>"><?= htmlspecialchars($r['sj_doc_number']) ?></a><?php else: ?>—<?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="7" style="text-align:center; color:var(--ink-muted);">Gak ada pengeluaran inventory bulan ini.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
