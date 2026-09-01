<?php
require_once __DIR__ . '/../backoffice-shared/auth.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
require_login();
$org = require_org();
require_module_access('manufaktur_penawaran');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT h.*, c.name AS vendor_name, c.address
     FROM manufaktur_penawaran h JOIN contacts c ON c.id=h.vendor_id
     WHERE h.id=? AND h.organization_id=?'
);
$stmt->execute([$id, $org['organization_id']]);
$h = $stmt->fetch();
if (!$h) { http_response_code(404); exit('Form Penawaran Harga tidak ditemukan.'); }

$projName = null;
if ($h['project_id']) {
    $pjStmt = $pdo->prepare('SELECT name FROM projects WHERE id=?');
    $pjStmt->execute([$h['project_id']]);
    $projName = $pjStmt->fetch()['name'] ?? null;
}

$lines = $pdo->prepare('SELECT * FROM manufaktur_penawaran_lines WHERE manufaktur_penawaran_id=?');
$lines->execute([$id]);
$lines = $lines->fetchAll();

const MP_PRICE_TYPE_LABELS_DETAIL = ['harga_frame' => 'Harga Frame', 'harga_finishing' => 'Harga Finishing', 'harga_packaging' => 'Harga Packaging', 'harga_qc' => 'Harga QC', 'harga_unit' => 'Harga Unit', 'harga_dll' => 'Harga Lain-lain'];
$priceStmt = $pdo->prepare('SELECT * FROM manufaktur_penawaran_line_prices WHERE line_id=?');
$grandTotal = 0;
foreach ($lines as &$l) {
    $priceStmt->execute([$l['id']]);
    $l['prices'] = $priceStmt->fetchAll();
    $l['line_total'] = array_sum(array_column($l['prices'], 'price_value'));
    $grandTotal += $l['line_total'];
}
unset($l);

