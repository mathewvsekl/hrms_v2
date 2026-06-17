<?php
/**
 * Internal patch applier for v2.8.5
 * Safely adds personal_email and personal_phone to the employees table.
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    echo "Connected to database successfully.\n";

    // Check if personal_email exists
    $checkEmail = $db->query("SHOW COLUMNS FROM `employees` LIKE 'personal_email'");
    if ($checkEmail->rowCount() === 0) {
        echo "Adding 'personal_email' column...\n";
        $db->exec("ALTER TABLE `employees` ADD COLUMN `personal_email` VARCHAR(100) NULL AFTER `email`");
        echo "Added 'personal_email' successfully.\n";
    } else {
        echo "'personal_email' already exists.\n";
    }

    // Check if personal_phone exists
    $checkPhone = $db->query("SHOW COLUMNS FROM `employees` LIKE 'personal_phone'");
    if ($checkPhone->rowCount() === 0) {
        echo "Adding 'personal_phone' column...\n";
        $db->exec("ALTER TABLE `employees` ADD COLUMN `personal_phone` VARCHAR(30) NULL AFTER `phone`");
        echo "Added 'personal_phone' successfully.\n";
    } else {
        echo "'personal_phone' already exists.\n";
    }

    echo "Migration v2.8.5 completed successfully!\n";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
