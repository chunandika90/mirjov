<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/upload.php';

$slug = $_GET['slug'] ?? '';
$project = null;
try {
    $stmt = db()->prepare('SELECT * FROM projects WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $project = $stmt->fetch();
    if ($project) {
        $g = db()->prepare('SELECT * FROM project_gallery WHERE project_id = ? ORDER BY sort_order');
        $g->execute([$project['id']]);
        $project['gallery'] = $g->fetchAll();
    }
} catch (Throwable $e) {
}

if (!$project) http_response_code(404);

$pageTitle = $project['name'] ?? 'Project Not Found';
if ($project) {
    $pageDescription = !empty($project['seo_description'])
        ? $project['seo_description']
        : mb_strimwidth(strip_tags((string) $project['story']), 0, 160, '...');
    if (!empty($project['seo_title'])) $pageTitleOverride = $project['seo_title'];
    $pageImage = image_url($project['cover_image']);
    $pageCanonical = SITE_URL . '/projects/' . urlencode($project['slug']);
    $pageOgType = 'article';
}
require __DIR__ . '/inc/head.php';
require __DIR__ . '/inc/nav.php';
?>

<?php if (!$project): ?>
  <section class="py-8 text-center"><div class="container"><h1 class="fs-3">Proyek tidak ditemukan</h1><a class="btn btn-outline-dark mt-3" href="/projects">Kembali ke Projects</a></div></section>
<?php else: ?>
  <style>
    /* Project detail page — same tone as product/category (.pdp-*/.pdf-*) */
    /* Jarak judul ke foto pertama dirapatkan (catatan Canva hal. 3:
       "judul dan foto pertama terlalu jauh jaraknya ... jarak lbh dekat"). */
    .pdf-hero { padding: 120px 0 10px; background: #fff; }
    .pdf-hero .container, .pdf-hero .pdf-title, .pdf-hero .pdf-meta, .pdf-hero p { text-align: center !important; }
    @media (max-width: 767.98px) { .pdf-hero { padding: 100px 0 14px; } }
    .pdf-eyebrow {
      display: block; font-family: 'Jost', sans-serif; font-weight: 500;
      font-size: 12px; letter-spacing: 4px; color: #a8895a; margin-bottom: 10px;
    }
    .pdf-title {
      font-family: 'Cormorant Garamond', serif; font-weight: 500;
      font-size: clamp(30px, 4.5vw, 52px); letter-spacing: 0.3px; line-height: 1.05;
      color: #1c1a17; margin-bottom: 0;
    }
    .pdf-meta {
      font-family: 'Jost', sans-serif; font-weight: 300; font-size: 15px;
      color: #5c564b; margin: 10px 0 0;
    }
    .pdf-page { background: #fff; }
    .pdf-desc {
      font-family: 'Jost', sans-serif; font-weight: 300; font-size: 17px;
      line-height: 1.9; color: #3a362f;
    }

    .pdf-project-gallery {
      display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;
      margin-top: 10px; background: #fff;
    }
    /* Cerita proyek dibikin rata tengah dan selebar terbatas, biar judul, cerita,
       dan foto pertama terbaca sebagai satu blok. */
    .pdf-page .pdf-desc { text-align: center; max-width: 70ch; margin: 0 auto; }
    .pdf-gallery-item { overflow: hidden; aspect-ratio: 4/5; background: #fff; }
    .pdf-gallery-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pdf-gallery-item.pdf-landscape { grid-column: 1 / -1; aspect-ratio: 16/9; }
    @media (max-width: 767.98px) {
      .pdf-project-gallery { grid-template-columns: 1fr; }
      .pdf-gallery-item.pdf-landscape { grid-column: auto; }
    }
  </style>

  <section class="pdf-hero">
    <div class="container">
      <?php // Susunan mengikuti mockup Canva hal. 3: nama proyek di tengah, lalu
            // baris "Project | tahun" tepat di bawahnya. Eyebrow lama dihapus karena
            // catatan meminta judulnya dipindah ke susunan ini.
            $tahunProyek = date('Y', strtotime($project['created_at'] ?? 'now')); ?>
      <h1 class="pdf-title"><?= htmlspecialchars($project['name']) ?></h1>
      <p class="pdf-meta">Project<?= $project['collection'] ? ' | ' . htmlspecialchars($project['collection']) : '' ?> | <?= $tahunProyek ?></p>
      <?php if ($project['location']): ?>
        <p class="mt-2 text-uppercase ls-2 fs--1" style="color:#8b8578;"><?= htmlspecialchars($project['location']) ?></p>
      <?php endif; ?>
    </div>
  </section>

  <section class="pt-0 pb-4 pdf-page">
    <div class="container" style="max-width:1000px;">
      <?php if (trim((string) $project['story']) !== ''): ?>
        <p class="pdf-desc"><?= nl2br(htmlspecialchars($project['story'])) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($project['gallery']): ?>
    <div class="pdf-project-gallery">
      <?php foreach ($project['gallery'] as $g): ?>
      <div class="pdf-gallery-item"><img src="<?= htmlspecialchars(image_url($g['image_path'])) ?>" alt="<?= htmlspecialchars($project['name']) ?>" loading="lazy"></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="container" style="max-width:1000px;">
      <hr class="my-5">
      <a class="btn btn-outline-dark" href="/projects">← Back to Projects</a>
    </div>
  </section>

  <script>
    (function () {
      // Gambar landscape (lebar > tinggi) otomatis di-span full-width; sisanya
      // (portrait/square) tetap berpasangan 2 kolom, mengikuti orientasi asli tiap foto.
      function classify(img) {
        var item = img.closest('.pdf-gallery-item');
        if (!item || !img.naturalWidth) return;
        if (img.naturalWidth / img.naturalHeight >= 1.2) {
          item.classList.add('pdf-landscape');
        }
      }
      document.querySelectorAll('.pdf-gallery-item img').forEach(function (img) {
        if (img.complete) classify(img);
        else img.addEventListener('load', function () { classify(img); });
      });
    })();
  </script>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
