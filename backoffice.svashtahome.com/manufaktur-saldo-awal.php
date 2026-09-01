<?php
$pageTitle = 'Input Saldo Awal Stock';
$activeMenu = 'manufaktur_saldo_awal';
require __DIR__ . '/includes/header.php';
require_module_access('manufaktur_surat_jalan');
require_once __DIR__ . '/../backoffice-shared/stock.php';

$pdo = db();
$flash = null;

// User non-Owner yang lokasinya dibatasin (lihat Master User) cuma boleh liat/input Saldo
// Awal buat lokasi dia sendiri — NULL berarti gak dibatasin (Owner atau gak di-assign).
$myWarehouseId = user_location_restriction();
$myWarehouseName = null;
if ($myWarehouseId !== null) {
    $mwStmt = $pdo->prepare('SELECT name FROM warehouses WHERE id=? AND organization_id=?');
    $mwStmt->execute([$myWarehouseId, $org['organization_id']]);
    $myWarehouseName = $mwStmt->fetchColumn() ?: null;
}

function next_saldo_awal_number(PDO $pdo, int $organizationId): string
{
    $year = (int) date('Y');
    $month = (int) date('n');
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT last_number FROM doc_counters WHERE organization_id=? AND doc_type=? AND year=? FOR UPDATE');
        $stmt->execute([$organizationId, 'SALDO', $year]);
        $row = $stmt->fetch();
        if ($row) {
            $next = (int) $row['last_number'] + 1;
            $pdo->prepare('UPDATE doc_counters SET last_number=? WHERE organization_id=? AND doc_type=? AND year=?')
                ->execute([$next, $organizationId, 'SALDO', $year]);
        } else {
            $next = 1;
            $pdo->prepare('INSERT INTO doc_counters (organization_id, doc_type, year, last_number) VALUES (?,?,?,?)')
                ->execute([$organizationId, 'SALDO', $year, $next]);
        }
        if ($ownTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownTransaction) $pdo->rollBack();
        throw $e;
    }
    return sprintf('SALDO/%d/%02d/%04d', $year, $month, $next);
}

