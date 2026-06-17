<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('DESCRIBE salary_advance_installments');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
