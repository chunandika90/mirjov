<?php
/**
 * Migrasi SEKALI JALAN (dengan konfirmasi): baca 3 halaman blog lama
 * (blog1_post.html, blog2_post.html, blog3_post.html) dan import ke
 * tabel `blog_posts`. Gambar TIDAK di-copy, cuma direferensikan ke lokasi asli.
 *
 * Tanpa konfirmasi, tidak ada apapun yang tersentuh di database. Begitu
 * ditekan "Ya, Timpa Ulang", post yang slug-nya udah ada akan DIUPDATE
 * (galeri lama dihapus, diisi ulang) — bukan di-skip lagi.
 *
 * HAPUS file ini setelah dicek hasilnya.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/helpers.php';

require_login();
$pdo = db();

$pagesDir = SITE_DIR_BASE . '/pages';
$files = ['blog1_post.html', 'blog2_post.html', 'blog3_post.html'];

function normalize_legacy_image_path(string $src): string
{
    $src = ltrim($src, '/');
    $src = preg_replace('#^(\.\./)+#', '', $src);
    return rtrim(SITE_URL, '/') . '/' . $src;
}

$existingCount = (int) $pdo->query('SELECT COUNT(*) c FROM blog_posts')->fetch()['c'];
$confirmed = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes';
$results = null;

if ($confirmed) {
    require_csrf();
    $results = [];

    foreach ($files as $filename) {
        $filePath = $pagesDir . '/' . $filename;
        if (!file_exists($filePath)) {
            $results[] = ['file' => $filename, 'status' => 'skip', 'note' => 'File tidak ditemukan'];
            continue;
        }
        $html = file_get_contents($filePath);

        try {
            if (!preg_match('/<img class="img-fluid rounded" src="([^"]+)" alt="" \/>\s*<h2 class="mt-4 text-transform-none fw-medium">(.*?)<\/h2>/s', $html, $m)) {
                $results[] = ['file' => $filename, 'status' => 'skip', 'note' => 'Judul/cover tidak ditemukan (struktur HTML beda dari pola yang diharapkan)'];
                continue;
            }
            $coverImage = normalize_legacy_image_path($m[1]);
            $title = trim(strip_tags($m[2]));

            $slug = slugify($title);
            $existing = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = ?');
            $existing->execute([$slug]);
            $existingRow = $existing->fetch();

            $content = '';
            if (preg_match('/<\/h2>(.*?)<a href="\.\.\/assets\/img\/blogs\//s', $html, $bodyBlock)) {
                preg_match_all('/<p>(.*?)<\/p>/s', $bodyBlock[1], $paras);
                $lines = array_map(function ($p) { return trim(preg_replace('/\s+/', ' ', strip_tags($p))); }, $paras[1]);
                $lines = array_filter($lines, function ($l) { return $l !== ''; });
                $content = implode("\n\n", $lines);
            }
            $excerpt = mb_substr($content, 0, 200);
            if (mb_strlen($content) > 200) $excerpt .= '…';

            $galleryPaths = [];
            preg_match_all('/data-gallery="posts"><img class="img-fluid rounded[^"]*" src="([^"]+)"/', $html, $imgs);
            foreach ($imgs[1] as $src) {
                $galleryPaths[] = normalize_legacy_image_path($src);
            }

            if ($existingRow) {
                $postId = (int) $existingRow['id'];
                $pdo->prepare('UPDATE blog_posts SET title=?, excerpt=?, content=?, cover_image=? WHERE id=?')
                    ->execute([$title, $excerpt, $content, $coverImage, $postId]);
                $pdo->prepare('DELETE FROM blog_gallery WHERE blog_post_id=?')->execute([$postId]);
                $verb = 'Updated';
            } else {
                $pdo->prepare('INSERT INTO blog_posts (title, slug, excerpt, content, cover_image, published_at) VALUES (?,?,?,?,?,?)')
                    ->execute([$title, $slug, $excerpt, $content, $coverImage, '2025-05-18 00:00:00']);
                $postId = (int) $pdo->lastInsertId();
                $verb = 'Imported';
            }

            foreach ($galleryPaths as $order => $path) {
                $pdo->prepare('INSERT INTO blog_gallery (blog_post_id, image_path, sort_order) VALUES (?,?,?)')
                    ->execute([$postId, $path, $order]);
            }

            $results[] = ['file' => $filename, 'status' => 'ok', 'note' => "{$verb}: \"{$title}\" — " . count($galleryPaths) . ' foto galeri'];
        } catch (Throwable $e) {
            $results[] = ['file' => $filename, 'status' => 'error', 'note' => $e->getMessage()];
        }
    }
}

$pageTitle = 'Migrasi Blog Lama';
$pageSubtitle = 'Sekali jalan — import 3 halaman blog statis ke database';
$activeNav = 'blog';
require __DIR__ . '/includes/header.php';
?>

<?php if ($results !== null): ?>
  <div class="section-card">
    <div class="section-head"><div><h2>Hasil Migrasi</h2></div></div>
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
    <p style="font-size:12px; color:var(--ink-muted); margin-top:16px;">Cek hasilnya di menu <a href="blog.php">Blog</a>, lalu hapus file <code>migrate-legacy-blog.php</code> ini dari server.</p>
  </div>
<?php else: ?>
  <div class="section-card">
    <div class="section-head"><div><h2>Konfirmasi Migrasi</h2></div></div>
    <?php if ($existingCount > 0): ?>
      <div class="flash error" style="margin-bottom:16px;">
        ⚠️ Sudah ada <strong><?= $existingCount ?> post</strong> di database. Post yang judulnya cocok sama file HTML lama akan <strong>DITIMPA</strong> (galeri lama dihapus, diisi ulang).
      </div>
    <?php else: ?>
      <p style="font-size:13px; color:var(--ink-muted); margin-bottom:16px;">Belum ada post di database. Migrasi akan mengimport 3 artikel blog lama.</p>
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
