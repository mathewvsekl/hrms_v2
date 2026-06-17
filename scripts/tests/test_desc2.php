<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('DESCRIBE payroll_records');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
