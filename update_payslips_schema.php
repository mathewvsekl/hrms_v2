<?php
require 'backend/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $db->exec('ALTER TABLE payslips ADD COLUMN company_id INT DEFAULT NULL AFTER employee_id');
    echo "Successfully added company_id column.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
