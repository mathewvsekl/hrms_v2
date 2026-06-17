-- HRMS V2 - Migration Patch v1.8.6
-- Consolidation of Notification System, Appraisal Refinements, and Company Metadata
-- Release ID: 20260328_210300
-- Baseline: v1.8.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. NOTIFICATION SYSTEM
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL COMMENT 'e.g., info, success, warning, error',
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `data` JSON NULL COMMENT 'Associated metadata for notification actions',
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at_utc` TIMESTAMP NULL DEFAULT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. APPRAISAL SYSTEM REFINEMENTS (Extracted from appraisal_refinements.sql)
ALTER TABLE `appraisal_cycles` ADD COLUMN IF NOT EXISTS `selected_offices` JSON NULL AFTER `status`;
ALTER TABLE `appraisal_cycles` ADD COLUMN IF NOT EXISTS `employee_deadline` DATE NULL AFTER `selected_offices`;
ALTER TABLE `appraisal_cycles` ADD COLUMN IF NOT EXISTS `manager_deadline` DATE NULL AFTER `employee_deadline`;
ALTER TABLE `appraisal_cycles` ADD COLUMN IF NOT EXISTS `hr_deadline` DATE NULL AFTER `manager_deadline`;

CREATE TABLE IF NOT EXISTS `appraisal_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `appraisal_id` INT NOT NULL,
    `approver_id` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'returned') DEFAULT 'pending',
    `comment` TEXT,
    `step_order` INT DEFAULT 0,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approver_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. COMPANY BRANDING & META (Extracted from add_company_contact_info_migration.sql)
ALTER TABLE `companies` 
ADD COLUMN IF NOT EXISTS `contact_phone` VARCHAR(30) NULL AFTER `address`,
ADD COLUMN IF NOT EXISTS `contact_email` VARCHAR(100) NULL AFTER `contact_phone`;

-- 4. SYSTEM VERSION TRACKING
UPDATE `global_settings` SET `setting_value` = 'v1.8.6' WHERE `setting_key` = 'system_version';
-- If system_version doesn't exist, insert it
INSERT IGNORE INTO `global_settings` (`setting_key`, `setting_value`, `category`) VALUES ('system_version', 'v1.8.6', 'system');

COMMIT;
