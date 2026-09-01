<?php
require_once __DIR__ . '/../backoffice-shared/auth.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
require_login();
$org = require_org();
require_module_access('manufaktur_surat_jalan');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM manufaktur_surat_jalan WHERE id=? AND organization_id=?');
$stmt->execute([$id, $org['organization_id']]);
$h = $stmt->fetch();
if (!$h) { http_response_code(404); exit('Surat Jalan tidak ditemukan.'); }

$lines = $pdo->prepare('SELECT * FROM manufaktur_surat_jalan_lines WHERE surat_jalan_id=? ORDER BY sort_order, id');
$lines->execute([$id]);
$lines = $lines->fetchAll();

/**
 * Tiap lembar fisik (kertas) dipotong jadi 2 salinan kembar (atas & bawah, isinya
 * SAMA) — biar bisa digunting jadi 2 surat jalan identik. Kalau barangnya kepanjangan
 * buat 1 lembar, lanjut ke lembar berikutnya (nomor tabel lanjut, bukan reset), dan
 * lembar itu juga dapet sepasang salinan kembar lagi. Tanda tangan cuma di lembar terakhir.
 */
const SJ_ROWS_PER_COPY = 10;
$pages = $lines ? array_chunk($lines, SJ_ROWS_PER_COPY) : [[]];
$totalPages = count($pages);
$minRowsLastCopy = 5;

