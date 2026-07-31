<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/helpers.php';

$admin = require_login();
$pdo = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'reply_ticket') {
        $id = (int) ($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $reply = trim($_POST['admin_reply'] ?? '');
        if (in_array($status, ['pending', 'processed', 'void'], true)) {
            $pdo->prepare('UPDATE custom_orders SET status=?, admin_reply=?, replied_at=NOW(), updated_by=? WHERE id=?')
                ->execute([$status, $reply ?: null, $admin['id'], $id]);
            $flash = ['ok', 'Tiket diperbarui.'];
        }
    } elseif ($action === 'update_status') {
        $id = (int) ($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['pending', 'processed', 'void'], true)) {
            $pdo->prepare('UPDATE custom_orders SET status=?, updated_by=? WHERE id=?')->execute([$status, $admin['id'], $id]);
            $flash = ['ok', 'Status tiket diperbarui.'];
        }
    }
    $_SESSION['flash'] = $flash;
    $returnQs = $_POST['return_qs'] ?? '';
    header('Location: orders.php' . ($returnQs ? '?' . $returnQs : ''));
    exit;
}

start_admin_session();
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

// Filter tanggal report — default Month-to-Date (tgl 1 bulan ini s/d hari ini).
// ?all=1 buat lihat semua tiket tanpa batas tanggal.
$showAll = !empty($_GET['all']);
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$dateWhere = $showAll ? '' : ' WHERE DATE(created_at) BETWEEN ? AND ?';
$dateParams = $showAll ? [] : [$dateFrom, $dateTo];

// Export CSV — jalan sebelum HTML dikirim
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $pdo->prepare('SELECT * FROM custom_orders' . $dateWhere . ' ORDER BY created_at DESC');
    $stmt->execute($dateParams);
    $rows = $stmt->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="custom-orders-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Nama', 'Kontak', 'Request', 'Balasan Admin', 'Tanggal', 'Status']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['id'], $r['customer_name'], $r['contact'], $r['request'], $r['admin_reply'], $r['created_at'], $r['status']]);
    }
    fclose($out);
    exit;
}

