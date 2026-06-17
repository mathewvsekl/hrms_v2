-- ================================================================
-- HRMS V2 - Production Migration Patch v1.8.0
-- HUMAN CAPITAL BASE SYNC RELEASE
-- Baseline: v1.7.0 | Target: v1.8.0
-- Date: 2026-03-26
-- ================================================================

START TRANSACTION;

-- ========================================================
-- 1. SOFT-DEACTIVATION FOR EMPLOYEE-COMPANY LINKS
-- ========================================================
-- Requirement: Transition from hard-delete to soft-deactivation
-- for analytical and historical data integrity.

ALTER TABLE employee_companies 
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE COMMENT 'Soft-deactivation flag',
ADD COLUMN IF NOT EXISTS deactivated_at_utc TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp of deactivation';

-- ========================================================
-- 2. GLOBAL SETTINGS: STRATEGIC NOMENCLATURE
-- ========================================================
-- Syncing system-wide naming with Executive Rebranding.

INSERT INTO global_settings (setting_key, setting_value, category)
VALUES 
('dashboard_title', 'HR Administration Centre', 'ui'),
('employee_label_singular', 'Human Capital Asset', 'ui'),
('employee_label_plural', 'Human Capital Base', 'ui'),
('attendance_label', 'Workforce Readiness', 'ui')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ========================================================
-- 3. ATTENDANCE STATUS REFINEMENTS (METRIC SYNC)
-- ========================================================
-- Ensure system-level status labels match the new nomenclature.

UPDATE office_attendance_status_definitions 
SET status_label = 'Workplace Presence' 
WHERE status_key = 'present';

UPDATE office_attendance_status_definitions 
SET status_label = 'Remote Contribution' 
WHERE status_key = 'work_from_home';

COMMIT;
