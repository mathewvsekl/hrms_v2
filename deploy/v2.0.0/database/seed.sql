SET FOREIGN_KEY_CHECKS = 0;
-- --------------------------------------------------------
-- OFFICE CONFIGURATION TEMPLATES SEEDER (2026 Compliance)
-- --------------------------------------------------------
-- Target Countries: UAE, Kenya, Uganda, Tanzania, Ethiopia, India

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

INSERT INTO `countries` (`id`, `name`, `iso_code`, `currency_code`, `default_timezone`, `tax_formula_config`, `pension_formula_config`) VALUES
(1, 'United Arab Emirates', 'ARE', 'AED', 'Asia/Dubai', '{"type": "no_income_tax", "vat_rate": 0.05}', '{"type": "gpps", "rate": "standard"}'),
(2, 'Kenya', 'KEN', 'KES', 'Africa/Nairobi', '{"type": "paye", "brackets": "standard_2026"}', '{"type": "nssf", "tier1": true, "tier2": true}'),
(3, 'Uganda', 'UGA', 'UGX', 'Africa/Kampala', '{"type": "paye", "brackets": "standard_2026"}', '{"type": "nssf", "employee_rate": 0.05, "employer_rate": 0.10}'),
(4, 'Tanzania', 'TZA', 'TZS', 'Africa/Dar_es_Salaam', '{"type": "paye", "brackets": "standard_2026"}', '{"type": "nssf", "employee_rate": 0.10, "employer_rate": 0.10}'),
(5, 'Ethiopia', 'ETH', 'ETB', 'Africa/Addis_Ababa', '{"type": "employment_tax", "brackets": "standard_2026"}', '{"type": "pf", "employee_rate": 0.07, "employer_rate": 0.11}'),
(6, 'India', 'IND', 'INR', 'Asia/Kolkata', '{"type": "tds", "regime": "new_2026"}', '{"type": "epf", "employee_rate": 0.12, "employer_rate": 0.12}');

-- 2. COMPANIES
INSERT INTO `companies` (`id`, `country_id`, `name`, `address`, `timezone`) VALUES
(1, 1, 'Avantgarde Inc FZCO', 'P.O. Box: 54833, 3W 214, DAFZA, Dubai, United Arab Emirates.\nTel: +971 4 2594192, Fax: +971 4 2502766', 'Asia/Dubai'),
(2, 2, 'Vision Scientific & Engineering Kenya Ltd.', 'Alpha Centre, Unit 87A, Mombasa Road\nP. O. Box: 14392-00800, Nairobi, Kenya\nTel: +254-722721665', 'Africa/Nairobi'),
(3, 3, 'Vision Scientific & Engineering Uganda Ltd.', 'Plot Shop G-3, Plot No. 231-233, Sixth Street\nIndustrial Area, Kampala-Uganda\nTel: +256 7091 67800', 'Africa/Kampala'),
(4, 4, 'Vision Scientific & Engineering Tanzania Ltd.', 'P.O Box: 5246-Dsm\nUnited Nations Road, Mtiti street\nDar Es Salaam, Tanzania\nTel: 00255 746 916 213', 'Africa/Dar_es_Salaam'),
(5, 5, 'Vision Scientific & Engineering Ethiopia Ltd.', 'Bole Biselex Building, 5th Floor\nAddis Ababa, Ethiopia', 'Africa/Addis_Ababa'),
(6, 6, 'Avantgarde Enterprises Pvt. Ltd. (India)', 'Bldg. No. 1-68/4 & 5, Arunodaya Co-Op Hsg. Soc. Ltd.\nMadhapur, Hyderabad - 500-081, India.\nTel: +91-40-4851-4122', 'Asia/Kolkata');

-- 4. OFFICE CUSTOM FIELDS (Compliance / Mandatory IDs)
-- UAE: Emirates ID, WPS Routing Code
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(1, 'emirates_id', 'Emirates ID', 'text', TRUE),
(1, 'wps_routing_code', 'WPS Routing Code', 'text', TRUE);

