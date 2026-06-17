<?php
require 'c:\Users\AneeshMathew\HRMS V2\backend\config\database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SHOW TABLES");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
