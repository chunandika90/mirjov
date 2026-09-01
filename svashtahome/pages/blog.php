<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/upload.php';
require_once __DIR__ . '/../../shared/helpers.php';
no_cache_headers();

$posts = [];
try {
    $posts = db()->query('SELECT * FROM blog_posts ORDER BY COALESCE(published_at, created_at) DESC')->fetchAll();
} catch (Throwable $e) {
    // DB belum siap
}

$pageTitle = 'Blog';
$pageDescription = 'Artikel & insight seputar mebel bespoke, material kayu premium, dan gaya hidup interior dari Svashta Home.';
$pageCanonical = SITE_URL . '/blog';
require __DIR__ . '/inc/head.php';
require __DIR__ . '/inc/nav.php';
?>

<section class="text-center py-0 bg-white">
  <div class="container-fluid">
    <div class="position-relative pt-8 pb-3">
      <h1 class="display-3 fs-7 fw-lighter ls-2 text-dark">BLOG</h1>
    </div>
  </div>
</section>

<section class="py-3">
  <div class="container">
    <?php if (!$posts): ?>
      <p class="text-center text-body-secondary">Belum ada artikel.</p>
    <?php else: ?>
    <div class="row g-4">
      <?php foreach ($posts as $p): ?>
      <div class="col-sm-6 col-lg-4">
        <div class="card border border-light h-100">
          <div class="overflow-hidden kb-zoom-hover"><a href="/blog/<?= urlencode($p['slug']) ?>"><img class="card-img-top" src="<?= htmlspecialchars(image_thumb_url($p['cover_image'])) ?>" alt="<?= htmlspecialchars($p['title']) ?>" /></a></div>
          <div class="card-body bg-light">
            <p class="text-body-secondary mb-1 fs--1"><?= htmlspecialchars(date('d M Y', strtotime($p['published_at'] ?? $p['created_at']))) ?></p>
            <h5 class="card-title mb-2 fs-0 text-transform-none font-base lh-sm fw-medium"><a class="text-900" href="/blog/<?= urlencode($p['slug']) ?>"><?= htmlspecialchars($p['title']) ?></a></h5>
            <p class="mb-0 text-body-secondary"><?= htmlspecialchars($p['excerpt']) ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
