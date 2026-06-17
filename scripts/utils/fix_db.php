<?php
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `user_otps` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `otp_code` VARCHAR(255) NOT NULL,
            `is_used` TINYINT(1) DEFAULT 0,
            `expires_at` DATETIME NOT NULL,
            `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table user_otps created/verified Successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
