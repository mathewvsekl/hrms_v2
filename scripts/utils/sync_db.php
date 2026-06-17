<?php
require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

echo "Starting Database Sync...\n";

// 1. Load and execute the main schema
$schemaFile = 'DATABASE_SCHEMA.sql';
if (file_exists($schemaFile)) {
    echo "Processing $schemaFile...\n";
    $sql = file_get_contents($schemaFile);
    
    // Split by semicolon (not perfect but works for this schema)
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        // Skip comments and empty queries
        if (empty($query) || str_starts_with($query, '--')) continue;
        try {
            $db->exec($query);
        } catch (Exception $e) {
            echo "Error executing query: " . $e->getMessage() . "\n";
            echo "Query Summary: " . substr($query, 0, 100) . "...\n";
        }
    }
    echo "Main schema processed.\n";
}

// 2. Run Appraisal Module Refinements (Columns I added recently)
echo "Running Appraisal Module Refinements...\n";
$refinements = [
    "ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS selected_offices JSON NULL AFTER status",
    "ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS employee_deadline DATE NULL AFTER selected_offices",
    "ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS manager_deadline DATE NULL AFTER employee_deadline",
    "ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS hr_deadline DATE NULL AFTER manager_deadline",
    "CREATE TABLE IF NOT EXISTS appraisal_approvals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appraisal_id INT NOT NULL,
        approver_id INT NOT NULL,
        status ENUM('pending', 'approved', 'returned') DEFAULT 'pending',
        comment TEXT,
        step_order INT DEFAULT 0,
        created_at_utc TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (appraisal_id) REFERENCES employee_appraisals(id) ON DELETE CASCADE,
        FOREIGN KEY (approver_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($refinements as $sql) {
    try {
        $db->exec($sql);
        echo "Executed: " . substr($sql, 0, 50) . "...\n";
    } catch (Exception $e) {
        echo "Error refined: " . $e->getMessage() . "\n";
    }
}

echo "Database Sync Complete.\n";
