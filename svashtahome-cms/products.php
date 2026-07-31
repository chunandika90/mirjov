<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/upload.php';
require_once __DIR__ . '/../shared/helpers.php';

$admin = require_login();
$pdo = db();
$flash = null;

const PRODUCT_CATEGORIES = ['sofa','table','chair','bed','cabinet','outdoor','collections','collaborations'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save_product': {
                $id = (int) ($_POST['product_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $category = $_POST['category'] ?? '';
                $price = $_POST['price'] !== '' ? (float) $_POST['price'] : null;
                $materials = trim($_POST['materials'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $seoTitle = trim($_POST['seo_title'] ?? '') ?: null;
                $seoDescription = trim($_POST['seo_description'] ?? '') ?: null;

                if ($name === '') throw new RuntimeException('Nama produk wajib diisi.');
                if (!in_array($category, PRODUCT_CATEGORIES, true)) throw new RuntimeException('Kategori tidak valid.');

                $coverImage = $_POST['existing_cover'] ?? null;
                if (!empty($_FILES['cover']['name'])) {
                    $coverImage = handle_image_upload($_FILES['cover'], 'products/cover', $name);
                } elseif ($id === 0) {
                    throw new RuntimeException('Cover photo wajib diupload.');
                }

                if ($id > 0) {
                    // Slug SENGAJA dibawa dari hidden field "existing_slug", bukan diregenerasi
                    // dari nama — biar URL produk tetap stabil walau namanya diedit (link lama,
                    // termasuk redirect halaman lama, tidak putus).
                    $slug = $_POST['existing_slug'] ?? unique_slug($pdo, 'products', $name, $id);
                    $stmt = $pdo->prepare('UPDATE products SET name=?, slug=?, category=?, price=?, materials=?, description=?, seo_title=?, seo_description=?, cover_image=?, updated_by=? WHERE id=?');
                    $stmt->execute([$name, $slug, $category, $price, $materials, $description, $seoTitle, $seoDescription, $coverImage, $admin['id'], $id]);
                    $productId = $id;
                } else {
                    $slug = unique_slug($pdo, 'products', $name);
                    $stmt = $pdo->prepare('INSERT INTO products (name, slug, category, price, materials, description, seo_title, seo_description, cover_image, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([$name, $slug, $category, $price, $materials, $description, $seoTitle, $seoDescription, $coverImage, $admin['id'], $admin['id']]);
                    $productId = (int) $pdo->lastInsertId();
                }

                // Gallery add-on (bisa lebih dari satu file sekaligus)
                if (!empty($_FILES['gallery']['name'][0])) {
                    $count = count($_FILES['gallery']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if ($_FILES['gallery']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $single = [
                            'name' => $_FILES['gallery']['name'][$i], 'type' => $_FILES['gallery']['type'][$i],
                            'tmp_name' => $_FILES['gallery']['tmp_name'][$i], 'error' => $_FILES['gallery']['error'][$i],
                            'size' => $_FILES['gallery']['size'][$i],
                        ];
                        $path = handle_image_upload($single, 'products/gallery', $name);
                        $pdo->prepare('INSERT INTO product_gallery (product_id, image_path) VALUES (?,?)')->execute([$productId, $path]);
                    }
                }

                // Highlight dinamis (bisa berapa aja) — replace semua tiap save
                $pdo->prepare('DELETE FROM product_highlights WHERE product_id=?')->execute([$productId]);
                $highlightLabels = $_POST['highlight_label'] ?? [];
                $highlightTexts = $_POST['highlight_text'] ?? [];
                $order = 0;
                foreach ($highlightLabels as $i => $rawLabel) {
                    $label = trim($rawLabel);
                    $text = trim($highlightTexts[$i] ?? '');
                    if ($label === '' && $text === '') continue;
                    $pdo->prepare('INSERT INTO product_highlights (product_id, label, text, sort_order) VALUES (?,?,?,?)')
                        ->execute([$productId, $label, $text, $order++]);
                }

                $flash = ['ok', 'Produk tersimpan.'];
                break;
            }

            case 'delete_product': {
                $id = (int) ($_POST['product_id'] ?? 0);
                $row = $pdo->prepare('SELECT cover_image FROM products WHERE id=?');
                $row->execute([$id]);
                $gallery = $pdo->prepare('SELECT image_path FROM product_gallery WHERE product_id=?');
                $gallery->execute([$id]);
                foreach ($gallery->fetchAll() as $g) delete_uploaded_image($g['image_path']);
                if ($existing = $row->fetch()) delete_uploaded_image($existing['cover_image']);
                $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
                $flash = ['ok', 'Produk dihapus.'];
                break;
            }

            case 'delete_product_photo': {
                $photoId = (int) ($_POST['photo_id'] ?? 0);
                $row = $pdo->prepare('SELECT image_path FROM product_gallery WHERE id=?');
                $row->execute([$photoId]);
                if ($existing = $row->fetch()) {
                    delete_uploaded_image($existing['image_path']);
                    $pdo->prepare('DELETE FROM product_gallery WHERE id=?')->execute([$photoId]);
                }
                $flash = ['ok', 'Foto dihapus.'];
                break;
            }
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }

    $_SESSION['flash'] = $flash;
    $redirect = 'products.php' . (!empty($_GET['category']) ? '?category=' . urlencode($_GET['category']) : '');
    header('Location: ' . $redirect);
    exit;
}

start_admin_session();
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$productSelect = 'SELECT p.*, cu.username AS created_by_name, uu.username AS updated_by_name
     FROM products p
     LEFT JOIN admin_users cu ON cu.id = p.created_by
     LEFT JOIN admin_users uu ON uu.id = p.updated_by';

$activeCategory = $_GET['category'] ?? '';
if ($activeCategory && in_array($activeCategory, PRODUCT_CATEGORIES, true)) {
    $stmt = $pdo->prepare($productSelect . ' WHERE p.category=? ORDER BY p.created_at DESC');
    $stmt->execute([$activeCategory]);
    $products = $stmt->fetchAll();
} else {
    $activeCategory = '';
    $products = $pdo->query($productSelect . ' ORDER BY p.created_at DESC')->fetchAll();
}

$editingProduct = null;
if (!empty($_GET['edit_product'])) {
    $stmt = $pdo->prepare($productSelect . ' WHERE p.id=?');
    $stmt->execute([(int) $_GET['edit_product']]);
    $editingProduct = $stmt->fetch() ?: null;
    if ($editingProduct) {
        $g = $pdo->prepare('SELECT * FROM product_gallery WHERE product_id=? ORDER BY sort_order');
        $g->execute([$editingProduct['id']]);
        $editingProduct['gallery'] = $g->fetchAll();
        $h = $pdo->prepare('SELECT * FROM product_highlights WHERE product_id=? ORDER BY sort_order');
        $h->execute([$editingProduct['id']]);
        $editingProduct['highlights'] = $h->fetchAll();
    }
}
$hl = $editingProduct['highlights'] ?? [];

$pageTitle = 'Products';
$pageSubtitle = 'Kelola katalog produk per kategori';
$activeNav = 'products';
require __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?><div class="flash <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="section-head" style="margin-bottom:18px;">
  <div></div>
  <button class="btn" data-open-modal="product-modal" onclick="document.getElementById('product-form').reset(); document.getElementById('product_id').value=''; document.getElementById('product-modal-title').textContent='Add Product'; document.getElementById('product-gallery-existing').innerHTML=''; document.getElementById('highlights-list').innerHTML='';">+ Add Product</button>
</div>

<div class="chip-row">
  <a class="chip<?= $activeCategory === '' ? ' active' : '' ?>" href="products.php">All</a>
  <?php foreach (PRODUCT_CATEGORIES as $cat): ?>
    <a class="chip<?= $activeCategory === $cat ? ' active' : '' ?>" href="products.php?category=<?= $cat ?>"><?= ucfirst($cat) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$products): ?>
  <div class="empty-row">Belum ada produk di kategori ini.</div>
<?php else: ?>
  <div class="card-grid">
    <?php foreach ($products as $p): ?>
      <div class="entity-card">
        <div class="photo-wrap">
          <img src="<?= htmlspecialchars(image_thumb_url($p['cover_image'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
          <span class="badge-overlay"><?= htmlspecialchars(ucfirst($p['category'])) ?></span>
        </div>
        <div class="card-info">
          <div class="t"><?= htmlspecialchars($p['name']) ?></div>
          <div class="d"><?= $p['price'] ? 'Rp ' . number_format((float) $p['price'], 0, ',', '.') : 'Harga belum diisi' ?></div>
        </div>
        <div class="card-actions">
          <a class="btn btn-sm btn-ghost" href="products.php?edit_product=<?= $p['id'] ?><?= $activeCategory ? '&category=' . $activeCategory : '' ?>#product-modal">EDIT</a>
          <form method="post" onsubmit="return confirm('Hapus produk ini?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_product">
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">DELETE</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ============ MODAL: Product ============ -->
<div class="modal-scrim<?= $editingProduct ? ' open' : '' ?>" id="product-modal">
  <div class="modal-card" style="width:640px;">
    <div class="modal-head">
      <div>
        <h3 id="product-modal-title"><?= $editingProduct ? 'Edit Product' : 'Add Product' ?></h3>
        <?php if ($editingProduct): ?><?= render_audit_trail($editingProduct['created_by_name'], $editingProduct['created_at'], $editingProduct['updated_by_name'], $editingProduct['updated_at']) ?><?php endif; ?>
      </div>
      <button class="modal-close" data-close-modal="product-modal">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" id="product-form">
      <div class="modal-body form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="product_id" id="product_id" value="<?= $editingProduct['id'] ?? '' ?>">
        <input type="hidden" name="existing_slug" value="<?= htmlspecialchars($editingProduct['slug'] ?? '') ?>">
        <input type="hidden" name="existing_cover" value="<?= htmlspecialchars($editingProduct['cover_image'] ?? '') ?>">

        <div class="field">
          <label>Cover Photo</label>
          <label class="dropzone">
            <input type="file" name="cover" accept="image/*" style="display:none;" data-preview-target="product-cover-preview">
            <img id="product-cover-preview" class="preview" src="<?= $editingProduct ? htmlspecialchars(image_url($editingProduct['cover_image'])) : '' ?>" style="<?= $editingProduct ? '' : 'display:none;' ?>">
            <span>Klik untuk pilih cover</span>
          </label>
        </div>

        <div class="field">
          <label>Detail Gallery</label>
          <div class="gallery-addon" id="product-gallery-existing" style="margin-bottom:8px;">
            <?php if ($editingProduct): foreach ($editingProduct['gallery'] as $g): ?>
              <div class="gallery-tile">
                <img src="<?= htmlspecialchars(image_thumb_url($g['image_path'])) ?>" alt="">
                <button type="button" class="remove-x" onclick="__submitDeleteForm('delete_product_photo', {photo_id: <?= (int) $g['id'] ?>})">&times;</button>
              </div>
            <?php endforeach; endif; ?>
          </div>
          <label class="dropzone">
            <input type="file" name="gallery[]" accept="image/*" multiple style="display:none;" onchange="this.nextElementSibling.textContent=this.files.length + ' foto dipilih';">
            <span>+ Tambah foto galeri</span>
          </label>
        </div>

        <div class="field-row" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
          <div class="field">
            <label>Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editingProduct['name'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>Type / Category</label>
            <select name="category" required>
              <option value="">— pilih —</option>
              <?php foreach (PRODUCT_CATEGORIES as $cat): ?>
                <option value="<?= $cat ?>" <?= ($editingProduct['category'] ?? '') === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label>Price (Rp)</label>
          <input type="number" name="price" step="1000" value="<?= htmlspecialchars($editingProduct['price'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Materials</label>
          <input type="text" name="materials" value="<?= htmlspecialchars($editingProduct['materials'] ?? '') ?>" placeholder="mis. Kayu jati, HPL import, Hettich soft-close">
        </div>
        <div class="field">
          <label>Description</label>
          <textarea name="description"><?= htmlspecialchars($editingProduct['description'] ?? '') ?></textarea>
        </div>

        <div class="seo-block">
          <div>
            <div class="seo-heading">SEO (Opsional)</div>
            <div class="seo-intro">Kosongin biar otomatis dari nama + deskripsi di atas. Isi kalau mau kontrol sendiri buat hasil pencarian Google & preview link share (WhatsApp/Instagram).</div>
          </div>
          <div class="field">
            <label>SEO Title</label>
            <input type="text" name="seo_title" value="<?= htmlspecialchars($editingProduct['seo_title'] ?? '') ?>" placeholder="Nama Produk — Svashta Home">
            <div class="seo-hint">Judul di tab browser &amp; hasil pencarian Google. Idealnya 50–60 karakter. <b>Contoh:</b> "Anang Bed — Svashta Home"</div>
          </div>
          <div class="field">
            <label>SEO Description</label>
            <textarea name="seo_description" placeholder="Muncul di hasil pencarian Google &amp; preview link (maks ~160 karakter)"><?= htmlspecialchars($editingProduct['seo_description'] ?? '') ?></textarea>
            <div class="seo-hint">Ringkasan 1–2 kalimat di bawah judul. Maks ~160 karakter — bikin orang penasaran klik.</div>
          </div>
        </div>

        <div class="field">
          <label>Product Highlights</label>
          <div id="highlights-list">
            <?php foreach ($hl as $h): ?>
              <div class="highlight-block" style="position:relative;">
                <button type="button" onclick="this.closest('.highlight-block').remove()" style="position:absolute; top:10px; right:10px; background:none; border:none; color:var(--danger); font-size:11px; font-weight:700; cursor:pointer;">✕ Hapus</button>
                <input type="text" name="highlight_label[]" placeholder="Label, mis. MEASUREMENTS" value="<?= htmlspecialchars($h['label']) ?>" style="margin-bottom:8px; padding-right:70px;">
                <textarea name="highlight_text[]" placeholder="Deskripsi singkat"><?= htmlspecialchars($h['text']) ?></textarea>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="add-item-btn" style="margin-bottom:0;" onclick="__addHighlightRow()">+ Tambah Highlight</div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close-modal="product-modal">Cancel</button>
        <button type="submit" class="btn">Save</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
