<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';

$currentAdmin = require_login();
$pdo = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_admin': {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                if (strlen($username) < 3) throw new RuntimeException('Username minimal 3 karakter.');
                if (strlen($password) < 8) throw new RuntimeException('Password minimal 8 karakter.');

                $existing = $pdo->prepare('SELECT id FROM admin_users WHERE username = ?');
                $existing->execute([$username]);
                if ($existing->fetch()) throw new RuntimeException('Username sudah dipakai.');

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')->execute([$username, $hash]);
                $flash = ['ok', "Admin \"{$username}\" berhasil ditambahkan."];
                break;
            }

            case 'delete_admin': {
                $id = (int) ($_POST['admin_id'] ?? 0);
                if ($id === (int) $currentAdmin['id']) {
                    throw new RuntimeException('Tidak bisa menghapus akun yang sedang lu pakai sendiri.');
                }
                $total = (int) $pdo->query('SELECT COUNT(*) c FROM admin_users')->fetch()['c'];
                if ($total <= 1) {
                    throw new RuntimeException('Tidak bisa menghapus — minimal harus ada 1 admin tersisa.');
                }
                $pdo->prepare('DELETE FROM admin_users WHERE id=?')->execute([$id]);
                $flash = ['ok', 'Admin dihapus.'];
                break;
            }
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }

    $_SESSION['flash'] = $flash;
    header('Location: admins.php');
    exit;
}

start_admin_session();
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$admins = $pdo->query('SELECT id, username, created_at FROM admin_users ORDER BY created_at')->fetchAll();

$pageTitle = 'Admin Users';
$pageSubtitle = 'Kelola siapa aja yang bisa login ke CMS ini';
$activeNav = 'admins';
require __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?><div class="flash <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<section class="section-card">
  <div class="section-head">
    <div><h2>Daftar Admin</h2><div class="section-hint"><?= count($admins) ?> akun terdaftar</div></div>
  </div>
  <?php foreach ($admins as $a): ?>
    <div class="item-row">
      <div class="thumb round" style="display:flex; align-items:center; justify-content:center; background:var(--ink); color:#fff; font-weight:700;"><?= htmlspecialchars(strtoupper(substr($a['username'], 0, 1))) ?></div>
      <div class="meta">
        <div class="t"><?= htmlspecialchars($a['username']) ?><?= (int) $a['id'] === (int) $currentAdmin['id'] ? ' <span style="color:var(--ink-muted); font-weight:400;">(ini lu)</span>' : '' ?></div>
        <div class="d">Dibuat <?= htmlspecialchars(date('d M Y', strtotime($a['created_at']))) ?></div>
      </div>
      <div class="row-actions">
        <?php if ((int) $a['id'] !== (int) $currentAdmin['id']): ?>
          <form method="post" onsubmit="return confirm('Hapus admin &quot;<?= htmlspecialchars(addslashes($a['username'])) ?>&quot;?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_admin">
            <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">DELETE</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<section class="section-card">
  <div class="section-head"><div><h2>Tambah Admin Baru</h2></div></div>
  <form method="post" class="form-grid" style="max-width:360px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_admin">
    <div class="field"><label>Username</label><input type="text" name="username" required minlength="3"></div>
    <div class="field"><label>Password</label><input type="password" name="password" required minlength="8"></div>
    <button class="btn" type="submit" style="align-self:flex-start;">+ Tambah Admin</button>
  </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
