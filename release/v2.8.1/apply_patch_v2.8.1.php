<?php
/**
 * Database Patch Applier v2.8.1
 * Safely applies schema changes for the v2.8.1 release.
 */

require_once __DIR__ . '/../../config/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting Migration v2.8.1...\n";

    // 1. Update approval_history module enum
    echo "Updating approval_history modules...\n";
    $pdo->exec("ALTER TABLE `approval_history` MODIFY COLUMN `module` ENUM('leave', 'appraisal', 'attendance', 'onboarding') NOT NULL");

    // 2. Add job_description to employees
    echo "Checking for job_description column...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM `employees` LIKE 'job_description'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `employees` ADD COLUMN `job_description` TEXT NULL COMMENT 'Brief employment details/role description' AFTER `employment_type` ");
        echo "Added job_description column.\n";
    } else {
        echo "job_description already exists.\n";
    }

    // 3. Drop tin_number
    echo "Dropping tin_number...\n";
    try {
        $pdo->exec("ALTER TABLE `employees` DROP COLUMN `tin_number` ");
        echo "Dropped tin_number.\n";
    } catch (Exception $e) {
        echo "tin_number already dropped or missing.\n";
    }

    // 4. Drop nssf_number
    echo "Dropping nssf_number...\n";
    try {
        $pdo->exec("ALTER TABLE `employees` DROP COLUMN `nssf_number` ");
        echo "Dropped nssf_number.\n";
    } catch (Exception $e) {
        echo "nssf_number already dropped or missing.\n";
    }

    echo "Migration v2.8.1 completed successfully!\n";

} catch (PDOException $e) {
    die("CRITICAL ERROR during migration: " . $e->getMessage() . "\n");
}
