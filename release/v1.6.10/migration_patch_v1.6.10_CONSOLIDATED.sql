-- HRMS V2 - MASTER CONSOLIDATED MIGRATION PATCH (v1.6.9 + v1.6.10)
-- Goal: Bring Production (v1.6.8) up to the latest state (v1.6.10)
-- Includes: Assets Module, RBAC Matrix, Designation Levels, Leave Restrictions, Countries & Dashboard Data
-- Baseline: v1.6.8 | Target: v1.6.10
-- Created: 2026-03-25

START TRANSACTION;

-- ========================================================
-- 1. SCHEMA HARDENING (Ensuring v1.6.8+ columns exist)
-- ========================================================
-- Ensure designations have 'level' for hierarchy/manager filtering (Conv 555a)
ALTER TABLE `designations` ADD COLUMN IF NOT EXISTS `level` INT DEFAULT 1;

-- Ensure countries have iso_code and currency_code
ALTER TABLE `countries` ADD COLUMN IF NOT EXISTS `iso_code` VARCHAR(3) NULL;
ALTER TABLE `countries` ADD COLUMN IF NOT EXISTS `currency_code` VARCHAR(3) NULL;
ALTER TABLE `countries` ADD COLUMN IF NOT EXISTS `default_timezone` VARCHAR(50) NULL;

-- ========================================================
-- 2. ASSETS MODULE SCHEMA (Conv 1db8)
-- ========================================================
CREATE TABLE IF NOT EXISTS `assets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
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
  `allocated_by_id` INT NULL,
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
-- 3. ROLE STANDARDIZATION & RBAC MATRIX (Conv d896)
-- ========================================================
UPDATE `roles` SET name = 'SUPER_ADMIN' WHERE name IN ('SuperAdmin', 'Super Admin', 'SUPER ADMIN');
UPDATE `roles` SET name = 'ADMIN' WHERE name IN ('Admin', 'ADMINISTRATOR');
UPDATE `roles` SET name = 'HR_MANAGER' WHERE name IN ('HRManager', 'HR Manager');
UPDATE `roles` SET name = 'COUNTRY_MANAGER' WHERE name IN ('CountryManager', 'Country Manager');
UPDATE `roles` SET name = 'HR_ASSISTANT' WHERE name IN ('HRAssistant', 'HR Assistant');
UPDATE `roles` SET name = 'EMPLOYEE' WHERE name IN ('Employee', 'Staff');

INSERT IGNORE INTO `roles` (`name`) VALUES 
('SUPER_ADMIN'), ('ADMIN'), ('HR_MANAGER'), ('COUNTRY_MANAGER'), ('HR_ASSISTANT'), ('EMPLOYEE');

-- permissions (Core Modules + New Modules)
INSERT IGNORE INTO `permissions` (`module`, `action`) VALUES 
('Dashboard', 'view'), ('Dashboard', 'create'), ('Dashboard', 'edit'), ('Dashboard', 'delete'), ('Dashboard', 'approve'),
('Directory', 'view'), ('Directory', 'create'), ('Directory', 'edit'), ('Directory', 'delete'), ('Directory', 'approve'),
('Employees', 'view'), ('Employees', 'create'), ('Employees', 'edit'), ('Employees', 'delete'), ('Employees', 'approve'),
('Attendance', 'view'), ('Attendance', 'create'), ('Attendance', 'edit'), ('Attendance', 'delete'), ('Attendance', 'approve'), ('Attendance', 'configure'),
('Leave', 'view'), ('Leave', 'create'), ('Leave', 'edit'), ('Leave', 'delete'), ('Leave', 'approve'),
('Payroll', 'view'), ('Payroll', 'create'), ('Payroll', 'edit'), ('Payroll', 'delete'), ('Payroll', 'approve'),
('Appraisals', 'view'), ('Appraisals', 'create'), ('Appraisals', 'edit'), ('Appraisals', 'delete'), ('Appraisals', 'approve'),
('Offboarding', 'view'), ('Offboarding', 'create'), ('Offboarding', 'edit'), ('Offboarding', 'delete'), ('Offboarding', 'approve'),
('Reports', 'view'), ('Reports', 'create'), ('Reports', 'edit'), ('Reports', 'delete'), ('Reports', 'approve'),
('Configuration', 'view'), ('Configuration', 'create'), ('Configuration', 'edit'), ('Configuration', 'delete'), ('Configuration', 'approve'),
('assets', 'view'), ('assets', 'manage'), ('assets', 'allocate');

-- Grant all to SUPER_ADMIN
SET @SuperAdminId = (SELECT id FROM `roles` WHERE name = 'SUPER_ADMIN' LIMIT 1);
INSERT IGNORE INTO `role_permissions` (role_id, permission_id)
SELECT @SuperAdminId, id FROM `permissions`;

-- ========================================================
-- 4. LEAVE CONFIGURATION (Gender Restricted - Conv 3604)
-- ========================================================
INSERT IGNORE INTO `leave_types` (`name`, `code`, `is_paid`, `gender_restriction`) VALUES 
('Annual Leave', 'AL', 1, 'none'),
('Sick Leave', 'SL', 1, 'none'),
('Maternity Leave', 'ML', 1, 'female'),
('Paternity Leave', 'PL', 1, 'male'),
('Unpaid Leave', 'UL', 0, 'none'),
('Compassionate Leave', 'CL', 1, 'none'),
('Study Leave', 'STL', 1, 'none');

-- ========================================================
-- 5. CORE SEED DATA (Dashboard & Org Readiness)
-- ========================================================
-- Countries
INSERT IGNORE INTO `countries` (`name`, `iso_code`, `currency_code`, `default_timezone`) VALUES 
('Uganda', 'UGA', 'UGX', 'Africa/Kampala'),
('United Arab Emirates', 'ARE', 'AED', 'Asia/Dubai'),
('India', 'IND', 'INR', 'Asia/Kolkata'),
('Kenya', 'KEN', 'KES', 'Africa/Nairobi'),
('Tanzania', 'TZA', 'TZS', 'Africa/Dar_es_Salaam'),
('Rwanda', 'RWA', 'RWF', 'Africa/Kigali');

-- Default Attendance Statuses for Company 1
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

COMMIT;
