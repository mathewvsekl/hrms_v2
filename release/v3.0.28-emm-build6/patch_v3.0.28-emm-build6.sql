-- HRMS V2 Database Patch: v3.0.28
-- Includes:
-- 1. Database Migration for HRMS (Multi-Company Support, Payslips, Advances)
-- 2. RBAC Security Audit Orchestration (base_role_id)
-- 3. StringNormalizer Updates (normalized_name)
-- 4. Attendance & Permission Error Resolving (Insert missing permissions)



-- 2. RBAC Security Audit Orchestration & StringNormalizer
ALTER TABLE `roles` ADD COLUMN `normalized_name` VARCHAR(100) AFTER `name`;
ALTER TABLE `roles` ADD COLUMN `base_role_id` INT NULL;
ALTER TABLE `roles` ADD CONSTRAINT `fk_roles_base_role` FOREIGN KEY (`base_role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL;

-- Standardize Roles
UPDATE `roles` SET `name` = 'HR Manager' WHERE `name` = 'HRManager';

-- Update Normalized Names
UPDATE `roles` SET `normalized_name` = 'super_admin' WHERE `name` = 'SuperAdmin';
UPDATE `roles` SET `normalized_name` = 'admin' WHERE `name` = 'Admin';
UPDATE `roles` SET `normalized_name` = 'hr_manager' WHERE `name` = 'HR Manager';
UPDATE `roles` SET `normalized_name` = 'country_manager' WHERE `name` = 'CountryManager';
UPDATE `roles` SET `normalized_name` = 'hr_assistant' WHERE `name` = 'HRAssistant';
UPDATE `roles` SET `normalized_name` = 'office_hrassistant' WHERE `name` = 'Office HRAssistant';
UPDATE `roles` SET `normalized_name` = 'employee' WHERE `name` = 'Employee';

-- Set base_role_id to their own ID for base roles
UPDATE `roles` SET `base_role_id` = `id` WHERE `name` IN ('SuperAdmin', 'Admin', 'HR Manager', 'CountryManager', 'HRAssistant', 'Office HRAssistant', 'Employee');

-- 3. Resolving Attendance & Asset Permission Error
INSERT IGNORE INTO `permissions` (`module`, `action`) VALUES
('Assets', 'view'), ('Assets', 'create'), ('Assets', 'edit'), ('Assets', 'delete'), ('Assets', 'approve'),
('Configuration', 'view'), ('Configuration', 'create'), ('Configuration', 'edit'), ('Configuration', 'delete'), ('Configuration', 'approve'),
('Attendance', 'view'), ('Attendance', 'create'), ('Attendance', 'edit'), ('Attendance', 'delete'), ('Attendance', 'approve');
