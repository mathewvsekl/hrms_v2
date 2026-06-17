<?php
require_once 'app/Core/Database.php';
$db = \Database::getInstance()->getConnection();

echo "=== USERS ===\n";
$stmt = $db->query("
    SELECT u.id, u.username, e.first_name, e.last_name 
    FROM users u 
    LEFT JOIN employees e ON u.employee_id = e.id
");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== NOTIFICATIONS ===\n";
$stmt = $db->query("SELECT * FROM notifications");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== SESSION (current) ===\n";
session_start();
print_r($_SESSION);
?>
