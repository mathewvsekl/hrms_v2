<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance()->getConnection();
try {
    $db->exec("ALTER TABLE payslips ADD COLUMN company_id INT NULL AFTER employee_id");
    echo "Column added successfully.";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
