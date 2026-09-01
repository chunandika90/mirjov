<?php
/**
 * @var string $pageTitle
 * @var string $activeMenu
 * @var bool $embedMode  set true SEBELUM require ini buat mode popup/iframe (skip sidebar+topbar)
 */
$embedMode = $embedMode ?? false;
require_once __DIR__ . '/../../backoffice-shared/auth.php';
require_once __DIR__ . '/../../backoffice-shared/modules.php';
$user = require_login();
$org = require_org();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'Backoffice') ?> — Wujud ERP</title>
<link rel="apple-touch-icon" sizes="180x180" href="assets/img/favicons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicons/favicon-16x16.png">
<link rel="shortcut icon" type="image/x-icon" href="assets/img/favicons/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css?v=<?= @filemtime(__DIR__ . '/../assets/css/app.css') ?: time() ?>">
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
</head>
<body>
<div class="app-shell">
  <?php if (!$embedMode): ?>
  <aside class="app-sidebar">
    <div class="brand">WUJUD ERP</div>
    <div class="org-name"><?= htmlspecialchars($org['legal_name']) ?></div>
    <nav class="app-nav">
      <a href="dashboard.php" class="<?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>

      <?php if (has_access('kontak') || has_access('penawaran') || has_access('master_barang')): ?>
        <div style="margin:12px 12px 4px; padding-top:16px; border-top:1px solid #3a362f; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:oklch(0.55 0.01 260);">Master Data</div>
      <?php endif; ?>
      <?php if (has_access('kontak')): ?>
        <a href="warehouses.php" class="<?= ($activeMenu ?? '') === 'warehouses' ? 'active' : '' ?>">Master Lokasi</a>
      <?php endif; ?>
      <?php if (has_access('master_barang')): ?>
        <a href="master-barang-kanban.php" class="<?= ($activeMenu ?? '') === 'products' ? 'active' : '' ?>">Master Barang</a>
      <?php endif; ?>
      <?php if (has_access('penawaran')): ?>
        <a href="projects.php" class="<?= ($activeMenu ?? '') === 'projects' ? 'active' : '' ?>">Master Project</a>
      <?php endif; ?>
      <?php if (has_access('kontak')): ?>
        <a href="contacts.php?type=vendor" class="<?= ($activeMenu ?? '') === 'contacts' && ($_GET['type'] ?? '') === 'vendor' ? 'active' : '' ?>">Master Vendor</a>
        <a href="contacts.php?type=customer" class="<?= ($activeMenu ?? '') === 'contacts' && ($_GET['type'] ?? '') === 'customer' ? 'active' : '' ?>">Master Customer</a>
        <a href="contacts.php" class="<?= ($activeMenu ?? '') === 'contacts' && empty($_GET['type']) ? 'active' : '' ?>" style="font-size:12px; opacity:.75;">Kontak (Semua)</a>
        <a href="materials.php" class="<?= ($activeMenu ?? '') === 'materials' ? 'active' : '' ?>" style="font-size:12px; opacity:.75;">Material</a>
        <a href="characteristics.php" class="<?= ($activeMenu ?? '') === 'characteristics' ? 'active' : '' ?>" style="font-size:12px; opacity:.75;">Karakteristik Produk</a>
        <a href="terms.php" class="<?= ($activeMenu ?? '') === 'terms' ? 'active' : '' ?>" style="font-size:12px; opacity:.75;">Syarat &amp; Ketentuan</a>
      <?php endif; ?>
      <?php if (has_access('penawaran')): ?>
        <a href="project-flow.php" class="<?= ($activeMenu ?? '') === 'project_flow' ? 'active' : '' ?>" style="font-size:12px; opacity:.75;">Project Flow</a>
      <?php endif; ?>

      <?php $manufakturLinks = ['manufaktur_penawaran' => ['manufaktur-penawaran.php', 'Form Penawaran Harga'], 'manufaktur_po' => ['manufaktur-po.php', 'Form Product Series'], 'manufaktur_surat_jalan' => ['manufaktur-surat-jalan.php', 'Form Surat Jalan'], 'manufaktur_label' => ['manufaktur-label.php', 'Form Label']]; $manufakturFirst = true; $manufakturVisible = array_filter(array_keys($manufakturLinks), fn($k) => has_access($k)); ?>
      <?php if ($manufakturVisible): ?>
        <div style="margin:12px 12px 4px; padding-top:16px; border-top:1px solid #3a362f; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:oklch(0.55 0.01 260);">Manufaktur</div>
      <?php endif; ?>
      <?php foreach ($manufakturLinks as $mKey => [$mHref, $mLabel]): if (!has_access($mKey)) continue; ?>
        <a href="<?= $mHref ?>" class="<?= ($activeMenu ?? '') === $mKey ? 'active' : '' ?>"><?= htmlspecialchars($mLabel) ?></a>
        <?php $manufakturFirst = false; ?>
      <?php endforeach; ?>
      <?php if ($manufakturVisible && has_access('laporan')): ?>
        <a href="inventory-report.php" class="<?= ($activeMenu ?? '') === 'inventory_report' ? 'active' : '' ?>">Inventory</a>
      <?php endif; ?>
      <?php if (has_access('manufaktur_surat_jalan')): ?>
        <a href="manufaktur-pengeluaran-inventory.php" class="<?= ($activeMenu ?? '') === 'manufaktur_pengeluaran_inventory' ? 'active' : '' ?>">Pengeluaran Inventory</a>
        <a href="manufaktur-saldo-awal.php" class="<?= ($activeMenu ?? '') === 'manufaktur_saldo_awal' ? 'active' : '' ?>">Input Saldo Awal</a>
      <?php endif; ?>

      <?php
      $moduleLinks = [
          'penawaran' => 'quotations.php', 'invoicing' => 'invoices.php', 'po' => 'purchase-orders.php',
          'spk' => 'spk.php', 'penerimaan' => 'goods-receipts.php', 'do' => 'delivery-orders.php',
          'kuitansi' => 'kuitansi.php', 'kontak' => 'contacts.php', 'laporan' => 'laporan.php',
      ];
      $manufakturKeys = ['manufaktur_penawaran', 'manufaktur_po', 'manufaktur_surat_jalan', 'manufaktur_label'];
      $moduleLinksVisible = array_filter(array_keys(MODULES), fn($k) => $k !== 'dashboard' && $k !== 'master_barang' && !in_array($k, $manufakturKeys, true) && has_access($k));
      ?>
      <?php if ($moduleLinksVisible || has_access('kontak') || has_access('master_barang')): ?>
        <div style="margin:12px 12px 4px; padding-top:16px; border-top:1px solid #3a362f; font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:oklch(0.55 0.01 260);">Modul Lain</div>
      <?php endif; ?>
      <?php if (has_access('master_barang')): ?>
        <a href="products.php" class="<?= ($activeMenu ?? '') === 'products_legacy' ? 'active' : '' ?>">Master Barang (Form Lengkap)</a>
      <?php endif; ?>
      <?php foreach (MODULES as $key => $label):
          if ($key === 'dashboard' || $key === 'master_barang' || in_array($key, $manufakturKeys, true) || !has_access($key)) continue;
          $href = $moduleLinks[$key] ?? '#';
      ?>
        <a href="<?= htmlspecialchars($href) ?>" class="<?= ($activeMenu ?? '') === $key ? 'active' : '' ?>"><?= htmlspecialchars($label) ?></a>
        <?php if ($key === 'invoicing' && has_access('po')): ?>
          <a href="material-requests.php" class="<?= ($activeMenu ?? '') === 'material_requests' ? 'active' : '' ?>">Request Material</a>
        <?php endif; ?>
      <?php endforeach; ?>

      <a href="users.php" class="<?= ($activeMenu ?? '') === 'users' ? 'active' : '' ?>" style="margin-top:12px; border-top:1px solid #3a362f; padding-top:16px;">Admin User</a>
      <a href="roles.php" class="<?= ($activeMenu ?? '') === 'roles' ? 'active' : '' ?>">Roles &amp; Akses</a>
    </nav>
  </aside>
  <?php endif; ?>
  <div class="app-main <?= $embedMode ? 'app-main-embed' : '' ?>">
    <?php if (!$embedMode): ?>
    <div class="app-topbar">
      <h1><?= htmlspecialchars($pageTitle ?? '') ?></h1>
      <div class="user">
        <div class="user-name"><?= htmlspecialchars($user['name']) ?> — <span class="pill <?= $org['role_name'] === 'Owner' ? 'owner' : '' ?>"><?= htmlspecialchars($org['role_name']) ?></span></div>
        <div class="user-actions">
          <a href="select-org.php">Ganti Organisasi</a>
          <a href="logout.php" class="btn btn-sm btn-ghost">Logout</a>
        </div>
      </div>
    </div>
    <?php endif; ?>
