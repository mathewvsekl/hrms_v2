<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('DESCRIBE employee_salary_components');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