-- Kenya: KRA PIN, National ID, SHA Number, NSSF
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(2, 'kra_pin', 'KRA PIN', 'text', TRUE),
(2, 'national_id', 'National ID', 'text', TRUE),
(2, 'sha_number', 'SHA Number', 'text', TRUE),
(2, 'nssf_number', 'NSSF Number', 'text', TRUE);

-- Uganda: NIN, TIN, NSSF
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(3, 'national_id_nin', 'National ID (NIN)', 'text', TRUE),
(3, 'tin_number', 'TIN Number', 'text', TRUE),
(3, 'nssf_number', 'NSSF Number', 'text', TRUE);

-- Tanzania: NIDA, TIN, NSSF
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(4, 'nida_id', 'NIDA ID', 'text', TRUE),
(4, 'tin_number', 'TIN Number', 'text', TRUE),
(4, 'nssf_number', 'NSSF Number', 'text', TRUE);

-- Ethiopia: TIN, Pension ID
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(5, 'tin_number', 'TIN Number', 'text', TRUE),
(5, 'pension_id', 'Pension ID', 'text', TRUE);

-- India: PAN, Aadhaar, UAN (EPF)
INSERT INTO `company_custom_fields` (`company_id`, `field_key`, `field_name`, `field_type`, `is_required`) VALUES
(6, 'pan_number', 'PAN', 'text', TRUE),
(6, 'aadhaar_number', 'Aadhaar Card', 'text', TRUE),
(6, 'uan_number', 'UAN (EPF)', 'text', TRUE);

COMMIT;
-- HRMS V2 - Foundation Data Seeder
-- Initializes global RBAC roles and the root fallback Super Admin

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- 1. Seed the 5-Tier Operational Roles
INSERT IGNORE INTO `roles` (`id`, `name`) VALUES
(1, 'SUPER_ADMIN'),
(2, 'HR_MANAGER'),
(3, 'COUNTRY_MANAGER'),
(4, 'HR_ASSISTANT'),
(5, 'EMPLOYEE');

-- 2. Seed Leave Types
INSERT IGNORE INTO `leave_types` (`id`, `name`, `code`) VALUES
(1, 'Annual Leave', 'AL'),
(2, 'Sick Leave', 'SL'),
(3, 'Maternity Leave', 'ML'),
(4, 'Paternity Leave', 'PL'),
(5, 'Casual Leave', 'CL');

-- 2. Seed the Root Super Admin Credential (mathew.vsekl@gmail.com / admin123)
-- The password_hash represents 'admin123' passed through PHP's password_hash(..., PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `employee_id`, `username`, `password_hash`, `is_active`) VALUES
(1, NULL, 'mathew.vsekl@gmail.com', '$2y$12$jDRfytCw9NO4FaecsDjqK.N5aQyfvothqGvxAK5YDBg3TFdO8LZfy', 1)
ON DUPLICATE KEY UPDATE `username` = 'mathew.vsekl@gmail.com';

-- 3. Bind the Root Credential to the SUPER_ADMIN Role matrix
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
-- Complete RBAC Configuration for HRMS V2
-- Roles: SuperAdmin, Admin, HRManager, CountryManager, HRAssistant, Employee

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Reset RBAC Tables (Optional: Use with caution in production)
-- TRUNCATE TABLE `role_permissions`;
-- TRUNCATE TABLE `roles`;
-- TRUNCATE TABLE `permissions`;

-- 2. Insert Roles
INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'SUPER_ADMIN'),
(2, 'ADMIN'),
(3, 'HR_MANAGER'),
(4, 'COUNTRY_MANAGER'),
(5, 'HR_ASSISTANT'),
(6, 'EMPLOYEE')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. Insert Permissions
-- Modules: Dashboard, Directory, Employees, Attendance, Leave, Payroll, Appraisals, Offboarding, Reports, Configuration
-- Actions: view, create, edit, delete, approve