function find_or_create_saldo_product(PDO $pdo, int $orgId, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE organization_id=? AND name=? LIMIT 1');
    $stmt->execute([$orgId, $name]);
    $row = $stmt->fetch();
    if ($row) return (int) $row['id'];
    $pdo->prepare("INSERT INTO products (organization_id, name, unit) VALUES (?,?,'pcs')")->execute([$orgId, $name]);
    return (int) $pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_saldo_awal') {
            require_module_access('manufaktur_surat_jalan', 'can_create');
            $tanggal = $_POST['tanggal'] ?? '';
            $warehouseId = (int) ($_POST['warehouse_id'] ?? 0);
            // Jaga-jaga dropdown lokasi di-bypass lewat devtools kalau user-nya dibatasin.
            if ($myWarehouseId !== null) $warehouseId = $myWarehouseId;
            $keterangan = trim($_POST['keterangan'] ?? '') ?: null;

            $rowsToInsert = [];

            // Baris manual dari form
            $lines = $_POST['lines'] ?? [];
            foreach ($lines as $line) {
                $name = trim($line['product_name'] ?? '');
                if ($name === '') continue;
                $qty = (float) ($line['qty'] ?? 0);
                $harga = (float) ($line['harga'] ?? 0);
                if ($qty <= 0) continue;
                $rowsToInsert[] = ['name' => $name, 'qty' => $qty, 'harga' => $harga];
            }

            // Baris dari file yang diupload (opsional, digabung sama baris manual) — dicari
            // by NAMA KOLOM (bukan posisi), biar gak gampang rusak kalau urutan/jumlah kolom
            // template "Export ke Excel" berubah lagi nanti. Support .xlsx (utama) dan .csv
            // (lama, buat jaga-jaga kalau masih ada file CSV export dari sebelumnya).
            if (!empty($_FILES['import_file']['name']) && ($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadName = $_FILES['import_file']['name'];
                $uploadTmp = $_FILES['import_file']['tmp_name'];
                $isXlsx = str_ends_with(mb_strtolower($uploadName), '.xlsx');

                if ($isXlsx) {
                    require_once __DIR__ . '/../backoffice-shared/xlsx_reader.php';
                    $named = xlsx_rows_to_named(read_xlsx_rows($uploadTmp));
                    foreach ($named['rows'] as $entry) {
                        $name = trim($entry['nama produk'] ?? '');
                        if ($name === '' || mb_strtoupper($name) === 'TOTAL') continue;
                        $qty = (float) str_replace(',', '.', $entry['qty'] ?? '0');
                        $harga = (float) str_replace(',', '.', $entry['harga per unit'] ?? '0');
                        if ($qty <= 0) continue;
                        $rowsToInsert[] = ['name' => $name, 'qty' => $qty, 'harga' => $harga];
                    }
                } else {
                    $fh = fopen($uploadTmp, 'r');
                    if ($fh) {
                        $header = null;
                        while (($row = fgetcsv($fh)) !== false) {
                            if ($header === null) { $header = array_map(fn($h) => mb_strtolower(trim((string) $h)), $row); continue; }
                            $nameIdx = array_search('nama produk', $header, true);
                            $qtyIdx = array_search('qty', $header, true);
                            $hargaIdx = array_search('harga per unit', $header, true);
                            $name = trim($row[$nameIdx] ?? '');
                            if ($name === '' || mb_strtoupper($name) === 'TOTAL') continue;
                            $qty = (float) str_replace(',', '.', trim($row[$qtyIdx] ?? '0'));
                            $harga = (float) str_replace(',', '.', trim($row[$hargaIdx] ?? '0'));
                            if ($qty <= 0) continue;
                            $rowsToInsert[] = ['name' => $name, 'qty' => $qty, 'harga' => $harga];
                        }
                        fclose($fh);
                    }
                }
            }

            if (!$tanggal) throw new RuntimeException('Tanggal wajib diisi.');
            if (!$warehouseId) throw new RuntimeException('Lokasi wajib dipilih.');
            if (!$rowsToInsert) throw new RuntimeException('Minimal 1 baris barang (manual atau dari file).');

            $pdo->beginTransaction();
            try {
                $docNumber = next_saldo_awal_number($pdo, $org['organization_id']);
                $pdo->prepare('INSERT INTO stock_opening_balance (organization_id, doc_number, tanggal, warehouse_id, keterangan, created_by) VALUES (?,?,?,?,?,?)')
                    ->execute([$org['organization_id'], $docNumber, $tanggal, $warehouseId, $keterangan, $user['id']]);
                $headerId = (int) $pdo->lastInsertId();

                $lineStmt = $pdo->prepare('INSERT INTO stock_opening_balance_lines (opening_balance_id, product_id, product_name_snapshot, qty, harga, sort_order) VALUES (?,?,?,?,?,?)');
                $sort = 0;
                foreach ($rowsToInsert as $r) {
                    $productId = find_or_create_saldo_product($pdo, $org['organization_id'], $r['name']);
                    $lineStmt->execute([$headerId, $productId, $r['name'], $r['qty'], $r['harga'], $sort]);
                    $sort++;

                    // Langsung catet ke stock_ledger sebagai stok masuk (opening balance).
                    $pdo->prepare(
                        'INSERT INTO stock_ledger (organization_id, warehouse_id, product_id, direction, qty, qty_remaining, unit_cost, ref_type, ref_id) VALUES (?,?,?,"in",?,?,?,"opening_balance",?)'
                    )->execute([$org['organization_id'], $warehouseId, $productId, $r['qty'], $r['qty'], $r['harga'], $headerId]);
                }

                $pdo->commit();
                header('Location: manufaktur-saldo-awal.php?id=' . $headerId);
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        } elseif ($action === 'delete_saldo_awal') {
            require_module_access('manufaktur_surat_jalan', 'can_delete');
            $id = (int) ($_POST['saldo_id'] ?? 0);
            $pdo->prepare('UPDATE stock_opening_balance SET deleted_by=?, deleted_at=NOW() WHERE id=? AND organization_id=?')
                ->execute([$user['id'], $id, $org['organization_id']]);
            $flash = ['ok', 'Dokumen ditandai dihapus (void). Catatan stok_ledger TIDAK otomatis dibalikin — sesuaikan manual kalau perlu.'];
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }
}

$warehouses = $pdo->prepare('SELECT id, name FROM warehouses WHERE organization_id=? AND vendor_id IS NULL AND deleted_at IS NULL ORDER BY name');
$warehouses->execute([$org['organization_id']]);
$warehouses = $warehouses->fetchAll();

$productsList = $pdo->prepare('SELECT id, name FROM products WHERE organization_id=? ORDER BY name');
$productsList->execute([$org['organization_id']]);
$productsList = $productsList->fetchAll();

$isNewForm = isset($_GET['new']);

if (!$isNewForm) {
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $prevMonth = date('Y-m', strtotime($month . '-01 -1 month'));
    $nextMonth = date('Y-m', strtotime($month . '-01 +1 month'));

    $railWhere = "h.organization_id=? AND DATE_FORMAT(h.created_at,'%Y-%m')=?";
    $railParams = [$org['organization_id'], $month];
    if ($myWarehouseId !== null) { $railWhere .= ' AND h.warehouse_id=?'; $railParams[] = $myWarehouseId; }
    $railStmt = $pdo->prepare(
        "SELECT h.*, w.name AS warehouse_name FROM stock_opening_balance h JOIN warehouses w ON w.id=h.warehouse_id
         WHERE $railWhere ORDER BY h.created_at DESC"
    );
    $railStmt->execute($railParams);
    $railItems = $railStmt->fetchAll();

    $selectedId = (int) ($_GET['id'] ?? ($railItems[0]['id'] ?? 0));
    $selected = null;
    $selectedLines = [];
    foreach ($railItems as $r) { if ((int) $r['id'] === $selectedId) { $selected = $r; break; } }
    if (!$selected && $selectedId) {
        $sWhere = 'h.id=? AND h.organization_id=?';
        $sParams = [$selectedId, $org['organization_id']];
        if ($myWarehouseId !== null) { $sWhere .= ' AND h.warehouse_id=?'; $sParams[] = $myWarehouseId; }
        $sStmt = $pdo->prepare("SELECT h.*, w.name AS warehouse_name FROM stock_opening_balance h JOIN warehouses w ON w.id=h.warehouse_id WHERE $sWhere");
        $sStmt->execute($sParams);
        $selected = $sStmt->fetch() ?: null;
    }
    if ($selected) {
        $userNameStmt = $pdo->prepare('SELECT name FROM users WHERE id=?');
        $userNameStmt->execute([$selected['created_by']]);
        $createdByName = $userNameStmt->fetch()['name'] ?? null;

        $lStmt = $pdo->prepare('SELECT * FROM stock_opening_balance_lines WHERE opening_balance_id=? ORDER BY sort_order, id');
        $lStmt->execute([$selected['id']]);
        $selectedLines = $lStmt->fetchAll();
    }
}
?>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<?php if ($isNewForm): ?>
  <style>
    .sa-page-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:18px; }
    .sa-page-head h2 { margin:0 0 4px; font-size:20px; }
    .sa-page-head p { margin:0; font-size:13px; color:var(--ink-muted); }
    .sa-section { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:18px; box-shadow:var(--shadow-card); }
    .sa-section-head { margin-bottom:16px; }
    .sa-section-head h3 { margin:0 0 2px; font-size:14px; }
    .sa-section-head p { margin:0; font-size:12px; color:var(--ink-muted); }
    .sa-submit-row { display:flex; justify-content:flex-end; gap:10px; }

    table.sa-box-table { width:100%; border-collapse:collapse; table-layout:fixed; }
    table.sa-box-table td { border:1px solid var(--border); padding:0; vertical-align:top; }
    table.sa-box-table td.lbl { width:22%; font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); background:oklch(0.97 0.003 90); padding:7px 9px; }
    table.sa-box-table td.field-cell { padding:2px 4px; }
    table.sa-box-table input, table.sa-box-table select { width:100%; border:none; background:transparent; padding:6px 6px; font-size:13px; box-sizing:border-box; font-family:inherit; }
    table.sa-box-table input:focus, table.sa-box-table select:focus { outline:2px solid var(--accent); outline-offset:-2px; }

    table.sa-line-table { width:100%; border-collapse:collapse; }
    table.sa-line-table th, table.sa-line-table td { border:1px solid var(--border); padding:0; vertical-align:middle; }
    table.sa-line-table th { background:oklch(0.97 0.003 90); font-size:10px; font-weight:700; text-transform:uppercase; padding:7px 6px; text-align:center; }
    table.sa-line-table td input[type=text], table.sa-line-table td input[type=number] { width:100%; border:none; background:transparent; padding:7px 6px; font-size:12.5px; box-sizing:border-box; }
    table.sa-line-table td input:focus { outline:2px solid var(--accent); outline-offset:-2px; }
    table.sa-line-table td.no-cell { text-align:center; font-size:12px; color:var(--ink-muted); width:32px; }
    table.sa-line-table td.rm-cell { text-align:center; width:36px; }
    .sa-add-row-btn { margin-top:10px; }
    .sa-import-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
  </style>

  <div class="sa-page-head">
    <div>
      <h2>Input Saldo Awal Stock</h2>
      <p>Isi barang manual dan/atau upload file — dua-duanya bisa digabung dalam 1 dokumen.</p>
    </div>
    <a class="btn btn-sm btn-ghost" href="manufaktur-saldo-awal.php">Batal</a>
  </div>

  <form method="post" enctype="multipart/form-data" id="sa-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_saldo_awal">

    <div class="sa-section">
      <div class="sa-section-head"><h3>Header</h3></div>
      <table class="sa-box-table">
        <tr>
          <td class="lbl">Tanggal Input</td><td class="field-cell"><input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required></td>
          <td class="lbl">Nama User</td><td class="field-cell"><input type="text" value="<?= htmlspecialchars($user['name']) ?>" disabled></td>
        </tr>
        <tr>
          <td class="lbl">Lokasi</td>
          <td class="field-cell">
            <?php if ($myWarehouseId !== null): ?>
              <input type="text" value="<?= htmlspecialchars($myWarehouseName ?? '') ?>" disabled>
              <input type="hidden" name="warehouse_id" value="<?= $myWarehouseId ?>">
            <?php else: ?>
              <select name="warehouse_id" required>
                <option value="">— Pilih lokasi —</option>
                <?php foreach ($warehouses as $w): ?>
                  <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </td>
          <td class="lbl">Keterangan</td><td class="field-cell"><input type="text" name="keterangan" placeholder="Opsional"></td>
        </tr>
      </table>
    </div>

    <div class="sa-section">
      <div class="sa-section-head"><h3>Detail Barang (Input Manual)</h3></div>
      <table class="sa-line-table" id="sa-line-table">
        <thead>
          <tr>
            <th style="width:32px;">No</th>
            <th>Master Barang</th>
            <th style="width:90px;">Qty</th>
            <th style="width:140px;">Harga / Unit</th>
            <th style="width:36px;"></th>
          </tr>
        </thead>
        <tbody id="sa-line-tbody"></tbody>
      </table>
      <button type="button" class="btn btn-sm sa-add-row-btn" id="sa-add-row-btn">+ Tambah Baris</button>
    </div>

    <div class="sa-section">
      <div class="sa-section-head"><h3>Atau Upload File Excel</h3><p>Format kolom sama kayak "Export ke Excel" di Laporan Inventory — download stok saat ini, edit Qty-nya, upload lagi. Barisnya digabung sama input manual di atas (boleh isi salah satu aja).</p></div>
      <div class="sa-import-row">
        <input type="file" name="import_file" accept=".xlsx">
        <a class="btn btn-sm btn-ghost" href="inventory-export.php">⬇ Download Template (dari stok saat ini)</a>
      </div>
    </div>

    <div class="sa-submit-row">
      <a class="btn btn-ghost" href="manufaktur-saldo-awal.php">Batal</a>
      <button type="submit" class="btn">Simpan &amp; Update Stok</button>
    </div>
  </form>

  <script>
  var SA_PRODUCT_NAMES = <?= json_encode(array_column($productsList, 'name')) ?>;

  document.addEventListener('DOMContentLoaded', function () {
    var tbody = document.getElementById('sa-line-tbody');
    var rowIndex = 0;

    function renumber() {
      tbody.querySelectorAll('tr').forEach(function (tr, i) {
        tr.querySelector('.no-cell').textContent = i + 1;
      });
    }

    function addRow() {
      var i = rowIndex++;
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="no-cell"></td>' +
        '<td><input type="text" class="sa-combo-product" name="lines[' + i + '][product_name]" autocomplete="off"></td>' +
        '<td><input type="number" step="0.01" min="0" name="lines[' + i + '][qty]"></td>' +
        '<td><input type="text" inputmode="numeric" class="rupiah-input" name="lines[' + i + '][harga]" placeholder="Rp"></td>' +
        '<td class="rm-cell"><button type="button" class="btn btn-sm btn-ghost sa-remove-row" style="padding:2px 8px;">✕</button></td>';
      tbody.appendChild(tr);
      var combo = tr.querySelector('.sa-combo-product');
      initCombobox(combo, SA_PRODUCT_NAMES);
      var priceInput = tr.querySelector('.rupiah-input');
      if (window.initRupiahInput) initRupiahInput(priceInput);
      tr.querySelector('.sa-remove-row').addEventListener('click', function () {
        tr.remove();
        renumber();
      });
      renumber();
    }

    document.getElementById('sa-add-row-btn').addEventListener('click', addRow);
    addRow();
    addRow();
    addRow();
  });
  </script>

