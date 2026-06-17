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
