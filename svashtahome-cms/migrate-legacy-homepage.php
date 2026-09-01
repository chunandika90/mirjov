<?php
/**
 * Migrasi SEKALI JALAN (dengan konfirmasi): isi CMS dengan konten Homepage
 * yang udah ada di index.html asli (4 hero slide + section video).
 *
 * Gambar TIDAK di-copy — cuma direferensikan ke lokasi asli di assets/img/...
 *
 * Collaborators & Client Reviews SENGAJA tidak dimigrasi — di situs asli
 * itu isinya placeholder duplikat (logo generik yang sama diulang 7x) dan
 * testimonial kosong tanpa teks, jadi tidak ada data asli yang worth dipindah.
 *
 * Begitu dikonfirmasi (tombol "Ya, Timpa Ulang"), hero slides lama DIHAPUS
 * lalu diisi ulang dari index.html, dan video section ditimpa juga —
 * tanpa konfirmasi, tidak ada apapun yang tersentuh di database.
 *
 * HAPUS file ini setelah dicek hasilnya di menu Homepage.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/upload.php';

require_login();
$pdo = db();

$slideCount = (int) $pdo->query('SELECT COUNT(*) c FROM hero_slides')->fetch()['c'];
$video = $pdo->query('SELECT * FROM homepage_video WHERE id = 1')->fetch();
$videoHasContent = $video && ($video['headline'] !== 'Watch our' || $video['slogan'] !== 'video' || $video['youtube_id']);
$hasExisting = $slideCount > 0 || $videoHasContent;

$confirmed = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes';
$log = null;

if ($confirmed) {
    require_csrf();
    $log = [];

    // Hero Slides — hapus semua yang ada, isi ulang dari index.html asli
    $pdo->exec('DELETE FROM hero_slides');
    $base = rtrim(SITE_URL, '/');
    $slides = [
        ['BESPOKE FINE FURNISHINGS', 'WE CRAFTS BESPOKE FINE FURNISHINGS THAT BLEND TIMELESS DESIGN, EXQUISITE MATERIALS, AND REFINED INDONESIAN CRAFTSMANSHIP.', $base . '/assets/img/headers/homepage.jpg'],
        ['INDONESIAN ARTISAN', 'WITH A BLEND OF HERITAGE TECHNIQUES AND MODERN PRECISION, WE HANDCRAFT FURNITURE THAT IS BOTH STRUCTURALLY SOUND AND ARTISTICALLY REFINED.', $base . '/assets/img/headers/homepage2.jpg'],
        ['ARCHITECT OF COMFORT', 'TRUE COMFORT IS NOT JUST FELT — IT IS ENGINEERED.', $base . '/assets/img/headers/homepage4.jpg'],
        ['SUSTAINABLE SOURCING', 'OUR COMMITMENT TO SUSTAINABILITY ENSURES YOUR FURNITURE IS MADE WITH RESPECT FOR NATURE AND FUTURE GENERATIONS.', $base . '/assets/img/headers/homepage5.jpg'],
    ];
    $stmt = $pdo->prepare('INSERT INTO hero_slides (title, subtitle, image_path, sort_order) VALUES (?,?,?,?)');
    foreach ($slides as $i => $s) {
        $stmt->execute([$s[0], $s[1], $s[2], $i]);
    }
    $log[] = ['section' => 'Hero Slides', 'status' => 'ok', 'note' => count($slides) . ' slide diisi ulang (yang lama dihapus dulu).'];

    // Homepage Video — timpa langsung
    $pdo->prepare('UPDATE homepage_video SET headline=?, slogan=?, youtube_id=? WHERE id=1')
        ->execute(['Watch our', 'video', 'jlWMTNZNOc0']);
    $log[] = ['section' => 'Homepage Video', 'status' => 'ok', 'note' => 'Headline/slogan/YouTube ID ditimpa dari index.html.'];
}

$pageTitle = 'Migrasi Konten Homepage';
$pageSubtitle = 'Sekali jalan — isi hero slides & video section dari index.html asli';
$activeNav = 'homepage';
require __DIR__ . '/includes/header.php';
?>

<?php if ($log !== null): ?>
  <div class="section-card">
    <div class="section-head"><div><h2>Hasil Migrasi</h2></div></div>
    <table class="data-table">
      <thead><tr><th>Section</th><th>Status</th><th>Keterangan</th></tr></thead>
      <tbody>
        <?php foreach ($log as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['section']) ?></td>
            <td><span class="status-pill completed">OK</span></td>
            <td><?= htmlspecialchars($r['note']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-size:12px; color:var(--ink-muted); margin-top:16px;">Cek hasilnya di menu <a href="homepage.php">Homepage</a>. Setelah oke, hapus file <code>migrate-legacy-homepage.php</code> ini dari server.</p>
  </div>
<?php else: ?>
  <div class="section-card">
    <div class="section-head"><div><h2>Konfirmasi Migrasi</h2></div></div>
    <?php if ($hasExisting): ?>
      <div class="flash error" style="margin-bottom:16px;">
        ⚠️ Sudah ada data di Homepage sekarang: <strong><?= $slideCount ?> hero slide</strong><?= $videoHasContent ? ' dan video section sudah pernah diisi/diedit' : '' ?>.
        Kalau lanjut, <strong>hero slides yang ada akan DIHAPUS</strong> lalu diisi ulang dari <code>index.html</code> asli, dan video section akan ditimpa.
      </div>
    <?php else: ?>
      <p style="font-size:13px; color:var(--ink-muted); margin-bottom:16px;">Belum ada data di Homepage. Migrasi akan mengisi 4 hero slide + video section dari <code>index.html</code> asli.</p>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="confirm" value="yes">
      <div class="form-actions" style="justify-content:flex-start;">
        <button type="submit" class="btn btn-danger" style="background:var(--danger); color:#fff; border-color:var(--danger);">Ya, <?= $hasExisting ? 'Timpa Ulang' : 'Jalankan Migrasi' ?></button>
        <a class="btn btn-ghost" href="dashboard.php">Batal</a>
      </div>
    </form>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
