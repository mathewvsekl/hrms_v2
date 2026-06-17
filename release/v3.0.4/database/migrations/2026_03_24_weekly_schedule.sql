-- Migration: Add office_weekly_schedules and upgrade status columns
-- This migration converts ENUM status columns to VARCHAR to support dynamic leave types 
-- and adds the weekly schedule table for office-level defaults.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Alter attendance_logs status
ALTER TABLE `attendance_logs` MODIFY COLUMN `status` VARCHAR(50) DEFAULT 'present';

-- 2. Alter office_attendance_configs status
ALTER TABLE `office_attendance_configs` MODIFY COLUMN `status` VARCHAR(50) NOT NULL;

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

SET FOREIGN_KEY_CHECKS = 1;
