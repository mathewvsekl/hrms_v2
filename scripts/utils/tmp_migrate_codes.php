<?php
require_once 'config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    // 1. Alter leave_types to add display_code and company_id
    try {
        $db->exec("ALTER TABLE leave_types ADD COLUMN company_id INT NULL AFTER id");
        $db->exec("ALTER TABLE leave_types ADD FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE");
        echo "Added company_id to leave_types.\n";
    } catch (Exception $e) { echo "company_id already exists or error: " . $e->getMessage() . "\n"; }
    
    try {
        $db->exec("ALTER TABLE leave_types ADD COLUMN display_code VARCHAR(10) NULL AFTER code");
        echo "Added display_code to leave_types.\n";
    } catch (Exception $e) { echo "display_code already exists or error: " . $e->getMessage() . "\n"; }

    // 2. Alter office_attendance_status_definitions to add display_code
    try {
        $db->exec("ALTER TABLE office_attendance_status_definitions ADD COLUMN display_code VARCHAR(10) NULL AFTER status_key");
        echo "Added display_code to office_attendance_status_definitions.\n";
    } catch (Exception $e) { echo "display_code already exists or error: " . $e->getMessage() . "\n"; }

    echo "Migration schemas altered successfully.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