function format_tanggal_indo_sj(?string $dateStr): string
{
    if (!$dateStr) return '—';
    $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($dateStr);
    return (int) date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/** Render 1 salinan (dipanggil 2x per lembar — atas & bawah, isinya identik). */
function render_sj_copy(array $h, array $pageLines, int $startNo, bool $isLastPage, int $pageIndex, int $totalPages): void
{
    $blankRows = $isLastPage ? max(0, $GLOBALS['minRowsLastCopy'] - count($pageLines)) : 0;
    ?>
    <div class="copy">
      <div class="doc-head">
        <div class="brand">
          <img src="assets/img/logo-mirjov.png" alt="Mirjov" style="width:30px; height:30px; object-fit:contain;">
          <div class="org-name">MIRJOV KARUNIA ABADI</div>
        </div>
        <div class="doc-title">
          <h1>Surat Jalan</h1>
          <?php if ($totalPages > 1): ?><div class="page-note">Halaman <?= $pageIndex + 1 ?> dari <?= $totalPages ?></div><?php endif; ?>
        </div>
        <div class="doc-meta-block">
          <div class="row"><span class="k">Tanggal</span><span>:</span><strong><?= htmlspecialchars(format_tanggal_indo_sj($h['tanggal'])) ?></strong></div>
          <div class="row"><span class="k">Nomor</span><span>:</span><strong><?= htmlspecialchars($h['doc_number']) ?></strong></div>
        </div>
      </div>

      <div class="info-row">
        <div class="left">
          <div class="r"><span class="k">Nomor Quotation</span><span>:</span><span class="v"><?= $h['nomor_quotation'] ? htmlspecialchars($h['nomor_quotation']) : '' ?></span></div>
          <div class="r"><span class="k">Nomor Order/PO</span><span>:</span><span class="v"><?= $h['nomor_order_po'] ? htmlspecialchars($h['nomor_order_po']) : '' ?></span></div>
          <div class="r"><span class="k">Nomor Polisi</span><span>:</span><span class="v"><?= $h['nomor_polisi'] ? htmlspecialchars($h['nomor_polisi']) : '' ?></span></div>
        </div>
        <div class="right">
          <div class="r"><span class="k">Kepada</span><span>:</span><span class="v"><?= $h['kepada_snapshot'] ? htmlspecialchars($h['kepada_snapshot']) : '' ?></span></div>
        </div>
      </div>

      <p class="lead-line">Dikirimkan barang - barang sebagai berikut :</p>

      <table class="sj-print-table">
        <thead>
          <tr><th style="width:28px;">No</th><th>Nama Barang</th><th style="width:60px;">Kode</th><th style="width:45px;">Qty</th><th style="width:55px;"></th><th style="width:38px;">Baik</th><th style="width:48px;">Lengkap</th><th>Keterangan</th></tr>
        </thead>
        <tbody>
          <?php foreach ($pageLines as $j => $ln): ?>
            <tr>
              <td class="center"><?= $startNo + $j ?></td>
              <td><?= htmlspecialchars($ln['product_name_snapshot']) ?></td>
              <td class="center"><?= $ln['item_code'] ? htmlspecialchars($ln['item_code']) : '' ?></td>
              <td class="center"><?= $ln['qty'] ? htmlspecialchars($ln['qty']) : '' ?></td>
              <td class="center"></td>
              <td class="center"><?= $ln['baik'] ? '&#10003;' : '' ?></td>
              <td class="center"><?= $ln['lengkap'] ? '&#10003;' : '' ?></td>
              <td><?= $ln['keterangan'] ? htmlspecialchars($ln['keterangan']) : '' ?></td>
            </tr>
          <?php endforeach; ?>
          <?php for ($b = 0; $b < $blankRows; $b++): ?>
            <tr><td class="blank-row">&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
          <?php endfor; ?>
        </tbody>
      </table>

      <?php if ($isLastPage): ?>
        <div class="sign-row">
          <div class="col"><div>Gudang</div><div class="sign-name"><?= htmlspecialchars($h['warehouse_snapshot'] ?? '') ?></div></div>
          <div class="col"><div>Driver</div><div class="sign-name"><?= htmlspecialchars($h['driver_name'] ?? '') ?><?= $h['nomor_polisi'] ? ' (' . htmlspecialchars($h['nomor_polisi']) . ')' : '' ?></div></div>
          <div class="col recv"><div>Diterima Oleh</div><div class="sign-name"><span class="line">&nbsp;</span></div></div>
        </div>
      <?php else: ?>
        <p style="text-align:right; font-size:9.5px; color:#766f60; margin:4px 0 0;">bersambung ke halaman berikutnya...</p>
      <?php endif; ?>

      <?php if (!empty($h['keterangan'])): ?>
        <div class="keterangan-box">
          <div class="k">Keterangan</div>
          <div class="v"><?= nl2br(htmlspecialchars($h['keterangan'])) ?></div>
        </div>
      <?php endif; ?>
    </div>
    <?php
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Surat Jalan <?= htmlspecialchars($h['doc_number']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5px; color: #1c1a17; background: #ececec; margin: 0; padding: 20px 0; }
  .page { max-width: 940px; margin: 0 auto 16px; background: #fff; border: 1px solid #1c1a17; padding: 14px 20px; }
  .copy + .copy { margin-top: 12px; padding-top: 12px; border-top: 1px dashed #999; position: relative; }
  .copy + .copy::before { content: '\2704 gunting di sini'; position: absolute; top: -8px; left: 50%; transform: translateX(-50%); background: #fff; padding: 0 8px; font-size: 8.5px; color: #999; }

  .doc-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
  .doc-head .brand { display: flex; align-items: center; gap: 8px; }
  .doc-head .brand .org-name { font-weight: 700; font-size: 11px; }
  .doc-title { text-align: center; flex: 1; }
  .doc-title h1 { margin: 0; font-size: 20px; letter-spacing: .03em; text-transform: uppercase; }
  .doc-title .page-note { margin-top: 2px; font-size: 9.5px; color: #766f60; font-weight: normal; text-transform: none; letter-spacing: normal; }
  .doc-meta-block { text-align: left; font-size: 10.5px; }
  .doc-meta-block .row { display: flex; gap: 6px; margin-bottom: 2px; }
  .doc-meta-block .row .k { min-width: 44px; }

  .info-row { display: flex; justify-content: space-between; gap: 20px; margin: 6px 0; }
  .info-row .left .r { display: flex; gap: 6px; margin-bottom: 2px; }
  .info-row .left .k { min-width: 82px; }
  .info-row .left .v { border-bottom: 1px solid #1c1a17; min-width: 140px; display: inline-block; }
  .info-row .right { text-align: left; }
  .info-row .right .r { display: flex; gap: 6px; }
  .info-row .right .k { min-width: 46px; }
  .info-row .right .v { border-bottom: 1px solid #1c1a17; min-width: 140px; display: inline-block; font-weight: 700; }

  .lead-line { margin: 6px 0 5px; }

  table.sj-print-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  table.sj-print-table th, table.sj-print-table td { border: 1px solid #1c1a17; padding: 2px 5px; font-size: 10px; }
  table.sj-print-table th { background: #f0ede6; font-size: 9px; text-transform: uppercase; text-align: center; }
  table.sj-print-table td.center { text-align: center; }
  table.sj-print-table td.blank-row { height: 15px; }

  .sign-row { display: flex; justify-content: space-between; margin-top: 4px; }
  .sign-row .col { font-size: 10.5px; }
  .sign-row .col.recv { text-align: right; }
  .sign-name { margin-top: 50px; }
  .sign-name .line { border-bottom: 1px solid #1c1a17; min-width: 140px; display: inline-block; }

  .keterangan-box { margin-top: 8px; border: 1px solid #1c1a17; padding: 4px 6px; min-height: 32px; }
  .keterangan-box .k { font-size: 8.5px; text-transform: uppercase; color: #766f60; margin-bottom: 2px; }
  .keterangan-box .v { font-size: 10px; white-space: pre-wrap; }

  .no-print { max-width: 940px; margin: 10px auto; text-align: center; }
  @page { size: A4 portrait; margin: 10mm; }
  @media print {
    body { background: #fff; padding: 0; }
    .page { border: none; max-width: none; margin: 0; }
    .page + .page { page-break-before: always; }
    .no-print { display: none; }
  }
</style>
</head>
<body>
  <?php foreach ($pages as $pageIndex => $pageLines): ?>
    <?php
    $isLastPage = $pageIndex === $totalPages - 1;
    $startNo = $pageIndex * SJ_ROWS_PER_COPY + 1;
    ?>
    <div class="page">
      <?php render_sj_copy($h, $pageLines, $startNo, $isLastPage, $pageIndex, $totalPages); ?>
      <?php render_sj_copy($h, $pageLines, $startNo, $isLastPage, $pageIndex, $totalPages); ?>
    </div>
  <?php endforeach; ?>

  <div class="no-print"><button onclick="window.print()">Print / Save PDF</button></div>
</body>
</html>
