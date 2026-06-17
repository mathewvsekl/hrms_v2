<?php
require_once __DIR__ . '/../index.php'; // Load configuration and DB

header("Content-Type: application/json");

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
$roles = [];

if ($userId) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT r.name 
        FROM user_roles ur 
        JOIN roles r ON ur.role_id = r.id 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

echo json_encode([
    'session_id' => session_id(),
    'user_id' => $userId,
    'roles' => $roles,
    'scope_company_id' => $_SESSION['scope_company_id'] ?? null,
    'server_time' => date('Y-m-d H:i:s'),
    'remote_addr' => $_SERVER['REMOTE_ADDR']
]);
?>
