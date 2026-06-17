-- HRMS V2 - Production Fix Patch (v3)
-- Standardize Role Names, Restore Admin Access, and Seed RBAC Matrix

START TRANSACTION;

-- 1. Standardize Role Names (Ensuring matching with Sidebar.jsx logic)
UPDATE `roles` SET name = 'SUPER_ADMIN' WHERE name IN ('SuperAdmin', 'Super Admin', 'SUPER ADMIN');
UPDATE `roles` SET name = 'ADMIN' WHERE name IN ('Admin', 'ADMINISTRATOR');
UPDATE `roles` SET name = 'HR_MANAGER' WHERE name IN ('HRManager', 'HR Manager');
UPDATE `roles` SET name = 'COUNTRY_MANAGER' WHERE name IN ('CountryManager', 'Country Manager');
UPDATE `roles` SET name = 'HR_ASSISTANT' WHERE name IN ('HRAssistant', 'HR Assistant');
UPDATE `roles` SET name = 'EMPLOYEE' WHERE name IN ('Employee', 'Staff');

-- 2. Ensure all 6 canonical roles definitely exist after normalization
INSERT IGNORE INTO `roles` (`name`) VALUES 
('SUPER_ADMIN'), 
('ADMIN'), 
('HR_MANAGER'), 
('COUNTRY_MANAGER'), 
('HR_ASSISTANT'), 
('EMPLOYEE');

-- 3. Seed Canonical Permissions Matrix (Required for RBAC Matrix to display)
INSERT IGNORE INTO `permissions` (`module`, `action`) VALUES 
('Dashboard', 'view'), ('Dashboard', 'create'), ('Dashboard', 'edit'), ('Dashboard', 'delete'), ('Dashboard', 'approve'),
('Directory', 'view'), ('Directory', 'create'), ('Directory', 'edit'), ('Directory', 'delete'), ('Directory', 'approve'),
('Employees', 'view'), ('Employees', 'create'), ('Employees', 'edit'), ('Employees', 'delete'), ('Employees', 'approve'),
('Attendance', 'view'), ('Attendance', 'create'), ('Attendance', 'edit'), ('Attendance', 'delete'), ('Attendance', 'approve'),
('Leave', 'view'), ('Leave', 'create'), ('Leave', 'edit'), ('Leave', 'delete'), ('Leave', 'approve'),
('Payroll', 'view'), ('Payroll', 'create'), ('Payroll', 'edit'), ('Payroll', 'delete'), ('Payroll', 'approve'),
('Appraisals', 'view'), ('Appraisals', 'create'), ('Appraisals', 'edit'), ('Appraisals', 'delete'), ('Appraisals', 'approve'),
('Offboarding', 'view'), ('Offboarding', 'create'), ('Offboarding', 'edit'), ('Offboarding', 'delete'), ('Offboarding', 'approve'),
('Reports', 'view'), ('Reports', 'create'), ('Reports', 'edit'), ('Reports', 'delete'), ('Reports', 'approve'),
('Configuration', 'view'), ('Configuration', 'create'), ('Configuration', 'edit'), ('Configuration', 'delete'), ('Configuration', 'approve');

-- 4. Re-assign Core Accounts and grant all permissions to SUPER_ADMIN
SET @SuperAdminId = (SELECT id FROM `roles` WHERE name = 'SUPER_ADMIN' LIMIT 1);
SET @AdminId = (SELECT id FROM `roles` WHERE name = 'ADMIN' LIMIT 1);

-- Re-assign mathew.vsekl@gmail.com
INSERT INTO `user_roles` (user_id, role_id) 
SELECT id, @SuperAdminId FROM `users` WHERE username = 'mathew.vsekl@gmail.com'
ON DUPLICATE KEY UPDATE role_id = @SuperAdminId;

-- Re-assign aneesh.mathew@visionscientificafrica.com
INSERT INTO `user_roles` (user_id, role_id) 
SELECT id, @AdminId FROM `users` WHERE username = 'aneesh.mathew@visionscientificafrica.com'
ON DUPLICATE KEY UPDATE role_id = @AdminId;

-- Grant ALL permissions to SUPER_ADMIN
INSERT IGNORE INTO `role_permissions` (role_id, permission_id)
SELECT @SuperAdminId, id FROM `permissions`;

COMMIT;
