<?php
require_once __DIR__ . '/config/database.php';
$db = \Database::getInstance()->getConnection();
try {
    $db->exec("ALTER TABLE payroll_components ADD COLUMN display_in_payslip TINYINT(1) DEFAULT 1;");
    echo "Column display_in_payslip added successfully.\n";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column display_in_payslip already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
