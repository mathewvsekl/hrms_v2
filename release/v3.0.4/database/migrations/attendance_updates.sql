-- Attendance Module Updates: Approval Workflow and Audit Logging
-- Author: Liam (Attendance Engineer)

-- 1. Update attendance_logs table
ALTER TABLE `attendance_logs` 
ADD COLUMN `approval_status` ENUM('draft', 'submitted', 'approved', 'rejected') DEFAULT 'approved' AFTER `status`,
ADD COLUMN `submitted_by_id` INT NULL AFTER `approval_status`,
ADD COLUMN `approved_by_id` INT NULL AFTER `submitted_by_id`,
ADD COLUMN `remarks` TEXT NULL AFTER `approved_by_id`,
ADD FOREIGN KEY (`submitted_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
ADD FOREIGN KEY (`approved_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- 2. Create attendance_audit_logs table
CREATE TABLE `attendance_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `attendance_log_id` INT NOT NULL,
  `changed_by_id` INT NOT NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `change_reason` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`attendance_log_id`) REFERENCES `attendance_logs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Set existing records to 'approved' (already default, but explicit for clarity)
UPDATE `attendance_logs` SET `approval_status` = 'approved';
