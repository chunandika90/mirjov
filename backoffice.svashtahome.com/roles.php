<?php
$pageTitle = 'Roles & Akses';
$activeMenu = 'roles';
require __DIR__ . '/includes/header.php';

$pdo = db();
$isOwner = $org['role_name'] === 'Owner';
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!$isOwner) {
        $flash = ['error', 'Cuma Owner yang bisa mengelola role.'];
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'add_role') {
                $name = trim($_POST['name'] ?? '');
                $isProduction = !empty($_POST['is_production_role']) ? 1 : 0;
                if ($name === '') throw new RuntimeException('Nama role wajib diisi.');
                $pdo->prepare('INSERT INTO roles (organization_id, name, is_production_role) VALUES (?,?,?)')->execute([$org['organization_id'], $name, $isProduction]);
                $roleId = (int) $pdo->lastInsertId();
                $accessStmt = $pdo->prepare('INSERT INTO role_module_access (role_id, module_key) VALUES (?,?)');
                foreach (array_keys(MODULES) as $moduleKey) {
                    $accessStmt->execute([$roleId, $moduleKey]);
                }
                $flash = ['ok', 'Role baru dibuat. Atur hak aksesnya di bawah.'];
            } elseif ($action === 'toggle_production_role') {
                $roleId = (int) ($_POST['role_id'] ?? 0);
                $check = $pdo->prepare('SELECT is_owner_role, is_production_role FROM roles WHERE id=? AND organization_id=?');
                $check->execute([$roleId, $org['organization_id']]);
                $role = $check->fetch();
                if (!$role) throw new RuntimeException('Role tidak ditemukan.');
                if ($role['is_owner_role']) throw new RuntimeException('Role Owner tidak perlu ditandai role produksi.');
                $pdo->prepare('UPDATE roles SET is_production_role=? WHERE id=?')->execute([$role['is_production_role'] ? 0 : 1, $roleId]);
                $flash = ['ok', 'Status role produksi diperbarui.'];
            } elseif ($action === 'save_access') {
                $roleId = (int) ($_POST['role_id'] ?? 0);
                $check = $pdo->prepare('SELECT is_owner_role FROM roles WHERE id=? AND organization_id=?');
                $check->execute([$roleId, $org['organization_id']]);
                $role = $check->fetch();
                if (!$role) throw new RuntimeException('Role tidak ditemukan.');
                if ($role['is_owner_role']) throw new RuntimeException('Role Owner selalu punya akses penuh, tidak bisa diubah.');

                $upd = $pdo->prepare('UPDATE role_module_access SET can_view=?, can_create=?, can_edit=?, can_delete=?, can_print=? WHERE role_id=? AND module_key=?');
                foreach (array_keys(MODULES) as $moduleKey) {
                    $p = $_POST['perm'][$moduleKey] ?? [];
                    $upd->execute([
                        !empty($p['view']) ? 1 : 0,
                        !empty($p['create']) ? 1 : 0,
                        !empty($p['edit']) ? 1 : 0,
                        !empty($p['delete']) ? 1 : 0,
                        !empty($p['print']) ? 1 : 0,
                        $roleId, $moduleKey,
                    ]);
                }
                $flash = ['ok', 'Hak akses role disimpan.'];
            } elseif ($action === 'delete_role') {
                $roleId = (int) ($_POST['role_id'] ?? 0);
                $check = $pdo->prepare('SELECT is_owner_role FROM roles WHERE id=? AND organization_id=?');
                $check->execute([$roleId, $org['organization_id']]);
                $role = $check->fetch();
                if (!$role) throw new RuntimeException('Role tidak ditemukan.');
                if ($role['is_owner_role']) throw new RuntimeException('Role Owner tidak bisa dihapus.');
                $inUse = $pdo->prepare('SELECT COUNT(*) c FROM user_organization_roles WHERE role_id=? AND status="active"');
                $inUse->execute([$roleId]);
                if ((int) $inUse->fetch()['c'] > 0) throw new RuntimeException('Role masih dipakai anggota aktif, pindahkan dulu role-nya sebelum hapus.');
                $pdo->prepare('DELETE FROM roles WHERE id=?')->execute([$roleId]);
                $flash = ['ok', 'Role dihapus.'];
            }
        } catch (Throwable $e) {
            $flash = ['error', $e->getMessage()];
        }
    }
}

