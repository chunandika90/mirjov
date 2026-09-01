<?php
$pageTitle = 'Generator Label Stock';
$activeMenu = 'manufaktur_label';
require __DIR__ . '/includes/header.php';
require_module_access('manufaktur_label');

$pdo = db();
$flash = null;

function next_manufaktur_label_number(PDO $pdo, int $organizationId): string
{
    $year = (int) date('Y');
    $month = (int) date('n');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT last_number FROM doc_counters WHERE organization_id=? AND doc_type=? AND year=? FOR UPDATE');
        $stmt->execute([$organizationId, 'LBL', $year]);
        $row = $stmt->fetch();
        if ($row) {
            $next = (int) $row['last_number'] + 1;
            $pdo->prepare('UPDATE doc_counters SET last_number=? WHERE organization_id=? AND doc_type=? AND year=?')
                ->execute([$next, $organizationId, 'LBL', $year]);
        } else {
            $next = 1;
            $pdo->prepare('INSERT INTO doc_counters (organization_id, doc_type, year, last_number) VALUES (?,?,?,?)')
                ->execute([$organizationId, 'LBL', $year, $next]);
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTransaction) $pdo->rollBack();
        throw $e;
    }
    return sprintf('LBL/%d/%02d/%04d', $year, $month, $next);
}

function touch_label_header(PDO $pdo, int $headerId, int $userId): void
{
    $pdo->prepare('UPDATE manufaktur_label SET updated_by=?, updated_at=NOW() WHERE id=?')->execute([$userId, $headerId]);
}

