<?php
/**
 * @var string $pageTitle
 * @var string $pageSubtitle
 * @var string $activeNav one of: dashboard, homepage, blog, products, projects, orders
 */
require_once __DIR__ . '/../../shared/auth.php';
$admin = require_login();

function nav_link(string $key, string $label, string $href, string $active): string
{
    $isActive = $key === $active;
    $classes = 'nav-item' . ($isActive ? ' active' : '');
    return '<a class="' . $classes . '" href="' . htmlspecialchars($href) . '"><span class="dot"></span>' . htmlspecialchars($label) . '</a>';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'Svashta Home CMS') ?> — Svashta Home CMS</title>
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars(SITE_URL) ?>/assets/img/favicons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars(SITE_URL) ?>/assets/img/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= htmlspecialchars(SITE_URL) ?>/assets/img/favicons/favicon-16x16.png">
<link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars(SITE_URL) ?>/assets/img/favicons/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/../assets/css/admin.css') ?: time() ?>">
<script>window.CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;</script>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="wordmark">SVASHTA HOME</div>
    <div class="panel-label">CMS ADMIN PANEL</div>
    <nav class="admin-nav">
      <?= nav_link('dashboard', 'Dashboard', 'dashboard.php', $activeNav ?? '') ?>
      <?= nav_link('homepage', 'Homepage', 'homepage.php', $activeNav ?? '') ?>
      <?= nav_link('blog', 'Blog', 'blog.php', $activeNav ?? '') ?>
      <?= nav_link('products', 'Products', 'products.php', $activeNav ?? '') ?>
      <?= nav_link('projects', 'Projects', 'projects.php', $activeNav ?? '') ?>
      <?= nav_link('orders', 'Custom Orders', 'orders.php', $activeNav ?? '') ?>
      <?= nav_link('admins', 'Admin Users', 'admins.php', $activeNav ?? '') ?>
    </nav>
    <div class="sidebar-foot">
      <div class="avatar"><?= htmlspecialchars(strtoupper(substr($admin['username'], 0, 1))) ?></div>
      <div>
        <div class="name"><?= htmlspecialchars($admin['username']) ?></div>
        <div class="site">svashtahome.com</div>
      </div>
    </div>
  </aside>
  <div class="admin-content">
    <header class="admin-header">
      <div>
        <h1><?= htmlspecialchars($pageTitle ?? '') ?></h1>
        <?php if (!empty($pageSubtitle)): ?><div class="sub"><?= htmlspecialchars($pageSubtitle) ?></div><?php endif; ?>
      </div>
      <a class="btn-outline" href="<?= htmlspecialchars(SITE_URL) ?>" target="_blank">VIEW LIVE SITE ↗</a>
    </header>
    <main class="admin-main">
