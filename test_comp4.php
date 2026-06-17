<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT * FROM payroll_components WHERE id = 13");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
