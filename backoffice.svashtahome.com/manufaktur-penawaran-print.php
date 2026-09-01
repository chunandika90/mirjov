<?php
require_once __DIR__ . '/../backoffice-shared/auth.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
require_login();
$org = require_org();
require_module_access('manufaktur_penawaran');

const MPP_PRICE_CATEGORY_SPEC = [
    'harga_frame' => ['label' => 'Biaya Frame / Konstruksi', 'detail' => 'Material utama & pembuatan struktur'],
    'harga_qc' => ['label' => 'Biaya QC', 'detail' => 'QC barang produksi'],
    'harga_finishing' => ['label' => 'Biaya Finishing', 'detail' => 'Pengecatan / laminasi / polishing'],
    'harga_komponen' => ['label' => 'Biaya Komponen', 'detail' => 'Hardware / adjuster / rel / dll'],
    'harga_packaging' => ['label' => 'Biaya Packaging / Pengemasan', 'detail' => 'Bubble wrap, wooden crate, karton'],
    'harga_dll' => ['label' => 'Biaya Tambahan / Lain-lain', 'detail' => 'Pengiriman / Instalasi / aksesoris'],
];

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

$priceStmt = $pdo->prepare('SELECT * FROM manufaktur_penawaran_line_prices WHERE line_id=?');
$attStmt = $pdo->prepare("SELECT file_path, original_name FROM manufaktur_penawaran_line_attachments WHERE line_id=? AND source='mj' ORDER BY id");
foreach ($lines as &$l) {
    $priceStmt->execute([$l['id']]);
    $prices = [];
    foreach ($priceStmt->fetchAll() as $pr) {
        $prices[$pr['price_type']] = (float) $pr['price_value'];
    }
    $l['price_map'] = $prices;
    $l['price_total'] = array_sum($prices);
    $l['grand_total'] = $l['price_total'] * (float) $l['qty'];

    $attStmt->execute([$l['id']]);
    $atts = $attStmt->fetchAll();
    $l['image_atts'] = array_values(array_filter($atts, fn($a) => in_array(strtolower(pathinfo($a['file_path'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true)));
    $l['pdf_atts'] = array_values(array_filter($atts, fn($a) => strtolower(pathinfo($a['file_path'], PATHINFO_EXTENSION)) === 'pdf'));
}
unset($l);

function format_tanggal_indo_ppf(?string $dateStr): string
{
    if (!$dateStr) return '—';
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($dateStr);
    return (int) date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
function format_tanggal_short_ppf(?string $dateStr): string
{
    if (!$dateStr) return '—';
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($dateStr);
    return (int) date('j', $ts) . '-' . $bulan[(int) date('n', $ts)] . '-' . date('y', $ts);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Form Purchase Order + Penawaran Harga <?= htmlspecialchars($h['doc_number']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #1c1a17; background: #ececec; margin: 0; padding: 24px 0; }
  .sheet { max-width: 860px; margin: 0 auto 24px; background: #fff; border: 1px solid #1c1a17; }

  .doc-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #1c1a17; padding: 14px 18px 12px; }
  .doc-head .brand { display: flex; align-items: center; gap: 10px; }
  .doc-head .brand img { width: 36px; height: 36px; object-fit: contain; }
  .doc-head .brand .org-name { font-weight: 700; font-size: 12px; }
  .doc-title { text-align: center; flex: 1; }
  .doc-title h1 { margin: 0; font-size: 16px; letter-spacing: .02em; text-transform: uppercase; }
  .doc-title h1.sub-title { font-size: 13px; margin-top: 1px; }
  .doc-meta-block { text-align: right; font-size: 11.5px; }
  .doc-meta-block .r { margin-bottom: 4px; }
  .doc-meta-block .k { font-weight: 700; }

  .meta-row { display: flex; justify-content: space-between; padding: 10px 18px 4px; font-size: 12px; }
  .meta-row .left .r { margin-bottom: 4px; }
  .meta-row .k { font-weight: 700; display: inline-block; min-width: 90px; }
  .meta-row .right { text-align: right; }
  .meta-row .right .k { border: 1px solid #1c1a17; padding: 3px 8px; font-weight: 700; min-width: 0; }
  .meta-row .right .v { font-weight: 700; padding-left: 6px; }

  .section-bar { border-top: 1px solid #1c1a17; border-bottom: 1px solid #1c1a17; font-weight: 700; font-size: 11.5px; padding: 6px 18px; margin: 10px 0 0; display: flex; justify-content: space-between; }

  .spec-table-wrap { padding: 12px 18px; }
  table.spec-table { width: 100%; border-collapse: collapse; margin: 0; }
  table.spec-table td { border: 1px solid #c9c4b6; padding: 5px 8px; font-size: 11.5px; vertical-align: top; }
  table.spec-table td.lbl { font-weight: 700; width: 26%; }
  table.spec-table td.remark-cell { vertical-align: top; }

  .price-wrap { padding: 0 18px 16px; }
  table.price-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  table.price-table th, table.price-table td { border: 1px solid #c9c4b6; padding: 5px 8px; font-size: 11.5px; }
  table.price-table th { font-weight: 700; text-transform: uppercase; font-size: 10px; border-bottom: 2px solid #1c1a17; }
  table.price-table td.num { text-align: right; }
  table.price-table tr.total-row td { font-weight: 700; border-top: 2px solid #1c1a17; border-bottom: none; }
  table.price-table tr.total-note td { border: none; font-size: 10px; font-style: italic; text-align: right; padding-top: 0; }
  table.price-table tr.grand-row td { font-weight: 700; border-top: 2px solid #1c1a17; border-bottom: 2px solid #1c1a17; }
  table.price-table tr.timeline-row td { font-weight: 700; }

  .est-note { font-size: 10.5px; font-style: italic; margin: 4px 0 0; }

  .sign-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 24px; font-size: 12px; }
  .sign-grid .name { border-top: 1px solid #1c1a17; margin-top: 55px; padding-top: 4px; font-weight: 700; text-align: center; }

  .attach-hint { border: 1px solid #1c1a17; font-size: 10.5px; font-weight: 700; font-style: italic; padding: 4px 8px; display: inline-block; margin-top: 14px; }
  .print-date { text-align: center; font-size: 11px; margin-top: 10px; }
  .sheet-body-pad { padding: 0 0 18px; }

  .attach-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
  .attach-chip { display: inline-flex; align-items: center; gap: 4px; border: 1px solid #c9c4b6; border-radius: 4px; padding: 2px 6px; font-size: 10px; }
  .attach-chip img { width: 26px; height: 26px; object-fit: cover; border-radius: 2px; }

  .photo-page { padding: 18px; display: flex; flex-direction: column; gap: 14px; min-height: 800px; }
  .photo-page-title { font-weight: 700; font-size: 13px; border-bottom: 1px solid #1c1a17; padding-bottom: 8px; }
  .photo-slot { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px dashed #cfc8b8; padding: 8px; min-height: 360px; }
  .photo-slot img { max-width: 100%; max-height: 360px; object-fit: contain; }

  .no-print { max-width: 860px; margin: 0 auto; text-align: center; }
  @media print {
    body { background: #fff; padding: 0; }
    .sheet { border: none; max-width: none; margin: 0; }
    .sheet + .sheet { page-break-before: always; }
    .no-print { display: none; }
  }
</style>
</head>
<body>
  <?php foreach ($lines as $l): ?>
  <div class="sheet">
    <div class="doc-head">
      <div class="brand">
        <img src="assets/img/logo-mirjov.png" alt="Mirjov">
        <div class="org-name">MIRJOV KARUNIA ABADI</div>
      </div>
      <div class="doc-title">
        <h1>Form Permintaan Harga</h1>
        <h1 class="sub-title">&amp; Penawaran Harga</h1>
      </div>
      <div class="brand" style="visibility:hidden;">
        <img src="assets/img/logo-mirjov.png" alt="">
        <div class="org-name">MIRJOV KARUNIA ABADI</div>
      </div>
    </div>

    <div class="meta-row">
      <div class="left">
        <div class="r"><span class="k">Tanggal</span> : <?= htmlspecialchars(strtoupper(format_tanggal_indo_ppf($h['tanggal']))) ?></div>
        <div class="r"><span class="k">No. Penawaran</span> : <?= htmlspecialchars($h['doc_number']) ?></div>
        <div class="r"><span class="k">Ketentuan DP</span> : <strong><?= htmlspecialchars($h['dp_terms'] ?: 'DP 50%') ?></strong></div>
      </div>
    </div>

    <div class="section-bar"><span>1. Informasi &amp; Spesifikasi Barang (MJ)</span></div>
    <div class="spec-table-wrap">
    <table class="spec-table">
      <tr>
        <td class="lbl">1. Nama Barang</td><td><?= htmlspecialchars($l['product_name_snapshot']) ?></td>
        <td class="lbl">Kode Barang</td><td rowspan="1"><?= $l['item_code'] ? htmlspecialchars($l['item_code']) : '' ?></td>
      </tr>
      <tr>
        <td class="lbl">2. Ukuran (mm)</td><td><?= $l['size_mm'] ? htmlspecialchars($l['size_mm']) : '' ?></td>
        <td class="lbl">Tekstur + Top Coat</td><td><?= $l['texture_topcoat'] ? htmlspecialchars($l['texture_topcoat']) : '' ?></td>
      </tr>
      <tr>
        <td class="lbl">3. Finishing (Opsi)</td><td><?= $l['finishing_snapshot'] ? htmlspecialchars($l['finishing_snapshot']) : '' ?></td>
        <td class="lbl" rowspan="7" style="vertical-align:top;">9. Remark / Catatan Tambahan</td>
        <td class="remark-cell" rowspan="7" style="vertical-align:top; white-space:pre-wrap;"><?= $l['keterangan_mj'] ? htmlspecialchars($l['keterangan_mj']) : '' ?></td>
      </tr>
      <tr><td class="lbl">4. Jumlah (Qty)</td><td><?= rtrim(rtrim(number_format((float) $l['qty'], 2, ',', '.'), '0'), ',') ?> UNITS</td></tr>
      <tr><td class="lbl">5. Material 1</td><td><?= $l['material_snapshot'] ? htmlspecialchars($l['material_snapshot']) : '' ?></td></tr>
      <tr><td class="lbl">6. Material 2</td><td><?= $l['material2_snapshot'] ? htmlspecialchars($l['material2_snapshot']) : '' ?></td></tr>
      <tr><td class="lbl">7. Wood</td><td><?= $l['wood'] ? htmlspecialchars($l['wood']) : '' ?></td></tr>
      <tr>
        <td class="lbl">8. Deadline</td>
        <td><?= $l['deadline_mj'] ? htmlspecialchars(strtoupper(format_tanggal_short_ppf($l['deadline_mj']))) : '' ?></td>
      </tr>
      <tr>
        <td class="lbl">9. Gambar Kerja</td>
        <td>
          <?php if ($l['image_atts'] || $l['pdf_atts']): ?>
            <div class="attach-chips">
              <?php foreach ($l['image_atts'] as $a): ?>
                <span class="attach-chip"><img src="<?= htmlspecialchars($a['file_path']) ?>" alt=""> <?= htmlspecialchars($a['original_name']) ?></span>
              <?php endforeach; ?>
              <?php foreach ($l['pdf_atts'] as $a): ?>
                <span class="attach-chip">📄 <?= htmlspecialchars($a['original_name']) ?></span>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <em style="color:#766f60;">Attachment file PDF / JPG</em>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <td class="lbl">10. Project / Cust.</td><td colspan="3"><?= $projName ? htmlspecialchars($projName) : '' ?></td>
      </tr>
    </table>
    </div>

    <div class="section-bar"><span>2. Rincian Harga &amp; Timeline (MMT)</span><span>No. Form Penawaran Harga: <strong><?= htmlspecialchars($h['doc_number']) ?></strong></span></div>
    <div class="price-wrap">
      <table class="price-table">
        <thead><tr><th style="width:34%;">Rincian Komponen Harga</th><th>Detail Spesifikasi</th><th style="width:18%;">Biaya (Rp) / Unit</th></tr></thead>
        <tbody>
          <?php foreach (MPP_PRICE_CATEGORY_SPEC as $key => $cat): ?>
            <tr>
              <td><?= htmlspecialchars($cat['label']) ?></td>
              <td><?= htmlspecialchars($cat['detail']) ?></td>
              <td class="num"><?= number_format($l['price_map'][$key] ?? 0, 0, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!empty($l['price_map']['harga_unit'])): ?>
            <tr><td>Harga Unit (lama)</td><td>—</td><td class="num"><?= number_format($l['price_map']['harga_unit'], 0, ',', '.') ?></td></tr>
          <?php endif; ?>
          <tr class="total-row">
            <td colspan="2">TOTAL HARGA (Frame + QC + Finishing + Komponen + Packaging) / Unit</td>
            <td class="num"><?= number_format($l['price_total'], 0, ',', '.') ?></td>
          </tr>
          <tr class="total-note"><td colspan="3">(dikalikan dengan jumlah qty)</td></tr>
          <tr class="grand-row">
            <td>GRAND TOTAL</td>
            <td class="num"><?= number_format($l['price_total'], 0, ',', '.') ?> &times; <?= rtrim(rtrim(number_format((float) $l['qty'], 2, ',', '.'), '0'), ',') ?> UNITS</td>
            <td class="num"><?= number_format($l['grand_total'], 0, ',', '.') ?></td>
          </tr>
          <tr class="timeline-row">
            <td>8. Timeline Selesai</td>
            <td colspan="2"><?= $l['timeline_pabrik'] ? htmlspecialchars(format_tanggal_short_ppf($l['timeline_pabrik'])) : '—' ?></td>
          </tr>
        </tbody>
      </table>
      <p class="est-note">(Estimasi …………… Hari Kerja (Mulai dari Approval Gambar &amp; <?= htmlspecialchars($h['dp_terms'] ?: 'DP 50%') ?>))</p>
      <?php if ($l['remark_pabrik']): ?><p class="est-note" style="font-style:normal;">Remark Manufaktur: <?= htmlspecialchars($l['remark_pabrik']) ?></p><?php endif; ?>

      <div class="sign-grid">
        <div><div>Dibuat Oleh,</div><div class="name">&nbsp;</div></div>
        <div><div>Disetujui Oleh (Pelanggan),</div><div class="name">( <?= htmlspecialchars($h['vendor_name']) ?> )</div></div>
      </div>

      <div class="attach-hint">*Attachment file PDF / JPG</div>
      <div class="print-date"><?= htmlspecialchars(date('d-m-y')) ?></div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php foreach ($lines as $l): if (!$l['image_atts']) continue; ?>
    <?php foreach (array_chunk($l['image_atts'], 2) as $chunk): ?>
    <div class="sheet photo-page">
      <div class="photo-page-title">Lampiran Gambar Kerja — <?= htmlspecialchars($l['product_name_snapshot']) ?></div>
      <?php foreach ($chunk as $a): ?>
        <div class="photo-slot"><img src="<?= htmlspecialchars($a['file_path']) ?>" alt=""></div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <div class="no-print"><button onclick="window.print()">Print / Save PDF</button></div>
</body>
</html>
