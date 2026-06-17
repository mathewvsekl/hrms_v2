<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

$tables = ['employees', 'employee_companies'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    try {
        $stmt = $db->query("DESCRIBE $table");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo $col['Field'] . " - " . $col['Type'] . "\n";
        }
    } catch (Exception $e) {
        echo "Table does not exist or error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
