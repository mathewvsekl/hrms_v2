<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

try {
    $db->exec("ALTER TABLE salary_advances ADD COLUMN deduction_start_date DATE NULL AFTER installment_amount");
    echo "Added deduction_start_date to salary_advances.\n";
} catch (Exception $e) {
    echo "Error adding deduction_start_date: " . $e->getMessage() . "\n";
}

echo "Done.\n";
