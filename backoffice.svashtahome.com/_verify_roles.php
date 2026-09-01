<?php
require_once __DIR__ . '/../backoffice-shared/db.php';
$secret = $_GET['secret'] ?? '';
if ($secret !== 'mirjov-migrate-2026') { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain');
$pdo = db();

foreach ($pdo->query("SELECT id, name, is_owner_role FROM roles ORDER BY id") as $r) {
    echo "=== Role: {$r['name']} (id={$r['id']}) ===\n";
    $stmt = $pdo->prepare("SELECT module_key, can_view, can_create, can_edit, can_delete, can_print FROM role_module_access WHERE role_id=? ORDER BY module_key");
    $stmt->execute([$r['id']]);
    foreach ($stmt->fetchAll() as $a) {
        if (!$a['can_view'] && !$a['can_create'] && !$a['can_edit'] && !$a['can_delete'] && !$a['can_print']) continue;
        echo "  {$a['module_key']}: view={$a['can_view']} create={$a['can_create']} edit={$a['can_edit']} delete={$a['can_delete']} print={$a['can_print']}\n";
    }
}