<?php else: ?>
  <style>
    .txn-rail-item.sa-rail-void { background:var(--danger-bg, #fde2e2) !important; }
    .txn-rail-item.sa-rail-void .doc { color:var(--danger, #b91c1c) !important; }
    .txn-rail .txn-rail-month .today-btn { margin:0; }
    .sa-info-table { margin-top:16px; display:grid; grid-template-columns: repeat(4, 1fr); border:1px solid var(--border); border-radius:10px; overflow:hidden; }
    @media (max-width: 780px) { .sa-info-table { grid-template-columns: repeat(2, 1fr); } }
    .sa-info-table .cell { padding:12px 16px; border-right:1px solid var(--border); border-top:1px solid var(--border); background:oklch(0.98 0.003 90); }
    .sa-info-table .cell:nth-child(-n+4) { border-top:none; }
    .sa-info-table .cell:nth-child(4n) { border-right:none; }
    .sa-info-table .k { display:block; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.02em; color:var(--ink-muted); margin-bottom:4px; }
    .sa-void-banner { margin-top:12px; background:var(--danger-bg, #fde2e2); color:var(--danger, #b91c1c); border:1px solid var(--danger, #b91c1c); border-radius:8px; padding:10px 14px; font-size:13px; }
    table.sa-detail-table { width:100%; border-collapse:collapse; margin-top:16px; font-size:12.5px; }
    table.sa-detail-table th, table.sa-detail-table td { border:1px solid var(--border); padding:7px 8px; text-align:left; }
    table.sa-detail-table th { background:oklch(0.97 0.003 90); font-size:10px; text-transform:uppercase; }
    table.sa-detail-table td.num { text-align:right; }
    table.sa-detail-table tr.total-row td { font-weight:700; border-top:2px solid var(--border); }
  </style>

  <div class="txn-shell">
    <div class="txn-rail">
      <div class="txn-rail-month">
        <a href="manufaktur-saldo-awal.php?month=<?= $prevMonth ?>">‹</a>
        <span><?= htmlspecialchars(date('F Y', strtotime($month . '-01'))) ?></span>
        <a href="manufaktur-saldo-awal.php?month=<?= $nextMonth ?>">›</a>
        <a class="today-btn" href="manufaktur-saldo-awal.php">Bulan Ini</a>
      </div>
      <div class="txn-rail-list">
        <?php foreach ($railItems as $r): ?>
          <?php $isVoided = (bool) $r['deleted_at']; ?>
          <a class="txn-rail-item <?= (int) $r['id'] === $selectedId ? 'active' : '' ?> <?= $isVoided ? 'sa-rail-void' : '' ?>" href="manufaktur-saldo-awal.php?month=<?= $month ?>&id=<?= $r['id'] ?>">
            <div class="doc" style="font-weight:700;"><?= htmlspecialchars($r['doc_number']) ?><?= $isVoided ? ' 🚫' : '' ?></div>
            <div class="sub"><?= htmlspecialchars(date('d M Y', strtotime($r['tanggal']))) ?> · <?= htmlspecialchars($r['warehouse_name']) ?></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$railItems): ?><div style="padding:14px; font-size:12px; color:var(--ink-muted);">Gak ada input saldo bulan ini.</div><?php endif; ?>
      </div>
      <?php if (has_access('manufaktur_surat_jalan', 'can_create')): ?>
        <div style="padding:10px;"><a class="btn btn-sm" style="width:100%;" href="manufaktur-saldo-awal.php?new=1">+ Input Saldo Awal</a></div>
      <?php endif; ?>
    </div>

    <div class="txn-detail">
      <?php if (!$selected): ?>
        <div class="card txn-empty">Pilih dokumen di kiri, atau buat yang baru.</div>
      <?php else: ?>
        <?php $totalQty = array_sum(array_column($selectedLines, 'qty')); $totalValue = array_sum(array_map(fn($l) => $l['qty'] * $l['harga'], $selectedLines)); ?>
        <div class="card">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <h2 style="margin:0; font-size:20px;"><?= htmlspecialchars($selected['doc_number']) ?></h2>
            <div class="txn-detail-actions">
              <a class="btn btn-sm btn-ghost" href="manufaktur-saldo-awal-print.php?id=<?= $selected['id'] ?>" target="_blank">🖨 Print</a>
              <?php if (has_access('manufaktur_surat_jalan', 'can_delete')): ?>
                <button class="btn btn-sm btn-ghost" type="button" onclick="if(confirm('Hapus dokumen ini? (catatan stok TIDAK otomatis dibalikin)')) __submitDeleteForm('delete_saldo_awal', {saldo_id: <?= $selected['id'] ?>})">Hapus</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="sa-info-table">
            <div class="cell"><span class="k">Tanggal</span><?= htmlspecialchars(date('d M Y', strtotime($selected['tanggal']))) ?></div>
            <div class="cell"><span class="k">Lokasi</span><?= htmlspecialchars($selected['warehouse_name']) ?></div>
            <div class="cell"><span class="k">Diinput oleh</span><?= htmlspecialchars($createdByName ?? '—') ?></div>
            <div class="cell"><span class="k">Keterangan</span><?= $selected['keterangan'] ? htmlspecialchars($selected['keterangan']) : '—' ?></div>
          </div>

          <?php if ($selected['deleted_at']): ?>
            <div class="sa-void-banner">🚫 Dokumen ini sudah <strong>dihapus (void)</strong> pada <?= htmlspecialchars(date('d M Y, H:i', strtotime($selected['deleted_at']))) ?></div>
          <?php endif; ?>

          <table class="sa-detail-table">
            <thead><tr><th style="width:32px;">No</th><th>Nama Barang</th><th style="width:90px;">Qty</th><th style="width:130px;">Harga</th><th style="width:150px;">Subtotal</th></tr></thead>
            <tbody>
              <?php foreach ($selectedLines as $i => $ln): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><?= htmlspecialchars($ln['product_name_snapshot']) ?></td>
                  <td class="num"><?= rtrim(rtrim(number_format((float) $ln['qty'], 2, ',', '.'), '0'), ',') ?></td>
                  <td class="num">Rp <?= number_format((float) $ln['harga'], 0, ',', '.') ?></td>
                  <td class="num">Rp <?= number_format((float) $ln['qty'] * (float) $ln['harga'], 0, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$selectedLines): ?><tr><td colspan="5" style="text-align:center; color:var(--ink-muted);">Belum ada barang.</td></tr><?php endif; ?>
              <tr class="total-row"><td colspan="2">Total</td><td class="num"><?= rtrim(rtrim(number_format($totalQty, 2, ',', '.'), '0'), ',') ?></td><td></td><td class="num">Rp <?= number_format($totalValue, 0, ',', '.') ?></td></tr>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
