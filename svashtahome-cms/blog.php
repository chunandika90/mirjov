<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/upload.php';
require_once __DIR__ . '/../shared/helpers.php';

$admin = require_login();
$pdo = db();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'save_post': {
                $id = (int) ($_POST['post_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $excerpt = trim($_POST['excerpt'] ?? '');
                $content = $_POST['content'] ?? '';
                $seoTitle = trim($_POST['seo_title'] ?? '') ?: null;
                $seoDescription = trim($_POST['seo_description'] ?? '') ?: null;
                if ($title === '') throw new RuntimeException('Judul wajib diisi.');

                $coverImage = $_POST['existing_cover'] ?? null;
                if (!empty($_FILES['cover']['name'])) {
                    $coverImage = handle_image_upload($_FILES['cover'], 'blog/cover', $title);
                } elseif ($id === 0) {
                    throw new RuntimeException('Cover image wajib diupload.');
                }

                if ($id > 0) {
                    // Slug dibawa dari hidden field "existing_slug" biar URL post stabil walau judul diedit.
                    $slug = $_POST['existing_slug'] ?? unique_slug($pdo, 'blog_posts', $title, $id);
                    $pdo->prepare('UPDATE blog_posts SET title=?, slug=?, excerpt=?, content=?, seo_title=?, seo_description=?, cover_image=?, updated_by=? WHERE id=?')
                        ->execute([$title, $slug, $excerpt, $content, $seoTitle, $seoDescription, $coverImage, $admin['id'], $id]);
                    $postId = $id;
                } else {
                    $slug = unique_slug($pdo, 'blog_posts', $title);
                    $pdo->prepare('INSERT INTO blog_posts (title, slug, excerpt, content, seo_title, seo_description, cover_image, published_at, created_by, updated_by) VALUES (?,?,?,?,?,?,?,NOW(),?,?)')
                        ->execute([$title, $slug, $excerpt, $content, $seoTitle, $seoDescription, $coverImage, $admin['id'], $admin['id']]);
                    $postId = (int) $pdo->lastInsertId();
                }

                if (!empty($_FILES['gallery']['name'][0])) {
                    $count = count($_FILES['gallery']['name']);
                    for ($i = 0; $i < $count; $i++) {
                        if ($_FILES['gallery']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        $single = [
                            'name' => $_FILES['gallery']['name'][$i], 'type' => $_FILES['gallery']['type'][$i],
                            'tmp_name' => $_FILES['gallery']['tmp_name'][$i], 'error' => $_FILES['gallery']['error'][$i],
                            'size' => $_FILES['gallery']['size'][$i],
                        ];
                        $path = handle_image_upload($single, 'blog/gallery', $title);
                        $pdo->prepare('INSERT INTO blog_gallery (blog_post_id, image_path) VALUES (?,?)')->execute([$postId, $path]);
                    }
                }
                $flash = ['ok', 'Post tersimpan.'];
                break;
            }

            case 'delete_post': {
                $id = (int) ($_POST['post_id'] ?? 0);
                $row = $pdo->prepare('SELECT cover_image FROM blog_posts WHERE id=?');
                $row->execute([$id]);
                $gallery = $pdo->prepare('SELECT image_path FROM blog_gallery WHERE blog_post_id=?');
                $gallery->execute([$id]);
                foreach ($gallery->fetchAll() as $g) delete_uploaded_image($g['image_path']);
                if ($existing = $row->fetch()) delete_uploaded_image($existing['cover_image']);
                $pdo->prepare('DELETE FROM blog_posts WHERE id=?')->execute([$id]);
                $flash = ['ok', 'Post dihapus.'];
                break;
            }

            case 'delete_post_photo': {
                $photoId = (int) ($_POST['photo_id'] ?? 0);
                $row = $pdo->prepare('SELECT image_path FROM blog_gallery WHERE id=?');
                $row->execute([$photoId]);
                if ($existing = $row->fetch()) {
                    delete_uploaded_image($existing['image_path']);
                    $pdo->prepare('DELETE FROM blog_gallery WHERE id=?')->execute([$photoId]);
                }
                $flash = ['ok', 'Foto dihapus.'];
                break;
            }
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }

    $_SESSION['flash'] = $flash;
    header('Location: blog.php');
    exit;
}

start_admin_session();
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$posts = $pdo->query(
    'SELECT p.*, cu.username AS created_by_name, uu.username AS updated_by_name
     FROM blog_posts p
     LEFT JOIN admin_users cu ON cu.id = p.created_by
     LEFT JOIN admin_users uu ON uu.id = p.updated_by
     ORDER BY p.created_at DESC'
)->fetchAll();

$editingPost = null;
if (!empty($_GET['edit_post'])) {
    $stmt = $pdo->prepare(
        'SELECT p.*, cu.username AS created_by_name, uu.username AS updated_by_name
         FROM blog_posts p
         LEFT JOIN admin_users cu ON cu.id = p.created_by
         LEFT JOIN admin_users uu ON uu.id = p.updated_by
         WHERE p.id=?'
    );
    $stmt->execute([(int) $_GET['edit_post']]);
    $editingPost = $stmt->fetch() ?: null;
    if ($editingPost) {
        $g = $pdo->prepare('SELECT * FROM blog_gallery WHERE blog_post_id=? ORDER BY sort_order');
        $g->execute([$editingPost['id']]);
        $editingPost['gallery'] = $g->fetchAll();
    }
}

$pageTitle = 'Blog';
$pageSubtitle = 'Kelola artikel blog';
$activeNav = 'blog';
require __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?><div class="flash <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="section-head" style="margin-bottom:18px;">
  <div></div>
  <button class="btn" data-open-modal="post-modal" onclick="document.getElementById('post-form').reset(); document.getElementById('post_id').value=''; document.getElementById('post-modal-title').textContent='Add New Post'; document.getElementById('post-gallery-existing').innerHTML='';">+ Add New Post</button>
</div>

<?php if (!$posts): ?>
  <div class="empty-row">Belum ada post.</div>
<?php else: ?>
  <section class="section-card">
    <?php foreach ($posts as $p): ?>
      <div class="item-row">
        <img class="thumb" src="<?= htmlspecialchars(image_thumb_url($p['cover_image'])) ?>" alt="">
        <div class="meta">
          <div class="t"><?= htmlspecialchars($p['title']) ?></div>
          <div class="d"><?= htmlspecialchars(date('d M Y', strtotime($p['published_at'] ?? $p['created_at']))) ?></div>
        </div>
        <div class="row-actions">
          <a class="btn btn-sm btn-ghost" href="blog.php?edit_post=<?= $p['id'] ?>#post-modal">EDIT</a>
          <form method="post" onsubmit="return confirm('Hapus post ini?');" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_post">
            <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">DELETE</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<div class="modal-scrim<?= $editingPost ? ' open' : '' ?>" id="post-modal">
  <div class="modal-card" style="width:640px;">
    <div class="modal-head">
      <div>
        <h3 id="post-modal-title"><?= $editingPost ? 'Edit Post' : 'Add New Post' ?></h3>
        <?php if ($editingPost): ?><?= render_audit_trail($editingPost['created_by_name'], $editingPost['created_at'], $editingPost['updated_by_name'], $editingPost['updated_at']) ?><?php endif; ?>
      </div>
      <button class="modal-close" data-close-modal="post-modal">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" id="post-form">
      <div class="modal-body form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_post">
        <input type="hidden" name="post_id" id="post_id" value="<?= $editingPost['id'] ?? '' ?>">
        <input type="hidden" name="existing_slug" value="<?= htmlspecialchars($editingPost['slug'] ?? '') ?>">
        <input type="hidden" name="existing_cover" value="<?= htmlspecialchars($editingPost['cover_image'] ?? '') ?>">

        <div class="field">
          <label>Cover Image</label>
          <label class="dropzone">
            <input type="file" name="cover" accept="image/*" style="display:none;" data-preview-target="post-cover-preview">
            <img id="post-cover-preview" class="preview" src="<?= $editingPost ? htmlspecialchars(image_url($editingPost['cover_image'])) : '' ?>" style="<?= $editingPost ? '' : 'display:none;' ?>">
            <span>Klik untuk pilih cover</span>
          </label>
        </div>

        <div class="field">
          <label>In-Article Photo Gallery</label>
          <div class="gallery-addon" id="post-gallery-existing" style="margin-bottom:8px;">
            <?php if ($editingPost): foreach ($editingPost['gallery'] as $g): ?>
              <div class="gallery-tile">
                <img src="<?= htmlspecialchars(image_thumb_url($g['image_path'])) ?>" alt="">
                <button type="button" class="remove-x" onclick="__submitDeleteForm('delete_post_photo', {photo_id: <?= (int) $g['id'] ?>})">&times;</button>
              </div>
            <?php endforeach; endif; ?>
          </div>
          <label class="dropzone">
            <input type="file" name="gallery[]" accept="image/*" multiple style="display:none;" onchange="this.nextElementSibling.textContent=this.files.length + ' foto dipilih';">
            <span>+ Tambah foto</span>
          </label>
        </div>

        <div class="field"><label>Title</label><input type="text" name="title" value="<?= htmlspecialchars($editingPost['title'] ?? '') ?>" required></div>
        <div class="field"><label>Excerpt</label><textarea name="excerpt"><?= htmlspecialchars($editingPost['excerpt'] ?? '') ?></textarea></div>
        <div class="field"><label>Content</label><textarea name="content" style="min-height:160px;"><?= htmlspecialchars($editingPost['content'] ?? '') ?></textarea></div>

        <div class="seo-block">
          <div>
            <div class="seo-heading">SEO (Opsional)</div>
            <div class="seo-intro">Kosongin biar otomatis dari judul + excerpt di atas. Isi kalau mau kontrol sendiri buat hasil pencarian Google & preview link share.</div>
          </div>
          <div class="field">
            <label>SEO Title</label>
            <input type="text" name="seo_title" value="<?= htmlspecialchars($editingPost['seo_title'] ?? '') ?>" placeholder="Judul Artikel — Svashta Home">
            <div class="seo-hint">Idealnya 50–60 karakter.</div>
          </div>
          <div class="field">
            <label>SEO Description</label>
            <textarea name="seo_description" placeholder="Muncul di hasil pencarian Google & preview link (maks ~160 karakter)"><?= htmlspecialchars($editingPost['seo_description'] ?? '') ?></textarea>
            <div class="seo-hint">Maks ~160 karakter.</div>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close-modal="post-modal">Cancel</button>
        <button type="submit" class="btn">Save</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
