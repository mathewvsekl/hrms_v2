-- HRMS V2 - Database Upgrade Patch: v1.6.4 -> v1.6.5
-- Date: 2026-03-24
-- Description: Adds Office-level attendance configurations, weekly schedules, and custom status support.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Upgrade attendance_logs structure
ALTER TABLE `attendance_logs` 
MODIFY COLUMN `status` VARCHAR(50) DEFAULT 'present',
MODIFY COLUMN `source` ENUM('web', 'mobile', 'biometric', 'manual', 'leave_module') DEFAULT 'web';

-- 2. Create office_attendance_configs table
CREATE TABLE IF NOT EXISTS `office_attendance_configs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `config_date` DATE NOT NULL,
  `status` VARCHAR(50) NOT NULL COMMENT 'Dynamic status key matching office_attendance_status_definitions or system defaults',
  `remarks` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_office_date` (`company_id`, `config_date`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create office_weekly_schedules table
CREATE TABLE IF NOT EXISTS `office_weekly_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `remarks` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `company_day` (`company_id`, `day_of_week`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create office_attendance_status_definitions table
CREATE TABLE IF NOT EXISTS `office_attendance_status_definitions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `status_key` VARCHAR(50) NOT NULL COMMENT 'e.g., work_from_home, on_site',
  `status_label` VARCHAR(100) NOT NULL COMMENT 'e.g., Work From Home',
  `color_code` VARCHAR(20) DEFAULT '#3b82f6',
  `is_default` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `company_status_key` (`company_id`, `status_key`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Log upgrade completion
-- INSERT INTO system_audit_log (action, details, version) VALUES ('DATABASE_UPGRADE', 'Schema upgraded to v1.6.5', 'v1.6.5');
