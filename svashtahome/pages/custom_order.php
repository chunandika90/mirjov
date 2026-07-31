<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $request = trim($_POST['request'] ?? '');

    if ($name === '' || $contact === '' || $request === '') {
        $error = 'Semua field wajib diisi.';
    } else {
        try {
            db()->prepare('INSERT INTO custom_orders (customer_name, contact, request, status) VALUES (?,?,?,?)')
                ->execute([$name, $contact, $request, 'pending']);

            $waMessage = "Halo Svashta Home, saya ingin melakukan Custom Order Furnitur dengan detail:\n\n"
                . "Nama: {$name}\n"
                . "No. WhatsApp/Email: {$contact}\n"
                . "Furnitur yang diinginkan: {$request}\n\n"
                . "Terima kasih";
            header('Location: https://wa.me/6281320300880?text=' . urlencode($waMessage));
            exit;
        } catch (Throwable $e) {
            $error = 'Maaf, terjadi kesalahan. Silakan coba lagi atau hubungi kami lewat WhatsApp.';
            if (DEBUG_MODE) {
                $error .= ' [DEBUG: ' . $e->getMessage() . ']';
            }
        }
    }
}

$pageTitle = 'Custom Order';
$pageDescription = 'Wujudkan furnitur impian Anda bersama Svashta Home — layanan custom order bespoke sesuai kebutuhan & selera Anda.';
$pageCanonical = SITE_URL . '/custom-order';
require __DIR__ . '/inc/head.php';
require __DIR__ . '/inc/nav.php';
?>

<style>
  /* Custom Order page — same tone as product/project (.pdf-*) */
  .pdf-hero { padding: 150px 0 40px; text-align: center; background: #faf8f4; }
  @media (max-width: 767.98px) { .pdf-hero { padding: 110px 0 30px; } }
  .pdf-eyebrow {
    display: block; font-family: 'Jost', sans-serif; font-weight: 500;
    font-size: 12px; letter-spacing: 4px; color: #a8895a; margin-bottom: 10px;
  }
  .pdf-title {
    font-family: 'Cormorant Garamond', serif; font-weight: 500;
    font-size: clamp(30px, 4.5vw, 52px); letter-spacing: 0.3px; line-height: 1.05;
    color: #1c1a17; margin-bottom: 0;
  }
  .pdf-page { background: #faf8f4; }
  .pdf-desc {
    font-family: 'Jost', sans-serif; font-weight: 300; font-size: 17px;
    line-height: 1.9; color: #3a362f;
  }
</style>

<section class="pdf-hero">
  <div class="container">
    <span class="pdf-eyebrow">SVASHTA HOME BESPOKE</span>
    <h1 class="pdf-title">Custom Order</h1>
  </div>
</section>

<section class="py-6 pdf-page">
  <div class="container" style="max-width:600px;">
    <p class="pdf-desc text-center mb-4">Wujudkan furnitur impian Anda bersama Svashta Home.</p>

    <?php if ($success): ?>
      <div class="alert alert-success text-center">Terima kasih! Permintaan custom order Anda sudah kami terima, tim kami akan segera menghubungi Anda.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post" class="row g-3">
        <div class="col-12">
          <label class="form-label">Nama Lengkap</label>
          <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">No. WhatsApp / Email</label>
          <input type="text" name="contact" class="form-control" required value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Ceritakan furnitur yang Anda inginkan</label>
          <textarea name="request" class="form-control" rows="5" required><?= htmlspecialchars($_POST['request'] ?? '') ?></textarea>
        </div>
        <div class="col-12 text-center">
          <button type="submit" class="btn btn-dark px-5">Kirim Permintaan</button>
        </div>
      </form>
    <?php endif; ?>

    <p class="text-center text-body-secondary mt-4">Atau langsung chat kami di
      <a href="https://wa.me/6281320300880" target="_blank">WhatsApp</a>.
    </p>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
