<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->query("SELECT c.id, c.name, c.country_id, co.currency_code FROM companies c LEFT JOIN countries co ON c.country_id = co.id");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
