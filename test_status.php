<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT id, month, year, status FROM payroll_records");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
