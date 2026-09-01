<?php
require_once __DIR__ . '/../backoffice-shared/auth.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
require_once __DIR__ . '/../backoffice-shared/label_icons.php';
require_login();
$org = require_org();
require_module_access('manufaktur_label');

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM manufaktur_label WHERE id=? AND organization_id=?');
$stmt->execute([$id, $org['organization_id']]);
$h = $stmt->fetch();
if (!$h) { http_response_code(404); exit('Form Label tidak ditemukan.'); }

$lines = $pdo->prepare('SELECT * FROM manufaktur_label_lines WHERE label_id=? ORDER BY sort_order, id');
$lines->execute([$id]);
$lines = $lines->fetchAll();

// Foto barang (kalau ada) — ambil dari galeri produk (product_photos), fallback ke photo_path lama.
$photoStmt = $pdo->prepare('SELECT file_path FROM product_photos WHERE product_id=? ORDER BY sort_order, id LIMIT 1');
$photoFallbackStmt = $pdo->prepare('SELECT photo_path FROM products WHERE id=?');
foreach ($lines as &$ln) {
    $ln['photo'] = null;
    if ($ln['product_id']) {
        $photoStmt->execute([$ln['product_id']]);
        $row = $photoStmt->fetch();
        if ($row) {
            $ln['photo'] = $row['file_path'];
        } else {
            $photoFallbackStmt->execute([$ln['product_id']]);
            $ln['photo'] = $photoFallbackStmt->fetch()['photo_path'] ?? null;
        }
    }
}
unset($ln);

$expandedLines = [];
foreach ($lines as $ln) {
    $labelCount = max(1, (int) ($ln['koli'] ?? 1));
    for ($n = 0; $n < $labelCount; $n++) {
        $expandedLines[] = $ln;
    }
}

const LABEL_PER_PAGE = 6;
$pages = array_chunk($expandedLines, LABEL_PER_PAGE);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Label <?= htmlspecialchars($h['doc_number']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Times New Roman', Georgia, serif; font-size: 12px; color: #000; background: #ececec; margin: 0; padding: 20px 0; }
  .page { width: 740px; margin: 0 auto 20px; background: #fff; border: 1px solid #000; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: repeat(3, 1fr); }
  .label-cell { border-right: 1px solid #000; border-bottom: 1px solid #000; padding: 16px 18px; min-height: 175px; }
  .grid .label-cell:nth-child(2n) { border-right: none; }
  .grid .label-cell:nth-last-child(-n+2) { border-bottom: none; }

  .label-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
  .label-head .brand { display: flex; align-items: center; gap: 8px; }
  .label-head .brand img { width: 30px; height: 30px; object-fit: contain; }
  .label-head .brand b { font-size: 13px; }
  .label-head .datecode { font-size: 12px; }

  .label-body { display: flex; gap: 12px; }
  .label-fields { flex: 1; }
  .label-photo { width: 80px; height: 80px; border: 1px solid #000; object-fit: cover; flex-shrink: 0; }
  .label-photo-empty { width: 80px; height: 80px; border: 1px dashed #999; flex-shrink: 0; }
  .label-photo-wrap { flex-shrink: 0; display: flex; flex-direction: column; align-items: center; gap: 5px; }
  .label-handling-icons { display: flex; gap: 3px; }
  .label-handling-icons svg { width: 26px; height: 26px; color: #9c3f37; }

  .label-field { margin-bottom: 6px; font-size: 12.5px; }
  .label-field .k { display: inline-block; width: 78px; }

  .label-sign { margin-top: 16px; font-size: 12.5px; }
  .label-sign .line { display: block; margin-top: 30px; border-top: 1px solid #000; width: 140px; padding-top: 3px; }

  .no-print { width: 740px; margin: 10px auto; text-align: center; }
  @page { size: A4 portrait; margin: 10mm; }
  @media print {
    body { background: #fff; padding: 0; }
    .page { border: none; width: auto; margin: 0; }
    .page + .page { page-break-before: always; }
    .no-print { display: none; }
  }
</style>
</head>
<body>
  <?php foreach ($pages as $page): ?>
    <div class="page">
      <div class="grid">
        <?php foreach ($page as $ln): ?>
          <div class="label-cell">
            <div class="label-head">
              <div class="brand">
                <img src="assets/img/logo-mirjov.png" alt="Mirjov">
                <b>MJT</b>
              </div>
              <div class="datecode"><?= htmlspecialchars(date('d', strtotime($h['tanggal']))) ?> / <?= htmlspecialchars(date('m', strtotime($h['tanggal']))) ?> / <?= htmlspecialchars(date('Y', strtotime($h['tanggal']))) ?></div>
            </div>
            <div class="label-body">
              <div class="label-fields">
                <div class="label-field"><span class="k">NAMA ITEM</span>: <?= htmlspecialchars($ln['product_name_snapshot']) ?><?= $ln['item_code'] ? ' (' . htmlspecialchars($ln['item_code']) . ')' : '' ?></div>
                <div class="label-field"><span class="k">UKURAN</span>: <?= $ln['ukuran'] ? htmlspecialchars($ln['ukuran']) : '' ?></div>
                <div class="label-field"><span class="k">TUJUAN</span>: <?= $ln['tujuan'] ? htmlspecialchars($ln['tujuan']) : '' ?></div>
              </div>
              <div class="label-photo-wrap">
                <?php if ($ln['photo']): ?>
                  <img class="label-photo" src="<?= htmlspecialchars($ln['photo']) ?>" alt="">
                <?php else: ?>
                  <div class="label-photo-empty"></div>
                <?php endif; ?>
                <?php label_handling_icons(); ?>
              </div>
            </div>
            <div class="label-sign">
              PEMBUAT,
              <div class="line">( <?= $ln['pembuat'] ? htmlspecialchars($ln['pembuat']) : '' ?> )</div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php for ($i = count($page); $i < LABEL_PER_PAGE; $i++): ?>
          <div class="label-cell"></div>
        <?php endfor; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$pages): ?><div class="page"><div style="padding:20px;">Belum ada label.</div></div><?php endif; ?>

  <div class="no-print"><button onclick="window.print()">Print / Save PDF</button></div>
</body>
</html>
