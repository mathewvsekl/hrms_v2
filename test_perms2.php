<?php
define('BASE_PATH', __DIR__ . '/backend');
require_once BASE_PATH . '/app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/backend/.env');
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$db = \Database::getInstance()->getConnection();
$stmt = $db->query("SELECT id, username FROM users LIMIT 15");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$auth = new \App\Services\AuthService();
foreach($users as $u) {
    echo "User: " . $u['username'] . "\n";
    $role = $auth->getUserRole($u['id']);
    echo "Role: " . $role . "\n";
    $perms = $auth->getUserPermissions($u['id']);
    print_r($perms);
    echo "--------------------------\n";
}
