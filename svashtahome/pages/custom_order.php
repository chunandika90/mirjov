<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';

$success = false;
$error = '';

$needOptions = [];
$forOptions = [];
$pageContent = ['eyebrow_text' => 'SVASHTA HOME BESPOKE FINE FURNISHINGS', 'subtitle_text' => 'I WANT TO BOOK FOR', 'title_text' => 'A Consultation'];
try {
    $needOptions = db()->query('SELECT label FROM consultation_need_options ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN);
    $forOptions = db()->query('SELECT label FROM consultation_for_options ORDER BY sort_order')->fetchAll(PDO::FETCH_COLUMN);
    $row = db()->query('SELECT eyebrow_text, subtitle_text, title_text FROM consultation_page WHERE id=1')->fetch();
    if ($row) $pageContent = $row;
} catch (Throwable $e) {
    // Tabel belum ada / DB belum siap — fallback ke default di atas
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $needCategory = trim($_POST['need_category'] ?? '');
    $needFor = trim($_POST['need_for'] ?? '');
    $request = trim($_POST['request'] ?? '');

    try {
        db()->prepare('INSERT INTO custom_orders (customer_name, location, contact, need_category, need_for, request, status) VALUES (?,?,?,?,?,?,?)')
            ->execute([$name, $location ?: null, $contact, $needCategory ?: null, $needFor ?: null, $request, 'pending']);

        $waLines = ['Halo Svashta Home, saya ingin melakukan konsultasi dengan detail:', ''];
        if ($name !== '') $waLines[] = "Nama: {$name}";
        if ($location !== '') $waLines[] = "Lokasi: {$location}";
        if ($contact !== '') $waLines[] = "No. WhatsApp: {$contact}";
        if ($needCategory !== '') $waLines[] = "Yang dibutuhkan: {$needCategory}";
        if ($needFor !== '') $waLines[] = "Untuk: {$needFor}";
        if ($request !== '') {
            $waLines[] = '';
            $waLines[] = "Detail: {$request}";
        }
        $waLines[] = '';
        $waLines[] = 'Terima kasih';

        header('Location: https://wa.me/6281320300880?text=' . urlencode(implode("\n", $waLines)));
        exit;
    } catch (Throwable $e) {
        $error = 'Maaf, terjadi kesalahan. Silakan coba lagi atau hubungi kami lewat WhatsApp.';
        if (DEBUG_MODE) {
            $error .= ' [DEBUG: ' . $e->getMessage() . ']';
        }
    }
}

$pageTitle = 'Consultation';
$pageDescription = 'Booking konsultasi bespoke furniture bersama Svashta Home — ceritakan kebutuhan Anda, tim kami akan segera menghubungi.';
$pageCanonical = SITE_URL . '/consultation';
require __DIR__ . '/inc/head.php';
require __DIR__ . '/inc/nav.php';
?>

<style>
  /* Consultation page — same tone as product/project (.pdf-*) */
  .pdf-hero { padding: 150px 0 6px; text-align: center; background: #faf8f4; }
  @media (max-width: 767.98px) { .pdf-hero { padding: 110px 0 4px; } }
  .pdf-eyebrow {
    display: block; font-family: 'Jost', sans-serif; font-weight: 500;
    font-size: clamp(18px, 2.6vw, 27px); letter-spacing: 3px; color: #a8895a; margin-bottom: 14px;
  }
  @media (max-width: 575.98px) { .pdf-eyebrow { letter-spacing: 1.5px; } }
  .pdf-subtitle {
    display: block; font-family: 'Jost', sans-serif; font-weight: 400;
    font-size: 14px; letter-spacing: 3px; color: #3a362f; margin-bottom: 6px;
    text-transform: uppercase;
  }
  .pdf-title {
    font-family: 'Cormorant Garamond', serif; font-weight: 500;
    font-size: clamp(30px, 4.5vw, 52px); letter-spacing: 0.3px; line-height: 1.05;
    color: #1c1a17; margin-bottom: 0;
  }
  .pdf-page { background: #faf8f4; }
  .pdf-form-section { padding: 6px 0 60px; }
  @media (max-width: 767.98px) { .pdf-form-section { padding: 4px 0 40px; } }
  .pdf-desc {
    font-family: 'Jost', sans-serif; font-weight: 300; font-size: 17px;
    line-height: 1.9; color: #3a362f;
  }
</style>

<section class="pdf-hero">
  <div class="container">
    <span class="pdf-eyebrow"><?= htmlspecialchars($pageContent['eyebrow_text']) ?></span>
    <span class="pdf-subtitle"><?= htmlspecialchars($pageContent['subtitle_text']) ?></span>
    <h1 class="pdf-title"><?= htmlspecialchars($pageContent['title_text']) ?></h1>
  </div>
</section>

<section class="pdf-form-section pdf-page">
  <div class="container" style="max-width:600px;">
    <?php if ($success): ?>
      <div class="alert alert-success text-center">Terima kasih! Permintaan konsultasi Anda sudah kami terima, tim kami akan segera menghubungi Anda.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" class="row g-3">
        <div class="col-12">
          <label class="form-label">Name</label>
          <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">My WhatsApp Number</label>
          <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">What I Need</label>
          <select name="need_category" class="form-select">
            <option value="">Choose</option>
            <?php foreach ($needOptions as $opt): ?>
              <option value="<?= htmlspecialchars($opt) ?>" <?= ($_POST['need_category'] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Need For</label>
          <select name="need_for" class="form-select">
            <option value="">Choose</option>
            <?php foreach ($forOptions as $opt): ?>
              <option value="<?= htmlspecialchars($opt) ?>" <?= ($_POST['need_for'] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Tell Us More About Your Need</label>
          <textarea name="request" class="form-control" rows="5"><?= htmlspecialchars($_POST['request'] ?? '') ?></textarea>
        </div>
        <div class="col-12 text-center">
          <button type="submit" class="btn btn-dark px-5">Request Consultation</button>
        </div>
      </form>
    <?php endif; ?>

    <p class="text-center text-body-secondary mt-4">Or kindly contact us via
      <a href="https://wa.me/6281320300880" target="_blank">WhatsApp</a>.
    </p>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
