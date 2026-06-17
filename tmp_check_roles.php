<?php
require 'backend/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT u.id, e.first_name, e.last_name, r.name, br.name as base_name FROM employees e JOIN users u ON u.employee_id = e.id JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id LEFT JOIN roles br ON r.base_role_id = br.id WHERE e.first_name = 'Atim'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
