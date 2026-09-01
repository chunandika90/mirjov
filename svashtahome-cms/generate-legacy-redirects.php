<?php
/**
 * SEKALI JALAN (dengan konfirmasi): scan semua halaman statis lama di
 * svashtahome/pages/ (gallery_*.html, blog*_post.html, projects_*.html,
 * dan halaman listing kategori/blog/projects) lalu tulis 301 redirect
 * ke halaman baru yang connect CMS, ke file svashtahome/pages/.htaccess.
 *
 * Pakai regex parsing YANG SAMA PERSIS dengan migrate-legacy-*.php,
 * jadi slug hasil redirect dijamin cocok sama slug yang ada di database.
 *
 * Redirect ditulis di antara marker "# BEGIN CMS REDIRECTS" dan
 * "# END CMS REDIRECTS" — aman dijalankan berkali-kali (blok lama
 * ditimpa ulang, isi .htaccess di luar blok itu tidak disentuh).
 *
 * HAPUS file ini setelah dicek hasilnya (buka salah satu link lama,
 * pastikan kelempar ke halaman baru).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/helpers.php';

require_login();
$pagesDir = SITE_DIR_BASE . '/pages';
$htaccessPath = $pagesDir . '/.htaccess';

$categoryLabels = ['sofa', 'table', 'chair', 'bed', 'cabinet', 'outdoor', 'collections', 'collaborations'];
$rules = [];
$log = [];

// ---- 1. Kategori listing (gallery_sofa.html dkk) -> category.php?cat= ----
foreach ($categoryLabels as $cat) {
    $file = "gallery_{$cat}.html";
    if (file_exists($pagesDir . '/' . $file)) {
        $rules[] = "Redirect 301 /pages/{$file} /pages/category.php?cat={$cat}";
        $log[] = ['file' => $file, 'target' => "category.php?cat={$cat}"];
    }
}

// ---- 2. Produk individual (gallery_<cat>_<nama>.html) -> product.php?slug= ----
foreach (glob($pagesDir . '/gallery_*.html') as $filePath) {
    $filename = basename($filePath);
    if (!preg_match('/^gallery_([a-z]+)_(.+)\.html$/', $filename, $m)) continue;
    if (!in_array($m[1], $categoryLabels, true)) continue;

    $html = file_get_contents($filePath);
    $name = null;
    if (preg_match('/<h4 class="fw-normal font-base">(.*?)<\/h4>/s', $html, $mm)) {
        $name = trim(strip_tags($mm[1]));
    }
    if (!$name && preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $mm)) {
        $name = trim(strip_tags($mm[1]));
    }
    if (!$name) { $log[] = ['file' => $filename, 'target' => null, 'note' => 'nama tidak ditemukan, dilewati']; continue; }

    $slug = slugify($name);
    $rules[] = "Redirect 301 /pages/{$filename} /pages/product.php?slug={$slug}";
    $log[] = ['file' => $filename, 'target' => "product.php?slug={$slug}"];
}

// ---- 3. Blog (blog.html + blog1/2/3_post.html) ----
if (file_exists($pagesDir . '/blog.html')) {
    $rules[] = 'Redirect 301 /pages/blog.html /pages/blog.php';
    $log[] = ['file' => 'blog.html', 'target' => 'blog.php'];
}
foreach (['blog1_post.html', 'blog2_post.html', 'blog3_post.html'] as $filename) {
    $filePath = $pagesDir . '/' . $filename;
    if (!file_exists($filePath)) continue;
    $html = file_get_contents($filePath);
    if (!preg_match('/<h2 class="mt-4 text-transform-none fw-medium">(.*?)<\/h2>/s', $html, $m)) {
        $log[] = ['file' => $filename, 'target' => null, 'note' => 'judul tidak ditemukan, dilewati'];
        continue;
    }
    $slug = slugify(trim(strip_tags($m[1])));
    $rules[] = "Redirect 301 /pages/{$filename} /pages/blog-single.php?slug={$slug}";
    $log[] = ['file' => $filename, 'target' => "blog-single.php?slug={$slug}"];
}

// ---- 4. Proyek (work-wide.html + projects_*.html) ----
if (file_exists($pagesDir . '/work-wide.html')) {
    $rules[] = 'Redirect 301 /pages/work-wide.html /pages/projects-list.php';
    $log[] = ['file' => 'work-wide.html', 'target' => 'projects-list.php'];
}
foreach (glob($pagesDir . '/projects_*.html') as $filePath) {
    $filename = basename($filePath);
    $html = file_get_contents($filePath);
    if (!preg_match('/<h1 class="display-3 fs-7 text-white fw-lighter ls-2"[^>]*>(.*?)<\/h1>/s', $html, $m)) {
        $log[] = ['file' => $filename, 'target' => null, 'note' => 'judul tidak ditemukan, dilewati'];
        continue;
    }
    $slug = slugify(trim(preg_replace('/\s+/', ' ', strip_tags($m[1]))));
    $rules[] = "Redirect 301 /pages/{$filename} /pages/project-single.php?slug={$slug}";
    $log[] = ['file' => $filename, 'target' => "project-single.php?slug={$slug}"];
}

// ---- 5. Custom order ----
if (file_exists($pagesDir . '/custom_order.html')) {
    $rules[] = 'Redirect 301 /pages/custom_order.html /pages/custom_order.php';
    $log[] = ['file' => 'custom_order.html', 'target' => 'custom_order.php'];
}

$confirmed = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes';
$written = false;
$writeError = null;

if ($confirmed) {
    require_csrf();
    $existing = file_exists($htaccessPath) ? file_get_contents($htaccessPath) : '';
    $existing = preg_replace('/\n?# BEGIN CMS REDIRECTS.*?# END CMS REDIRECTS\n?/s', '', $existing);
    $block = "# BEGIN CMS REDIRECTS\n" . implode("\n", $rules) . "\n# END CMS REDIRECTS\n";
    $newContent = rtrim($existing) . "\n\n" . $block;
    if (file_put_contents($htaccessPath, ltrim($newContent))) {
        $written = true;
    } else {
        $writeError = 'Gagal menulis file .htaccess — cek permission folder pages/ bisa ditulis PHP (biasanya 755).';
    }
}

$pageTitle = 'Redirect Halaman Lama';
$pageSubtitle = 'Sekali jalan — arahkan semua URL statis lama ke halaman baru yang connect CMS';
$activeNav = 'products';
require __DIR__ . '/includes/header.php';
?>

<?php if ($written): ?>
  <div class="flash ok" style="margin-bottom:16px;">✅ <?= count($rules) ?> redirect berhasil ditulis ke <code>pages/.htaccess</code>.</div>
<?php elseif ($writeError): ?>
  <div class="flash error" style="margin-bottom:16px;">❌ <?= htmlspecialchars($writeError) ?></div>
<?php endif; ?>

<div class="section-card">
  <div class="section-head"><div><h2><?= $confirmed && $written ? 'Redirect Ditulis' : 'Konfirmasi' ?></h2><div class="section-hint"><?= count($rules) ?> redirect ditemukan dari <?= count($log) ?> halaman lama.</div></div></div>

  <?php if (!$confirmed): ?>
    <p style="font-size:13px; color:var(--ink-muted); margin-bottom:16px;">
      Ini akan nulis blok <code># BEGIN CMS REDIRECTS ... # END CMS REDIRECTS</code> ke <code>pages/.htaccess</code>.
      Kalau file itu udah ada isi lain (rules lain), isi di luar blok itu <strong>tidak disentuh</strong> — cuma blok redirect ini yang ditimpa tiap dijalankan ulang.
    </p>
  <?php endif; ?>

  <table class="data-table">
    <thead><tr><th>Halaman Lama</th><th>Diarahkan Ke</th></tr></thead>
    <tbody>
      <?php foreach ($log as $r): ?>
        <tr>
          <td class="num"><?= htmlspecialchars($r['file']) ?></td>
          <td class="num"><?= $r['target'] ? htmlspecialchars($r['target']) : '<span style="color:var(--ink-muted);">' . htmlspecialchars($r['note'] ?? 'dilewati') . '</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (!$confirmed): ?>
    <form method="post" style="margin-top:16px;">
      <?= csrf_field() ?>
      <input type="hidden" name="confirm" value="yes">
      <div class="form-actions" style="justify-content:flex-start;">
        <button type="submit" class="btn btn-danger" style="background:var(--danger); color:#fff; border-color:var(--danger);">Ya, Tulis Redirect</button>
        <a class="btn btn-ghost" href="dashboard.php">Batal</a>
      </div>
    </form>
  <?php else: ?>
    <p style="font-size:12px; color:var(--ink-muted); margin-top:16px;">Coba buka salah satu URL lama (mis. <code><?= htmlspecialchars(SITE_URL) ?>/pages/gallery_bed_anang.html</code>) — harusnya otomatis kelempar ke halaman baru. Setelah dicek oke, hapus file <code>generate-legacy-redirects.php</code> ini dari server.</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
