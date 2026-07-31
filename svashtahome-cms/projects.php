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
            case 'save_project': {
                $id = (int) ($_POST['project_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $location = trim($_POST['location'] ?? '');
                $excerpt = trim($_POST['excerpt'] ?? '') ?: null;
                $collection = trim($_POST['collection'] ?? '');
                $story = trim($_POST['story'] ?? '');
                $seoTitle = trim($_POST['seo_title'] ?? '') ?: null;
                $seoDescription = trim($_POST['seo_description'] ?? '') ?: null;
                if ($name === '') throw new RuntimeException('Nama proyek wajib diisi.');

                $coverImage = $_POST['existing_cover'] ?? null;
                if (!empty($_FILES['cover']['name'])) {
                    $coverImage = handle_image_upload($_FILES['cover'], 'projects/cover', $name);
                } elseif ($id === 0) {
                    throw new RuntimeException('Cover photo wajib diupload.');
                }

                if ($id > 0) {
                    // Slug dibawa dari hidden field "existing_slug" biar URL proyek stabil walau nama diedit.
                    $slug = $_POST['existing_slug'] ?? unique_slug($pdo, 'projects', $name, $id);
                    $pdo->prepare('UPDATE projects SET name=?, slug=?, location=?, excerpt=?, collection=?, story=?, seo_title=?, seo_description=?, cover_image=?, updated_by=? WHERE id=?')
                        ->execute([$name, $slug, $location, $excerpt, $collection, $story, $seoTitle, $seoDescription, $coverImage, $admin['id'], $id]);
                    $projectId = $id;
                } else {
                    $slug = unique_slug($pdo, 'projects', $name);
                    $pdo->prepare('INSERT INTO projects (name, slug, location, excerpt, collection, story, seo_title, seo_description, cover_image, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$name, $slug, $location, $excerpt, $collection, $story, $seoTitle, $seoDescription, $coverImage, $admin['id'], $admin['id']]);
                    $projectId = (int) $pdo->lastInsertId();
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
                        $path = handle_image_upload($single, 'projects/gallery', $name);
                        $pdo->prepare('INSERT INTO project_gallery (project_id, image_path) VALUES (?,?)')->execute([$projectId, $path]);
                    }
                }
                $flash = ['ok', 'Proyek tersimpan.'];
                break;
            }

            case 'delete_project': {
                $id = (int) ($_POST['project_id'] ?? 0);
                $row = $pdo->prepare('SELECT cover_image FROM projects WHERE id=?');
                $row->execute([$id]);
                $gallery = $pdo->prepare('SELECT image_path FROM project_gallery WHERE project_id=?');
                $gallery->execute([$id]);
                foreach ($gallery->fetchAll() as $g) delete_uploaded_image($g['image_path']);
                if ($existing = $row->fetch()) delete_uploaded_image($existing['cover_image']);
                $pdo->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
                $flash = ['ok', 'Proyek dihapus.'];
                break;
            }

            case 'delete_project_photo': {
                $photoId = (int) ($_POST['photo_id'] ?? 0);
                $row = $pdo->prepare('SELECT image_path FROM project_gallery WHERE id=?');
                $row->execute([$photoId]);
                if ($existing = $row->fetch()) {
                    delete_uploaded_image($existing['image_path']);
                    $pdo->prepare('DELETE FROM project_gallery WHERE id=?')->execute([$photoId]);
                }
                $flash = ['ok', 'Foto dihapus.'];
                break;
            }
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }

    $_SESSION['flash'] = $flash;
    header('Location: projects.php');
    exit;
}

start_admin_session();
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$projects = $pdo->query(
    'SELECT p.*, cu.username AS created_by_name, uu.username AS updated_by_name
     FROM projects p
     LEFT JOIN admin_users cu ON cu.id = p.created_by
     LEFT JOIN admin_users uu ON uu.id = p.updated_by
     ORDER BY p.created_at DESC'
)->fetchAll();

$editingProject = null;
if (!empty($_GET['edit_project'])) {
    $stmt = $pdo->prepare(
        'SELECT p.*, cu.username AS created_by_name, uu.username AS updated_by_name
         FROM projects p
         LEFT JOIN admin_users cu ON cu.id = p.created_by
         LEFT JOIN admin_users uu ON uu.id = p.updated_by
         WHERE p.id=?'
    );
    $stmt->execute([(int) $_GET['edit_project']]);
    $editingProject = $stmt->fetch() ?: null;
    if ($editingProject) {
        $g = $pdo->prepare('SELECT * FROM project_gallery WHERE project_id=? ORDER BY sort_order');
        $g->execute([$editingProject['id']]);
        $editingProject['gallery'] = $g->fetchAll();
    }
}

$pageTitle = 'Projects';
$pageSubtitle = 'Kelola portofolio proyek';
$activeNav = 'projects';
require __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?><div class="flash <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="section-head" style="margin-bottom:18px;">
  <div></div>
  <button class="btn" data-open-modal="project-modal" onclick="document.getElementById('project-form').reset(); document.getElementById('project_id').value=''; document.getElementById('project-modal-title').textContent='Add Project'; document.getElementById('project-gallery-existing').innerHTML='';">+ Add Project</button>
