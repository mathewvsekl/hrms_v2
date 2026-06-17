-- HRMS V2.6.0 Database Migration Patch (Rollback Restoration)
-- Description: Reverts ISO-prefixed status codes to legacy generic strings (e.g., PR_UG -> present)
-- Prepared by: Antigravity (Advanced Coding Agent) 
-- Date: 2026-04-01

START TRANSACTION;

-- 1. Restore Attendance Logs
-- Inverting the V2.5.0 mapping logic
UPDATE attendance_logs SET status = 'present' WHERE status LIKE 'PR_%';
UPDATE attendance_logs SET status = 'absent' WHERE status LIKE 'AB_%';
UPDATE attendance_logs SET status = 'late' WHERE status LIKE 'LT_%';
UPDATE attendance_logs SET status = 'half_day' WHERE status LIKE 'HD_%';
UPDATE attendance_logs SET status = 'on_leave' WHERE status LIKE 'OL_%';
UPDATE attendance_logs SET status = 'training' WHERE status LIKE 'TR_%';
UPDATE attendance_logs SET status = 'on_site' WHERE status LIKE 'OS_%';
UPDATE attendance_logs SET status = 'work_from_home' WHERE status LIKE 'WH_%';
UPDATE attendance_logs SET status = 'remote' WHERE status LIKE 'RE_%';
UPDATE attendance_logs SET status = 'business_trip' WHERE status LIKE 'BT_%';
UPDATE attendance_logs SET status = 'late_arrival' WHERE status LIKE 'LA_%';
UPDATE attendance_logs SET status = 'weekend' WHERE status LIKE 'WE_%';
UPDATE attendance_logs SET status = 'public_holiday' WHERE status LIKE 'PH_%';
UPDATE attendance_logs SET status = 'holiday' WHERE status LIKE 'HO_%';

-- 2. Restore Status Definitions Mapping
-- Map keys like PR_UG to present based on prefix
UPDATE office_attendance_status_definitions SET status_key = 'present' WHERE status_key LIKE 'PR_%';
UPDATE office_attendance_status_definitions SET status_key = 'absent' WHERE status_key LIKE 'AB_%';
UPDATE office_attendance_status_definitions SET status_key = 'work_from_home' WHERE status_key LIKE 'WH_%';
UPDATE office_attendance_status_definitions SET status_key = 'on_site' WHERE status_key LIKE 'OS_%';
UPDATE office_attendance_status_definitions SET status_key = 'training' WHERE status_key LIKE 'TR_%';
UPDATE office_attendance_status_definitions SET status_key = 'weekend' WHERE status_key LIKE 'WE_%';
UPDATE office_attendance_status_definitions SET status_key = 'public_holiday' WHERE status_key LIKE 'PH_%';
UPDATE office_attendance_status_definitions SET status_key = 'holiday' WHERE status_key LIKE 'HO_%';

-- 3. Restore Leave Type Codes
UPDATE leave_types SET code = 'SL' WHERE code = 'SL_SYS1';
UPDATE leave_types SET code = 'AL' WHERE code = 'AL_SYS1' OR code = 'AL_UG';

-- 4. Schema Cleanup
-- Drop the iso2_code column if it was successfully added in v2.4.0/v2.5.0
SET @s = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'countries' AND COLUMN_NAME = 'iso2_code' AND TABLE_SCHEMA = DATABASE()) > 0,
    'ALTER TABLE countries DROP COLUMN iso2_code',
    'SELECT 1'
));
PREPARE stmt FROM @s;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;

-- Final Verification Check (Optional)
-- SELECT DISTINCT status FROM attendance_logs;
