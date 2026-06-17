-- Migration: Add General Audit Logs Table
-- Date: 2026-05-16

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL COMMENT 'User who performed the action',
    `action` VARCHAR(50) NOT NULL COMMENT 'CREATE, UPDATE, DELETE, LOGIN, etc.',
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'employees, companies, assets, etc.',
    `entity_id` INT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at_utc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
