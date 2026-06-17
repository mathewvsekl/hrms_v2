-- HRMS V2 Migration Patch v2.7.0
-- Generated on 2026-05-13
-- Focus: Finalizing Leave Cancellation workflow

-- IMPORTANT: Ensure you have selected the correct database before running this script.
-- Example: USE hrms_v2; 


-- 1. Leave Requests Schema Hardening (Cancellation Reason)
-- Adding missing column that caused the 'Failed to request cancellation' error
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL AFTER attachment_path;

-- 2. Redundancy Check: Ensure remarks and attachment_path exist
-- (In case previous v2.6.8/v2.6.9 migrations were partially skipped)
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS remarks TEXT NULL AFTER manager_comment;
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL AFTER remarks;

-- 3. Redundancy Check: Leave Type Multi-Company Isolation
ALTER TABLE leave_types ADD UNIQUE KEY IF NOT EXISTS `unique_company_code` (`company_id`, `code`);

-- 4. Attendance Status Soft Delete
ALTER TABLE office_attendance_status_definitions ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0;