</div>

<?php if (!$projects): ?>
  <div class="empty-row">Belum ada proyek.</div>
<?php else: ?>
  <div class="card-grid">
    <?php foreach ($projects as $p): ?>
      <div class="entity-card ratio-43">
        <div class="photo-wrap"><img src="<?= htmlspecialchars(image_thumb_url($p['cover_image'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>"></div>
        <div class="card-info">
          <div class="t"><?= htmlspecialchars($p['name']) ?></div>
          <div class="d"><?= htmlspecialchars($p['location'] ?: '—') ?></div>
        </div>
        <div class="card-actions">
          <a class="btn btn-sm btn-ghost" href="projects.php?edit_project=<?= $p['id'] ?>#project-modal">EDIT</a>
          <form method="post" onsubmit="return confirm('Hapus proyek ini?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_project">
            <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-danger" type="submit">DELETE</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="modal-scrim<?= $editingProject ? ' open' : '' ?>" id="project-modal">
  <div class="modal-card" style="width:600px;">
    <div class="modal-head">
      <div>
        <h3 id="project-modal-title"><?= $editingProject ? 'Edit Project' : 'Add Project' ?></h3>
        <?php if ($editingProject): ?><?= render_audit_trail($editingProject['created_by_name'], $editingProject['created_at'], $editingProject['updated_by_name'], $editingProject['updated_at']) ?><?php endif; ?>
      </div>
      <button class="modal-close" data-close-modal="project-modal">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" id="project-form">
      <div class="modal-body form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_project">
        <input type="hidden" name="project_id" id="project_id" value="<?= $editingProject['id'] ?? '' ?>">
        <input type="hidden" name="existing_slug" value="<?= htmlspecialchars($editingProject['slug'] ?? '') ?>">
        <input type="hidden" name="existing_cover" value="<?= htmlspecialchars($editingProject['cover_image'] ?? '') ?>">

        <div class="field">
          <label>Cover Photo</label>
          <label class="dropzone">
            <input type="file" name="cover" accept="image/*" style="display:none;" data-preview-target="project-cover-preview">
            <img id="project-cover-preview" class="preview" src="<?= $editingProject ? htmlspecialchars(image_url($editingProject['cover_image'])) : '' ?>" style="<?= $editingProject ? '' : 'display:none;' ?>">
            <span>Klik untuk pilih cover</span>
          </label>
        </div>

        <div class="field">
          <label>Detail Gallery</label>
          <div class="gallery-addon" id="project-gallery-existing" style="margin-bottom:8px;">
            <?php if ($editingProject): foreach ($editingProject['gallery'] as $g): ?>
              <div class="gallery-tile">
                <img src="<?= htmlspecialchars(image_thumb_url($g['image_path'])) ?>" alt="">
                <button type="button" class="remove-x" onclick="__submitDeleteForm('delete_project_photo', {photo_id: <?= (int) $g['id'] ?>})">&times;</button>
              </div>
            <?php endforeach; endif; ?>
          </div>
          <label class="dropzone">
            <input type="file" name="gallery[]" accept="image/*" multiple style="display:none;" onchange="this.nextElementSibling.textContent=this.files.length + ' foto dipilih';">
            <span>+ Tambah foto galeri</span>
          </label>
        </div>

        <div class="field"><label>Name</label><input type="text" name="name" value="<?= htmlspecialchars($editingProject['name'] ?? '') ?>" required></div>
        <div class="field"><label>Location</label><input type="text" name="location" value="<?= htmlspecialchars($editingProject['location'] ?? '') ?>"></div>
        <div class="field"><label>Excerpt (deskripsi pendek di card listing Projects)</label><textarea name="excerpt" placeholder="Muncul di bawah judul pas halaman Projects list, maks ~160 karakter"><?= htmlspecialchars($editingProject['excerpt'] ?? '') ?></textarea></div>
        <div class="field"><label>Collection</label><input type="text" name="collection" value="<?= htmlspecialchars($editingProject['collection'] ?? '') ?>"></div>
        <div class="field"><label>Project Story</label><textarea name="story"><?= htmlspecialchars($editingProject['story'] ?? '') ?></textarea></div>

        <div class="seo-block">
          <div>
            <div class="seo-heading">SEO (Opsional)</div>
            <div class="seo-intro">Kosongin biar otomatis dari nama + cerita proyek di atas. Isi kalau mau kontrol sendiri buat hasil pencarian Google & preview link share.</div>
          </div>
          <div class="field">
            <label>SEO Title</label>
            <input type="text" name="seo_title" value="<?= htmlspecialchars($editingProject['seo_title'] ?? '') ?>" placeholder="Nama Proyek — Svashta Home">
            <div class="seo-hint">Idealnya 50–60 karakter.</div>
          </div>
          <div class="field">
            <label>SEO Description</label>
            <textarea name="seo_description" placeholder="Muncul di hasil pencarian Google & preview link (maks ~160 karakter)"><?= htmlspecialchars($editingProject['seo_description'] ?? '') ?></textarea>
            <div class="seo-hint">Maks ~160 karakter.</div>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close-modal="project-modal">Cancel</button>
        <button type="submit" class="btn">Save</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
