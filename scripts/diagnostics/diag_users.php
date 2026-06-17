<?php
require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

echo "--- SESSION DATA ---\n";
session_start();
print_r($_SESSION);

echo "\n--- CURRENT USER INFO ---\n";
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT u.id, u.username, e.first_name, e.last_name FROM users u JOIN employees e ON u.employee_id = e.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} else {
    echo "No user logged in in this CLI session.\n";
}

echo "\n--- ALL USERS ---\n";
$stmt = $db->query("SELECT u.id, u.username, e.first_name, e.last_name FROM users u LEFT JOIN employees e ON u.employee_id = e.id");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- ALL NOTIFICATIONS ---\n";
$stmt = $db->query("SELECT * FROM notifications");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
