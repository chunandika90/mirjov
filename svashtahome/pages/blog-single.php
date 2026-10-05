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

// Foto: satu jadi cover di atas, dua berikutnya jadi kolom kanan di samping teks,
// sisanya (kalau ada) ditaruh di bawah. Cover tidak diulang di kolom kanan.
$gallery = $post['gallery'] ?? [];
$coverImg = $post['cover_image'] ?: ($gallery[0]['image_path'] ?? '');
$sideImages = [];
$restImages = [];
foreach ($gallery as $g) {
    if ($g['image_path'] === $coverImg) continue;
    if (count($sideImages) < 2) $sideImages[] = $g['image_path'];
    else $restImages[] = $g['image_path'];
}
?>

<?php if (!$post): ?>
  <section class="py-8 text-center"><div class="container"><h1 class="fs-3">Artikel tidak ditemukan</h1><a class="btn btn-outline-dark mt-3" href="/blog">Kembali ke Blog</a></div></section>
<?php else: ?>
  <style>
    /* Halaman detail Blog — senada dengan produk/project (.pdf-*), latar putih. */
    .pdf-hero { padding: 130px 0 32px; text-align: center; background: #fff; }
    @media (max-width: 767.98px) { .pdf-hero { padding: 100px 0 24px; } }
    .pdf-eyebrow {
      display: block; font-family: 'Jost', sans-serif; font-weight: 500;
      font-size: 12px; letter-spacing: 4px; color: #a8895a; margin-bottom: 10px;
    }
    /* Judul dikecilkan dari clamp(30px,4.5vw,52px) — di layar lebar tadinya makan
       tiga baris dan menenggelamkan isi artikel. */
    .pdf-title {
      font-family: 'Cormorant Garamond', serif; font-weight: 500;
      font-size: clamp(26px, 2.9vw, 38px); letter-spacing: 0.3px; line-height: 1.2;
      color: #1c1a17; margin: 0 auto; max-width: 30ch;
    }
    .pdf-page { background: #fff; }
    .pdf-desc {
      font-family: 'Jost', sans-serif; font-weight: 300; font-size: 17px;
      line-height: 1.9; color: #3a362f;
    }

    /* Foto TIDAK dipotong lagi: sebelumnya dipaksa aspect-ratio 16/9 + object-fit
       cover, jadi kursi/meja kepotong di atas-bawah. Sekarang ikut rasio aslinya. */
    .pdf-blog-cover img,
    .pdf-blog-side img,
    .pdf-blog-rest img { width: 100%; height: auto; display: block; }
    .pdf-blog-cover { margin-bottom: 56px; }
    .pdf-blog-side img + img { margin-top: 20px; }

    /* Kolom foto ikut turun bareng teks panjang, tapi berhenti menempel di atas. */
    @media (min-width: 992px) { .pdf-blog-side { position: sticky; top: 100px; } }

    .pdf-quote { background: #faf8f5; padding: 64px 0; margin: 64px 0; }
    .pdf-quote blockquote {
      font-family: 'Cormorant Garamond', serif; font-weight: 400; font-style: italic;
      font-size: clamp(21px, 2.3vw, 31px); line-height: 1.5; color: #1c1a17;
      max-width: 760px; margin: 0 auto; text-align: center;
    }
    .pdf-quote cite {
      display: block; margin-top: 22px; font-family: 'Jost', sans-serif; font-style: normal;
      font-weight: 500; font-size: 12px; letter-spacing: 3px; text-transform: uppercase; color: #a8895a;
    }
  </style>

  <section class="pdf-hero">
    <div class="container">
      <span class="pdf-eyebrow">BLOG — SVASHTA HOME</span>
      <h1 class="pdf-title"><?= htmlspecialchars($post['title']) ?></h1>
      <p class="mt-3 mb-0 text-uppercase ls-2 fs--1" style="color:#8b8578;"><?= htmlspecialchars(date('d M Y', strtotime($post['published_at'] ?? $post['created_at']))) ?></p>
    </div>
  </section>

  <section class="pb-6 pdf-page">
    <div class="container" style="max-width:1140px;">

      <?php if ($coverImg): ?>
        <div class="pdf-blog-cover">
          <img src="<?= htmlspecialchars(image_url($coverImg)) ?>" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
        </div>
      <?php endif; ?>

      <div class="row g-5">
        <div class="col-lg-7">
          <div class="pdf-desc"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
        </div>
        <?php if ($sideImages): ?>
          <div class="col-lg-5">
            <div class="pdf-blog-side">
              <?php foreach ($sideImages as $img): ?>
                <img src="<?= htmlspecialchars(image_url($img)) ?>" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($post['quote'])): ?>
      <!-- Ditandai schema.org/Quotation supaya kutipannya kebaca mesin pencari. -->
      <div class="pdf-quote">
        <div class="container">
          <blockquote itemscope itemtype="https://schema.org/Quotation">
            <span itemprop="text">&ldquo;<?= htmlspecialchars($post['quote']) ?>&rdquo;</span>
            <?php if (!empty($post['quote_source'])): ?>
              <cite itemprop="spokenByCharacter"><?= htmlspecialchars($post['quote_source']) ?></cite>
            <?php endif; ?>
          </blockquote>
        </div>
      </div>
    <?php endif; ?>

    <div class="container" style="max-width:1140px;">
      <?php if ($restImages): ?>
        <div class="row g-4 pdf-blog-rest mb-5">
          <?php foreach ($restImages as $img): ?>
            <div class="col-md-6"><img src="<?= htmlspecialchars(image_url($img)) ?>" alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy"></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <hr class="my-5">
      <a class="btn btn-outline-dark" href="/blog">&larr; Back to Blog</a>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
