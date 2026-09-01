<?php
require_once __DIR__ . '/../backoffice-shared/auth.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
require_login();
$org = require_org();
require_module_access('manufaktur_surat_jalan');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT h.*, w.name AS warehouse_name FROM stock_opening_balance h JOIN warehouses w ON w.id=h.warehouse_id WHERE h.id=? AND h.organization_id=?');
$stmt->execute([$id, $org['organization_id']]);
$h = $stmt->fetch();
if (!$h) { http_response_code(404); exit('Dokumen tidak ditemukan.'); }

$userStmt = $pdo->prepare('SELECT name FROM users WHERE id=?');
$userStmt->execute([$h['created_by']]);
$createdByName = $userStmt->fetch()['name'] ?? '—';

$lines = $pdo->prepare('SELECT * FROM stock_opening_balance_lines WHERE opening_balance_id=? ORDER BY sort_order, id');
$lines->execute([$id]);
$lines = $lines->fetchAll();
$totalQty = array_sum(array_column($lines, 'qty'));
$totalValue = array_sum(array_map(fn($l) => (float) $l['qty'] * (float) $l['harga'], $lines));

function format_tanggal_indo_sa(?string $dateStr): string
{
    if (!$dateStr) return '—';
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($dateStr);
    return (int) date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Input Saldo Awal <?= htmlspecialchars($h['doc_number']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #1c1a17; background: #ececec; margin: 0; padding: 24px 0; }
  .sheet { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #1c1a17; padding: 22px 26px; }

  .doc-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #1c1a17; padding-bottom: 14px; margin-bottom: 16px; }
  .doc-head .brand { display: flex; align-items: center; gap: 10px; }
  .doc-head .brand img { width: 38px; height: 38px; object-fit: contain; }
  .doc-head .brand .org-name { font-weight: 700; font-size: 13px; }
  .doc-title { text-align: center; flex: 1; }
  .doc-title h1 { margin: 0; font-size: 17px; letter-spacing: .02em; text-transform: uppercase; }
  .doc-meta-block { text-align: right; font-size: 12px; }
  .doc-meta-block .r { margin-bottom: 3px; }
  .doc-meta-block .k { font-weight: 700; }

  table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  table.info-table td { border: 1px solid #c9c4b6; padding: 5px 8px; font-size: 12px; vertical-align: top; }
  table.info-table td.k { font-weight: 700; width: 110px; }

  table.item-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
  table.item-table th, table.item-table td { border: 1px solid #c9c4b6; padding: 5px 8px; font-size: 11.5px; }
  table.item-table th { font-weight: 700; text-transform: uppercase; font-size: 10px; border-bottom: 2px solid #1c1a17; }
  table.item-table td.num { text-align: right; }
  table.item-table tr.total-row td { font-weight: 700; border-top: 2px solid #1c1a17; }

  .sign-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 30px; text-align: center; font-size: 12px; }
  .sign-grid .name { border-top: 1px solid #1c1a17; margin-top: 55px; padding-top: 4px; font-weight: 700; }

  .no-print { max-width: 860px; margin: 10px auto; text-align: center; }
  @page { size: A4 portrait; margin: 10mm; }
  @media print { body { background: #fff; padding: 0; } .sheet { border: none; max-width: none; margin: 0; } .no-print { display: none; } }
</style>
</head>
<body>
  <div class="sheet">
    <div class="doc-head">
      <div class="brand">
        <img src="assets/img/logo-mirjov.png" alt="Mirjov">
        <div class="org-name">MIRJOV KARUNIA ABADI</div>
      </div>
      <div class="doc-title"><h1>Lembar Input Saldo Awal</h1></div>
      <div class="doc-meta-block">
        <div class="r"><span class="k">Tanggal</span> : <?= htmlspecialchars(format_tanggal_indo_sa($h['tanggal'])) ?></div>
        <div class="r"><span class="k">Nomor</span> : <?= htmlspecialchars($h['doc_number']) ?></div>
      </div>
    </div>

    <table class="info-table">
      <tr><td class="k">Lokasi</td><td><?= htmlspecialchars($h['warehouse_name']) ?></td><td class="k">Diinput oleh</td><td><?= htmlspecialchars($createdByName) ?></td></tr>
      <?php if ($h['keterangan']): ?><tr><td class="k">Keterangan</td><td colspan="3"><?= htmlspecialchars($h['keterangan']) ?></td></tr><?php endif; ?>
    </table>

    <table class="item-table">
      <thead><tr><th style="width:28px;">No</th><th>Nama Barang</th><th style="width:70px;">Qty</th><th style="width:100px;">Harga</th><th style="width:120px;">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($lines as $i => $ln): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($ln['product_name_snapshot']) ?></td>
            <td class="num"><?= rtrim(rtrim(number_format((float) $ln['qty'], 2, ',', '.'), '0'), ',') ?></td>
            <td class="num"><?= number_format((float) $ln['harga'], 0, ',', '.') ?></td>
            <td class="num"><?= number_format((float) $ln['qty'] * (float) $ln['harga'], 0, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        <tr class="total-row"><td colspan="2">Total</td><td class="num"><?= rtrim(rtrim(number_format($totalQty, 2, ',', '.'), '0'), ',') ?></td><td></td><td class="num"><?= number_format($totalValue, 0, ',', '.') ?></td></tr>
      </tbody>
    </table>

    <div class="sign-grid">
      <div><div>Diinput oleh,</div><div class="name"><?= htmlspecialchars($createdByName) ?></div></div>
      <div><div>Diketahui/Disetujui oleh,</div><div class="name">&nbsp;</div></div>
    </div>
  </div>

  <div class="no-print"><button onclick="window.print()">Print / Save PDF</button></div>
</body>
</html>
