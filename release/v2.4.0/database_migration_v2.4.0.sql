-- HRMS V2 Database Migration: v2.3.0 -> v2.4.0
-- Purpose: Support Multi-Company Attendance Codes and Leave Type Branding (Idempotent Fix)
-- Date: 2026-04-01

DELIMITER //

CREATE PROCEDURE IF NOT EXISTS Migration_v2_4_0()
BEGIN
    -- 1. Add company_id to leave_types if missing
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='leave_types' AND COLUMN_NAME='company_id' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `leave_types` ADD COLUMN `company_id` INT NULL AFTER `id`;
        ALTER TABLE `leave_types` ADD CONSTRAINT `fk_leave_types_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE;
    END IF;

    -- 2. Add display_code to leave_types if missing
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='leave_types' AND COLUMN_NAME='display_code' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `leave_types` ADD COLUMN `display_code` VARCHAR(10) NULL AFTER `code`;
    END IF;

    -- 3. Add display_code to office_attendance_status_definitions if missing
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='office_attendance_status_definitions' AND COLUMN_NAME='display_code' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `office_attendance_status_definitions` ADD COLUMN `display_code` VARCHAR(10) NULL AFTER `status_key`;
    END IF;

    -- 4. Update existing records with default display codes
    UPDATE `leave_types` SET `display_code` = UPPER(LEFT(`code`, 3)) WHERE `display_code` IS NULL;
    UPDATE `office_attendance_status_definitions` SET `display_code` = UPPER(LEFT(`status_key`, 3)) WHERE `display_code` IS NULL;

END //

DELIMITER ;

-- Execute and cleanup
CALL Migration_v2_4_0();
DROP PROCEDURE IF EXISTS Migration_v2_4_0;
