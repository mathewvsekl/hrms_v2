<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();

try {
    $db->exec("ALTER TABLE payroll_components ADD COLUMN round_off TINYINT(1) DEFAULT 0 AFTER is_income_tax");
    echo "Column 'round_off' added successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'round_off' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
