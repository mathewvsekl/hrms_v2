-- migration_patch_v1.8.5.sql
-- Add color_code to leave_types to support attendance and leave calendar colors

-- ALTER TABLE `leave_types` ADD COLUMN `color_code` VARCHAR(7) DEFAULT '#6b7280' AFTER `gender_restriction`;

-- Update default colors for standard leave types
UPDATE `leave_types` SET `color_code` = '#10b981' WHERE `code` IN ('AL', 'annual');
UPDATE `leave_types` SET `color_code` = '#ef4444' WHERE `code` IN ('SL', 'sick');
UPDATE `leave_types` SET `color_code` = '#f59e0b' WHERE `code` IN ('ML', 'PL', 'maternity', 'paternity');
UPDATE `leave_types` SET `color_code` = '#3b82f6' WHERE `code` IN ('UL', 'unpaid');
UPDATE `leave_types` SET `color_code` = '#8b5cf6' WHERE `code` IN ('ST', 'study');
UPDATE `leave_types` SET `color_code` = '#ec4899' WHERE `code` IN ('CP', 'compassionate');
