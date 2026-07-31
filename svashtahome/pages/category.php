<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/upload.php';
require_once __DIR__ . '/../../shared/helpers.php';
no_cache_headers();

const CATEGORY_LABELS = [
    'sofa' => 'Sofa', 'table' => 'Table', 'chair' => 'Chair', 'bed' => 'Bed',
    'cabinet' => 'Cabinet', 'outdoor' => 'Outdoor', 'collections' => 'Collections', 'collaborations' => 'Collaborations',
];

$cat = $_GET['cat'] ?? '';
if (!isset(CATEGORY_LABELS[$cat])) {
    $cat = '';
}

$products = [];
try {
    if ($cat) {
        $stmt = db()->prepare('SELECT * FROM products WHERE category = ? ORDER BY created_at DESC');
        $stmt->execute([$cat]);
    } else {
        $stmt = db()->query('SELECT * FROM products ORDER BY created_at DESC');
    }
    $products = $stmt->fetchAll();
    $hlStmt = db()->prepare('SELECT * FROM product_highlights WHERE product_id = ? ORDER BY sort_order');
    foreach ($products as &$p) {
        $hlStmt->execute([$p['id']]);
        $p['highlights'] = $hlStmt->fetchAll();
    }
    unset($p);
} catch (Throwable $e) {
    // DB belum siap — grid tampil kosong dengan pesan di bawah
}

$pageTitle = $cat ? CATEGORY_LABELS[$cat] : 'All Products';
$pageDescription = $cat
    ? 'Koleksi ' . strtolower(CATEGORY_LABELS[$cat]) . ' bespoke dari Svashta Home — mebel custom premium buatan tangan pengrajin Indonesia.'
    : 'Jelajahi koleksi lengkap furnitur bespoke Svashta Home — sofa, meja, kursi, tempat tidur, kabinet, dan outdoor furniture custom premium.';
$pageCanonical = SITE_URL . ($cat ? '/product/kategori/' . urlencode($cat) : '/product');
require __DIR__ . '/inc/head.php';
require __DIR__ . '/inc/nav.php';
?>

<section class="text-center py-0" style="background:#faf8f4;">
  <div class="container-fluid">
    <div class="position-relative pt-8 pb-3">
      <h1 class="display-3 fs-7 fw-lighter ls-2 text-dark"><?= htmlspecialchars(strtoupper($pageTitle)) ?></h1>
      <div class="d-flex justify-content-center mt-2">
        <ol class="breadcrumb text-uppercase ls-2">
          <li class="breadcrumb-item"><a class="text-700" href="/">Home</a></li>
          <li class="breadcrumb-item active text-700" aria-current="page">Product</li>
          <li class="breadcrumb-item active text-dark" aria-current="page"><?= htmlspecialchars($pageTitle) ?></li>
        </ol>
      </div>
    </div>
  </div>
</section>

