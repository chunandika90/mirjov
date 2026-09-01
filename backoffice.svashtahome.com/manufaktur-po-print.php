<?php
require_once __DIR__ . '/../backoffice-shared/auth.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
require_login();
$org = require_org();
require_module_access('manufaktur_po');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT h.*, c.name AS vendor_name, c.address, p.name AS project_name
     FROM manufaktur_po h JOIN contacts c ON c.id=h.vendor_id LEFT JOIN projects p ON p.id=h.project_id
     WHERE h.id=? AND h.organization_id=?'
);
$stmt->execute([$id, $org['organization_id']]);
$h = $stmt->fetch();
if (!$h) { http_response_code(404); exit('Form Product Series tidak ditemukan.'); }

$lines = $pdo->prepare('SELECT * FROM manufaktur_po_lines WHERE manufaktur_po_id=?');
$lines->execute([$id]);
$lines = $lines->fetchAll();

$compStmt = $pdo->prepare('SELECT * FROM manufaktur_po_line_components WHERE line_id=?');
foreach ($lines as &$l) {
    $compStmt->execute([$l['id']]);
    $l['components'] = $compStmt->fetchAll();
}
unset($l);

function format_tanggal_indo_po(?string $dateStr): string
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
<title>Product Series <?= htmlspecialchars($h['doc_number']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12.5px; color: #1c1a17; background: #ececec; margin: 0; padding: 24px 0; }
  .sheet { max-width: 900px; margin: 0 auto 24px; background: #fff; border: 1px solid #1c1a17; padding: 22px 26px; }
  .doc-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #1c1a17; padding-bottom: 14px; margin-bottom: 16px; }
  .doc-head .brand { display: flex; align-items: center; gap: 12px; }
  .doc-head .brand .org-name { font-weight: 700; font-size: 14px; }
  .doc-title { text-align: center; flex: 1; }
  .doc-title h1 { margin: 0; font-size: 20px; letter-spacing: .04em; text-transform: uppercase; }
  .doc-meta-block { text-align: right; font-size: 12px; }
  .doc-meta-block .row { display: flex; justify-content: flex-end; gap: 8px; }
  .doc-meta-block .row .k { color: #766f60; min-width: 55px; text-align: left; }
  .doc-meta-block .row .v { font-weight: 600; }

  table.info-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  table.info-table td { border: 1px solid #1c1a17; padding: 5px 8px; font-size: 12px; vertical-align: top; }
  table.info-table td.k { font-weight: 700; width: 110px; background: #f6f4ef; }

  .comp-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin: 18px 0; }
  .comp-box { border: 1px solid #1c1a17; }
  .comp-box .comp-title { background: #1c1a17; color: #fff; text-align: center; font-weight: 700; font-size: 11px; padding: 6px 4px; text-transform: uppercase; }
  .comp-box .comp-img { height: 90px; display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid #1c1a17; background: #fafafa; }
  .comp-box .comp-img img { max-width: 100%; max-height: 100%; object-fit: cover; }
  .comp-box .comp-kv { width: 100%; border-collapse: collapse; }
  .comp-box .comp-kv td { border-top: 1px solid #1c1a17; padding: 3px 6px; font-size: 10.5px; }
  .comp-box .comp-kv td.k { font-weight: 700; width: 45%; }

  .note-block { margin-top: 20px; font-size: 11.5px; }
  .sign-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 30px; text-align: center; font-size: 12px; }
  .sign-grid .name { border-top: 1px solid #1c1a17; margin-top: 55px; padding-top: 4px; font-weight: 700; }

  .no-print { max-width: 900px; margin: 0 auto; text-align: center; }
  @media print { body { background: #fff; padding: 0; } .sheet { border: none; max-width: none; margin: 0; } .no-print { display: none; } }
</style>
</head>
<body>
  <div class="sheet">
    <div class="doc-head">
      <div class="brand">
        <img src="assets/img/logo-mirjov.png" alt="Mirjov" style="width:42px; height:42px; object-fit:contain;">
        <div class="org-name">MIRJOV KARUNIA ABADI</div>
      </div>
      <div class="doc-title"><h1>Product Series</h1></div>
      <div class="doc-meta-block">
        <div class="row"><span class="k">Tanggal</span><span>:</span><span class="v"><?= htmlspecialchars(format_tanggal_indo_po($h['tanggal'])) ?></span></div>
        <div class="row"><span class="k">Nomor</span><span>:</span><span class="v"><?= htmlspecialchars($h['doc_number']) ?></span></div>
        <?php if ($h['po_number_vendor']): ?><div class="row"><span class="k">No. PO Vendor</span><span>:</span><span class="v"><?= htmlspecialchars($h['po_number_vendor']) ?></span></div><?php endif; ?>
      </div>
    </div>

    <table class="info-table">
      <tr><td class="k">Vendor</td><td><?= htmlspecialchars($h['vendor_name']) ?></td><td class="k">Project</td><td><?= $h['project_name'] ? htmlspecialchars($h['project_name']) : '—' ?></td></tr>
      <tr><td class="k">Pemesan</td><td><?= $h['pemesan'] ? htmlspecialchars($h['pemesan']) : '—' ?></td><td class="k">Waktu Produksi</td><td><?= $h['waktu_produksi'] ? htmlspecialchars($h['waktu_produksi']) : '—' ?></td></tr>
      <?php if ($h['keterangan']): ?><tr><td class="k">Keterangan</td><td colspan="3"><?= htmlspecialchars($h['keterangan']) ?></td></tr><?php endif; ?>
    </table>

    <?php foreach ($lines as $l): ?>
      <table class="info-table">
        <tr><td class="k">Product</td><td><?= htmlspecialchars($l['product_name_snapshot']) ?></td><td class="k">Series</td><td><?= $l['series'] ? htmlspecialchars($l['series']) : '—' ?></td></tr>
        <tr><td class="k">Size (mm)</td><td><?= $l['size_mm'] ? htmlspecialchars($l['size_mm']) : '—' ?></td><td class="k">Qty</td><td><?= rtrim(rtrim(number_format((float) $l['qty'], 2, ',', '.'), '0'), ',') ?></td></tr>
        <?php if ($l['item_code'] || $l['remarks']): ?>
          <tr><td class="k">Item Code</td><td><?= $l['item_code'] ? htmlspecialchars($l['item_code']) : '—' ?></td><td class="k">Remarks</td><td><?= $l['remarks'] ? htmlspecialchars($l['remarks']) : '—' ?></td></tr>
        <?php endif; ?>
      </table>

      <?php if ($l['components']): ?>
      <div class="comp-grid">
        <?php foreach ($l['components'] as $c): ?>
          <div class="comp-box">
            <div class="comp-title"><?= htmlspecialchars($c['component_name']) ?></div>
            <div class="comp-img">
              <?php if ($c['photo_path']): ?><img src="<?= htmlspecialchars($c['photo_path']) ?>" alt=""><?php endif; ?>
            </div>
            <table class="comp-kv">
              <tr><td class="k">Pembuat</td><td><?= $c['pembuat'] ? htmlspecialchars($c['pembuat']) : '' ?></td></tr>
              <tr><td class="k">Code</td><td><?= $c['code'] ? htmlspecialchars($c['code']) : '' ?></td></tr>
              <tr><td class="k">Material</td><td><?= $c['material'] ? htmlspecialchars($c['material']) : '' ?></td></tr>
            </table>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="note-block">
      <strong>NOTE:</strong><br>
      1. Pembayaran: 50% DP dan 50% setelah barang selesai.
    </div>

    <div class="sign-grid">
      <div><div>Pemesan</div><div class="name">&nbsp;</div></div>
      <div><div>Pembuat (Vendor)</div><div class="name">( <?= htmlspecialchars($h['vendor_name']) ?> )</div></div>
    </div>
  </div>

  <div class="no-print"><button onclick="window.print()">Print / Save PDF</button></div>
</body>
</html>
