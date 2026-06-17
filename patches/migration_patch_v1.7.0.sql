-- ================================================================
-- HRMS V2 - Production Migration Patch v1.7.0
-- FULL FEATURE SYNC RELEASE
-- Baseline: v1.6.13 | Target: v1.7.0
-- Date: 2026-03-26
-- ================================================================
-- This version is primarily a CODE-SYNC release. All v1.6.13 schema
-- changes (soft-deactivation) are already applied. This patch ensures
-- data readiness for newly deployed features.
-- ================================================================

START TRANSACTION;

-- ========================================================
-- 1. ENSURE ATTENDANCE STATUS DEFINITIONS EXIST
-- ========================================================
SET @DefaultCompanyId = (SELECT id FROM companies LIMIT 1);

INSERT IGNORE INTO office_attendance_status_definitions
(company_id, status_key, status_label, color_code, is_default, sort_order)
SELECT @DefaultCompanyId, 'present', 'Present', '#10b981', 0, 0 FROM (SELECT 1) AS tmp WHERE @DefaultCompanyId IS NOT NULL
UNION ALL
SELECT @DefaultCompanyId, 'absent', 'Absent', '#ef4444', 0, 6 FROM (SELECT 1) AS tmp WHERE @DefaultCompanyId IS NOT NULL
UNION ALL
SELECT @DefaultCompanyId, 'work_from_home', 'Work From Home', '#6366f1', 0, 7 FROM (SELECT 1) AS tmp WHERE @DefaultCompanyId IS NOT NULL
UNION ALL
SELECT @DefaultCompanyId, 'on_site', 'On Site', '#06b6d4', 1, 1 FROM (SELECT 1) AS tmp WHERE @DefaultCompanyId IS NOT NULL
UNION ALL
SELECT @DefaultCompanyId, 'remote', 'Remote', '#3b82f6', 0, 2 FROM (SELECT 1) AS tmp WHERE @DefaultCompanyId IS NOT NULL
UNION ALL
SELECT @DefaultCompanyId, 'late', 'Late Arrival', '#f59e0b', 0, 3 FROM (SELECT 1) AS tmp WHERE @DefaultCompanyId IS NOT NULL
UNION ALL
SELECT @DefaultCompanyId, 'training', 'Training', '#8b5cf6', 0, 4 FROM (SELECT 1) AS tmp WHERE @DefaultCompanyId IS NOT NULL
UNION ALL
SELECT @DefaultCompanyId, 'business_trip', 'Business Trip', '#10b981', 0, 5 FROM (SELECT 1) AS tmp WHERE @DefaultCompanyId IS NOT NULL;

-- ========================================================
-- 2. ENSURE LEAVE TYPES EXIST
-- ========================================================
INSERT IGNORE INTO leave_types (name, code, is_paid, gender_restriction) VALUES
('Annual Leave', 'AL', 1, 'none'),
('Sick Leave', 'SL', 1, 'none'),
('Maternity Leave', 'ML', 1, 'female'),
('Paternity Leave', 'PL', 1, 'male'),
('Unpaid Leave', 'UL', 0, 'none'),
('Compassionate Leave', 'CL', 1, 'none'),
('Study Leave', 'STL', 1, 'none');

-- ========================================================
-- 3. ENSURE ATTENDANCE CONFIGURE PERMISSION EXISTS
-- ========================================================
INSERT IGNORE INTO permissions (module, action) VALUES
('Attendance', 'configure');

SET @AdminId = (SELECT id FROM roles WHERE name IN ('ADMIN', 'Admin') LIMIT 1);
SET @HRManagerId = (SELECT id FROM roles WHERE name IN ('HR_MANAGER', 'HRManager') LIMIT 1);
SET @AttConfigPerm = (SELECT id FROM permissions WHERE module = 'Attendance' AND action = 'configure' LIMIT 1);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT @AdminId, @AttConfigPerm FROM (SELECT 1) AS tmp WHERE @AdminId IS NOT NULL AND @AttConfigPerm IS NOT NULL
UNION ALL
SELECT @HRManagerId, @AttConfigPerm FROM (SELECT 1) AS tmp WHERE @HRManagerId IS NOT NULL AND @AttConfigPerm IS NOT NULL;

-- ========================================================
-- 4. ENSURE DESIGNATION LEVEL COLUMN EXISTS
-- ========================================================
ALTER TABLE designations ADD COLUMN IF NOT EXISTS level INT DEFAULT 0;

COMMIT;
