<?php
require_once __DIR__ . '/../backoffice-shared/db.php';
$secret = $_GET['secret'] ?? '';
if ($secret !== 'mirjov-migrate-2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain');
$pdo = db();

// master_barang kelewat pas create_staff_role() jalan (modules.php live saat itu belum
// punya key master_barang) — Staff Admin & Staff Gudang harusnya full access ke situ.
$fix = [
    'Staff Admin' => [1, 1, 1, 0, 1],
    'Staff Gudang' => [1, 1, 1, 0, 1],
];
foreach ($fix as $roleName => [$view, $create, $edit, $delete, $print]) {
    $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name=?');
    $roleStmt->execute([$roleName]);
    $roleId = $roleStmt->fetchColumn();
    if (!$roleId) { echo "SKIP $roleName — role gak ketemu\n"; continue; }

    $exists = $pdo->prepare('SELECT id FROM role_module_access WHERE role_id=? AND module_key=?');
    $exists->execute([$roleId, 'master_barang']);
    if ($exists->fetch()) {
        $pdo->prepare('UPDATE role_module_access SET can_view=?, can_create=?, can_edit=?, can_delete=?, can_print=? WHERE role_id=? AND module_key=?')
            ->execute([$view, $create, $edit, $delete, $print, $roleId, 'master_barang']);
        echo "UPDATED $roleName (id=$roleId) -> master_barang\n";
    } else {
        $pdo->prepare('INSERT INTO role_module_access (role_id, module_key, can_view, can_create, can_edit, can_delete, can_print) VALUES (?,?,?,?,?,?,?)')
            ->execute([$roleId, 'master_barang', $view, $create, $edit, $delete, $print]);
        echo "INSERTED $roleName (id=$roleId) -> master_barang\n";
    }
}

echo "\n=== Verifikasi ===\n";
foreach ($pdo->query("SELECT r.name AS role_name, rma.* FROM role_module_access rma JOIN roles r ON r.id=rma.role_id WHERE rma.module_key='master_barang' ORDER BY r.id") as $r) {
    echo json_encode($r) . "\n";
}