$roles = $pdo->prepare('SELECT * FROM roles WHERE organization_id = ? ORDER BY is_owner_role DESC, name');
$roles->execute([$org['organization_id']]);
$roles = $roles->fetchAll();

$editingRoleId = (int) ($_GET['role_id'] ?? 0);
$access = [];
if ($editingRoleId) {
    $stmt = $pdo->prepare('SELECT * FROM role_module_access WHERE role_id = ?');
    $stmt->execute([$editingRoleId]);
    foreach ($stmt->fetchAll() as $row) {
        $access[$row['module_key']] = $row;
    }
}
?>

<?php if ($flash): ?><div class="alert alert-<?= $flash[0] === 'ok' ? 'ok' : 'error' ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<?php if ($isOwner): ?>
<div class="card" style="margin-bottom:20px;">
  <h3 style="margin-top:0;">Buat Role Baru</h3>
  <form method="post" style="display:flex; gap:10px; align-items:flex-end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_role">
    <div class="field" style="min-width:220px; margin-bottom:0;">
      <label>Nama Role (mis. Manager, SPV Purchasing, Staff, Pabrik)</label>
      <input type="text" name="name" required>
    </div>
    <div class="field" style="margin-bottom:0;">
      <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
        <input type="checkbox" name="is_production_role" value="1" style="width:auto;">
        Role produksi (Pabrik)
      </label>
    </div>
    <button type="submit" class="btn">+ Buat Role</button>
  </form>
  <p style="font-size:12px; color:var(--ink-muted); margin:8px 0 0;">Role produksi bisa mengisi field Harga/Timeline/Remark khusus di Form Penawaran Harga (Manufaktur).</p>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
  <table class="data-table">
    <thead><tr><th>Role</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($roles as $r): ?>
        <tr>
          <td>
            <?= htmlspecialchars($r['name']) ?>
            <?php if ($r['is_owner_role']): ?><span class="pill owner">OWNER — FULL ACCESS</span><?php endif; ?>
            <?php if ($r['is_production_role']): ?><span class="pill">PRODUKSI</span><?php endif; ?>
          </td>
          <td>
            <a class="btn btn-sm btn-ghost" href="roles.php?role_id=<?= $r['id'] ?>">Atur Akses</a>
            <?php if ($isOwner && !$r['is_owner_role']): ?>
              <button class="btn btn-sm btn-ghost" type="button" onclick="__submitDeleteForm('toggle_production_role', {role_id: <?= $r['id'] ?>})"><?= $r['is_production_role'] ? 'Cabut Status Produksi' : 'Tandai Role Produksi' ?></button>
              <button class="btn btn-sm btn-ghost" type="button" onclick="if(confirm('Hapus role ini?')) __submitDeleteForm('delete_role', {role_id: <?= $r['id'] ?>})">Hapus</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($editingRoleId && $access): ?>
<div class="card">
  <h3 style="margin-top:0;">Hak Akses per Modul</h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_access">
    <input type="hidden" name="role_id" value="<?= $editingRoleId ?>">
    <div style="overflow-x:auto;">
      <table class="data-table matrix-table">
        <thead><tr><th>Modul</th><th>View</th><th>Tambah</th><th>Edit</th><th>Hapus</th><th>Print</th></tr></thead>
        <tbody>
          <?php foreach (MODULES as $key => $label): $a = $access[$key] ?? []; ?>
          <tr>
            <td><?= htmlspecialchars($label) ?></td>
            <?php foreach (['view', 'create', 'edit', 'delete', 'print'] as $perm): ?>
              <td><input type="checkbox" name="perm[<?= $key ?>][<?= $perm ?>]" <?= !empty($a['can_' . $perm]) ? 'checked' : '' ?> <?= $isOwner ? '' : 'disabled' ?>></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($isOwner): ?><button type="submit" class="btn" style="margin-top:14px;">Simpan Hak Akses</button><?php endif; ?>
  </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
