<?php
/**
 * Jalankan SEKALI lewat browser (mis. https://cms.svashtahome.com/setup-create-admin.php)
 * buat bikin akun admin pertama, lalu HAPUS FILE INI dari server.
 * Jangan biarkan file ini nongkrong di server produksi — siapapun yang tau
 * URL-nya bisa bikin akun admin baru selama file ini masih ada.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';

$message = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($username) < 3) {
        $message = 'Username minimal 3 karakter.';
    } elseif (strlen($password) < 8) {
        $message = 'Password minimal 8 karakter.';
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) c FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()['c'] > 0) {
            $message = 'Username sudah dipakai.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')->execute([$username, $hash]);
            $message = 'Admin "' . htmlspecialchars($username) . '" berhasil dibuat.';
            $done = true;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Setup Admin — Svashta Home CMS</title>
<link rel="stylesheet" href="assets/css/admin.css"></head>
<body>
<div class="login-shell">
  <div class="login-card">
    <div class="wordmark">SVASHTA HOME</div>
    <div class="sub">Setup akun admin pertama</div>
    <?php if ($message): ?><div class="flash <?= $done ? 'ok' : 'error' ?>"><?= $message ?></div><?php endif; ?>
    <?php if ($done): ?>
      <p style="font-size:12.5px;">⚠️ <strong>Hapus file <code>setup-create-admin.php</code> ini dari server sekarang</strong>, lalu <a href="login.php">login di sini</a>.</p>
    <?php else: ?>
      <form method="post" class="form-grid">
        <div class="field"><label>Username</label><input type="text" name="username" required></div>
        <div class="field"><label>Password</label><input type="password" name="password" required minlength="8"></div>
        <button class="btn" type="submit" style="width:100%;">Buat Admin</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
