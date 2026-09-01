<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/upload.php';

$projects = [];
try {
    $projects = db()->query('SELECT * FROM projects ORDER BY created_at DESC')->fetchAll();
} catch (Throwable $e) {
}

$pageTitle = 'Projects';
$pageDescription = 'Portofolio proyek interior & furnishing bespoke yang sudah dikerjakan Svashta Home untuk klien di berbagai lokasi.';
$pageCanonical = SITE_URL . '/projects';
require __DIR__ . '/inc/head.php';
require __DIR__ . '/inc/nav.php';
?>

<section class="text-center py-0" style="background:#fff;">
  <div class="container-fluid">
    <div class="position-relative pt-8 pb-3">
      <h1 class="display-3 fs-7 fw-lighter ls-2 text-dark">PROJECTS</h1>
    </div>
  </div>
</section>

<style>
  .prj-card-body { text-align: center; }
  .prj-card-body .card-title { max-width: 100%; }
  .prj-excerpt {
    font-family: 'Jost', sans-serif; font-weight: 300; font-size: 14px;
    line-height: 1.7; color: #5c564b; max-width: 40ch; margin: 6px auto 0;
  }
</style>

<section class="py-3" style="background:#fff;">
  <div class="container">
    <?php if (!$projects): ?>
      <p class="text-center text-body-secondary">Belum ada proyek dipublikasikan.</p>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($projects as $p): ?>
      <div class="col-md-6">
        <div class="card border-0 h-100">
          <div class="overflow-hidden kb-zoom-hover" style="aspect-ratio:4/3;">
            <a href="/projects/<?= urlencode($p['slug']) ?>"><img class="w-100 h-100" style="object-fit:cover;" src="<?= htmlspecialchars(image_url($p['cover_image'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>" /></a>
          </div>
          <div class="card-body px-0 pt-3 prj-card-body">
            <h5 class="card-title mb-1 fs-1 text-transform-none font-base lh-sm fw-medium"><a class="text-900" href="/projects/<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['name']) ?></a></h5>
            <p class="mb-0 text-body-secondary"><?= htmlspecialchars($p['location']) ?></p>
            <?php if (!empty($p['excerpt'])): ?>
              <p class="prj-excerpt"><?= htmlspecialchars($p['excerpt']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
