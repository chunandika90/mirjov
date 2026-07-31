<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/auth.php';

if (current_admin()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username !== '' && $password !== '' && attempt_login($username, $password)) {
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Username atau password salah.';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Svashta Home CMS</title>
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars(SITE_URL) ?>/assets/img/favicons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars(SITE_URL) ?>/assets/img/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= htmlspecialchars(SITE_URL) ?>/assets/img/favicons/favicon-16x16.png">
<link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars(SITE_URL) ?>/assets/img/favicons/favicon.ico">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <div class="wordmark">SVASHTA HOME</div>
    <div class="sub">CMS ADMIN PANEL</div>
    <?php if ($error): ?><div class="flash error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="form-grid">
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button class="btn" type="submit" style="width:100%;">Masuk</button>
    </form>
  </div>
</div>
</body>
</html>