function format_tanggal_indo_detail(?string $dateStr): string
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
<title>Form Penawaran Harga <?= htmlspecialchars($h['doc_number']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12.5px; color: #1c1a17; background: #ececec; margin: 0; padding: 24px 0; }
  .sheet { max-width: 860px; margin: 0 auto 24px; background: #fff; border: 1px solid #1c1a17; padding: 22px 26px; }
  .doc-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #1c1a17; padding-bottom: 14px; margin-bottom: 16px; }
  .doc-head .brand { display: flex; align-items: center; gap: 12px; }
  .doc-head .brand .org-name { font-weight: 700; font-size: 14px; }
  .doc-title { text-align: center; flex: 1; }
  .doc-title h1 { margin: 0; font-size: 22px; letter-spacing: .04em; text-transform: uppercase; }
  .doc-meta-block { text-align: right; font-size: 12px; }
  .doc-meta-block .row { display: flex; justify-content: flex-end; gap: 8px; }
  .doc-meta-block .row .k { color: #766f60; min-width: 55px; text-align: left; }
  .doc-meta-block .row .v { font-weight: 600; }

  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin-bottom: 16px; font-size: 12.5px; }
  .info-grid .row { display: flex; gap: 8px; padding: 2px 0; }
  .info-grid .k { color: #766f60; min-width: 90px; flex-shrink: 0; }
  .info-grid .v { border-bottom: 1px solid #cfc8b8; flex: 1; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  th, td { border: 1px solid #1c1a17; padding: 6px 8px; text-align: left; vertical-align: top; font-size: 12px; }
  th { background: #f0ede6; font-size: 10.5px; text-transform: uppercase; letter-spacing: .02em; }
  .num { text-align: right; }

  .detail-block { border: 1px solid #cfc8b8; border-radius: 6px; padding: 14px 18px; margin: 18px 0 24px; }
  .detail-block .wording-text { font-size: 12.5px; line-height: 1.6; margin: 0 0 14px; }
  .detail-block .bank-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #766f60; margin: 0 0 6px; }
  .detail-block .bank-grid { display: grid; grid-template-columns: 90px 1fr; row-gap: 3px; font-size: 12.5px; max-width: 320px; }
  .detail-block .bank-grid .k { color: #766f60; }
  .detail-block .bank-grid .v { font-weight: 600; }

  .sign-block { margin-top: 16px; text-align: right; font-size: 12.5px; }
  .sign-block .place-date { margin-bottom: 60px; }
  .sign-block .name { display: inline-block; border-top: 1px solid #1c1a17; padding-top: 4px; font-weight: 600; min-width: 180px; text-align: center; }

  .no-print { max-width: 860px; margin: 0 auto; text-align: center; }
  @media print { body { background: #fff; padding: 0; } .sheet { border: none; max-width: none; margin: 0; } .no-print { display: none; } }
</style>
</head>
<body>
  <div class="sheet">
    <div class="doc-head">
      <div class="brand">
        <img src="assets/img/logo-mirjov.png" alt="Mirjov" style="width:44px; height:44px; object-fit:contain;">
        <div class="org-name">MIRJOV KARUNIA ABADI</div>
      </div>
      <div class="doc-title"><h1>Form Penawaran Harga</h1></div>
      <div class="doc-meta-block">
        <div class="row"><span class="k">Tanggal</span><span>:</span><span class="v"><?= htmlspecialchars(format_tanggal_indo_detail($h['tanggal'])) ?></span></div>
        <div class="row"><span class="k">Nomor</span><span>:</span><span class="v"><?= htmlspecialchars($h['doc_number']) ?></span></div>
      </div>
    </div>

    <div class="info-grid">
      <div class="row"><span class="k">Vendor</span><span>:</span><span class="v"><?= htmlspecialchars($h['vendor_name']) ?></span></div>
      <div class="row"><span class="k">Project</span><span>:</span><span class="v"><?= $projName ? htmlspecialchars($projName) : '' ?></span></div>
      <div class="row"><span class="k">Alamat Vendor</span><span>:</span><span class="v"><?= $h['address'] ? htmlspecialchars($h['address']) : '' ?></span></div>
      <div class="row"><span class="k">Keterangan</span><span>:</span><span class="v"><?= $h['keterangan'] ? htmlspecialchars($h['keterangan']) : '' ?></span></div>
    </div>

    <table>
      <thead><tr><th>Kode Barang</th><th>Finishing</th><th>Material</th><th class="num" style="width:50px;">Qty</th><th>Rincian Harga</th><th class="num" style="width:110px;">Subtotal</th><th style="width:90px;">Timeline</th></tr></thead>
      <tbody>
        <?php foreach ($lines as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['product_name_snapshot']) ?></td>
          <td><?= $l['finishing_snapshot'] ? htmlspecialchars($l['finishing_snapshot']) : '—' ?></td>
          <td><?= $l['material_snapshot'] ? htmlspecialchars($l['material_snapshot']) : '—' ?></td>
          <td class="num"><?= rtrim(rtrim(number_format((float) $l['qty'], 2, ',', '.'), '0'), ',') ?></td>
          <td>
            <?php if ($l['prices']): foreach ($l['prices'] as $pr): ?>
              <div><?= MP_PRICE_TYPE_LABELS_DETAIL[$pr['price_type']] ?? $pr['price_type'] ?>: Rp <?= number_format((float) $pr['price_value'], 0, ',', '.') ?></div>
            <?php endforeach; else: ?>—<?php endif; ?>
          </td>
          <td class="num">Rp <?= number_format($l['line_total'], 0, ',', '.') ?></td>
          <td><?= $l['timeline_pabrik'] ? htmlspecialchars(format_tanggal_indo_detail($l['timeline_pabrik'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <tr><td colspan="4" style="text-align:right; font-weight:700;">TOTAL</td><td></td><td class="num" style="font-weight:700;">Rp <?= number_format($grandTotal, 0, ',', '.') ?></td><td></td></tr>
      </tbody>
    </table>

    <?php if ($h['wording_pelunasan'] || $h['bank_name'] || $h['bank_norek'] || $h['bank_an']): ?>
      <div class="detail-block">
        <?php if ($h['wording_pelunasan']): ?>
          <div class="wording-text"><?= nl2br(htmlspecialchars($h['wording_pelunasan'])) ?></div>
        <?php endif; ?>
        <?php if ($h['bank_name'] || $h['bank_norek'] || $h['bank_an']): ?>
          <div class="bank-title">NB — Rekening Pembayaran</div>
          <div class="bank-grid">
            <div class="k">Bank</div><div class="v"><?= $h['bank_name'] ? htmlspecialchars($h['bank_name']) : '—' ?></div>
            <div class="k">No. Rekening</div><div class="v"><?= $h['bank_norek'] ? htmlspecialchars($h['bank_norek']) : '—' ?></div>
            <div class="k">Atas Nama</div><div class="v"><?= $h['bank_an'] ? htmlspecialchars($h['bank_an']) : '—' ?></div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="sign-block">
      <div class="place-date">Jepara, <?= htmlspecialchars(format_tanggal_indo_detail(date('Y-m-d'))) ?></div>
      <div class="name">( <?= htmlspecialchars($h['vendor_name']) ?> )</div>
    </div>
  </div>

  <div class="no-print"><button onclick="window.print()">Print / Save PDF</button></div>
</body>
</html>
