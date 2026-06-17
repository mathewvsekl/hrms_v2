<?php
require_once __DIR__ . '/backend/vendor/autoload.php';
require_once __DIR__ . '/backend/config/database.php';

$db = \Database::getInstance()->getConnection();
$stmt = $db->query("
    SELECT u.id, u.username, r.name as role_name
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    WHERE r.name = 'Admin' OR r.name = 'ADMIN'
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Admin Users found: " . count($users) . "\n";
print_r($users);

$authService = new \App\Services\AuthService();
foreach ($users as $u) {
    echo "Permissions for user " . $u['username'] . ":\n";
    print_r($authService->getUserPermissions($u['id']));
}
