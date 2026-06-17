<?php
require 'c:\Users\AneeshMathew\HRMS V2\backend\config\database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('SELECT u.id, u.username, u.is_active, u.employee_id, e.email FROM users u LEFT JOIN employees e ON u.employee_id = e.id LIMIT 20');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
