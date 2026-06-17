-- Migration: Add audit columns to attendance_logs
-- Created: 2026-03-26

ALTER TABLE `attendance_logs` 
ADD COLUMN `is_default_applied` TINYINT(1) DEFAULT 0 COMMENT 'True if status was automatically applied by system',
ADD COLUMN `is_manually_modified` TINYINT(1) DEFAULT 0 COMMENT 'True if status was overridden by a user',
ADD COLUMN `actor_type` ENUM('system', 'user') DEFAULT 'user' COMMENT 'Who performed the last status change';

-- Update existing records: if source is 'manual', set is_manually_modified = 1
UPDATE `attendance_logs` SET `is_manually_modified` = 1, `actor_type` = 'user' WHERE `source` = 'manual';
-- If source is 'leave_module' or 'holiday_sync', set is_default_applied = 1, actor_type = 'system'
UPDATE `attendance_logs` SET `is_default_applied` = 1, `actor_type` = 'system' WHERE `source` IN ('leave_module', 'holiday_sync');
