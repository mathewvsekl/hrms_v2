<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("DESCRIBE exchange_rates");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt2 = $db->query("SELECT * FROM exchange_rates LIMIT 5");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