INSERT INTO `permissions` (`module`, `action`) VALUES
('Dashboard', 'view'),
('Directory', 'view'), ('Directory', 'create'), ('Directory', 'edit'), ('Directory', 'delete'),
('Employees', 'view'), ('Employees', 'create'), ('Employees', 'edit'), ('Employees', 'delete'),
('Attendance', 'view'), ('Attendance', 'create'), ('Attendance', 'edit'), ('Attendance', 'delete'), ('Attendance', 'approve'),
('Leave', 'view'), ('Leave', 'create'), ('Leave', 'edit'), ('Leave', 'delete'), ('Leave', 'approve'),
('Payroll', 'view'), ('Payroll', 'create'), ('Payroll', 'edit'), ('Payroll', 'delete'), ('Payroll', 'approve'),
('Appraisals', 'view'), ('Appraisals', 'create'), ('Appraisals', 'edit'), ('Appraisals', 'delete'), ('Appraisals', 'approve'),
('Offboarding', 'view'), ('Offboarding', 'create'), ('Offboarding', 'edit'), ('Offboarding', 'delete'), ('Offboarding', 'approve'),
('Reports', 'view'),
('Configuration', 'view'), ('Configuration', 'create'), ('Configuration', 'edit'), ('Configuration', 'delete')
ON DUPLICATE KEY UPDATE `module` = VALUES(`module`), `action` = VALUES(`action`);

-- 4. Map Roles to Permissions
DELETE FROM `role_permissions`;

-- SuperAdmin (Role 1): ALL Permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Admin (Role 2): ALL Permissions (Same as SuperAdmin)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions`;

-- HRManager (Role 3): Full control over operations, View-only for Config
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE `module` NOT IN ('Configuration')
UNION
SELECT 3, id FROM `permissions` WHERE `module` = 'Configuration' AND `action` = 'view';

-- CountryManager (Role 4): Same as HRManager (Note: Data scoping is handled in backend)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions` WHERE `module` NOT IN ('Configuration')
UNION
SELECT 4, id FROM `permissions` WHERE `module` = 'Configuration' AND `action` = 'view';

-- HRAssistant (Role 5): View/Create/Edit operations, No Delete/Approve
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions` WHERE `action` IN ('view', 'create', 'edit') AND `module` NOT IN ('Configuration');

-- Employee (Role 6): Basic viewing and self-service (Attendance/Leave requests)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE `action` = 'view' AND `module` NOT IN ('Configuration')
UNION
SELECT 6, id FROM `permissions` WHERE `module` IN ('Attendance', 'Leave') AND `action` = 'create';

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;
-- Permissions Seeder for HRMS V2
-- Modules: Dashboard, Directory, Employees, Attendance, Leave, Payroll, Appraisals, Offboarding, Reports, Configuration
-- Actions: view, create, edit, delete, approve

INSERT INTO `permissions` (`module`, `action`) VALUES
('Dashboard', 'view'),
('Directory', 'view'), ('Directory', 'create'), ('Directory', 'edit'), ('Directory', 'delete'),
('Employees', 'view'), ('Employees', 'create'), ('Employees', 'edit'), ('Employees', 'delete'),
('Attendance', 'view'), ('Attendance', 'create'), ('Attendance', 'edit'), ('Attendance', 'delete'), ('Attendance', 'approve'),
('Leave', 'view'), ('Leave', 'create'), ('Leave', 'edit'), ('Leave', 'delete'), ('Leave', 'approve'),
('Payroll', 'view'), ('Payroll', 'create'), ('Payroll', 'edit'), ('Payroll', 'delete'), ('Payroll', 'approve'),
('Appraisals', 'view'), ('Appraisals', 'create'), ('Appraisals', 'edit'), ('Appraisals', 'delete'), ('Appraisals', 'approve'),
('Offboarding', 'view'), ('Offboarding', 'create'), ('Offboarding', 'edit'), ('Offboarding', 'delete'), ('Offboarding', 'approve'),
('Reports', 'view'),
('Configuration', 'view'), ('Configuration', 'create'), ('Configuration', 'edit'), ('Configuration', 'delete');

-- Grant all permissions to SUPER_ADMIN (Role ID 1)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;
