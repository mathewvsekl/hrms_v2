<?php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS `notifications` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT NOT NULL,
      `type` VARCHAR(50) NOT NULL COMMENT 'e.g., leave_request, appraisal_update, system_alert',
      `title` VARCHAR(255) NOT NULL,
      `message` TEXT NOT NULL,
      `data` JSON NULL COMMENT 'Store related IDs like { \"leave_id\": 123, \"link\": \"/leave/123\" }',
      `is_read` TINYINT(1) DEFAULT 0,
      `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `read_at_utc` TIMESTAMP NULL,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
      INDEX `idx_user_unread` (`user_id`, `is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $db->exec($sql);
    echo "Migration successful: notifications table created.\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
