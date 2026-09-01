<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';

$pageTitle = 'Dashboard';
$pageSubtitle = 'Ringkasan konten Svashta Home';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';

$counts = [
    'products' => (int) db()->query('SELECT COUNT(*) c FROM products')->fetch()['c'],
    'projects' => (int) db()->query('SELECT COUNT(*) c FROM projects')->fetch()['c'],
    'blog_posts' => (int) db()->query('SELECT COUNT(*) c FROM blog_posts')->fetch()['c'],
    'orders' => (int) db()->query('SELECT COUNT(*) c FROM custom_orders')->fetch()['c'],
];
$migrationTools = [
    ['file' => 'migrate-legacy-homepage.php', 'label' => 'Migrasi Konten Homepage', 'desc' => 'Isi hero slides & video section dari index.html asli'],
    ['file' => 'migrate-legacy-products.php', 'label' => 'Migrasi Produk Lama', 'desc' => 'Import 66 halaman produk statis ke database'],
    ['file' => 'migrate-legacy-blog.php', 'label' => 'Migrasi Blog Lama', 'desc' => 'Import 3 halaman blog statis ke database'],
    ['file' => 'migrate-legacy-projects.php', 'label' => 'Migrasi Proyek Lama', 'desc' => 'Import halaman proyek statis ke database'],
    ['file' => 'generate-legacy-redirects.php', 'label' => 'Redirect Halaman Lama', 'desc' => 'Arahkan semua URL statis lama (gallery_*.html dkk) ke halaman baru yang connect CMS'],
];
$availableMigrations = array_filter($migrationTools, function ($m) { return file_exists(__DIR__ . '/' . $m['file']); });
?>

<?php if ($availableMigrations): ?>
<div class="section-card" style="border-color:#0a0a0a;">
  <div class="section-head">
    <div>
      <h2>Setup Sekali Jalan</h2>
      <div class="section-hint">Migrasi konten lama ke database — hapus file-nya dari server setelah dipakai, kartu ini otomatis hilang.</div>
    </div>
  </div>
  <div class="chip-row" style="margin-bottom:0;">
    <?php foreach ($availableMigrations as $m): ?>
      <a class="chip" href="<?= htmlspecialchars($m['file']) ?>" title="<?= htmlspecialchars($m['desc']) ?>"><?= htmlspecialchars($m['label']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="stat-grid">
  <div class="stat-card"><div class="val num"><?= $counts['products'] ?></div><div class="lbl">Total Products</div></div>
  <div class="stat-card"><div class="val num"><?= $counts['projects'] ?></div><div class="lbl">Total Projects</div></div>
  <div class="stat-card"><div class="val num"><?= $counts['blog_posts'] ?></div><div class="lbl">Blog Posts</div></div>
  <div class="stat-card"><div class="val num"><?= $counts['orders'] ?></div><div class="lbl">Custom Orders</div></div>
</div>

<div class="section-card">
  <div class="section-head">
    <div>
      <h2>Semua modul sudah aktif</h2>
      <div class="section-hint">Homepage, Blog, Products, Projects, dan Custom Orders sudah bisa dikelola.</div>
    </div>
  </div>
  <div class="chip-row" style="margin-bottom:0;">
    <a class="chip" href="homepage.php">Homepage</a>
    <a class="chip" href="blog.php">Blog</a>
    <a class="chip" href="products.php">Products</a>
    <a class="chip" href="projects.php">Projects</a>
    <a class="chip" href="orders.php">Custom Orders</a>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
