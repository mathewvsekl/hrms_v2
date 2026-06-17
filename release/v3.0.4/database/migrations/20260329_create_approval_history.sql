-- Migrations for Approval History
-- Created: 2026-03-29 
-- Module: System Audit

CREATE TABLE IF NOT EXISTS `approval_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module` ENUM('leave', 'appraisal', 'attendance') NOT NULL,
    `reference_id` INT NOT NULL COMMENT 'ID from leave_requests, employee_appraisals, or attendance_logs',
    `actor_id` INT NOT NULL COMMENT 'users.id of the person who performed the action',
    `action` VARCHAR(50) NOT NULL COMMENT 'submitted, approved, rejected, returned, cancelled, finalized',
    `comment` TEXT NULL,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_module_ref` (`module`, `reference_id`),
    FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
