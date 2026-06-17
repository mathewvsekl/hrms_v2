<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->query("DESCRIBE tax_slabs");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}
