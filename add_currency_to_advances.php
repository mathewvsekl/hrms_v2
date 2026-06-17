<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

try {
    $db->exec("ALTER TABLE salary_advances ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER amount");
    echo "Successfully added currency_code to salary_advances.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
