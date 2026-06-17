-- HRMS V2 - Migration Fix for Company Updates (v1.6.4)
-- Run this on your Production Database inside the HRMS V2 database.

-- 1. Ensure contact fields exist in companies
ALTER TABLE `companies` 
ADD COLUMN IF NOT EXISTS `contact_phone` VARCHAR(30) AFTER `address`,
ADD COLUMN IF NOT EXISTS `contact_email` VARCHAR(100) AFTER `contact_phone`;

-- 2. Ensure attendance_mode ENUM is correct and is_time_tracking_enabled exists
-- Pre-migrate old values (standard, strict, flexible) to 'time_based' to prevent truncation errors
UPDATE `companies` SET `attendance_mode` = 'time_based' WHERE `attendance_mode` NOT IN ('time_based', 'status_based');

ALTER TABLE `companies` 
MODIFY COLUMN `attendance_mode` ENUM('time_based', 'status_based') DEFAULT 'time_based',
ADD COLUMN IF NOT EXISTS `is_time_tracking_enabled` BOOLEAN DEFAULT FALSE AFTER `attendance_mode`;

-- 3. Verify character set (matching the schema)
ALTER TABLE `companies` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
