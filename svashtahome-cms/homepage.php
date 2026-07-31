<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/upload.php';
require_once __DIR__ . '/../shared/helpers.php';

$admin = require_login();
$pdo = db();
$flash = null;

// ============================================================
// POST handlers — semua pakai CSRF + prepared statements
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            case 'save_slide': {
                $id = (int) ($_POST['slide_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $subtitle = trim($_POST['subtitle'] ?? '');
                if ($title === '') throw new RuntimeException('Judul slide wajib diisi.');

                $imagePath = $_POST['existing_image'] ?? '';
                if (!empty($_FILES['image']['name'])) {
                    $imagePath = handle_image_upload($_FILES['image'], 'homepage/hero', $title);
                } elseif ($id === 0) {
                    throw new RuntimeException('Gambar slide wajib diupload.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE hero_slides SET title=?, subtitle=?, image_path=?, updated_by=? WHERE id=?');
                    $stmt->execute([$title, $subtitle, $imagePath, $admin['id'], $id]);
                } else {
                    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 s FROM hero_slides')->fetch()['s'];
                    $stmt = $pdo->prepare('INSERT INTO hero_slides (title, subtitle, image_path, sort_order, created_by, updated_by) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([$title, $subtitle, $imagePath, $sort, $admin['id'], $admin['id']]);
                }
                $flash = ['ok', 'Slide tersimpan.'];
                break;
            }

            case 'delete_slide': {
                $id = (int) ($_POST['slide_id'] ?? 0);
                $row = $pdo->prepare('SELECT image_path FROM hero_slides WHERE id=?');
                $row->execute([$id]);
                if ($existing = $row->fetch()) {
                    delete_uploaded_image($existing['image_path']);
                    $pdo->prepare('DELETE FROM hero_slides WHERE id=?')->execute([$id]);
                }
                $flash = ['ok', 'Slide dihapus.'];
                break;
            }

            case 'save_video': {
                $headline = trim($_POST['headline'] ?? '');
                $slogan = trim($_POST['slogan'] ?? '');
                $youtubeId = trim($_POST['youtube_id'] ?? '');
                $videoPath = $_POST['existing_video'] ?? null;
                if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                    if ($_FILES['video_file']['size'] > 40 * 1024 * 1024) {
                        throw new RuntimeException('Ukuran video maksimal 40MB.');
                    }
                    $targetDir = rtrim(UPLOAD_DIR_BASE, '/') . '/homepage/video';
                    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
                    $filename = bin2hex(random_bytes(16)) . '.mp4';
                    move_uploaded_file($_FILES['video_file']['tmp_name'], $targetDir . '/' . $filename);
                    $videoPath = 'homepage/video/' . $filename;
                }
                $stmt = $pdo->prepare('UPDATE homepage_video SET headline=?, slogan=?, youtube_id=?, video_path=?, updated_by=? WHERE id=1');
                $stmt->execute([$headline, $slogan, $youtubeId ?: null, $videoPath, $admin['id']]);
                $flash = ['ok', 'Section video tersimpan.'];
                break;
            }

            case 'save_collaborator': {
                $id = (int) ($_POST['collaborator_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $link = trim($_POST['link_url'] ?? '');
                if ($name === '') throw new RuntimeException('Nama collaborator wajib diisi.');

                $imagePath = $_POST['existing_image'] ?? '';
                if (!empty($_FILES['image']['name'])) {
                    $imagePath = handle_image_upload($_FILES['image'], 'homepage/collaborators', $name);
                } elseif ($id === 0) {
                    throw new RuntimeException('Logo wajib diupload.');
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE collaborators SET name=?, image_path=?, link_url=?, updated_by=? WHERE id=?');
                    $stmt->execute([$name, $imagePath, $link ?: null, $admin['id'], $id]);
                } else {
                    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 s FROM collaborators')->fetch()['s'];
                    $stmt = $pdo->prepare('INSERT INTO collaborators (name, image_path, link_url, sort_order, created_by, updated_by) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([$name, $imagePath, $link ?: null, $sort, $admin['id'], $admin['id']]);
                }
                $flash = ['ok', 'Collaborator tersimpan.'];
                break;
            }

            case 'delete_collaborator': {
                $id = (int) ($_POST['collaborator_id'] ?? 0);
                $row = $pdo->prepare('SELECT image_path FROM collaborators WHERE id=?');
                $row->execute([$id]);
                if ($existing = $row->fetch()) {
                    delete_uploaded_image($existing['image_path']);
                    $pdo->prepare('DELETE FROM collaborators WHERE id=?')->execute([$id]);
                }
                $flash = ['ok', 'Collaborator dihapus.'];
                break;
            }

            case 'save_review': {
                $id = (int) ($_POST['review_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $quote = trim($_POST['quote'] ?? '');
                $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
                if ($name === '' || $quote === '') throw new RuntimeException('Nama dan quote wajib diisi.');

                $avatarPath = $_POST['existing_avatar'] ?? null;
                if (!empty($_FILES['avatar']['name'])) {
                    $avatarPath = handle_image_upload($_FILES['avatar'], 'homepage/reviews/avatar', $name);
                }
                $photoPath = $_POST['existing_photo'] ?? null;
                if (!empty($_FILES['photo']['name'])) {
                    $photoPath = handle_image_upload($_FILES['photo'], 'homepage/reviews/photo', $name);
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE reviews SET name=?, avatar_path=?, photo_path=?, quote=?, rating=?, updated_by=? WHERE id=?');
                    $stmt->execute([$name, $avatarPath, $photoPath, $quote, $rating, $admin['id'], $id]);
                    $reviewId = $id;
                } else {
                    $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 s FROM reviews')->fetch()['s'];
                    $stmt = $pdo->prepare('INSERT INTO reviews (name, avatar_path, photo_path, quote, rating, sort_order, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?)');
                    $stmt->execute([$name, $avatarPath, $photoPath, $quote, $rating, $sort, $admin['id'], $admin['id']]);
                    $reviewId = (int) $pdo->lastInsertId();
                }

                $flash = ['ok', 'Review tersimpan.'];
                break;
            }

            case 'delete_review': {
                $id = (int) ($_POST['review_id'] ?? 0);
                $row = $pdo->prepare('SELECT avatar_path, photo_path FROM reviews WHERE id=?');
                $row->execute([$id]);
                if ($existing = $row->fetch()) {
                    delete_uploaded_image($existing['avatar_path']);
                    delete_uploaded_image($existing['photo_path']);
                }
                $pdo->prepare('DELETE FROM reviews WHERE id=?')->execute([$id]);
                $flash = ['ok', 'Review dihapus.'];
                break;
            }

            case 'save_review_bg': {
                if (!empty($_FILES['review_bg']['name'])) {
                    $bgPath = handle_image_upload($_FILES['review_bg'], 'homepage/backgrounds', 'review-section');
                    $pdo->prepare('UPDATE homepage_backgrounds SET review_bg_image=?, updated_by=? WHERE id=1')->execute([$bgPath, $admin['id']]);
                    $flash = ['ok', 'Background section Review tersimpan.'];
                } else {
                    $flash = ['error', 'Pilih foto dulu.'];
                }
                break;
            }

            case 'add_partner_logo': {
                if (empty($_FILES['image']['name'])) throw new RuntimeException('Pilih file logo dulu.');
                $imagePath = handle_image_upload($_FILES['image'], 'homepage/partners', 'partner-logo');
                $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 s FROM partner_logos')->fetch()['s'];
                $pdo->prepare('INSERT INTO partner_logos (image_path, sort_order, created_by, updated_by) VALUES (?,?,?,?)')->execute([$imagePath, $sort, $admin['id'], $admin['id']]);
                $flash = ['ok', 'Logo partner ditambahkan.'];
                break;
            }

            case 'delete_partner_logo': {
                $id = (int) ($_POST['logo_id'] ?? 0);
                $row = $pdo->prepare('SELECT image_path FROM partner_logos WHERE id=?');
                $row->execute([$id]);
                if ($existing = $row->fetch()) {
                    delete_uploaded_image($existing['image_path']);
                    $pdo->prepare('DELETE FROM partner_logos WHERE id=?')->execute([$id]);
                }
                $flash = ['ok', 'Logo partner dihapus.'];
                break;
            }
        }
    } catch (Throwable $e) {
        $flash = ['error', $e->getMessage()];
    }

    // PRG pattern — redirect supaya refresh tidak submit ulang form
    $_SESSION['flash'] = $flash;
    header('Location: homepage.php');
    exit;
}

start_admin_session();
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ============================================================
// Data untuk render
// ============================================================
// homepage_video & homepage_backgrounds itu tabel single-row settings — cuma
// pernah di-UPDATE, gak pernah di-INSERT lewat CMS, jadi gak punya created_by.
function audit_select(string $table, string $alias = 't', bool $hasCreatedBy = true): string
{
    $createdJoin = $hasCreatedBy
        ? "cu.username AS created_by_name,"
        : "NULL AS created_by_name,";
    $createdOn = $hasCreatedBy
        ? "LEFT JOIN admin_users cu ON cu.id = {$alias}.created_by"
        : "";
    return "SELECT {$alias}.*, {$createdJoin} uu.username AS updated_by_name
            FROM {$table} {$alias}
            {$createdOn}
            LEFT JOIN admin_users uu ON uu.id = {$alias}.updated_by";
}

$slides = $pdo->query(audit_select('hero_slides') . ' ORDER BY t.sort_order')->fetchAll();
$video = $pdo->query(audit_select('homepage_video', 't', false) . ' WHERE t.id=1')->fetch();
$collaborators = $pdo->query(audit_select('collaborators') . ' ORDER BY t.sort_order')->fetchAll();
$reviews = $pdo->query(audit_select('reviews') . ' ORDER BY t.sort_order')->fetchAll();
$partnerLogos = $pdo->query(audit_select('partner_logos') . ' ORDER BY t.sort_order')->fetchAll();
$backgrounds = $pdo->query(audit_select('homepage_backgrounds', 't', false) . ' WHERE t.id=1')->fetch();

$editingSlide = null;
if (!empty($_GET['edit_slide'])) {
    $stmt = $pdo->prepare(audit_select('hero_slides') . ' WHERE t.id=?');
    $stmt->execute([(int) $_GET['edit_slide']]);
    $editingSlide = $stmt->fetch() ?: null;
}
$editingCollaborator = null;
if (!empty($_GET['edit_collaborator'])) {
    $stmt = $pdo->prepare(audit_select('collaborators') . ' WHERE t.id=?');
    $stmt->execute([(int) $_GET['edit_collaborator']]);
    $editingCollaborator = $stmt->fetch() ?: null;
}
$editingReview = null;
if (!empty($_GET['edit_review'])) {
    $stmt = $pdo->prepare(audit_select('reviews') . ' WHERE t.id=?');
    $stmt->execute([(int) $_GET['edit_review']]);
    $editingReview = $stmt->fetch() ?: null;
}

$pageTitle = 'Homepage';
$pageSubtitle = 'Edit konten yang tampil di svashtahome.com';
$activeNav = 'homepage';
require __DIR__ . '/includes/header.php';
?>

<?php if ($flash): ?>
  <div class="flash <?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div>
<?php endif; ?>

<!-- ============ HERO CAROUSEL ============ -->
<section class="section-card">
  <div class="section-head">
    <div><h2>Hero Carousel</h2><div class="section-hint">Slider besar di paling atas homepage.</div></div>
    <button class="btn" data-open-modal="slide-modal" onclick="document.getElementById('slide-form').reset(); document.getElementById('slide_id').value=''; document.getElementById('slide-modal-title').textContent='Add Slide';">+ Add Slide</button>
  </div>
  <?php if (!$slides): ?>
    <div class="empty-row">Belum ada slide.</div>
  <?php else: foreach ($slides as $s): ?>
    <div class="item-row">
      <img class="thumb" src="<?= htmlspecialchars(image_thumb_url($s['image_path'])) ?>" alt="">
      <div class="meta">
        <div class="t"><?= htmlspecialchars($s['title']) ?></div>
        <div class="d"><?= htmlspecialchars($s['subtitle']) ?></div>
      </div>
      <div class="row-actions">
        <a class="btn btn-sm btn-ghost" href="homepage.php?edit_slide=<?= $s['id'] ?>#slide-modal">EDIT</a>
        <form method="post" onsubmit="return confirm('Hapus slide ini?');" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_slide">
          <input type="hidden" name="slide_id" value="<?= $s['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">DELETE</button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>
</section>

<!-- ============ HOMEPAGE VIDEO ============ -->
<section class="section-card">
  <div class="section-head">
    <div><h2>Homepage Video</h2><div class="section-hint">Section "Watch our video".</div></div>
  </div>
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_video">
    <input type="hidden" name="existing_video" value="<?= htmlspecialchars($video['video_path'] ?? '') ?>">
    <div class="field">
      <label>Headline</label>
      <input type="text" name="headline" value="<?= htmlspecialchars($video['headline'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Slogan</label>
      <input type="text" name="slogan" value="<?= htmlspecialchars($video['slogan'] ?? '') ?>">
    </div>
    <div class="field">
      <label>YouTube Video ID (opsional, mis. jlWMTNZNOc0)</label>
      <input type="text" name="youtube_id" value="<?= htmlspecialchars($video['youtube_id'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Atau upload file video (.mp4, maks 40MB)</label>
      <label class="dropzone">
        <input type="file" name="video_file" accept="video/mp4" style="display:none;" onchange="this.nextElementSibling.textContent=this.files[0]?this.files[0].name:'Klik untuk pilih file video';">
        <span><?= $video['video_path'] ? 'Video tersimpan: ' . htmlspecialchars(basename($video['video_path'])) : 'Klik untuk pilih file video' ?></span>
      </label>
    </div>
    <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap;">
      <button class="btn" type="submit">SAVE VIDEO SECTION</button>
      <?= render_audit_trail(null, null, $video['updated_by_name'] ?? null, $video['updated_at'] ?? null) ?>
    </div>
  </form>
</section>

<!-- ============ COLLABORATORS ============ -->
<section class="section-card">
  <div class="section-head">
    <div><h2>Meet Our Collaborators</h2><div class="section-hint">Logo bulat di section "Collaborations".</div></div>
    <button class="btn" data-open-modal="collaborator-modal" onclick="document.getElementById('collaborator-form').reset(); document.getElementById('collaborator_id').value=''; document.getElementById('collaborator-modal-title').textContent='Add Collaborator';">+ Add</button>
  </div>
  <?php if (!$collaborators): ?>
    <div class="empty-row">Belum ada collaborator.</div>
  <?php else: ?>
    <div class="logo-grid">
      <?php foreach ($collaborators as $c): ?>
        <div class="logo-tile">
          <img src="<?= htmlspecialchars(image_thumb_url($c['image_path'])) ?>" alt="">
          <div class="name"><?= htmlspecialchars($c['name']) ?></div>
          <div class="tile-actions">
            <a class="btn btn-sm btn-ghost" href="homepage.php?edit_collaborator=<?= $c['id'] ?>#collaborator-modal">EDIT</a>
            <form method="post" onsubmit="return confirm('Hapus collaborator ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_collaborator">
              <input type="hidden" name="collaborator_id" value="<?= $c['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">DELETE</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- ============ CLIENT REVIEWS ============ -->
<section class="section-card">
  <div class="section-head">
    <div><h2>Client Reviews</h2><div class="section-hint">Testimonial slider.</div></div>
    <button class="btn" data-open-modal="review-modal" onclick="document.getElementById('review-form').reset(); document.getElementById('review_id').value=''; document.getElementById('review-modal-title').textContent='Add Review'; document.querySelectorAll('#review-form .rating-input button').forEach(function(b){b.textContent='★';});">+ Add</button>
  </div>

  <div style="display:flex; align-items:center; gap:14px; padding:12px; border:1px solid var(--border); border-radius:9px; background:var(--surface-2); margin-bottom:18px;">
    <img src="<?= htmlspecialchars($backgrounds['review_bg_image'] ? image_thumb_url($backgrounds['review_bg_image']) : SITE_URL . '/assets/img/backgrounds/testimonial.jpg') ?>" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:6px;flex-shrink:0;">
    <div style="flex:1;">
      <div style="font-size:12.5px;font-weight:600;">Background Section Review</div>
      <div style="font-size:11px;color:var(--ink-muted);">Foto latar belakang gelap di section testimonial. Kosongin buat pakai foto default tema.</div>
      <?= render_audit_trail(null, null, $backgrounds['updated_by_name'] ?? null, $backgrounds['updated_at'] ?? null) ?>
    </div>
    <form method="post" enctype="multipart/form-data" style="display:flex; align-items:center; gap:8px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_review_bg">
      <input type="file" name="review_bg" accept="image/*" required style="font-size:11px;">
      <button class="btn btn-sm" type="submit">Save</button>
    </form>
  </div>

  <?php if (!$reviews): ?>
    <div class="empty-row">Belum ada review.</div>
  <?php else: foreach ($reviews as $r): ?>
    <div class="item-row">
      <img class="thumb round" src="<?= htmlspecialchars($r['avatar_path'] ? image_thumb_url($r['avatar_path']) : 'https://ui-avatars.com/api/?name=' . urlencode($r['name'])) ?>" alt="">
      <div class="meta">
        <div class="t"><?= htmlspecialchars($r['name']) ?> <span class="stars"><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></span></div>
        <div class="d"><?= htmlspecialchars($r['quote']) ?></div>
      </div>
      <div class="row-actions">
        <a class="btn btn-sm btn-ghost" href="homepage.php?edit_review=<?= $r['id'] ?>#review-modal">EDIT</a>
        <form method="post" onsubmit="return confirm('Hapus review ini?');" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete_review">
          <input type="hidden" name="review_id" value="<?= $r['id'] ?>">
          <button class="btn btn-sm btn-danger" type="submit">DELETE</button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>
</section>

<!-- ============ PARTNER & CLIENT LOGOS ============ -->
<section class="section-card">
  <div class="section-head">
    <div><h2>Partner &amp; Client Logos</h2><div class="section-hint">Add-only — logo tinggal upload, tidak ada edit.</div></div>
    <button class="btn" data-open-modal="partner-modal">+ Add</button>
  </div>
  <?php if (!$partnerLogos): ?>
    <div class="empty-row">Belum ada logo.</div>
  <?php else: ?>
    <div class="logo-grid">
      <?php foreach ($partnerLogos as $l): ?>
        <div class="logo-tile square">
          <img src="<?= htmlspecialchars(image_thumb_url($l['image_path'])) ?>" alt="">
          <div class="tile-actions">
            <form method="post" onsubmit="return confirm('Hapus logo ini?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_partner_logo">
              <input type="hidden" name="logo_id" value="<?= $l['id'] ?>">
              <button class="btn btn-sm btn-danger" type="submit">DELETE</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- ============ MODAL: Slide ============ -->
<div class="modal-scrim<?= $editingSlide ? ' open' : '' ?>" id="slide-modal">
  <div class="modal-card">
    <div class="modal-head">
      <div>
        <h3 id="slide-modal-title"><?= $editingSlide ? 'Edit Slide' : 'Add Slide' ?></h3>
        <?php if ($editingSlide): ?><?= render_audit_trail($editingSlide['created_by_name'], $editingSlide['created_at'], $editingSlide['updated_by_name'], $editingSlide['updated_at']) ?><?php endif; ?>
      </div>
      <button class="modal-close" data-close-modal="slide-modal">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" id="slide-form">
      <div class="modal-body form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_slide">
        <input type="hidden" name="slide_id" id="slide_id" value="<?= $editingSlide['id'] ?? '' ?>">
        <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editingSlide['image_path'] ?? '') ?>">
        <div class="field">
          <label>Slide Image</label>
          <label class="dropzone">
            <input type="file" name="image" accept="image/*" style="display:none;" data-preview-target="slide-preview">
            <img id="slide-preview" class="preview" src="<?= $editingSlide ? htmlspecialchars(image_url($editingSlide['image_path'])) : '' ?>" style="<?= $editingSlide ? '' : 'display:none;' ?>">
            <span>Klik untuk pilih gambar</span>
          </label>
        </div>
        <div class="field">
          <label>Title</label>
          <input type="text" name="title" value="<?= htmlspecialchars($editingSlide['title'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Subtext</label>
          <textarea name="subtitle"><?= htmlspecialchars($editingSlide['subtitle'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close-modal="slide-modal">Cancel</button>
        <button type="submit" class="btn">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL: Collaborator ============ -->
<div class="modal-scrim<?= $editingCollaborator ? ' open' : '' ?>" id="collaborator-modal">
  <div class="modal-card">
    <div class="modal-head">
      <div>
        <h3 id="collaborator-modal-title"><?= $editingCollaborator ? 'Edit Collaborator' : 'Add Collaborator' ?></h3>
        <?php if ($editingCollaborator): ?><?= render_audit_trail($editingCollaborator['created_by_name'], $editingCollaborator['created_at'], $editingCollaborator['updated_by_name'], $editingCollaborator['updated_at']) ?><?php endif; ?>
      </div>
      <button class="modal-close" data-close-modal="collaborator-modal">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" id="collaborator-form">
      <div class="modal-body form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_collaborator">
        <input type="hidden" name="collaborator_id" id="collaborator_id" value="<?= $editingCollaborator['id'] ?? '' ?>">
        <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editingCollaborator['image_path'] ?? '') ?>">
        <div class="field">
          <label>Logo</label>
          <label class="dropzone">
            <input type="file" name="image" accept="image/*" style="display:none;" data-preview-target="collab-preview">
            <img id="collab-preview" class="preview" src="<?= $editingCollaborator ? htmlspecialchars(image_url($editingCollaborator['image_path'])) : '' ?>" style="<?= $editingCollaborator ? '' : 'display:none;' ?>">
            <span>Klik untuk pilih logo</span>
          </label>
        </div>
        <div class="field">
          <label>Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($editingCollaborator['name'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Link URL</label>
          <input type="url" name="link_url" value="<?= htmlspecialchars($editingCollaborator['link_url'] ?? '') ?>" placeholder="https://...">
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close-modal="collaborator-modal">Cancel</button>
        <button type="submit" class="btn">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL: Review ============ -->
<div class="modal-scrim<?= $editingReview ? ' open' : '' ?>" id="review-modal">
  <div class="modal-card">
    <div class="modal-head">
      <div>
        <h3 id="review-modal-title"><?= $editingReview ? 'Edit Review' : 'Add Review' ?></h3>
        <?php if ($editingReview): ?><?= render_audit_trail($editingReview['created_by_name'], $editingReview['created_at'], $editingReview['updated_by_name'], $editingReview['updated_at']) ?><?php endif; ?>
      </div>
      <button class="modal-close" data-close-modal="review-modal">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" id="review-form">
      <div class="modal-body form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_review">
        <input type="hidden" name="review_id" id="review_id" value="<?= $editingReview['id'] ?? '' ?>">
        <input type="hidden" name="existing_avatar" value="<?= htmlspecialchars($editingReview['avatar_path'] ?? '') ?>">
        <input type="hidden" name="existing_photo" value="<?= htmlspecialchars($editingReview['photo_path'] ?? '') ?>">
        <div class="field">
          <label>Profile Photo (kecil, sebelah nama)</label>
          <label class="dropzone">
            <input type="file" name="avatar" accept="image/*" style="display:none;" data-preview-target="avatar-preview">
            <img id="avatar-preview" class="preview" src="<?= ($editingReview['avatar_path'] ?? null) ? htmlspecialchars(image_url($editingReview['avatar_path'])) : '' ?>" style="<?= ($editingReview['avatar_path'] ?? null) ? '' : 'display:none;' ?>">
            <span>Foto profil reviewer (opsional)</span>
          </label>
        </div>
        <div class="field">
          <label>Review Photo (gede, side-by-side sama teks)</label>
          <label class="dropzone">
            <input type="file" name="photo" accept="image/*" style="display:none;" data-preview-target="photo-preview">
            <img id="photo-preview" class="preview" src="<?= ($editingReview['photo_path'] ?? null) ? htmlspecialchars(image_url($editingReview['photo_path'])) : '' ?>" style="<?= ($editingReview['photo_path'] ?? null) ? '' : 'display:none;' ?>">
            <span>Klik untuk pilih foto — ini yang tampil side-by-side sama teks review di homepage</span>
          </label>
        </div>
        <div class="field">
          <label>Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($editingReview['name'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Rating</label>
          <div class="rating-input">
            <input type="hidden" name="rating" value="<?= $editingReview['rating'] ?? 5 ?>">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <button type="button" data-val="<?= $i ?>">☆</button>
            <?php endfor; ?>
          </div>
        </div>
        <div class="field">
          <label>Quote</label>
          <textarea name="quote" required><?= htmlspecialchars($editingReview['quote'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close-modal="review-modal">Cancel</button>
        <button type="submit" class="btn">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ MODAL: Partner Logo (add-only) ============ -->
<div class="modal-scrim" id="partner-modal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>Add Partner Logo</h3>
      <button class="modal-close" data-close-modal="partner-modal">&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data">
      <div class="modal-body form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_partner_logo">
        <div class="field">
          <label>Logo</label>
          <label class="dropzone">
            <input type="file" name="image" accept="image/*" required style="display:none;" data-preview-target="partner-preview">
            <img id="partner-preview" class="preview" style="display:none;">
            <span>Klik untuk pilih logo</span>
          </label>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close-modal="partner-modal">Cancel</button>
        <button type="submit" class="btn">Save</button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
