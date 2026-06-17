-- ================================================================
-- HRMS V2 - Production Migration Patch v1.8.2
-- CONSOLIDATED RELEASE MIGRATION (v1.8.1 + v1.8.2)
-- Baseline: v1.8.0 | Target: v1.8.2
-- Date: 2026-03-26
-- ================================================================

START TRANSACTION;

-- --------------------------------------------------------
-- 1. LEAVE TYPES: GENDER RESTRICTION (v1.8.1)
-- --------------------------------------------------------
ALTER TABLE leave_types 
ADD COLUMN IF NOT EXISTS gender_restriction ENUM('none', 'male', 'female') DEFAULT 'none';

UPDATE leave_types SET gender_restriction = 'female' WHERE code LIKE '%maternity%';
UPDATE leave_types SET gender_restriction = 'male' WHERE code LIKE '%paternity%';

-- --------------------------------------------------------
-- 2. OFFICE WEEKLY SCHEDULES (v1.8.2)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `office_weekly_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT NOT NULL,
    `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    `status` VARCHAR(50) DEFAULT 'Workday',
    `remarks` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `company_day` (`company_id`, `day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. ATTENDANCE AUDIT & DEFAULTS (v1.8.2)
-- --------------------------------------------------------

-- Add Audit Columns to attendance_logs
ALTER TABLE `attendance_logs` ADD COLUMN IF NOT EXISTS `is_default_applied` TINYINT(1) DEFAULT 0;
ALTER TABLE `attendance_logs` ADD COLUMN IF NOT EXISTS `is_manually_modified` TINYINT(1) DEFAULT 0;
ALTER TABLE `attendance_logs` ADD COLUMN IF NOT EXISTS `actor_type` ENUM('system', 'user') DEFAULT 'user';

-- Convert ENUM columns to VARCHAR for flexibility (Statuses and Sources)
ALTER TABLE `attendance_logs` MODIFY COLUMN `source` VARCHAR(50) DEFAULT 'web';
ALTER TABLE `attendance_logs` MODIFY COLUMN `status` VARCHAR(50) DEFAULT 'present';
ALTER TABLE `attendance_logs` MODIFY COLUMN `approval_status` VARCHAR(50) DEFAULT 'approved';

COMMIT;
