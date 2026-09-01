<?php
require_once __DIR__ . '/../backoffice-shared/db.php';
require_once __DIR__ . '/../backoffice-shared/modules.php';
$secret = $_GET['secret'] ?? '';
if ($secret !== 'mirjov-migrate-2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain');
$pdo = db();

// 1) Backfill 'master_barang' buat role yang UDAH ADA — MODULES nambah key baru, tapi
//    role_module_access lama gak otomatis punya row-nya. Mirror dari permission 'kontak'
//    role itu (yang sebelumnya nyampur Master Barang), biar gak ada yang tiba-tiba
//    kehilangan akses Master Barang gara-gara split ini.
echo "=== Backfill master_barang buat role yang udah ada ===\n";
$roles = $pdo->query('SELECT id, organization_id, name, is_owner_role FROM roles')->fetchAll();
foreach ($roles as $r) {
    $exists = $pdo->prepare('SELECT id FROM role_module_access WHERE role_id=? AND module_key=?');
    $exists->execute([$r['id'], 'master_barang']);
    if ($exists->fetch()) { echo "SKIP role={$r['name']} (id={$r['id']}) — udah ada row master_barang\n"; continue; }

    if ($r['is_owner_role']) {
        $pdo->prepare('INSERT INTO role_module_access (role_id, module_key, can_view, can_create, can_edit, can_delete, can_print) VALUES (?,?,1,1,1,1,1)')
            ->execute([$r['id'], 'master_barang']);
        echo "ADDED role={$r['name']} (Owner) -> master_barang full access\n";
        continue;
    }

    $kontak = $pdo->prepare('SELECT can_view, can_create, can_edit, can_delete, can_print FROM role_module_access WHERE role_id=? AND module_key=?');
    $kontak->execute([$r['id'], 'kontak']);
    $k = $kontak->fetch();
    if ($k) {
        $pdo->prepare('INSERT INTO role_module_access (role_id, module_key, can_view, can_create, can_edit, can_delete, can_print) VALUES (?,?,?,?,?,?,?)')
            ->execute([$r['id'], 'master_barang', $k['can_view'], $k['can_create'], $k['can_edit'], $k['can_delete'], $k['can_print']]);
        echo "ADDED role={$r['name']} -> master_barang mirrored from kontak (view={$k['can_view']} create={$k['can_create']} edit={$k['can_edit']} delete={$k['can_delete']} print={$k['can_print']})\n";
    } else {
        $pdo->prepare('INSERT INTO role_module_access (role_id, module_key) VALUES (?,?)')->execute([$r['id'], 'master_barang']);
        echo "ADDED role={$r['name']} -> master_barang row kosong (kontak juga gak ada row-nya)\n";
    }
}

// 2) Bikin 2 role baru: Staff Admin & Staff Gudang.
function create_staff_role(PDO $pdo, int $orgId, string $name, array $fullAccessModules, array $viewOnlyModules): int
{
    $existing = $pdo->prepare('SELECT id FROM roles WHERE organization_id=? AND name=?');
    $existing->execute([$orgId, $name]);
    if ($row = $existing->fetch()) {
        echo "SKIP role '$name' — udah ada (id={$row['id']})\n";
        return (int) $row['id'];
    }

    $pdo->prepare('INSERT INTO roles (organization_id, name, is_production_role) VALUES (?,?,0)')->execute([$orgId, $name]);
    $roleId = (int) $pdo->lastInsertId();

    $accessStmt = $pdo->prepare('INSERT INTO role_module_access (role_id, module_key, can_view, can_create, can_edit, can_delete, can_print) VALUES (?,?,?,?,?,?,?)');
    foreach (array_keys(MODULES) as $moduleKey) {
        if (in_array($moduleKey, $fullAccessModules, true)) {
            $accessStmt->execute([$roleId, $moduleKey, 1, 1, 1, 0, 1]); // full CRUD kecuali delete (default aman, bisa di-adjust di Roles & Akses)
        } elseif (in_array($moduleKey, $viewOnlyModules, true)) {
            $accessStmt->execute([$roleId, $moduleKey, 1, 0, 0, 0, 1]);
        } else {
            $accessStmt->execute([$roleId, $moduleKey, 0, 0, 0, 0, 0]);
        }
    }
    echo "CREATED role '$name' (id=$roleId)\n";
    return $roleId;
}

echo "\n=== Bikin role Staff Admin & Staff Gudang ===\n";
$orgId = 1;

// Staff Admin: seluruh Master Data (termasuk Master Barang) + seluruh Manufaktur, view utk Laporan.
create_staff_role(
    $pdo, $orgId, 'Staff Admin',
    ['kontak', 'master_barang', 'manufaktur_penawaran', 'manufaktur_po', 'manufaktur_surat_jalan', 'manufaktur_label'],
    ['laporan']
);

// Staff Gudang: CUMA Master Barang (bukan Lokasi/Vendor/Customer/dll) + seluruh Manufaktur.
// Pembatasan lokasi (cuma bisa kirim dari gudang sendiri) diatur TERPISAH lewat kolom
// warehouse_id di Master User (user_organization_roles) — lihat user_location_restriction().
create_staff_role(
    $pdo, $orgId, 'Staff Gudang',
    ['master_barang', 'manufaktur_penawaran', 'manufaktur_po', 'manufaktur_surat_jalan', 'manufaktur_label'],
    ['laporan']
);

echo "\nDONE\n";