<style>
  /* Product detail panel (thumbnail-grid-content) — scoped to .pdp-* only */
  .pdp-card { background: #f6f3ee !important; color: #1c1a17; }
  .pdp-eyebrow {
    display: block; font-family: 'Jost', sans-serif; font-weight: 500;
    font-size: 10px; letter-spacing: 3px; color: #a8895a; margin-bottom: 6px;
  }
  .pdp-title {
    font-family: 'Cormorant Garamond', serif; font-weight: 500;
    font-size: clamp(18px, 1.7vw, 22px); letter-spacing: 0.3px; color: #1c1a17;
    line-height: 1.1; margin-bottom: 6px; text-transform: none;
  }
  .pdp-img-col { align-self: stretch; }
  .pdp-img-col > div { height: 100%; min-height: 280px; max-height: 66vh; }
  .pdp-img-col img { height: 100%; object-fit: cover; }
  .pdp-price { font-family: 'Jost', sans-serif; font-weight: 400; color: #1c1a17; font-size: 12px; }
  .pdp-desc {
    font-family: 'Jost', sans-serif; font-weight: 300; font-size: 12.5px;
    line-height: 1.5; color: #3a362f; max-width: 52ch;
  }
  .pdp-divider {
    border: none; height: 1px; background: rgba(168, 137, 90, 0.4);
    margin: 10px 0 10px;
  }
  .pdp-spec { margin-bottom: 7px; }
  .pdp-spec-label {
    display: block; font-family: 'Jost', sans-serif; font-weight: 500;
    font-size: 9px; letter-spacing: 2px; color: #8b8578; margin-bottom: 2px;
  }
  .pdp-spec-text {
    font-family: 'Jost', sans-serif; font-weight: 300; font-size: 11.5px;
    line-height: 1.4; color: #3a362f; margin: 0;
  }
  .pdp-btn {
    display: inline-block; font-family: 'Jost', sans-serif; font-weight: 500;
    text-transform: uppercase; letter-spacing: 2px; font-size: 10px;
    padding: 9px 26px; background: #1c1a17; color: #f6f3ee !important;
    border: none; text-decoration: none; transition: background .3s ease;
  }
  .pdp-btn:hover { background: #a8895a; color: #1c1a17 !important; }
  @media (min-width: 992px) {
    .thumbnail-grid-content { max-height: 74vh; overflow-y: auto; }
  }
</style>

<section class="py-3" style="background:#faf8f4;">
  <div class="container">
    <div class="d-flex justify-content-center gap-2 flex-wrap mb-4">
      <a class="btn btn-sm hvr-sweep-top <?= $cat === '' ? 'btn-dark' : 'btn-outline-dark' ?>" href="/product">All</a>
      <?php foreach (CATEGORY_LABELS as $key => $label): ?>
        <a class="btn btn-sm hvr-sweep-top <?= $cat === $key ? 'btn-dark' : 'btn-outline-dark' ?>" href="/product/kategori/<?= $key ?>"><?= strtoupper($label) ?></a>
      <?php endforeach; ?>
    </div>

    <?php if (!$products): ?>
      <p class="text-center text-body-secondary">Belum ada produk di kategori ini.</p>
    <?php else: ?>
    <div class="row g-2 position-relative thumbnail-grid-container" id="selector">
      <?php foreach ($products as $i => $p):
        $prevTarget = $i > 0 ? '#collapse-' . ($i - 1) : '#!';
        $nextTarget = $i < count($products) - 1 ? '#collapse-' . ($i + 1) : '#!';
      ?>
      <div class="col-12 col-sm-6 col-lg-4 thumbnail-grid-item">
        <img class="thumbnail-gridder" src="<?= htmlspecialchars(image_thumb_url($p['cover_image'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $i ?>" aria-expanded="false" aria-controls="collapse-<?= $i ?>" />
        <div class="position-absolute start-0">
          <div class="collapse thumbnail-grid-content" data-bs-parent="#selector" id="collapse-<?= $i ?>">
            <div class="card card-body border-0 pdp-card m-0 px-3 py-2">
              <div class="row align-items-stretch">
                <div class="thumbnail-grid-navigation">
                  <a class="thumbnail-close" href="#!"><i class="fas fa-times"></i></a>
                  <a class="thumbnail-grid-nav prev" href="#!" data-grid-target="<?= $prevTarget ?>" data-thumbnail-grid-nav="data-thumbnail-grid-nav"><i class="fas fa-angle-left"></i></a>
                  <a class="thumbnail-grid-nav next" href="#!" data-grid-target="<?= $nextTarget ?>" data-thumbnail-grid-nav="data-thumbnail-grid-nav"><i class="fas fa-angle-right"></i></a>
                </div>
                <div class="col-7 pdp-img-col">
                  <div class="d-none d-lg-block p-0"><img class="w-100 img-fluid rounded" src="<?= htmlspecialchars(image_url($p['cover_image'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>" /></div>
                </div>
                <div class="col-lg-5">
                  <span class="pdp-eyebrow"><?= htmlspecialchars(strtoupper(CATEGORY_LABELS[$p['category']] ?? $p['category'])) ?> COLLECTION — HANDCRAFTED IN INDONESIA</span>
                  <h5 class="pdp-title">The <?= htmlspecialchars($p['name']) ?></h5>
                  <?php if ($p['price']): ?><p class="pdp-price mb-2">Rp <?= number_format((float) $p['price'], 0, ',', '.') ?></p><?php endif; ?>
                  <p class="pdp-desc"><?= nl2br(htmlspecialchars($p['description'])) ?></p>
                  <?php if ($p['highlights']): ?><hr class="pdp-divider"><?php endif; ?>
                  <?php foreach ($p['highlights'] as $h): ?>
                    <div class="pdp-spec">
                      <span class="pdp-spec-label"><?= htmlspecialchars(strtoupper($h['label'])) ?></span>
                      <p class="pdp-spec-text"><?= htmlspecialchars($h['text']) ?></p>
                    </div>
                  <?php endforeach; ?>
                  <a class="pdp-btn mt-2" href="/product/<?= urlencode($p['slug']) ?>">Discover The <?= htmlspecialchars(strtoupper($p['name'])) ?></a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
