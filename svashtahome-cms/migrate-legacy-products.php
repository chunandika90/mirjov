<?php
/**
 * Migrasi SEKALI JALAN (dengan konfirmasi): baca 66 halaman produk statis
 * lama di svashtahome/pages/gallery_*.html dan import ke tabel `products`
 * (+ product_gallery + product_highlights). Gambar TIDAK di-copy — hanya
 * direferensikan ke lokasi asli di assets/img/...
 *
 * Tanpa konfirmasi, tidak ada apapun yang tersentuh di database — cuma
 * nunjukin ringkasan. Begitu ditekan "Ya, Timpa Ulang", produk yang
 * slug-nya udah ada akan DIUPDATE (gallery & highlight lama dihapus,
 * diisi ulang) — bukan di-skip lagi. Produk yang belum ada, ditambah baru.
 *
 * HAPUS file ini setelah migrasi selesai dan hasilnya sudah dicek di
 * menu Products.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/helpers.php';

require_login();
$pdo = db();

$pagesDir = SITE_DIR_BASE . '/pages';
$files = glob($pagesDir . '/gallery_*.html');

$categoryListingFiles = [
    'gallery_sofa.html', 'gallery_table.html', 'gallery_chair.html', 'gallery_bed.html',
    'gallery_cabinet.html', 'gallery_outdoor.html', 'gallery_collections.html', 'gallery_collaborations.html',
];
$validCategories = ['sofa', 'table', 'chair', 'bed', 'cabinet', 'outdoor', 'collections', 'collaborations'];

function normalize_legacy_image_path(string $src): string
{
    $src = ltrim($src, '/');
    $src = preg_replace('#^(\.\./)+#', '', $src);
    return rtrim(SITE_URL, '/') . '/' . $src;
}

$existingCount = (int) $pdo->query('SELECT COUNT(*) c FROM products')->fetch()['c'];
$confirmed = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes';
$results = null;

if ($confirmed) {
    require_csrf();
    $results = [];

    foreach ($files as $filePath) {
        $filename = basename($filePath);
        if (in_array($filename, $categoryListingFiles, true)) continue;

        if (!preg_match('/^gallery_([a-z]+)_(.+)\.html$/', $filename, $m)) {
            $results[] = ['file' => $filename, 'status' => 'skip', 'note' => 'Nama file tidak sesuai pola gallery_<kategori>_<nama>.html'];
            continue;
        }
        $category = $m[1];
        if (!in_array($category, $validCategories, true)) {
            $results[] = ['file' => $filename, 'status' => 'skip', 'note' => "Kategori '{$category}' tidak dikenali"];
            continue;
        }

        $html = file_get_contents($filePath);
        if ($html === false) {
            $results[] = ['file' => $filename, 'status' => 'error', 'note' => 'Gagal membaca file'];
            continue;
        }

        try {
            $name = null;
            if (preg_match('/<h4 class="fw-normal font-base">(.*?)<\/h4>/s', $html, $mm)) {
                $name = trim(strip_tags($mm[1]));
            }
            if (!$name && preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $mm)) {
                $name = trim(strip_tags($mm[1]));
            }
            if (!$name) {
                $results[] = ['file' => $filename, 'status' => 'skip', 'note' => 'Nama produk tidak ditemukan di HTML'];
                continue;
            }

            $slug = slugify($name);
            $existing = $pdo->prepare('SELECT id FROM products WHERE slug = ?');
            $existing->execute([$slug]);
            $existingRow = $existing->fetch();

            $description = '';
            if (preg_match('/<h4 class="fw-normal font-base">.*?<\/h4>\s*<p>(.*?)<\/p>/s', $html, $mm)) {
                $description = trim(preg_replace('/\s+/', ' ', strip_tags($mm[1])));
            }

            $galleryPaths = [];
            if (preg_match('/swiper theme-slider.*?<div class="swiper-nav">/s', $html, $sliderBlock)) {
                preg_match_all('/<img class="img-fluid" src="([^"]+)"/', $sliderBlock[0], $imgs);
                foreach ($imgs[1] as $src) {
                    $galleryPaths[] = normalize_legacy_image_path($src);
                }
            }
            $coverImage = $galleryPaths[0] ?? null;
            if (!$coverImage) {
                $results[] = ['file' => $filename, 'status' => 'skip', 'note' => 'Tidak ada gambar galeri ditemukan'];
                continue;
            }

            $highlights = [];
            preg_match_all('/data-bs-toggle="collapse" data-bs-target="#spec\d+"[^>]*>\s*([A-Z0-9 &\/\-]+?)\s*<i/s', $html, $labels);
            preg_match_all('/<div class="collapse mt-2" id="spec\d+">\s*<div class="ps-3 text-body-secondary[^"]*">(.*?)<\/div>/s', $html, $texts);
            $labelList = $labels[1] ?? [];
            $textList = $texts[1] ?? [];
            foreach ($labelList as $i => $label) {
                $text = isset($textList[$i]) ? trim(preg_replace('/\s+/', ' ', strip_tags($textList[$i]))) : '';
                if (trim($label) === '' && $text === '') continue;
                $highlights[] = [trim($label), $text];
            }

            $pdo->beginTransaction();
            if ($existingRow) {
                $productId = (int) $existingRow['id'];
                $pdo->prepare('UPDATE products SET name=?, category=?, description=?, cover_image=? WHERE id=?')
                    ->execute([$name, $category, $description, $coverImage, $productId]);
                $pdo->prepare('DELETE FROM product_gallery WHERE product_id=?')->execute([$productId]);
                $pdo->prepare('DELETE FROM product_highlights WHERE product_id=?')->execute([$productId]);
                $verb = 'Updated';
            } else {
                $pdo->prepare('INSERT INTO products (name, slug, category, price, materials, description, cover_image) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$name, $slug, $category, null, null, $description, $coverImage]);
                $productId = (int) $pdo->lastInsertId();
                $verb = 'Imported';
            }

            foreach ($galleryPaths as $order => $path) {
                $pdo->prepare('INSERT INTO product_gallery (product_id, image_path, sort_order) VALUES (?,?,?)')
                    ->execute([$productId, $path, $order]);
            }
            foreach ($highlights as $order => [$label, $text]) {
                $pdo->prepare('INSERT INTO product_highlights (product_id, label, text, sort_order) VALUES (?,?,?,?)')
                    ->execute([$productId, $label, $text, $order]);
            }
            $pdo->commit();

            $results[] = ['file' => $filename, 'status' => 'ok', 'note' => "{$verb}: \"{$name}\" ({$category}) — " . count($galleryPaths) . ' foto, ' . count($highlights) . ' spec'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $results[] = ['file' => $filename, 'status' => 'error', 'note' => $e->getMessage()];
        }
    }
}

$pageTitle = 'Migrasi Produk Lama';
$pageSubtitle = 'Sekali jalan — import 66 halaman produk statis ke database';
$activeNav = 'products';
require __DIR__ . '/includes/header.php';
?>

<?php if ($results !== null): ?>
  <div class="section-card">
    <div class="section-head"><div><h2>Hasil Migrasi</h2><div class="section-hint"><?= count($results) ?> file diproses dari <?= count($files) ?> file gallery_*.html ditemukan.</div></div></div>
    <table class="data-table">
      <thead><tr><th>File</th><th>Status</th><th>Keterangan</th></tr></thead>
      <tbody>
        <?php foreach ($results as $r): ?>
          <tr>
            <td class="num"><?= htmlspecialchars($r['file']) ?></td>
            <td><span class="status-pill <?= $r['status'] === 'ok' ? 'completed' : ($r['status'] === 'error' ? 'pending' : 'in_progress') ?>"><?= strtoupper($r['status']) ?></span></td>
            <td><?= htmlspecialchars($r['note']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:12px; color:var(--ink-muted); margin-top:16px;">⚠️ Setelah dicek hasilnya di menu <a href="products.php">Products</a>, hapus file <code>migrate-legacy-products.php</code> ini dari server.</p>
  </div>
<?php else: ?>
  <div class="section-card">
    <div class="section-head"><div><h2>Konfirmasi Migrasi</h2><div class="section-hint"><?= count($files) ?> file <code>gallery_*.html</code> ditemukan.</div></div></div>
    <?php if ($existingCount > 0): ?>
      <div class="flash error" style="margin-bottom:16px;">
        ⚠️ Sudah ada <strong><?= $existingCount ?> produk</strong> di database. Produk yang nama/slug-nya cocok sama file HTML lama akan <strong>DITIMPA</strong> (galeri & spesifikasi lama dihapus, diisi ulang) — produk lain yang tidak nyambung ke file lama tidak akan disentuh.
      </div>
    <?php else: ?>
      <p style="font-size:13px; color:var(--ink-muted); margin-bottom:16px;">Belum ada produk di database. Migrasi akan mengimport dari 66 halaman produk lama.</p>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="confirm" value="yes">
      <div class="form-actions" style="justify-content:flex-start;">
        <button type="submit" class="btn btn-danger" style="background:var(--danger); color:#fff; border-color:var(--danger);">Ya, <?= $existingCount > 0 ? 'Timpa Ulang' : 'Jalankan Migrasi' ?></button>
        <a class="btn btn-ghost" href="dashboard.php">Batal</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
