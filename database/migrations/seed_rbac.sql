-- Complete RBAC Configuration for HRMS V2
-- Roles: SuperAdmin, Admin, HRManager, CountryManager, HRAssistant, Employee

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Reset RBAC Tables (Optional: Use with caution in production)
-- TRUNCATE TABLE `role_permissions`;
-- TRUNCATE TABLE `roles`;
-- TRUNCATE TABLE `permissions`;

-- 2. Insert Roles
INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'SuperAdmin'),
(2, 'Admin'),
(3, 'HRManager'),
(4, 'CountryManager'),
(5, 'HRAssistant'),
(6, 'Employee')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 3. Insert Permissions
-- Logical Scoping: View-only modules have only 'view' action.
-- Sensitive modules have 'delete' restricted by role logic later.

INSERT INTO `permissions` (`module`, `action`) VALUES
('Dashboard', 'view'),
('Directory', 'view'),
('Employees', 'view'), ('Employees', 'create'), ('Employees', 'edit'), ('Employees', 'delete'),
('Attendance', 'view'), ('Attendance', 'create'), ('Attendance', 'edit'), ('Attendance', 'delete'), ('Attendance', 'approve'), ('Attendance', 'configure'),
('Leave', 'view'), ('Leave', 'create'), ('Leave', 'edit'), ('Leave', 'delete'), ('Leave', 'approve'),
('Payroll', 'view'), ('Payroll', 'create'), ('Payroll', 'edit'), ('Payroll', 'delete'), ('Payroll', 'approve'),
('Appraisals', 'view'), ('Appraisals', 'create'), ('Appraisals', 'edit'), ('Appraisals', 'delete'), ('Appraisals', 'approve'),
('Offboarding', 'view'), ('Offboarding', 'create'), ('Offboarding', 'edit'), ('Offboarding', 'delete'), ('Offboarding', 'approve'),
('Assets', 'view'), ('Assets', 'create'), ('Assets', 'edit'), ('Assets', 'delete'), ('Assets', 'allocate'),
('Reports', 'view'),
('Configuration', 'view'), ('Configuration', 'create'), ('Configuration', 'edit'), ('Configuration', 'delete')
ON DUPLICATE KEY UPDATE `module` = VALUES(`module`), `action` = VALUES(`action`);

-- 4. Map Roles to Permissions
DELETE FROM `role_permissions`;

-- SuperAdmin (Role 1): ALL Permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Admin (Role 2): Full control but NO 'delete' on sensitive data (Employees, Payroll, Attendance)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` 
WHERE NOT (`module` IN ('Employees', 'Payroll', 'Attendance') AND `action` = 'delete');

-- HRManager (Role 3): Full control over operations, View-only for Config, No 'delete' on core data
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` 
WHERE `module` NOT IN ('Configuration') 
AND NOT (`module` IN ('Employees', 'Payroll', 'Attendance') AND `action` = 'delete')
UNION
SELECT 3, id FROM `permissions` WHERE `module` = 'Configuration' AND `action` = 'view';

-- CountryManager (Role 4): Same as HRManager (Note: Data scoping is handled in backend)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 4, id FROM `permissions` 
WHERE `module` NOT IN ('Configuration')
AND NOT (`module` IN ('Employees', 'Payroll', 'Attendance') AND `action` = 'delete')
UNION
SELECT 4, id FROM `permissions` WHERE `module` = 'Configuration' AND `action` = 'view';

-- HRAssistant (Role 5): View/Create/Edit/Delete operations, No Approve
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 5, id FROM `permissions` 
WHERE `action` IN ('view', 'create', 'edit', 'delete') 
AND `module` NOT IN ('Configuration')
UNION
SELECT 5, id FROM `permissions` 
WHERE `module` = 'Configuration' AND `action` IN ('view', 'create', 'edit');

-- Employee (Role 6): Basic viewing and self-service (Attendance/Leave requests)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 6, id FROM `permissions` WHERE `action` = 'view' AND `module` NOT IN ('Configuration')
UNION
SELECT 6, id FROM `permissions` WHERE `module` IN ('Attendance', 'Leave') AND `action` = 'create';

SET FOREIGN_KEY_CHECKS = 1;

COMMIT;
