<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

try {
    $db->query("ALTER TABLE salary_advance_installments ADD COLUMN deduction_date DATE NULL, ADD COLUMN remaining_balance DECIMAL(15,2) NULL;");
    echo "Columns added successfully.";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Columns already exist.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
