<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/upload.php';

$slug = $_GET['slug'] ?? '';
$post = null;
try {
    $stmt = db()->prepare('SELECT * FROM blog_posts WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    if ($post) {
        $g = db()->prepare('SELECT * FROM blog_gallery WHERE blog_post_id = ? ORDER BY sort_order');
        $g->execute([$post['id']]);
        $post['gallery'] = $g->fetchAll();
    }
} catch (Throwable $e) {
}

if (!$post) http_response_code(404);

$pageTitle = $post['title'] ?? 'Post Not Found';
if ($post) {
    $pageDescription = !empty($post['seo_description'])
        ? $post['seo_description']
        : ($post['excerpt'] ?: mb_strimwidth(strip_tags((string) $post['content']), 0, 160, '...'));
    if (!empty($post['seo_title'])) $pageTitleOverride = $post['seo_title'];
    $pageImage = image_url($post['cover_image']);
    $pageCanonical = SITE_URL . '/blog/' . urlencode($post['slug']);
    $pageOgType = 'article';
}
require __DIR__ . '/inc/head.php';
require __DIR__ . '/inc/nav.php';
?>

<?php if (!$post): ?>
  <section class="py-8 text-center"><div class="container"><h1 class="fs-3">Artikel tidak ditemukan</h1><a class="btn btn-outline-dark mt-3" href="/blog">Kembali ke Blog</a></div></section>
<?php else: ?>
  <style>
    /* Blog detail page — same tone as product/project (.pdf-*), background putih */
    .pdf-hero { padding: 150px 0 40px; text-align: center; background: #fff; }
    @media (max-width: 767.98px) { .pdf-hero { padding: 110px 0 30px; } }
    .pdf-eyebrow {
      display: block; font-family: 'Jost', sans-serif; font-weight: 500;
      font-size: 12px; letter-spacing: 4px; color: #a8895a; margin-bottom: 10px;
    }
    .pdf-title {
      font-family: 'Cormorant Garamond', serif; font-weight: 500;
      font-size: clamp(30px, 4.5vw, 52px); letter-spacing: 0.3px; line-height: 1.05;
      color: #1c1a17; margin-bottom: 0;
    }
    .pdf-page { background: #fff; }
    .pdf-desc {
      font-family: 'Jost', sans-serif; font-weight: 300; font-size: 17px;
      line-height: 1.9; color: #3a362f;
    }
    .pdf-blog-gallery .swiper-button-prev, .pdf-blog-gallery .swiper-button-next {
      width: 44px; height: 44px; background: rgba(28,26,23,0.35); border-radius: 50%;
    }
    .pdf-blog-gallery .swiper-button-prev:after, .pdf-blog-gallery .swiper-button-next:after { font-size: 16px; color: #f6f3ee; }
    .pdf-blog-gallery img { aspect-ratio: 16/9; object-fit: cover; }
  </style>

  <section class="pdf-hero">
    <div class="container">
      <span class="pdf-eyebrow">BLOG — SVASHTA HOME</span>
      <h1 class="pdf-title"><?= htmlspecialchars($post['title']) ?></h1>
      <p class="mt-3 text-uppercase ls-2 fs--1" style="color:#8b8578;"><?= htmlspecialchars(date('d M Y', strtotime($post['published_at'] ?? $post['created_at']))) ?></p>
    </div>
  </section>

  <section class="py-6 pdf-page">
    <div class="container" style="max-width:900px;">
      <?php
      $blogSlides = $post['gallery'] ?: [];
      if (!$blogSlides && $post['cover_image']) {
          $blogSlides = [['image_path' => $post['cover_image']]];
      }
      ?>
      <?php if ($blogSlides): ?>
      <div class="swiper theme-slider pdf-blog-gallery mb-5" data-swiper='{"autoplay":{ "delay": 7000 },"spaceBetween":5,"loop":true,"loopedSlides":5,"slideToClickedSlide":true}'>
        <div class="swiper-wrapper">
          <?php foreach ($blogSlides as $g): ?>
          <div class="swiper-slide"><img class="img-fluid w-100" src="<?= htmlspecialchars(image_url($g['image_path'])) ?>" alt="<?= htmlspecialchars($post['title']) ?>" /></div>
          <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-nav">
          <div class="swiper-button-prev"></div>
          <div class="swiper-button-next"></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="pdf-desc"><?= nl2br(htmlspecialchars($post['content'])) ?></div>

      <hr class="my-5">
      <a class="btn btn-outline-dark" href="/blog">← Back to Blog</a>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
