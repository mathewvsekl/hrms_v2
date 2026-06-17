<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

$tables = ['payroll_records', 'payroll_components', 'employee_salary_components', 'exchange_rates'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    $stmt = $db->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    echo "\n";
}
