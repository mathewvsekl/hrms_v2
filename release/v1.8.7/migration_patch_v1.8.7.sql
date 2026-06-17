-- HRMS V2 Migration Patch v1.8.7
-- Focus: Multi-Type Leave Requests & Cancellation status

-- 1. Update Leave Requests Status Enum
ALTER TABLE `leave_requests` 
MODIFY COLUMN `status` ENUM('pending', 'approved', 'rejected', 'cancel_requested', 'cancelled') DEFAULT 'pending';

-- 2. Add Group ID for bundling multi-type requests
ALTER TABLE `leave_requests`
ADD COLUMN `request_group_id` VARCHAR(50) NULL AFTER `approved_by_id`,
ADD INDEX (`request_group_id`);

-- 3. Update version metadata
UPDATE `global_settings` SET `setting_value` = 'v1.8.7' WHERE `setting_key` = 'app_version';
