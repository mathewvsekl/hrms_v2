-- HRMS V2 Migration Patch v2.6.9
-- Generated on 2026-05-13

-- 1. Leave Type Multi-Company Isolation
-- Note: If 'code' index was already removed, the following line can be skipped.
-- ALTER TABLE leave_types DROP INDEX code;
ALTER TABLE leave_types ADD UNIQUE KEY IF NOT EXISTS `unique_company_code` (`company_id`, `code`);

-- 2. Attendance Status Soft Delete
-- Enable hiding of status definitions without breaking historical logs
ALTER TABLE office_attendance_status_definitions ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0;

-- 3. Leave Requests Schema Hardening
-- Add missing columns required by the new multi-segment logic
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS remarks TEXT NULL AFTER manager_comment;
ALTER TABLE leave_requests ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL AFTER remarks;

-- 4. Metadata Update
-- (Optional) Update system settings or seed new values if required.
