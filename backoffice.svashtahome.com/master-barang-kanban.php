<?php
$pageTitle = 'Master Barang';
$activeMenu = 'products';
require __DIR__ . '/includes/header.php';
require_module_access('master_barang');
require_once __DIR__ . '/../backoffice-shared/image_upload.php';

$pdo = db();
$flash = null;

function next_category_code(PDO $pdo, int $orgId): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM product_categories WHERE organization_id=?');
    $stmt->execute([$orgId]);
    $n = (int) $stmt->fetch()['c'] + 1;
    return sprintf('KTG-%03d', $n);
}
function next_subcategory_code(PDO $pdo, int $orgId, int $categoryId, string $categoryCode): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM product_subcategories WHERE organization_id=? AND category_id=?');
    $stmt->execute([$orgId, $categoryId]);
    $n = (int) $stmt->fetch()['c'] + 1;
    return sprintf('%s-%03d', $categoryCode, $n);
}
function next_product_code(PDO $pdo, int $orgId, string $parentCode): string
{
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM products WHERE organization_id=? AND code LIKE ?');
    $stmt->execute([$orgId, $parentCode . '-%']);
    $n = (int) $stmt->fetch()['c'] + 1;
    return sprintf('%s-%03d', $parentCode, $n);
}
// Barang harus selalu punya Sub Kategori beneran (biar gak ada lagi bucket
// "Tanpa Sub Kategori" yang gak bisa diedit) — kalau user gak pilih Sub Kategori
// pas nambah barang, otomatis dibikinin/dipakein Sub Kategori senama kategorinya.
function find_or_create_default_subcategory(PDO $pdo, int $orgId, int $categoryId, string $categoryCode, string $categoryName): array
{
    $stmt = $pdo->prepare('SELECT id, code FROM product_subcategories WHERE organization_id=? AND category_id=? AND name=?');
    $stmt->execute([$orgId, $categoryId, $categoryName]);
    $row = $stmt->fetch();
    if ($row) return [(int) $row['id'], $row['code']];
    $code = next_subcategory_code($pdo, $orgId, $categoryId, $categoryCode);
    $pdo->prepare('INSERT INTO product_subcategories (organization_id, category_id, code, name) VALUES (?,?,?,?)')->execute([$orgId, $categoryId, $code, $categoryName]);
    return [(int) $pdo->lastInsertId(), $code];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';
    $orgId = $org['organization_id'];
    try {
        if ($action === 'add_category') {
            require_module_access('master_barang', 'can_create');
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException('Nama kategori wajib diisi.');
            $code = next_category_code($pdo, $orgId);
            $pdo->prepare('INSERT INTO product_categories (organization_id, code, name) VALUES (?,?,?)')->execute([$orgId, $code, $name]);
            $flash = ['ok', 'Kategori ditambahkan.'];
            header('Location: master-barang-kanban.php?category_id=' . $pdo->lastInsertId());
            exit;
        } elseif ($action === 'rename_category') {
            require_module_access('master_barang', 'can_edit');
            $id = (int) ($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException('Nama kategori wajib diisi.');
            $pdo->prepare('UPDATE product_categories SET name=? WHERE id=? AND organization_id=?')->execute([$name, $id, $orgId]);
            $flash = ['ok', 'Kategori diperbarui.'];
            header('Location: master-barang-kanban.php?category_id=' . $id);
            exit;
        } elseif ($action === 'delete_category') {
            require_module_access('master_barang', 'can_delete');
            $id = (int) ($_POST['category_id'] ?? 0);
            $subCount = $pdo->prepare('SELECT COUNT(*) c FROM product_subcategories WHERE category_id=? AND organization_id=?');
            $subCount->execute([$id, $orgId]);
            $prodCount = $pdo->prepare('SELECT COUNT(*) c FROM products WHERE category_id=? AND organization_id=?');
            $prodCount->execute([$id, $orgId]);
            if ((int) $subCount->fetch()['c'] > 0 || (int) $prodCount->fetch()['c'] > 0) {
                throw new RuntimeException('Kategori ini masih ada sub kategori/barang di dalamnya — kosongin dulu sebelum dihapus.');
            }
            $pdo->prepare('DELETE FROM product_categories WHERE id=? AND organization_id=?')->execute([$id, $orgId]);
            $flash = ['ok', 'Kategori dihapus.'];
        } elseif ($action === 'add_subcategory') {
            require_module_access('master_barang', 'can_create');
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException('Nama sub kategori wajib diisi.');
            $catStmt = $pdo->prepare('SELECT code FROM product_categories WHERE id=? AND organization_id=?');
            $catStmt->execute([$categoryId, $orgId]);
            $catRow = $catStmt->fetch();
            if (!$catRow) throw new RuntimeException('Kategori gak ditemukan.');
            $code = next_subcategory_code($pdo, $orgId, $categoryId, $catRow['code']);
            $pdo->prepare('INSERT INTO product_subcategories (organization_id, category_id, code, name) VALUES (?,?,?,?)')->execute([$orgId, $categoryId, $code, $name]);
            $flash = ['ok', 'Sub kategori ditambahkan.'];
            header('Location: master-barang-kanban.php?category_id=' . $categoryId . '&subcategory_id=' . $pdo->lastInsertId());
            exit;
        } elseif ($action === 'rename_subcategory') {
            require_module_access('master_barang', 'can_edit');
            $id = (int) ($_POST['subcategory_id'] ?? 0);
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException('Nama sub kategori wajib diisi.');
            $pdo->prepare('UPDATE product_subcategories SET name=? WHERE id=? AND organization_id=?')->execute([$name, $id, $orgId]);
            $flash = ['ok', 'Sub kategori diperbarui.'];
            header('Location: master-barang-kanban.php?category_id=' . $categoryId . '&subcategory_id=' . $id);
            exit;
        } elseif ($action === 'delete_subcategory') {
            require_module_access('master_barang', 'can_delete');
            $id = (int) ($_POST['subcategory_id'] ?? 0);
            $prodCount = $pdo->prepare('SELECT COUNT(*) c FROM products WHERE subcategory_id=? AND organization_id=?');
            $prodCount->execute([$id, $orgId]);
            if ((int) $prodCount->fetch()['c'] > 0) {
                throw new RuntimeException('Sub kategori ini masih ada barang di dalamnya — kosongin dulu sebelum dihapus.');
            }
            $pdo->prepare('DELETE FROM product_subcategories WHERE id=? AND organization_id=?')->execute([$id, $orgId]);
            $flash = ['ok', 'Sub kategori dihapus.'];
        } elseif ($action === 'add_product') {
            require_module_access('master_barang', 'can_create');
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0) ?: null;
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException('Nama barang wajib diisi.');
            $catStmt = $pdo->prepare('SELECT code, name FROM product_categories WHERE id=? AND organization_id=?');
            $catStmt->execute([$categoryId, $orgId]);
            $catRow = $catStmt->fetch();
            if (!$catRow) throw new RuntimeException('Kategori gak ditemukan.');
            if ($subcategoryId) {
                $subStmt = $pdo->prepare('SELECT code FROM product_subcategories WHERE id=? AND organization_id=?');
                $subStmt->execute([$subcategoryId, $orgId]);
                $subRow = $subStmt->fetch();
                if (!$subRow) throw new RuntimeException('Sub kategori gak ditemukan.');
                $parentCode = $subRow['code'];
            } else {
                [$subcategoryId, $parentCode] = find_or_create_default_subcategory($pdo, $orgId, $categoryId, $catRow['code'], $catRow['name']);
            }
            $code = next_product_code($pdo, $orgId, $parentCode);
            $pdo->prepare("INSERT INTO products (organization_id, name, unit, category, category_id, subcategory_id, code) VALUES (?,?,'pcs',?,?,?,?)")
                ->execute([$orgId, $name, $catRow['name'], $categoryId, $subcategoryId, $code]);
            $flash = ['ok', 'Barang ditambahkan.'];
            header('Location: master-barang-kanban.php?category_id=' . $categoryId . '&subcategory_id=' . ($subcategoryId ?: 0));
            exit;
        } elseif ($action === 'rename_product') {
            require_module_access('master_barang', 'can_edit');
            $id = (int) ($_POST['product_id'] ?? 0);
            $categoryId = (int) ($_POST['category_id'] ?? 0);
            $subcategoryId = (int) ($_POST['subcategory_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException('Nama barang wajib diisi.');
            $pdo->prepare('UPDATE products SET name=? WHERE id=? AND organization_id=?')->execute([$name, $id, $orgId]);
            $flash = ['ok', 'Barang diperbarui.'];
            header('Location: master-barang-kanban.php?category_id=' . $categoryId . '&subcategory_id=' . $subcategoryId);
            exit;
        } elseif ($action === 'delete_product') {
            require_module_access('master_barang', 'can_delete');
            $id = (int) ($_POST['product_id'] ?? 0);
            $photoStmt = $pdo->prepare('SELECT photo_path FROM products WHERE id=? AND organization_id=?');
            $photoStmt->execute([$id, $orgId]);
            $photoToDelete = $photoStmt->fetchColumn();
            $pdo->prepare('DELETE FROM products WHERE id=? AND organization_id=?')->execute([$id, $orgId]);
            delete_product_photo($photoToDelete ?: null);
            $flash = ['ok', 'Barang dihapus.'];
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }
}

$categories = $pdo->prepare('SELECT * FROM product_categories WHERE organization_id=? ORDER BY name');
$categories->execute([$org['organization_id']]);
$categories = $categories->fetchAll();

$activeCategoryId = (int) ($_GET['category_id'] ?? 0);
$activeCategory = null;
$subcategories = [];
$directProductCount = 0;
if ($activeCategoryId) {
    foreach ($categories as $c) { if ((int) $c['id'] === $activeCategoryId) { $activeCategory = $c; break; } }
    if ($activeCategory) {
        $subStmt = $pdo->prepare('SELECT * FROM product_subcategories WHERE organization_id=? AND category_id=? ORDER BY name');
        $subStmt->execute([$org['organization_id'], $activeCategoryId]);
        $subcategories = $subStmt->fetchAll();

        $dpStmt = $pdo->prepare('SELECT COUNT(*) c FROM products WHERE organization_id=? AND category_id=? AND subcategory_id IS NULL');
        $dpStmt->execute([$org['organization_id'], $activeCategoryId]);
        $directProductCount = (int) $dpStmt->fetch()['c'];
    } else {
        $activeCategoryId = 0;
    }
}

$activeSubcategoryId = isset($_GET['subcategory_id']) ? (int) $_GET['subcategory_id'] : null; // null = belum pilih, 0 = "tanpa sub kategori"
$activeSubcategory = null;
$products = [];
if ($activeCategoryId && $activeSubcategoryId !== null) {
    if ($activeSubcategoryId > 0) {
        foreach ($subcategories as $s) { if ((int) $s['id'] === $activeSubcategoryId) { $activeSubcategory = $s; break; } }
        if (!$activeSubcategory) $activeSubcategoryId = null;
    }
    if ($activeSubcategoryId !== null) {
        if ($activeSubcategoryId > 0) {
            $prodStmt = $pdo->prepare('SELECT * FROM products WHERE organization_id=? AND subcategory_id=? ORDER BY name');
            $prodStmt->execute([$org['organization_id'], $activeSubcategoryId]);
        } else {
            $prodStmt = $pdo->prepare('SELECT * FROM products WHERE organization_id=? AND category_id=? AND subcategory_id IS NULL ORDER BY name');
            $prodStmt->execute([$org['organization_id'], $activeCategoryId]);
        }
        $products = $prodStmt->fetchAll();
    }
}
?>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<style>
  .mbk-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; }
  .mbk-head h2 { margin:0 0 4px; font-size:20px; }
  .mbk-head p { margin:0; font-size:13px; color:var(--ink-muted); }
  .mbk-board { display:flex; gap:14px; align-items:flex-start; overflow-x:auto; }
  .mbk-col { flex:0 0 300px; background:var(--surface); border:1px solid var(--border); border-radius:12px; box-shadow:var(--shadow-card); display:flex; flex-direction:column; max-height:calc(100vh - 220px); }
  .mbk-col-head { padding:12px 14px; border-bottom:1px solid var(--border); font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--ink-muted); }
  .mbk-col-body { flex:1; overflow-y:auto; padding:8px; }
  .mbk-card { display:flex; align-items:center; gap:8px; padding:9px 10px; border-radius:8px; margin-bottom:6px; border:1px solid transparent; cursor:pointer; }
  .mbk-card:hover { background:oklch(0.97 0.003 90); }
  .mbk-card.active { background:oklch(0.94 0.03 250); border-color:var(--accent); }
  .mbk-card-virtual { border-style:dashed; border-color:var(--border-strong); }
  .mbk-card-virtual .name { font-style:italic; color:var(--ink-muted); }
  .mbk-card-virtual.active { background:oklch(0.96 0.01 250); }
  .mbk-card .code { font-size:9.5px; font-weight:700; color:var(--ink-muted); display:block; }
  .mbk-card .name { font-size:13px; font-weight:600; display:block; }
  .mbk-card .main { flex:1; min-width:0; }
  .mbk-card .main a { text-decoration:none; color:inherit; display:block; }
  .mbk-card .actions { display:flex; gap:4px; flex-shrink:0; }
  .mbk-card .actions button { border:none; background:transparent; cursor:pointer; font-size:12px; padding:2px 4px; color:var(--ink-muted); border-radius:4px; }
  .mbk-card .actions button:hover { background:oklch(0.9 0.05 30); }
  .mbk-empty { padding:16px 10px; text-align:center; font-size:12px; color:var(--ink-muted); }
  .mbk-add-form { padding:8px; border-top:1px solid var(--border); display:flex; gap:6px; }
  .mbk-add-form input { flex:1; padding:7px 9px; border:1px solid var(--border); border-radius:6px; font-size:12.5px; }
  .mbk-add-form button { padding:7px 10px; border-radius:6px; border:none; background:var(--accent); color:#fff; font-size:12.5px; cursor:pointer; white-space:nowrap; }
  .mbk-rename-row { display:none; padding:6px 10px; gap:6px; }
  .mbk-rename-row.show { display:flex; }
  .mbk-rename-row input { flex:1; padding:5px 7px; border:1px solid var(--border); border-radius:5px; font-size:12px; }
  .mbk-rename-row button { padding:5px 8px; border-radius:5px; border:none; background:var(--accent); color:#fff; font-size:11px; cursor:pointer; }
</style>

<div class="mbk-head">
  <div>
    <h2>Master Barang</h2>
    <p>Kategori → Sub Kategori → Barang. Klik kolom buat drill-down, ✎ buat ganti nama, 🗑 buat hapus (kosongin isinya dulu).</p>
  </div>
  <a class="btn btn-sm btn-ghost" href="master-barang-kanban.php">↺ Reset</a>
</div>

<div class="mbk-board">
  <div class="mbk-col">
    <div class="mbk-col-head">1. Kategori</div>
    <div class="mbk-col-body">
      <?php foreach ($categories as $c): ?>
        <div class="mbk-card <?= (int) $c['id'] === $activeCategoryId ? 'active' : '' ?>" data-row="cat-<?= $c['id'] ?>">
          <div class="main">
            <a href="master-barang-kanban.php?category_id=<?= $c['id'] ?>">
              <span class="code"><?= htmlspecialchars($c['code']) ?></span>
              <span class="name"><?= htmlspecialchars($c['name']) ?></span>
            </a>
          </div>
          <?php if (has_access('master_barang', 'can_edit')): ?>
            <div class="actions">
              <button type="button" onclick="mbkToggleRename('cat-<?= $c['id'] ?>')">✎</button>
              <?php if (has_access('master_barang', 'can_delete')): ?>
                <button type="button" onclick="if(confirm('Hapus kategori ini?')) __submitDeleteForm('delete_category', {category_id: <?= $c['id'] ?>})">🗑</button>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="mbk-rename-row" id="rename-cat-<?= $c['id'] ?>">
          <form method="post" style="display:flex; gap:6px; flex:1;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="rename_category">
            <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
            <input type="text" name="name" value="<?= htmlspecialchars($c['name']) ?>" required>
            <button type="submit">✓</button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$categories): ?><div class="mbk-empty">Belum ada kategori.</div><?php endif; ?>
    </div>
    <?php if (has_access('master_barang', 'can_create')): ?>
      <form method="post" class="mbk-add-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <input type="text" name="name" placeholder="+ Kategori baru..." required>
        <button type="submit">Tambah</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($activeCategory): ?>
    <div class="mbk-col">
      <div class="mbk-col-head">2. Sub Kategori — <?= htmlspecialchars($activeCategory['name']) ?></div>
      <div class="mbk-col-body">
        <?php if ($directProductCount > 0): ?>
          <div class="mbk-card mbk-card-virtual <?= $activeSubcategoryId === 0 ? 'active' : '' ?>" title="Ini bukan sub kategori beneran — cuma kumpulan barang yang belum dimasukin ke sub kategori manapun. Bikin sub kategori baru di bawah, terus pindahin barangnya.">
            <div class="main">
              <a href="master-barang-kanban.php?category_id=<?= $activeCategoryId ?>&subcategory_id=0">
                <span class="code">— otomatis —</span>
                <span class="name">Tanpa Sub Kategori (<?= $directProductCount ?> barang)</span>
              </a>
            </div>
          </div>
        <?php endif; ?>
        <?php foreach ($subcategories as $s): ?>
          <div class="mbk-card <?= (int) $s['id'] === $activeSubcategoryId ? 'active' : '' ?>" data-row="sub-<?= $s['id'] ?>">
            <div class="main">
              <a href="master-barang-kanban.php?category_id=<?= $activeCategoryId ?>&subcategory_id=<?= $s['id'] ?>">
                <span class="code"><?= htmlspecialchars($s['code']) ?></span>
                <span class="name"><?= htmlspecialchars($s['name']) ?></span>
              </a>
            </div>
            <?php if (has_access('master_barang', 'can_edit')): ?>
              <div class="actions">
                <button type="button" onclick="mbkToggleRename('sub-<?= $s['id'] ?>')">✎</button>
                <?php if (has_access('master_barang', 'can_delete')): ?>
                  <button type="button" onclick="if(confirm('Hapus sub kategori ini?')) __submitDeleteForm('delete_subcategory', {subcategory_id: <?= $s['id'] ?>, category_id: <?= $activeCategoryId ?>})">🗑</button>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="mbk-rename-row" id="rename-sub-<?= $s['id'] ?>">
            <form method="post" style="display:flex; gap:6px; flex:1;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="rename_subcategory">
              <input type="hidden" name="subcategory_id" value="<?= $s['id'] ?>">
              <input type="hidden" name="category_id" value="<?= $activeCategoryId ?>">
              <input type="text" name="name" value="<?= htmlspecialchars($s['name']) ?>" required>
              <button type="submit">✓</button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if (!$subcategories && !$directProductCount): ?><div class="mbk-empty">Belum ada sub kategori.</div><?php endif; ?>
      </div>
      <?php if (has_access('master_barang', 'can_create')): ?>
        <form method="post" class="mbk-add-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_subcategory">
          <input type="hidden" name="category_id" value="<?= $activeCategoryId ?>">
          <input type="text" name="name" placeholder="+ Sub kategori baru..." required>
          <button type="submit">Tambah</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($activeCategory && $activeSubcategoryId !== null): ?>
    <div class="mbk-col">
      <div class="mbk-col-head">3. Barang — <?= $activeSubcategory ? htmlspecialchars($activeSubcategory['name']) : 'Tanpa Sub Kategori' ?></div>
      <div class="mbk-col-body">
        <?php foreach ($products as $p): ?>
          <div class="mbk-card" data-row="prod-<?= $p['id'] ?>">
            <div class="main">
              <a href="manufaktur-product-info.php?id=<?= $p['id'] ?>" onclick="return mbkOpenProductInfo(event, <?= $p['id'] ?>)">
                <span class="code"><?= htmlspecialchars($p['code'] ?: '—') ?></span>
                <span class="name"><?= htmlspecialchars($p['name']) ?></span>
              </a>
            </div>
            <div class="actions">
              <button type="button" title="Detail Lengkap" onclick="mbkOpenProductInfo(event, <?= $p['id'] ?>)">🔍</button>
              <?php if (has_access('master_barang', 'can_edit')): ?>
                <button type="button" title="Ganti Nama" onclick="mbkToggleRename('prod-<?= $p['id'] ?>')">✎</button>
                <?php if (has_access('master_barang', 'can_delete')): ?>
                  <button type="button" title="Hapus" onclick="if(confirm('Hapus barang ini? Gak bisa dibalikin.')) __submitDeleteForm('delete_product', {product_id: <?= $p['id'] ?>, category_id: <?= $activeCategoryId ?>, subcategory_id: <?= $activeSubcategoryId ?>})">🗑</button>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="mbk-rename-row" id="rename-prod-<?= $p['id'] ?>">
            <form method="post" style="display:flex; gap:6px; flex:1;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="rename_product">
              <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="category_id" value="<?= $activeCategoryId ?>">
              <input type="hidden" name="subcategory_id" value="<?= $activeSubcategoryId ?>">
              <input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required>
              <button type="submit">✓</button>
            </form>
          </div>
        <?php endforeach; ?>
        <?php if (!$products): ?><div class="mbk-empty">Belum ada barang.</div><?php endif; ?>
      </div>
      <?php if (has_access('master_barang', 'can_create')): ?>
        <form method="post" class="mbk-add-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_product">
          <input type="hidden" name="category_id" value="<?= $activeCategoryId ?>">
          <input type="hidden" name="subcategory_id" value="<?= $activeSubcategoryId ?>">
          <input type="text" name="name" placeholder="+ Barang baru..." required>
          <button type="submit">Tambah</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="modal-scrim" id="mbk-pinfo-modal">
  <div class="modal-card modal-card-lg">
    <div class="modal-head">
      <h3>Detail Barang</h3>
      <button type="button" class="modal-close" data-close-modal="mbk-pinfo-modal" onclick="mbkCloseProductInfo()">✕</button>
    </div>
    <div class="modal-body">
      <iframe id="mbk-pinfo-iframe" src="about:blank"></iframe>
    </div>
  </div>
</div>

<script>
function mbkToggleRename(rowKey) {
  var el = document.getElementById('rename-' + rowKey);
  if (el) el.classList.toggle('show');
}

function mbkOpenProductInfo(e, id) {
  if (e && (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1)) return true; // biar tetep bisa buka tab baru
  if (e && e.preventDefault) e.preventDefault();
  document.getElementById('mbk-pinfo-iframe').src = 'manufaktur-product-info.php?id=' + id + '&edit=1&embed=1';
  document.getElementById('mbk-pinfo-modal').classList.add('open');
  return false;
}
function mbkCloseProductInfo() {
  document.getElementById('mbk-pinfo-modal').classList.remove('open');
  document.getElementById('mbk-pinfo-iframe').src = 'about:blank';
}
document.getElementById('mbk-pinfo-modal').addEventListener('click', function (e) {
  if (e.target === this) mbkCloseProductInfo();
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
