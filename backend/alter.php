<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();
try {
    $db->exec('ALTER TABLE payroll_components ADD COLUMN is_income_tax TINYINT(1) DEFAULT 0 AFTER is_non_taxable');
    echo 'Added is_income_tax\n';
} catch (PDOException $e) {
    echo 'is_income_tax exists or error: ' . $e->getMessage() . '\n';
}
