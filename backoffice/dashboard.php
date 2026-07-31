<?php
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
  <p>Selamat datang di <strong><?= htmlspecialchars($org['legal_name']) ?></strong>.</p>
  <p style="color:var(--ink-muted); font-size:13px;">
    Modul transaksi (Penawaran, Invoicing, PO, SPK, dst.) belum dibangun — ini fondasi
    login &amp; Admin User dulu sesuai konsep di DOKUMENTASI_ARSITEKTUR.md. Kelola anggota
    &amp; hak akses organisasi lewat menu <a href="users.php">Admin User</a> dan
    <a href="roles.php">Roles &amp; Akses</a> di sidebar.
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
