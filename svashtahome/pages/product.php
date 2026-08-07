<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/upload.php';
require_once __DIR__ . '/../../shared/helpers.php';
no_cache_headers();

$slug = $_GET['slug'] ?? '';
$product = null;
try {
    $stmt = db()->prepare('SELECT * FROM products WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    if ($product) {
        $g = db()->prepare('SELECT * FROM product_gallery WHERE product_id = ? ORDER BY sort_order');
        $g->execute([$product['id']]);
        $product['gallery'] = $g->fetchAll();
        $h = db()->prepare('SELECT * FROM product_highlights WHERE product_id = ? ORDER BY sort_order');
        $h->execute([$product['id']]);
        $product['highlights'] = $h->fetchAll();

        $ig = db()->prepare('SELECT * FROM product_instagram_posts WHERE product_id = ? ORDER BY sort_order');
        $ig->execute([$product['id']]);
        $product['instagram_posts'] = $ig->fetchAll();

        $related = db()->prepare('SELECT * FROM products WHERE category = ? AND id != ? ORDER BY RAND() LIMIT 4');
        $related->execute([$product['category'], $product['id']]);
        $relatedProducts = $related->fetchAll();
    }
} catch (Throwable $e) {
    // DB belum siap — tampilkan pesan "produk tidak ditemukan" di bawah, bukan error mentah
}

if (!$product) {
    http_response_code(404);
}

$pageTitle = $product['name'] ?? 'Product Not Found';
if ($product) {
    $pageDescription = !empty($product['seo_description'])
        ? $product['seo_description']
        : mb_strimwidth(strip_tags((string) $product['description']), 0, 160, '...');
    if (!empty($product['seo_title'])) $pageTitleOverride = $product['seo_title'];
    $pageImage = image_url($product['cover_image']);
    $pageCanonical = SITE_URL . '/product/' . urlencode($product['slug']);
    $pageOgType = 'product';
}
require __DIR__ . '/inc/head.php';
require __DIR__ . '/inc/nav.php';
?>

<?php if (!$product): ?>
  <section class="py-8 text-center">
    <div class="container">
      <h1 class="fs-3">Produk tidak ditemukan</h1>
      <p class="text-body-secondary">Produk yang Anda cari mungkin sudah dipindahkan atau dihapus.</p>
      <a class="btn btn-outline-dark mt-3" href="/product">Lihat semua produk</a>
    </div>
  </section>
