<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';

$admin = require_login();
$pdo = db();
$flash = null;

const OPTION_TABLES = [
    'need' => 'consultation_need_options',
    'for' => 'consultation_for_options',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save_wording': {
                $eyebrow = trim($_POST['eyebrow_text'] ?? '');
                $subtitle = trim($_POST['subtitle_text'] ?? '');
                $title = trim($_POST['title_text'] ?? '');
                if ($eyebrow === '' || $subtitle === '' || $title === '') {
                    throw new RuntimeException('Semua field wording wajib diisi.');
                }
                $pdo->prepare('UPDATE consultation_page SET eyebrow_text=?, subtitle_text=?, title_text=?, updated_by=? WHERE id=1')
                    ->execute([$eyebrow, $subtitle, $title, $admin['id']]);
                $flash = ['ok', 'Wording halaman tersimpan.'];
                break;
            }

            case 'add_option': {
                $group = $_POST['group'] ?? '';
                if (!isset(OPTION_TABLES[$group])) throw new RuntimeException('Grup tidak valid.');
                $label = trim($_POST['label'] ?? '');
                if ($label === '') throw new RuntimeException('Label wajib diisi.');
                $table = OPTION_TABLES[$group];
                $sort = (int) $pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 s FROM $table")->fetch()['s'];
                $pdo->prepare("INSERT INTO $table (label, sort_order) VALUES (?, ?)")->execute([$label, $sort]);
                $flash = ['ok', 'Opsi ditambahkan.'];
                break;
            }

            case 'edit_option': {
                $group = $_POST['group'] ?? '';
                if (!isset(OPTION_TABLES[$group])) throw new RuntimeException('Grup tidak valid.');
                $id = (int) ($_POST['option_id'] ?? 0);
                $label = trim($_POST['label'] ?? '');
                if ($label === '') throw new RuntimeException('Label wajib diisi.');
                $table = OPTION_TABLES[$group];
                $pdo->prepare("UPDATE $table SET label=? WHERE id=?")->execute([$label, $id]);
                $flash = ['ok', 'Opsi diperbarui.'];
                break;
            }

            case 'delete_option': {
                $group = $_POST['group'] ?? '';
                if (!isset(OPTION_TABLES[$group])) throw new RuntimeException('Grup tidak valid.');
                $id = (int) ($_POST['option_id'] ?? 0);
                $table = OPTION_TABLES[$group];
                $pdo->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
                $flash = ['ok', 'Opsi dihapus.'];
                break;
            }
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }

    $_SESSION['flash'] = $flash;
    header('Location: consultation.php');
    exit;
}

start_admin_session();
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$page = $pdo->query('SELECT * FROM consultation_page WHERE id=1')->fetch();
$needOptions = $pdo->query('SELECT * FROM consultation_need_options ORDER BY sort_order')->fetchAll();
$forOptions = $pdo->query('SELECT * FROM consultation_for_options ORDER BY sort_order')->fetchAll();

$pageTitle = 'Consultation';
$pageSubtitle = 'Atur wording & pilihan dropdown di halaman Consultation (dulu Custom Order)';
$activeNav = 'consultation';
require __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?><div class="flash <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<section class="section-card">
  <div class="section-head">
    <div><h2>Wording Halaman</h2><div class="section-hint">Tampil di atas form — eyebrow, subtitle, judul besar</div></div>
  </div>
  <form method="post" class="form-grid" style="max-width:480px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_wording">
    <div class="field"><label>Eyebrow (baris kecil paling atas)</label><input type="text" name="eyebrow_text" required value="<?= htmlspecialchars($page['eyebrow_text']) ?>"></div>
    <div class="field"><label>Subtitle</label><input type="text" name="subtitle_text" required value="<?= htmlspecialchars($page['subtitle_text']) ?>"></div>
    <div class="field"><label>Judul Besar</label><input type="text" name="title_text" required value="<?= htmlspecialchars($page['title_text']) ?>"></div>
    <button class="btn" type="submit" style="align-self:flex-start;">Simpan Wording</button>
  </form>
</section>

<section class="section-card">
  <div class="section-head">
    <div><h2>Dropdown "What I Need"</h2><div class="section-hint"><?= count($needOptions) ?> opsi</div></div>
  </div>
  <?php foreach ($needOptions as $o): ?>
    <div class="item-row">
      <form method="post" style="display:flex; gap:8px; flex:1; align-items:center;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_option">
        <input type="hidden" name="group" value="need">
        <input type="hidden" name="option_id" value="<?= $o['id'] ?>">
        <input type="text" name="label" value="<?= htmlspecialchars($o['label']) ?>" style="flex:1;">
        <button class="btn btn-sm" type="submit">Simpan</button>
      </form>
      <form method="post" onsubmit="return confirm('Hapus opsi &quot;<?= htmlspecialchars(addslashes($o['label'])) ?>&quot;?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_option">
        <input type="hidden" name="group" value="need">
        <input type="hidden" name="option_id" value="<?= $o['id'] ?>">
        <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
      </form>
    </div>
  <?php endforeach; ?>
  <form method="post" class="form-grid" style="max-width:420px; margin-top:14px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_option">
    <input type="hidden" name="group" value="need">
    <div class="field"><label>Opsi Baru</label><input type="text" name="label" required></div>
    <button class="btn" type="submit" style="align-self:flex-start;">+ Tambah Opsi</button>
  </form>
</section>

<section class="section-card">
  <div class="section-head">
    <div><h2>Dropdown "Need For"</h2><div class="section-hint"><?= count($forOptions) ?> opsi</div></div>
  </div>
  <?php foreach ($forOptions as $o): ?>
    <div class="item-row">
      <form method="post" style="display:flex; gap:8px; flex:1; align-items:center;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_option">
        <input type="hidden" name="group" value="for">
        <input type="hidden" name="option_id" value="<?= $o['id'] ?>">
        <input type="text" name="label" value="<?= htmlspecialchars($o['label']) ?>" style="flex:1;">
        <button class="btn btn-sm" type="submit">Simpan</button>
      </form>
      <form method="post" onsubmit="return confirm('Hapus opsi &quot;<?= htmlspecialchars(addslashes($o['label'])) ?>&quot;?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_option">
        <input type="hidden" name="group" value="for">
        <input type="hidden" name="option_id" value="<?= $o['id'] ?>">
        <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
      </form>
    </div>
  <?php endforeach; ?>
  <form method="post" class="form-grid" style="max-width:420px; margin-top:14px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_option">
    <input type="hidden" name="group" value="for">
    <div class="field"><label>Opsi Baru</label><input type="text" name="label" required></div>
    <button class="btn" type="submit" style="align-self:flex-start;">+ Tambah Opsi</button>
  </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
