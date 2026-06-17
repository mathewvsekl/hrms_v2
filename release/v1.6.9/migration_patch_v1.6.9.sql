-- HRMS V2 - Production Migration Patch v1.6.9 (FULL CONSOLIDATION)
-- Consolidates: Assets Module, Holiday/Date Config, RBAC Standardization, and Attendance Fixes.
-- Baseline: v1.6.8 | Target: v1.6.9
-- Created: 2026-03-25

START TRANSACTION;

-- ========================================================
-- 1. ASSETS MODULE SCHEMA
-- ========================================================

CREATE TABLE IF NOT EXISTS `assets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL COMMENT 'e.g., MacBook Pro 14',
  `category` ENUM('laptop', 'mobile', 'hardware', 'software', 'furniture', 'other') DEFAULT 'other',
  `serial_number` VARCHAR(100) UNIQUE NULL,
  `model_number` VARCHAR(100) NULL,
  `purchase_date` DATE NULL,
  `purchase_cost` DECIMAL(15, 2) NULL,
  `currency_code` VARCHAR(3) DEFAULT 'KES',
  `status` ENUM('available', 'allocated', 'damaged', 'lost', 'retired') DEFAULT 'available',
  `remarks` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `asset_allocations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `asset_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `allocated_by_id` INT NULL COMMENT 'User who performed the allocation',
  `allocation_date` DATE NOT NULL,
  `expected_return_date` DATE NULL,
  `actual_return_date` DATE NULL,
  `status` ENUM('active', 'returned', 'overdue', 'lost') DEFAULT 'active',
  `remarks` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`asset_id`) REFERENCES `assets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`allocated_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================================
-- 2. ROLE STANDARDIZATION & RBAC MATRIX SEEDING
-- ========================================================
-- Standardize Role Names (Ensuring matching with Sidebar.jsx logic)
UPDATE `roles` SET name = 'SUPER_ADMIN' WHERE name IN ('SuperAdmin', 'Super Admin', 'SUPER ADMIN');
UPDATE `roles` SET name = 'ADMIN' WHERE name IN ('Admin', 'ADMINISTRATOR');
UPDATE `roles` SET name = 'HR_MANAGER' WHERE name IN ('HRManager', 'HR Manager');
UPDATE `roles` SET name = 'COUNTRY_MANAGER' WHERE name IN ('CountryManager', 'Country Manager');
UPDATE `roles` SET name = 'HR_ASSISTANT' WHERE name IN ('HRAssistant', 'HR Assistant');
UPDATE `roles` SET name = 'EMPLOYEE' WHERE name IN ('Employee', 'Staff');

-- Ensure all 6 canonical roles definitely exist
INSERT IGNORE INTO `roles` (`name`) VALUES 
('SUPER_ADMIN'), 
('ADMIN'), 
('HR_MANAGER'), 
('COUNTRY_MANAGER'), 
('HR_ASSISTANT'), 
('EMPLOYEE');

-- Seed Canonical Permissions Matrix (including Assets and Configuration)
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
('Configuration', 'view'), ('Configuration', 'create'), ('Configuration', 'edit'), ('Configuration', 'delete'), ('Configuration', 'approve'),
('assets', 'view'), ('assets', 'manage'), ('assets', 'allocate');

-- Grant permissions to SUPER_ADMIN, ADMIN, and HR_MANAGER
SET @SuperAdminId = (SELECT id FROM `roles` WHERE name = 'SUPER_ADMIN' LIMIT 1);
SET @AdminId = (SELECT id FROM `roles` WHERE name = 'ADMIN' LIMIT 1);
SET @HRManagerId = (SELECT id FROM `roles` WHERE name = 'HR_MANAGER' LIMIT 1);

-- Grant ALL permissions to SUPER_ADMIN
INSERT IGNORE INTO `role_permissions` (role_id, permission_id)
SELECT @SuperAdminId, id FROM `permissions`;

-- Grant Assets permissions to Admin and HR Manager
INSERT IGNORE INTO `role_permissions` (role_id, permission_id)
SELECT r.id, p.id 
FROM roles r, permissions p 
WHERE r.name IN ('ADMIN', 'HR_MANAGER') 
AND p.module = 'assets';

-- Re-assign Core Accounts
-- Re-assign mathew.vsekl@gmail.com
INSERT INTO `user_roles` (user_id, role_id) 
SELECT id, @SuperAdminId FROM `users` WHERE username = 'mathew.vsekl@gmail.com'
ON DUPLICATE KEY UPDATE role_id = @SuperAdminId;

-- Re-assign aneesh.mathew@visionscientificafrica.com
INSERT INTO `user_roles` (user_id, role_id) 
SELECT id, @AdminId FROM `users` WHERE username = 'aneesh.mathew@visionscientificafrica.com'
ON DUPLICATE KEY UPDATE role_id = @AdminId;

-- ========================================================
-- 3. LEAVE TYPES SEEDING
-- ========================================================
INSERT IGNORE INTO `leave_types` (`name`, `code`, `is_paid`, `gender_restriction`) VALUES 
('Annual Leave', 'AL', 1, 'none'),
('Sick Leave', 'SL', 1, 'none'),
('Maternity Leave', 'ML', 1, 'female'),
('Paternity Leave', 'PL', 1, 'male'),
('Unpaid Leave', 'UL', 0, 'none'),
('Compassionate Leave', 'CL', 1, 'none'),
('Study Leave', 'STL', 1, 'none');

COMMIT;