<?php else: ?>

  <style>
    /* Product detail page (full page) — scoped to .pdf-* only */
    .pdf-page { background: #faf8f4; }
    .pdf-hero { padding: 150px 0 40px; text-align: center; background: #faf8f4; }
    @media (max-width: 767.98px) {
      .pdf-hero { padding: 110px 0 0; }
    }
    .pdf-hero .breadcrumb { justify-content: center; }
    .pdf-eyebrow {
      display: block; font-family: 'Jost', sans-serif; font-weight: 500;
      font-size: 12px; letter-spacing: 4px; color: #a8895a; margin-bottom: 10px;
    }
    .pdf-title {
      font-family: 'Cormorant Garamond', serif; font-weight: 500;
      font-size: clamp(30px, 4.5vw, 52px); letter-spacing: 0.3px; line-height: 1.05;
      color: #1c1a17; margin-bottom: 0;
    }
    .pdf-section { padding: 76px 56px 140px; }
    .pdf-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 100px; }
    .pdf-desc {
      font-family: 'Jost', sans-serif; font-weight: 300; font-size: 17px;
      line-height: 1.9; color: #3a362f; max-width: 56ch;
    }
    .pdf-btn {
      display: inline-block; margin-top: 28px; font-family: 'Jost', sans-serif; font-weight: 500;
      text-transform: uppercase; letter-spacing: 3px; font-size: 12px;
      padding: 18px 46px; background: #1c1a17; color: #f6f3ee !important;
      border: none; text-decoration: none; transition: background .3s ease;
    }
    .pdf-btn:hover { background: #a8895a; color: #1c1a17 !important; }
    .pdf-specs { border-left: 1px solid #e2dccd; padding-left: 56px; }
    .pdf-specs-label {
      display: block; font-family: 'Jost', sans-serif; font-weight: 500;
      font-size: 12px; letter-spacing: 3px; color: #8b8578; margin-bottom: 24px;
    }
    .pdf-spec-block { margin-bottom: 40px; }
    .pdf-spec-block:last-child { margin-bottom: 0; }
    .pdf-spec-title {
      font-family: 'Jost', sans-serif; font-weight: 500; font-size: 13px;
      letter-spacing: 3px; color: #1c1a17; margin: 0 0 10px; padding-bottom: 10px;
      border-bottom: 1px solid #e2dccd;
    }
    .pdf-spec-text {
      font-family: 'Jost', sans-serif; font-weight: 300; font-size: 15px;
      line-height: 1.8; color: #5c564b; margin: 0;
    }
    .pdf-gallery .swiper-button-prev, .pdf-gallery .swiper-button-next {
      width: 44px; height: 44px; background: rgba(28,26,23,0.35); border-radius: 50%;
    }
    .pdf-gallery .swiper-button-prev:after, .pdf-gallery .swiper-button-next:after { font-size: 16px; color: #f6f3ee; }
    .pdf-grid.has-ig { grid-template-columns: 1.3fr 1fr 1fr; gap: 60px; }
    .pdf-ig-col { border-left: 1px solid #e2dccd; padding-left: 30px; }
    .pdf-ig-label {
      display: block; font-family: 'Jost', sans-serif; font-weight: 500;
      font-size: 12px; letter-spacing: 3px; color: #8b8578; margin-bottom: 24px;
    }
    .pdf-ig-grid { display: flex; flex-direction: column; gap: 24px; }
    .pdf-ig-grid > * { min-width: 0; max-width: 100%; }
    @media (max-width: 991.98px) {
      .pdf-grid, .pdf-grid.has-ig { grid-template-columns: 1fr; gap: 40px; }
      .pdf-specs, .pdf-ig-col { border-left: none; padding-left: 0; border-top: 1px solid #e2dccd; padding-top: 32px; }
      .pdf-section { padding: 48px 20px 80px; }
    }
  </style>

  <section class="pdf-hero">
    <div class="container">
      <span class="pdf-eyebrow"><?= htmlspecialchars(strtoupper($product['category'])) ?> COLLECTION</span>
      <h1 class="pdf-title">The <?= htmlspecialchars($product['name']) ?></h1>
      <div class="d-flex justify-content-center mt-3">
        <ol class="breadcrumb text-uppercase ls-1 fs--2">
          <li class="breadcrumb-item"><a class="text-700" href="/">Home</a></li>
          <li class="breadcrumb-item active text-700" aria-current="page">Product</li>
          <li class="breadcrumb-item active text-dark" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
        </ol>
      </div>
    </div>
  </section>

  <section class="py-6 pdf-page">
    <div class="container">
      <div class="row">
        <div class="col-12 mb-4">
          <?php if ($product['gallery']): ?>
          <div class="swiper theme-slider pdf-gallery" data-swiper='{"autoplay":{ "delay": 10000 },"spaceBetween":5,"loop":true,"loopedSlides":5,"slideToClickedSlide":true}'>
            <div class="swiper-wrapper">
              <?php foreach ($product['gallery'] as $g): ?>
              <div class="swiper-slide"><img class="img-fluid" src="<?= htmlspecialchars(image_url($g['image_path'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>" /></div>
              <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-nav">
              <div class="swiper-button-prev"></div>
              <div class="swiper-button-next"></div>
            </div>
          </div>
          <?php else: ?>
          <img class="img-fluid w-100" src="<?= htmlspecialchars(image_url($product['cover_image'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
          <?php endif; ?>
        </div>

        <div class="pdf-section w-100">
        <div class="pdf-grid<?= !empty($product['instagram_posts']) ? ' has-ig' : '' ?>">
          <div>
            <?php if ($product['price']): ?>
              <p class="fw-semibold mb-3">Rp <?= number_format((float) $product['price'], 0, ',', '.') ?></p>
            <?php endif; ?>
            <?php if ($product['materials']): ?>
              <p class="text-body-secondary mb-2"><strong>Materials:</strong> <?= htmlspecialchars($product['materials']) ?></p>
            <?php endif; ?>
            <p class="pdf-desc"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
            <a class="pdf-btn" href="/consultation">Enquire About This Piece</a>
          </div>

          <?php if ($product['highlights']): ?>
          <div class="pdf-specs">
            <span class="pdf-specs-label">SPECIFICATIONS</span>
            <?php foreach ($product['highlights'] as $hl): ?>
              <div class="pdf-spec-block">
                <h5 class="pdf-spec-title"><?= htmlspecialchars(strtoupper($hl['label'])) ?></h5>
                <p class="pdf-spec-text"><?= htmlspecialchars($hl['text']) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($product['instagram_posts'])): ?>
          <div class="pdf-ig-col">
            <span class="pdf-ig-label">AS SEEN ON INSTAGRAM</span>
            <div class="pdf-ig-grid">
              <?php foreach ($product['instagram_posts'] as $ig): ?>
                <blockquote class="instagram-media" data-instgrm-permalink="<?= htmlspecialchars($ig['instagram_url']) ?>" data-instgrm-version="14" style="margin:0 auto;"></blockquote>
              <?php endforeach; ?>
            </div>
          </div>
          <script async src="//www.instagram.com/embed.js"></script>
          <?php endif; ?>
        </div>
        </div>
      </div>

      <?php if (!empty($relatedProducts)): ?>
      <div class="row">
        <div class="col-12">
          <hr class="mt-5 mb-4" />
          <h4 class="fw-normal mb-3">You May Also Like</h4>
        </div>
        <?php foreach ($relatedProducts as $rp): ?>
        <div class="col-sm-6 col-lg-3 mb-4 mb-lg-0">
          <div class="card border border-light h-100">
            <div class="overflow-hidden kb-zoom-hover"><a href="/product/<?= urlencode($rp['slug']) ?>"><img class="card-img-top" src="<?= htmlspecialchars(image_thumb_url($rp['cover_image'])) ?>" alt="<?= htmlspecialchars($rp['name']) ?>" /></a></div>
            <div class="card-body bg-light">
              <h5 class="card-title mb-2 fs-0 text-transform-none font-base lh-sm fw-medium"><a class="text-900" href="/product/<?= urlencode($rp['slug']) ?>"><?= htmlspecialchars($rp['name']) ?></a></h5>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
