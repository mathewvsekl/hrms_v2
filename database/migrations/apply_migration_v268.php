<?php
require_once 'config/config.php';
require_once 'config/database.php';

echo "Applying migration patch v2.6.8 to environment: " . ACTIVE_ENVIRONMENT . "\n";

try {
    $db = Database::getInstance()->getConnection();
    
    $sqlFile = 'release/v2.6.8/migration_patch_v2.6.8.sql';
    if (!file_exists($sqlFile)) {
        die("Error: Migration file not found at $sqlFile\n");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Execute the SQL
    // Note: We use exec() or multiple queries if needed. 
    // Since PDO doesn't support multiple queries in one exec() easily without emulation, 
    // and we have multiple statements, we'll split them or use the raw SQL.
    
    echo "Executing migration SQL...\n";
    
    // 1. Handle Leave Type Unique Key
    echo "- Updating leave_types unique key...\n";
    try {
        $db->exec("ALTER TABLE leave_types DROP INDEX code");
        echo "  Dropped old 'code' index.\n";
    } catch (Exception $e) {
        echo "  Index 'code' not found or already dropped. Skipping drop.\n";
    }
    
    try {
        $db->exec("ALTER TABLE leave_types ADD UNIQUE KEY `unique_company_code` (`company_id`, `code`)");
        echo "  Added 'unique_company_code' index.\n";
    } catch (Exception $e) {
        echo "  Unique key 'unique_company_code' already exists or error: " . $e->getMessage() . "\n";
    }
    
    // 2. Handle Attendance Status Soft Delete
    echo "- Adding is_deleted to office_attendance_status_definitions...\n";
    try {
        $db->exec("ALTER TABLE office_attendance_status_definitions ADD COLUMN is_deleted TINYINT(1) DEFAULT 0");
        echo "  Added 'is_deleted' column.\n";
    } catch (Exception $e) {
        echo "  Column 'is_deleted' already exists or error: " . $e->getMessage() . "\n";
    }
    
    // 3. Handle Leave Requests missing columns
    echo "- Adding missing columns to leave_requests...\n";
    try {
        $db->exec("ALTER TABLE leave_requests ADD COLUMN remarks TEXT NULL AFTER manager_comment");
        echo "  Added 'remarks' column to 'leave_requests'.\n";
    } catch (Exception $e) {
        echo "  Column 'remarks' already exists or error: " . $e->getMessage() . "\n";
    }

    try {
        $db->exec("ALTER TABLE leave_requests ADD COLUMN attachment_path VARCHAR(255) NULL AFTER remarks");
        echo "  Added 'attachment_path' column to 'leave_requests'.\n";
    } catch (Exception $e) {
        echo "  Column 'attachment_path' already exists or error: " . $e->getMessage() . "\n";
    }

    echo "Migration process complete.\n";
    
    // Verification
    echo "Verifying changes...\n";
    
    $lrColumns = $db->query("DESCRIBE leave_requests")->fetchAll(PDO::FETCH_COLUMN);
    echo "- Verifying 'leave_requests' columns:\n";
    foreach (['remarks', 'attachment_path'] as $col) {
        if (in_array($col, $lrColumns)) {
            echo "  [OK] Column '$col' found.\n";
        } else {
            echo "  [WARNING] Column '$col' NOT found!\n";
        }
    }

    $columns = $db->query("DESCRIBE office_attendance_status_definitions")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('is_deleted', $columns)) {
        echo "- Column 'is_deleted' found in 'office_attendance_status_definitions'.\n";
    } else {
        echo "- WARNING: Column 'is_deleted' NOT found!\n";
    }
    
    $indices = $db->query("SHOW INDEX FROM leave_types")->fetchAll(PDO::FETCH_ASSOC);
    $hasUnique = false;
    foreach ($indices as $index) {
        if ($index['Key_name'] === 'unique_company_code') {
            $hasUnique = true;
            break;
        }
    }
    if ($hasUnique) {
        echo "- Unique index 'unique_company_code' found in 'leave_types'.\n";
    } else {
        echo "- WARNING: Unique index 'unique_company_code' NOT found!\n";
    }

} catch (Exception $e) {
    echo "ERROR applying migration: " . $e->getMessage() . "\n";
}
