<?php
/**
 * Migrasi SEKALI JALAN (dengan konfirmasi): baca halaman proyek lama
 * (projects_*.html) dan import ke tabel `projects`. Cuma nama + galeri foto
 * yang dimigrasi — field location/collection/story di HTML asli isinya
 * masih placeholder bawaan template, jadi sengaja dikosongin, isi manual
 * lewat menu Projects.
 *
 * Tanpa konfirmasi, tidak ada apapun yang tersentuh di database. Begitu
 * ditekan "Ya, Timpa Ulang", proyek yang slug-nya udah ada akan DIUPDATE
 * (nama & galeri ditimpa ulang dari HTML) — tapi location/collection/story
 * yang udah diisi manual TIDAK ikut ditimpa/dikosongkan lagi.
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
$files = glob($pagesDir . '/projects_*.html');

function normalize_legacy_image_path(string $src): string
{
    $src = ltrim($src, '/');
    $src = preg_replace('#^(\.\./)+#', '', $src);
    return rtrim(SITE_URL, '/') . '/' . $src;
}

$existingCount = (int) $pdo->query('SELECT COUNT(*) c FROM projects')->fetch()['c'];
$confirmed = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes';
$results = null;

if ($confirmed) {
    require_csrf();
    $results = [];

    foreach ($files as $filePath) {
        $filename = basename($filePath);
        $html = file_get_contents($filePath);

        try {
            if (!preg_match('/<h1 class="display-3 fs-7 text-white fw-lighter ls-2"[^>]*>(.*?)<\/h1>/s', $html, $m)) {
                $results[] = ['file' => $filename, 'status' => 'skip', 'note' => 'Judul tidak ditemukan'];
                continue;
            }
            $name = trim(preg_replace('/\s+/', ' ', strip_tags($m[1])));

            $slug = slugify($name);
            $existing = $pdo->prepare('SELECT id FROM projects WHERE slug = ?');
            $existing->execute([$slug]);
            $existingRow = $existing->fetch();

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

            if ($existingRow) {
                // location/collection/story SENGAJA tidak ikut di-UPDATE, biar edit manual admin tidak hilang.
                $projectId = (int) $existingRow['id'];
                $pdo->prepare('UPDATE projects SET name=?, cover_image=? WHERE id=?')
                    ->execute([$name, $coverImage, $projectId]);
                $pdo->prepare('DELETE FROM project_gallery WHERE project_id=?')->execute([$projectId]);
                $verb = 'Updated';
            } else {
                $pdo->prepare('INSERT INTO projects (name, slug, location, collection, story, cover_image) VALUES (?,?,?,?,?,?)')
                    ->execute([$name, $slug, '', '', '', $coverImage]);
                $projectId = (int) $pdo->lastInsertId();
                $verb = 'Imported';
            }

            foreach ($galleryPaths as $order => $path) {
                $pdo->prepare('INSERT INTO project_gallery (project_id, image_path, sort_order) VALUES (?,?,?)')
                    ->execute([$projectId, $path, $order]);
            }

            $results[] = ['file' => $filename, 'status' => 'ok', 'note' => "{$verb}: \"{$name}\" — " . count($galleryPaths) . ' foto'];
        } catch (Throwable $e) {
            $results[] = ['file' => $filename, 'status' => 'error', 'note' => $e->getMessage()];
        }
    }
}

$pageTitle = 'Migrasi Proyek Lama';
$pageSubtitle = 'Sekali jalan — import halaman proyek statis ke database';
$activeNav = 'projects';
require __DIR__ . '/includes/header.php';
?>

<?php if ($results !== null): ?>
  <div class="section-card">
    <div class="section-head"><div><h2>Hasil Migrasi</h2><div class="section-hint"><?= count($results) ?> file diproses dari <?= count($files) ?> file projects_*.html ditemukan.</div></div></div>
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
    <p style="font-size:12px; color:var(--ink-muted); margin-top:16px;">Cek hasilnya di menu <a href="projects.php">Projects</a>, lengkapi location/story manual, lalu hapus file <code>migrate-legacy-projects.php</code> ini dari server.</p>
  </div>
<?php else: ?>
  <div class="section-card">
    <div class="section-head"><div><h2>Konfirmasi Migrasi</h2><div class="section-hint"><?= count($files) ?> file <code>projects_*.html</code> ditemukan.</div></div></div>
    <?php if ($existingCount > 0): ?>
      <div class="flash error" style="margin-bottom:16px;">
        ⚠️ Sudah ada <strong><?= $existingCount ?> proyek</strong> di database. Proyek yang namanya cocok sama file HTML lama akan <strong>DITIMPA</strong> (nama & galeri ditimpa ulang) — location/collection/story yang udah diisi manual tetap aman, tidak ikut dihapus.
      </div>
    <?php else: ?>
      <p style="font-size:13px; color:var(--ink-muted); margin-bottom:16px;">Belum ada proyek di database. Migrasi akan mengimport dari halaman proyek lama.</p>
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
