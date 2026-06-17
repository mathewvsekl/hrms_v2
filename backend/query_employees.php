<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('SELECT e.id, e.first_name, e.last_name FROM employees e LEFT JOIN employee_companies ec ON e.id=ec.employee_id');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