$countStmt = function (string $extraWhere = '') use ($pdo, $dateWhere, $dateParams) {
    $where = $dateWhere;
    $params = $dateParams;
    if ($extraWhere !== '') {
        $where = $where === '' ? " WHERE $extraWhere" : "$where AND $extraWhere";
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM custom_orders' . $where);
    $stmt->execute($params);
    return (int) $stmt->fetch()['c'];
};
$counts = [
    'total' => $countStmt(),
    'pending' => $countStmt("status='pending'"),
    'processed' => $countStmt("status='processed'"),
    'void' => $countStmt("status='void'"),
];

$orderSelect = 'SELECT o.*, uu.username AS updated_by_name FROM custom_orders o LEFT JOIN admin_users uu ON uu.id = o.updated_by';

$ordersStmt = $pdo->prepare($orderSelect . ' WHERE 1=1' . str_replace(' WHERE ', ' AND ', $dateWhere) . ' ORDER BY o.created_at DESC');
$ordersStmt->execute($dateParams);
$orders = $ordersStmt->fetchAll();

$ticket = null;
if (!empty($_GET['ticket'])) {
    $stmt = $pdo->prepare($orderSelect . ' WHERE o.id=?');
    $stmt->execute([(int) $_GET['ticket']]);
    $ticket = $stmt->fetch() ?: null;
}

$pageTitle = 'Custom Orders';
$pageSubtitle = 'Tiket permintaan custom order dari customer';
$activeNav = 'orders';
require __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?><div class="flash <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<form method="get" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
  <div style="font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-muted);">
    <?= $showAll ? 'Semua Tiket' : 'Report ' . htmlspecialchars(date('d M Y', strtotime($dateFrom))) . ' – ' . htmlspecialchars(date('d M Y', strtotime($dateTo))) ?>
  </div>
  <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
  <span style="color:var(--ink-muted);">s/d</span>
  <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>">
  <button type="submit" class="btn btn-sm">Filter</button>
  <a class="btn btn-sm btn-ghost" href="orders.php">Bulan Ini</a>
  <a class="btn btn-sm btn-ghost" href="orders.php?all=1">Semua Tiket</a>
</form>

<div class="stat-grid">
  <div class="stat-card"><div class="val num"><?= $counts['total'] ?></div><div class="lbl">Total Tiket</div></div>
  <div class="stat-card"><div class="val num"><?= $counts['pending'] ?></div><div class="lbl">Pending</div></div>
  <div class="stat-card"><div class="val num"><?= $counts['processed'] ?></div><div class="lbl">Processed</div></div>
  <div class="stat-card"><div class="val num"><?= $counts['void'] ?></div><div class="lbl">Void</div></div>
</div>

<div class="section-head" style="margin-bottom:14px;">
  <div></div>
  <a class="btn" href="orders.php?export=csv<?= $showAll ? '&all=1' : '&from=' . urlencode($dateFrom) . '&to=' . urlencode($dateTo) ?>">EXPORT REPORT</a>
</div>

<?php if (!$orders): ?>
  <div class="empty-row">Belum ada custom order masuk.</div>
<?php else: ?>
<div style="overflow-x:auto;">
  <table class="data-table">
    <thead><tr><th>Customer</th><th>Contact</th><th>Request</th><th>Date</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><?= htmlspecialchars($o['customer_name']) ?></td>
          <td><?= htmlspecialchars($o['contact']) ?></td>
          <td style="max-width:280px;"><?= htmlspecialchars(mb_strimwidth($o['request'], 0, 120, '...')) ?></td>
          <td class="num"><?= htmlspecialchars(date('d M Y', strtotime($o['created_at']))) ?></td>
          <td><span class="status-pill <?= $o['status'] ?>"><?= strtoupper($o['status']) ?></span></td>
          <td><a class="btn btn-sm btn-ghost" href="orders.php?ticket=<?= $o['id'] ?>#ticket-modal">BUKA TIKET</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="modal-scrim<?= $ticket ? ' open' : '' ?>" id="ticket-modal">
  <div class="modal-card" style="width:600px;">
    <div class="modal-head">
      <div>
        <h3>Tiket #<?= $ticket['id'] ?? '' ?></h3>
        <?php if ($ticket): ?><?= render_audit_trail(null, null, $ticket['updated_by_name'], $ticket['updated_at']) ?><?php endif; ?>
      </div>
      <button class="modal-close" data-close-modal="ticket-modal">&times;</button>
    </div>
    <?php if ($ticket): ?>
    <form method="post">
      <div class="modal-body form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reply_ticket">
        <input type="hidden" name="order_id" value="<?= $ticket['id'] ?>">
        <input type="hidden" name="return_qs" value="<?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') ?>">

        <div class="field">
          <label>Customer</label>
          <div><?= htmlspecialchars($ticket['customer_name']) ?> — <?= htmlspecialchars($ticket['contact']) ?></div>
        </div>
        <div class="field">
          <label>Request</label>
          <div style="white-space:pre-wrap;"><?= htmlspecialchars($ticket['request']) ?></div>
        </div>
        <div class="field">
          <label>Balasan Admin</label>
          <textarea name="admin_reply" rows="4" placeholder="Tulis jawaban/catatan buat tiket ini..."><?= htmlspecialchars($ticket['admin_reply'] ?? '') ?></textarea>
          <?php if ($ticket['replied_at']): ?>
            <div class="seo-hint">Terakhir dibalas <?= $ticket['updated_by_name'] ? 'oleh ' . htmlspecialchars($ticket['updated_by_name']) . ' ' : '' ?>: <?= htmlspecialchars(date('d M Y H:i', strtotime($ticket['replied_at']))) ?></div>
          <?php endif; ?>
        </div>
        <div class="field">
          <label>Status</label>
          <select name="status">
            <option value="pending" <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="processed" <?= $ticket['status'] === 'processed' ? 'selected' : '' ?>>Processed</option>
            <option value="void" <?= $ticket['status'] === 'void' ? 'selected' : '' ?>>Void</option>
          </select>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close-modal="ticket-modal">Tutup</button>
        <button type="submit" class="btn">Simpan Balasan</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
