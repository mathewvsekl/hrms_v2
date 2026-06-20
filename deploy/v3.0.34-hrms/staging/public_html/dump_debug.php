<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT * FROM roles");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "ROLES:\n"; print_r($roles);

$stmt = $db->query("SELECT u.id as user_id, u.employee_id, e.first_name FROM users u JOIN employees e ON u.employee_id = e.id WHERE e.first_name LIKE '%Atim%'");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nUSERS:\n"; print_r($users);

if (count($users) > 0) {
    $stmt = $db->prepare("SELECT * FROM user_roles WHERE user_id = ?");
    $stmt->execute([$users[0]['user_id']]);
    $userRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nUSER_ROLES:\n"; print_r($userRoles);
    
    $stmt = $db->prepare("SELECT * FROM employee_companies WHERE employee_id = ?");
    $stmt->execute([$users[0]['employee_id']]);
    echo "\nEMPLOYEE_COMPANIES:\n"; print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