function find_or_create_label_product(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO products (organization_id, name, unit) VALUES (?,?,'pcs')")->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

function save_label_lines(PDO $pdo, int $headerId, array $lines): int
{
    $stmt = $pdo->prepare('INSERT INTO manufaktur_label_lines (label_id, product_id, product_name_snapshot, item_code, ukuran, koli, tujuan, pembuat, sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
    global $org;
    $count = 0;
    foreach ($lines as $i => $line) {
        $name = trim($line['product_name'] ?? '');
        if ($name === '') continue;
        $productId = find_or_create_label_product($pdo, $org['organization_id'], $name);
        $itemCode = trim($line['item_code'] ?? '') ?: null;
        $ukuran = trim($line['ukuran'] ?? '') ?: null;
        $koli = trim($line['koli'] ?? '') ?: null;
        $tujuan = trim($line['tujuan'] ?? '') ?: null;
        $pembuat = trim($line['pembuat'] ?? '') ?: null;
        $stmt->execute([$headerId, $productId, $name, $itemCode, $ukuran, $koli, $tujuan, $pembuat, $count]);
        $count++;
    }
    return $count;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_manufaktur_label') {
            require_module_access('manufaktur_label', 'can_create');
            $tanggal = $_POST['tanggal'] ?? '';
            $lines = $_POST['lines'] ?? [];
            if (!$tanggal) throw new RuntimeException('Tanggal wajib diisi.');
            if (!$lines) throw new RuntimeException('Minimal 1 label.');

            $pdo->beginTransaction();
            try {
                $docNumber = next_manufaktur_label_number($pdo, $org['organization_id']);
                $pdo->prepare('INSERT INTO manufaktur_label (organization_id, doc_number, tanggal, created_by) VALUES (?,?,?,?)')
                    ->execute([$org['organization_id'], $docNumber, $tanggal, $user['id']]);
                $headerId = (int) $pdo->lastInsertId();

                $lineCount = save_label_lines($pdo, $headerId, $lines);
                if (!$lineCount) throw new RuntimeException('Minimal 1 label yang valid.');

                $pdo->commit();
                header('Location: manufaktur-label.php?id=' . $headerId);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'update_manufaktur_label_full') {
            require_module_access('manufaktur_label', 'can_edit');
            $headerId = (int) ($_POST['manufaktur_label_id'] ?? 0);
            $check = $pdo->prepare('SELECT id FROM manufaktur_label WHERE id=? AND organization_id=?');
            $check->execute([$headerId, $org['organization_id']]);
            if (!$check->fetch()) throw new RuntimeException('Dokumen tidak ditemukan.');

            $tanggal = $_POST['tanggal'] ?? '';
            $lines = $_POST['lines'] ?? [];
            if (!$tanggal) throw new RuntimeException('Tanggal wajib diisi.');
            if (!$lines) throw new RuntimeException('Minimal 1 label.');

            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE manufaktur_label SET tanggal=? WHERE id=?')->execute([$tanggal, $headerId]);
                $pdo->prepare('DELETE FROM manufaktur_label_lines WHERE label_id=?')->execute([$headerId]);
                save_label_lines($pdo, $headerId, $lines);

                $pdo->commit();
                touch_label_header($pdo, $headerId, $user['id']);
                header('Location: manufaktur-label.php?id=' . $headerId);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'delete_manufaktur_label') {
            require_module_access('manufaktur_label', 'can_delete');
            $id = (int) ($_POST['manufaktur_label_id'] ?? 0);
            $pdo->prepare('UPDATE manufaktur_label SET deleted_by=?, deleted_at=NOW() WHERE id=? AND organization_id=?')
                ->execute([$user['id'], $id, $org['organization_id']]);
            $flash = ['ok', 'Form Label ditandai dihapus (void).'];
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }
}

// Kode barang + nama barang yang udah pernah dipakai di modul lain (PO/Surat Jalan) —
// dipakai buat auto-lengkapi Kode Barang pas user ketik/pilih Nama Item yang sudah ada.
// Kolom item_code/product_name_snapshot di 3 tabel ini punya collation beda (dibuat di
// waktu berbeda) — paksa COLLATE yang sama biar UNION-nya gak error "Illegal mix of collations".
$knownItemsStmt = $pdo->prepare(
    "SELECT product_name_snapshot COLLATE utf8mb4_unicode_ci AS name, item_code COLLATE utf8mb4_unicode_ci AS item_code FROM manufaktur_surat_jalan_lines WHERE item_code IS NOT NULL AND item_code<>''
     UNION
     SELECT product_name_snapshot COLLATE utf8mb4_unicode_ci AS name, item_code COLLATE utf8mb4_unicode_ci AS item_code FROM manufaktur_po_lines WHERE item_code IS NOT NULL AND item_code<>''
     UNION
     SELECT product_name_snapshot COLLATE utf8mb4_unicode_ci AS name, item_code COLLATE utf8mb4_unicode_ci AS item_code FROM manufaktur_label_lines WHERE item_code IS NOT NULL AND item_code<>''"
);
$knownItemsStmt->execute();
$knownItemCodeMap = [];
foreach ($knownItemsStmt->fetchAll() as $row) {
    $knownItemCodeMap[$row['name']] = $row['item_code'];
}

$productsList = $pdo->prepare('SELECT id, name FROM products WHERE organization_id=? ORDER BY name');
$productsList->execute([$org['organization_id']]);
$productsList = $productsList->fetchAll();

$isNewForm = isset($_GET['new']);
$editId = (int) ($_GET['edit'] ?? 0);
$isEditMode = $editId > 0;
$editHeader = null;
$editLines = [];
if ($isEditMode) {
    $eStmt = $pdo->prepare('SELECT * FROM manufaktur_label WHERE id=? AND organization_id=?');
    $eStmt->execute([$editId, $org['organization_id']]);
    $editHeader = $eStmt->fetch() ?: null;
    if ($editHeader) {
        $elStmt = $pdo->prepare('SELECT * FROM manufaktur_label_lines WHERE label_id=? ORDER BY sort_order, id');
        $elStmt->execute([$editId]);
        $editLines = $elStmt->fetchAll();
    } else {
        $isEditMode = false;
    }
}

if (!$isNewForm && !$isEditMode) {
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

    $railStmt = $pdo->prepare(
        "SELECT h.* FROM manufaktur_label h
         WHERE h.organization_id=? AND DATE_FORMAT(h.created_at,'%Y-%m')=? ORDER BY h.created_at DESC"
    );
    $railStmt->execute([$org['organization_id'], $month]);
    $railItems = $railStmt->fetchAll();

    $selectedId = (int) ($_GET['id'] ?? ($railItems[0]['id'] ?? 0));
    $selected = null;
    $selectedLines = [];
    foreach ($railItems as $r) { if ((int) $r['id'] === $selectedId) { $selected = $r; break; } }
    if (!$selected && $selectedId) {
        $sStmt = $pdo->prepare('SELECT * FROM manufaktur_label WHERE id=? AND organization_id=?');
        $sStmt->execute([$selectedId, $org['organization_id']]);
        $selected = $sStmt->fetch() ?: null;
    }
    if ($selected) {
        $userNameStmt = $pdo->prepare('SELECT name FROM users WHERE id=?');
        $fetchUserName = function ($userId) use ($userNameStmt) {
            if (!$userId) return null;
            $userNameStmt->execute([$userId]);
            return $userNameStmt->fetch()['name'] ?? null;
        };
        $headerCreatedByName = $fetchUserName($selected['created_by']);
        $headerUpdatedByName = $fetchUserName($selected['updated_by']);
        $headerDeletedByName = $fetchUserName($selected['deleted_by']);

        $lStmt = $pdo->prepare('SELECT * FROM manufaktur_label_lines WHERE label_id=? ORDER BY sort_order, id');
        $lStmt->execute([$selected['id']]);
        $selectedLines = $lStmt->fetchAll();
    }
}
?>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<?php if ($isNewForm || $isEditMode): ?>
  <style>
    .lbl-page-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; }
    .lbl-page-head h2 { margin:0 0 4px; font-size:20px; }
    .lbl-page-head p { margin:0; font-size:13px; color:var(--ink-muted); }
    .lbl-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:18px; box-shadow:var(--shadow-card); }
    .lbl-section-head { margin-bottom:16px; }
    .lbl-section-head h3 { margin:0 0 2px; font-size:14px; }
    .lbl-submit-row { display:flex; justify-content:flex-end; gap:10px; }

    table.lbl-box-table { width:100%; border-collapse:collapse; table-layout:fixed; max-width:420px; }
    table.lbl-box-table td { border:1px solid var(--border); padding:0; vertical-align:top; }
    table.lbl-box-table td.lbl { width:32%; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); background:oklch(0.97 0.003 90); padding:7px 9px; }
    table.lbl-box-table td.field-cell { padding:2px 4px; }
    table.lbl-box-table input { width:100%; border:none; background:transparent; padding:6px 6px; font-size:13px; box-sizing:border-box; font-family:inherit; }
    table.lbl-box-table input:focus { outline:2px solid var(--accent); outline-offset:-2px; border-radius:3px; }

    table.lbl-line-table { width:100%; border-collapse:collapse; }
    table.lbl-line-table th, table.lbl-line-table td { border:1px solid var(--border); padding:0; vertical-align:middle; }
    table.lbl-line-table th { background:oklch(0.97 0.003 90); font-size:10px; font-weight:700; text-transform:uppercase; padding:7px 6px; text-align:center; }
    table.lbl-line-table td input[type=text] { width:100%; border:none; background:transparent; padding:7px 6px; font-size:12.5px; box-sizing:border-box; }
    table.lbl-line-table td input:focus { outline:2px solid var(--accent); outline-offset:-2px; }
    table.lbl-line-table td.no-cell { text-align:center; font-size:12px; color:var(--ink-muted); width:32px; }
    table.lbl-line-table td.rm-cell { text-align:center; width:36px; }
    .lbl-add-row-btn { margin-top:10px; }
  </style>

  <div class="lbl-page-head">
    <div>
      <h2><?= $isEditMode ? 'Edit Label Stock' : 'Generate Label Stock' ?></h2>
      <p>Cari kode barang yang udah pernah dipakai, atau ketik baru — tiap baris jadi 1 label siap cetak &amp; ditempel di stock barang (4 label / halaman).</p>
    </div>
    <a class="btn btn-sm btn-ghost" href="manufaktur-label.php<?= $isEditMode ? '?id=' . $editId : '' ?>">Batal</a>
  </div>

  <form method="post" id="lbl-form">
    <?= csrf_field() ?>
    <?php if ($isEditMode): ?>
      <input type="hidden" name="action" value="update_manufaktur_label_full">
      <input type="hidden" name="manufaktur_label_id" value="<?= $editHeader['id'] ?>">
    <?php else: ?>
      <input type="hidden" name="action" value="save_manufaktur_label">
    <?php endif; ?>

    <div class="lbl-section">
      <div class="lbl-section-head"><h3>Informasi Umum</h3></div>
      <table class="lbl-box-table">
        <tr><td class="lbl">Tanggal</td><td class="field-cell"><input type="date" name="tanggal" value="<?= $isEditMode ? htmlspecialchars($editHeader['tanggal']) : date('Y-m-d') ?>" required></td></tr>
      </table>
    </div>

    <div class="lbl-section">
      <div class="lbl-section-head"><h3>Daftar Label</h3></div>
      <table class="lbl-line-table" id="lbl-line-table">
        <thead>
          <tr>
            <th style="width:32px;">No</th>
            <th>Nama Item</th>
            <th style="width:100px;">Kode Barang</th>
            <th style="width:90px;">Ukuran</th>
            <th style="width:90px;">Jumlah Label</th>
            <th style="width:110px;">Tujuan</th>
            <th style="width:110px;">Pembuat</th>
            <th style="width:36px;"></th>
          </tr>
        </thead>
        <tbody id="lbl-line-tbody">
          <?php
          $rowsToRender = $isEditMode ? $editLines : [];
          $rowIdx = 0;
          foreach ($rowsToRender as $ln):
          ?>
            <tr>
              <td class="no-cell"><?= $rowIdx + 1 ?></td>
              <td><input type="text" class="lbl-combo-product" name="lines[<?= $rowIdx ?>][product_name]" value="<?= htmlspecialchars($ln['product_name_snapshot']) ?>" autocomplete="off"></td>
              <td><input type="text" class="lbl-item-code" name="lines[<?= $rowIdx ?>][item_code]" value="<?= htmlspecialchars($ln['item_code'] ?? '') ?>"></td>
              <td><input type="text" name="lines[<?= $rowIdx ?>][ukuran]" value="<?= htmlspecialchars($ln['ukuran'] ?? '') ?>"></td>
              <td><input type="number" min="1" step="1" name="lines[<?= $rowIdx ?>][koli]" value="<?= htmlspecialchars($ln['koli'] ?? '') ?>" placeholder="cth. 5"></td>
              <td><input type="text" name="lines[<?= $rowIdx ?>][tujuan]" value="<?= htmlspecialchars($ln['tujuan'] ?? '') ?>"></td>
              <td><input type="text" name="lines[<?= $rowIdx ?>][pembuat]" value="<?= htmlspecialchars($ln['pembuat'] ?? '') ?>"></td>
              <td class="rm-cell"><button type="button" class="btn btn-sm btn-ghost lbl-remove-row" style="padding:2px 8px;">✕</button></td>
            </tr>
          <?php $rowIdx++; endforeach; ?>
        </tbody>
      </table>
      <button type="button" class="btn btn-sm lbl-add-row-btn" id="lbl-add-row-btn">+ Tambah Label</button>
    </div>

    <div class="lbl-submit-row">
      <a class="btn btn-ghost" href="manufaktur-label.php<?= $isEditMode ? '?id=' . $editId : '' ?>">Batal</a>
      <button type="submit" class="btn"><?= $isEditMode ? 'Simpan Perubahan' : 'Simpan Form Label' ?></button>
    </div>
  </form>

  <script>
  var LBL_PRODUCT_NAMES = <?= json_encode(array_column($productsList, 'name')) ?>;
  var LBL_ITEM_CODE_MAP = <?= json_encode($knownItemCodeMap) ?>;

  document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('lbl-line-tbody');
    var rowIndex = tbody.querySelectorAll('tr').length;

    function renumber() {
      tbody.querySelectorAll('tr').forEach(function (tr, i) {
        tr.querySelector('.no-cell').textContent = i + 1;
      });
    }

    function bindRow(tr) {
      var combo = tr.querySelector('.lbl-combo-product');
      var codeInput = tr.querySelector('.lbl-item-code');
      if (combo) {
        initCombobox(combo, LBL_PRODUCT_NAMES);
        combo.addEventListener('change', function () {
          if (!codeInput.value && LBL_ITEM_CODE_MAP[combo.value]) {
            codeInput.value = LBL_ITEM_CODE_MAP[combo.value];
          }
        });
        combo.addEventListener('blur', function () {
          if (!codeInput.value && LBL_ITEM_CODE_MAP[combo.value]) {
            codeInput.value = LBL_ITEM_CODE_MAP[combo.value];
          }
        });
      }
      tr.querySelector('.lbl-remove-row').addEventListener('click', function () {
        tr.remove();
        renumber();
      });
    }

    function addRow() {
      var i = rowIndex++;
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="no-cell"></td>' +
        '<td><input type="text" class="lbl-combo-product" name="lines[' + i + '][product_name]" autocomplete="off"></td>' +
        '<td><input type="text" class="lbl-item-code" name="lines[' + i + '][item_code]"></td>' +
        '<td><input type="text" name="lines[' + i + '][ukuran]"></td>' +
        '<td><input type="number" min="1" step="1" name="lines[' + i + '][koli]" placeholder="cth. 5"></td>' +
        '<td><input type="text" name="lines[' + i + '][tujuan]"></td>' +
        '<td><input type="text" name="lines[' + i + '][pembuat]"></td>' +
        '<td class="rm-cell"><button type="button" class="btn btn-sm btn-ghost lbl-remove-row" style="padding:2px 8px;">✕</button></td>';
      tbody.appendChild(tr);
      bindRow(tr);
      renumber();
    }

    tbody.querySelectorAll('tr').forEach(bindRow);
    document.getElementById('lbl-add-row-btn').addEventListener('click', addRow);

    <?php if (!$isEditMode): ?>
    addRow();
    <?php endif; ?>
  });
  </script>

