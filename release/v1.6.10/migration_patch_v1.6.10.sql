-- HRMS V2 - Production Migration Patch v1.6.10
-- Goal: Seed Dashboard Data (Countries & Attendance Statuses)
-- Baseline: v1.6.9 | Target: v1.6.10
-- Created: 2026-03-25

START TRANSACTION;

-- ========================================================
-- 1. SEED CORE COUNTRIES (Dashboard Geographic Dist.)
-- ========================================================
INSERT IGNORE INTO `countries` (`name`, `iso_code`, `currency_code`, `default_timezone`) VALUES 
('Uganda', 'UGA', 'UGX', 'Africa/Kampala'),
('United Arab Emirates', 'ARE', 'AED', 'Asia/Dubai'),
('India', 'IND', 'INR', 'Asia/Kolkata'),
('Kenya', 'KEN', 'KES', 'Africa/Nairobi'),
('Tanzania', 'TZA', 'TZS', 'Africa/Dar_es_Salaam'),
('Rwanda', 'RWA', 'RWF', 'Africa/Kigali');

-- ========================================================
-- 2. SEED DEFAULT ATTENDANCE STATUS COLORS (Optional per company)
-- ========================================================
-- Note: These can be customized in the UI, but seeding a few for the first company provides an immediate visual experience
SET @DefaultCompanyId = (SELECT id FROM `companies` LIMIT 1);

INSERT IGNORE INTO `office_attendance_status_definitions` 
(`company_id`, `status_key`, `status_label`, `color_code`, `is_default`, `sort_order`) 
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
-- 3. ENSURE ESSENTIAL PERMISSIONS
-- ========================================================
-- Ensure Admin/HR can manage attendance status definitions
INSERT IGNORE INTO `permissions` (`module`, `action`) VALUES 
('Attendance', 'configure');

SET @AdminId = (SELECT id FROM `roles` WHERE name = 'ADMIN' LIMIT 1);
SET @HRManagerId = (SELECT id FROM `roles` WHERE name = 'HR_MANAGER' LIMIT 1);
SET @PermissionId = (SELECT id FROM `permissions` WHERE module = 'Attendance' AND action = 'configure' LIMIT 1);

INSERT IGNORE INTO `role_permissions` (role_id, permission_id) VALUES 
(@AdminId, @PermissionId),
(@HRManagerId, @PermissionId);

COMMIT;