<?php else: ?>
  <style>
    .txn-rail-item.lbl-rail-void { background:var(--danger-bg, #fde2e2) !important; }
    .txn-rail-item.lbl-rail-void .doc { color:var(--danger, #b91c1c) !important; }
    #lbl-rail-list .txn-rail-item { padding:12px; }
    #lbl-rail-list .txn-rail-item .sub { margin-top:4px; }
    .txn-rail .txn-rail-month .today-btn { margin:0; }
    #lbl-rail-search-wrap { padding:0 0 10px; }
    .lbl-void-banner { margin-top:12px; background:var(--danger-bg, #fde2e2); color:var(--danger, #b91c1c); border:1px solid var(--danger, #b91c1c); border-radius:8px; padding:10px 14px; font-size:13px; }
    .lbl-audit-log { margin-top:12px; border:1px solid var(--border); border-radius:8px; overflow:hidden; font-size:11.5px; }
    .lbl-audit-log .row { display:flex; padding:7px 12px; border-top:1px solid var(--border); background:oklch(0.98 0.003 90); }
    .lbl-audit-log .row:first-child { border-top:none; }
    .lbl-audit-log .k { width:60px; flex-shrink:0; font-weight:600; text-transform:uppercase; font-size:10px; letter-spacing:.02em; color:var(--ink-muted); align-self:center; }
    table.lbl-detail-table { width:100%; border-collapse:collapse; margin-top:16px; font-size:12.5px; }
    table.lbl-detail-table th, table.lbl-detail-table td { border:1px solid var(--border); padding:7px 8px; text-align:left; }
    table.lbl-detail-table th { background:oklch(0.97 0.003 90); font-size:10px; text-transform:uppercase; }
  </style>

  <div class="txn-shell">
    <div class="txn-rail">
      <div class="txn-rail-month">
        <a href="manufaktur-label.php?month=<?= $prevMonth ?>">‹</a>
        <span><?= htmlspecialchars(date('F Y', strtotime($month . '-01'))) ?></span>
        <a href="manufaktur-label.php?month=<?= $nextMonth ?>">›</a>
        <a class="today-btn" href="manufaktur-label.php">Bulan Ini</a>
      </div>
      <div id="lbl-rail-search-wrap">
        <input type="text" id="lbl-rail-search" placeholder="Cari nomor dokumen..." style="width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:6px; font-size:12.5px; box-sizing:border-box;">
      </div>
      <div class="txn-rail-list" id="lbl-rail-list">
        <?php foreach ($railItems as $r): ?>
          <?php
          $isVoided = (bool) $r['deleted_at'];
          $searchBlob = mb_strtolower($r['doc_number']);
          ?>
          <a class="txn-rail-item <?= (int) $r['id'] === $selectedId ? 'active' : '' ?> <?= $isVoided ? 'lbl-rail-void' : '' ?>" data-search="<?= htmlspecialchars($searchBlob) ?>" href="manufaktur-label.php?month=<?= $month ?>&id=<?= $r['id'] ?>">
            <div class="doc" style="font-weight:700;"><?= htmlspecialchars($r['doc_number']) ?><?= $isVoided ? ' 🚫' : '' ?></div>
            <div class="sub"><?= htmlspecialchars(date('d M Y', strtotime($r['tanggal']))) ?></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$railItems): ?><div style="padding:14px; font-size:12px; color:var(--ink-muted);">Gak ada Form Label bulan ini.</div><?php endif; ?>
      </div>
      <?php if (has_access('manufaktur_label', 'can_create')): ?>
        <div style="padding:10px;"><a class="btn btn-sm" style="width:100%;" href="manufaktur-label.php?new=1">+ Generate Label</a></div>
      <?php endif; ?>
    </div>

    <div class="txn-detail">
      <?php if (!$selected): ?>
        <div class="card txn-empty">Pilih dokumen di kiri, atau buat yang baru.</div>
      <?php else: ?>
        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <h2 style="margin:0; font-size:20px;"><?= htmlspecialchars($selected['doc_number']) ?></h2>
            <div class="txn-detail-actions">
              <a class="btn btn-sm btn-ghost" href="manufaktur-label-print.php?id=<?= $selected['id'] ?>" target="_blank">🖨 Print</a>
              <?php if (has_access('manufaktur_label', 'can_edit')): ?>
                <a class="btn btn-sm btn-ghost" href="manufaktur-label.php?edit=<?= $selected['id'] ?>">✎ Edit</a>
              <?php endif; ?>
              <?php if (has_access('manufaktur_label', 'can_delete')): ?>
                <button class="btn btn-sm btn-ghost" type="button" onclick="if(confirm('Hapus dokumen ini?')) __submitDeleteForm('delete_manufaktur_label', {manufaktur_label_id: <?= $selected['id'] ?>})">Hapus</button>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($selected['deleted_at']): ?>
            <div class="lbl-void-banner">🚫 Dokumen ini sudah <strong>dihapus (void)</strong> oleh <strong><?= htmlspecialchars($headerDeletedByName ?? '—') ?></strong> pada <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['deleted_at']))) ?></div>
          <?php endif; ?>

          <div class="lbl-audit-log">
            <div class="row"><span class="k">Dibuat</span><span><strong><?= htmlspecialchars($headerCreatedByName ?? '—') ?></strong> · <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['created_at']))) ?></span></div>
            <?php if ($selected['updated_at']): ?>
              <div class="row"><span class="k">Diedit</span><span><strong><?= htmlspecialchars($headerUpdatedByName ?? '—') ?></strong> · <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['updated_at']))) ?></span></div>
            <?php endif; ?>
          </div>

          <table class="lbl-detail-table">
            <thead><tr><th style="width:32px;">No</th><th>Nama Item</th><th style="width:100px;">Kode Barang</th><th style="width:90px;">Ukuran</th><th style="width:90px;">Jumlah Label</th><th>Tujuan</th><th>Pembuat</th></tr></thead>
            <tbody>
              <?php foreach ($selectedLines as $i => $ln): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><?= htmlspecialchars($ln['product_name_snapshot']) ?></td>
                  <td><?= $ln['item_code'] ? htmlspecialchars($ln['item_code']) : '—' ?></td>
                  <td><?= $ln['ukuran'] ? htmlspecialchars($ln['ukuran']) : '—' ?></td>
                  <td><?= $ln['koli'] ? htmlspecialchars($ln['koli']) : '—' ?></td>
                  <td><?= $ln['tujuan'] ? htmlspecialchars($ln['tujuan']) : '—' ?></td>
                  <td><?= $ln['pembuat'] ? htmlspecialchars($ln['pembuat']) : '—' ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$selectedLines): ?><tr><td colspan="7" style="text-align:center; color:var(--ink-muted);">Belum ada label.</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var lblRailSearch = document.getElementById('lbl-rail-search');
    if (lblRailSearch) {
      lblRailSearch.addEventListener('input', function () {
        var q = lblRailSearch.value.trim().toLowerCase();
        document.querySelectorAll('#lbl-rail-list .txn-rail-item').forEach(function (item) {
          var hay = item.getAttribute('data-search') || '';
          item.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
        });
      });
    }
  });
  </script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
