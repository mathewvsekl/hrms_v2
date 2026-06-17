SET FOREIGN_KEY_CHECKS = 0;
-- HRMS Multi-Country, Multi-Office, Multi-Currency Database Schema
-- Designed by: Sofia (Data Architect) | Validated by: Noah (Logic Auditor) | Coordinated by: Nova (System Architect)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- CORE ORGANIZATION MAPPING
-- --------------------------------------------------------

CREATE TABLE `countries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `iso_code` VARCHAR(3) NOT NULL,
  `currency_code` VARCHAR(3) NOT NULL, -- Core requirement: Multi-Currency base
  `default_timezone` VARCHAR(50) NOT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `public_holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `country_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL COMMENT 'e.g., Diwali, Independence Day, Eid Al Fitr',
  `holiday_date` DATE NOT NULL,
  `year` INT NOT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `legal_entities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `country_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `registration_number` VARCHAR(100),
  `tax_formula_config` JSON NULL DEFAULT NULL COMMENT 'JSON structure defining the tax rules/formulas',
  `pension_formula_config` JSON NULL DEFAULT NULL COMMENT 'JSON structure defining the pension rules/formulas',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `offices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `country_id` INT NOT NULL,
  `legal_entity_id` INT NULL COMMENT 'Link to the legal entity for tax/pension compliance',
  `name` VARCHAR(100) NOT NULL,
  `address` TEXT,
  `timezone` VARCHAR(50) NOT NULL,
  `attendance_mode` ENUM('time_based', 'status_based') DEFAULT 'time_based',
  `is_time_tracking_enabled` BOOLEAN DEFAULT FALSE COMMENT 'Super Admin toggle for strict clock-in/out vs macro daily status',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `legal_entity_id` INT NOT NULL COMMENT 'Maps to corporate level to prevent multi-office duplication',
  `name` VARCHAR(150) NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Soft delete for historical integrity',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `designations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `level` INT DEFAULT 0 COMMENT 'Integer ranking for hierarchy tracking',
  `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Soft delete for historical integrity',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- MULTI-OFFICE CUSTOM FIELDS ENGINE
-- --------------------------------------------------------
-- Requirement: Support Custom Fields per office

CREATE TABLE `office_custom_fields` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `office_id` INT NOT NULL,
  `field_key` VARCHAR(50) NOT NULL COMMENT 'JSON key reference for custom_data',
  `field_name` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text', 'number', 'date', 'boolean', 'dropdown', 'json_array') NOT NULL,
  `field_options` JSON NULL DEFAULT NULL COMMENT 'JSON array of options for dropdowns',
  `display_order` INT DEFAULT 0,
  `is_required` BOOLEAN DEFAULT FALSE,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`office_id`) REFERENCES `offices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- EMPLOYEES
-- --------------------------------------------------------

CREATE TABLE `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_code` VARCHAR(50) UNIQUE NOT NULL,
  `office_id` INT NOT NULL,
  `department_id` INT NULL COMMENT 'Structural linkage',
  `designation_id` INT NULL COMMENT 'Job title linkage',
  `reporting_manager_id` INT NULL COMMENT 'Hierarchical superior, must be checked for circular dependencies',
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `phone` VARCHAR(30) NULL,
  `date_of_birth` DATE NULL,
  `gender` ENUM('male', 'female', 'other') NULL,
  `nationality` VARCHAR(50) NULL,
  `tin_number` VARCHAR(50) NULL COMMENT 'Tax Identification Number',
  `nssf_number` VARCHAR(50) NULL COMMENT 'National Social Security Fund ID',
  `bank_account_no` VARCHAR(50) NULL,
  `bank_name` VARCHAR(100) NULL,
  `profile_image_path` VARCHAR(255) NULL,
  `employment_type` ENUM('full_time', 'part_time', 'contractor') NOT NULL DEFAULT 'full_time',
  `status` ENUM('active', 'inactive', 'onboarding', 'offboarding') DEFAULT 'active',
  `hire_date` DATE NOT NULL,
  `custom_data` JSON NULL DEFAULT NULL COMMENT 'JSON object storing office-specific custom field values',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`office_id`) REFERENCES `offices`(`id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`designation_id`) REFERENCES `designations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reporting_manager_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- EMPLOYEE DOCUMENTS
-- --------------------------------------------------------

CREATE TABLE `employee_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `document_name` VARCHAR(150) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `document_type` ENUM('id_proof', 'contract', 'certificate', 'payslip', 'other') DEFAULT 'other',
  `uploaded_by_id` INT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- EMPLOYEE CONTRACTS
-- --------------------------------------------------------

CREATE TABLE `employee_contracts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `contract_type` ENUM('permanent', 'fixed_term', 'probation') NOT NULL DEFAULT 'permanent',
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL COMMENT 'NULL for permanent contracts',
  `probation_end_date` DATE NULL,
  `notes` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




-- --------------------------------------------------------
-- MULTI-CURRENCY PAYROLL ENGINE
-- --------------------------------------------------------
-- Requirement: Multi currency payroll and salary tracking

CREATE TABLE `salary_structures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `base_salary` DECIMAL(15, 2) NOT NULL,
  `currency_code` VARCHAR(3) NOT NULL, -- Supports multi-currency specifically for the structure
  `effective_date` DATE NOT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `payroll_runs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `office_id` INT NOT NULL,
  `month` INT NOT NULL,
  `year` INT NOT NULL,
  `status` ENUM('draft', 'processing', 'completed', 'paid') DEFAULT 'draft',
  `processed_at_utc` TIMESTAMP NULL COMMENT 'UTC Normalized',
  FOREIGN KEY (`office_id`) REFERENCES `offices`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `payroll_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payroll_run_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `currency_code` VARCHAR(3) NOT NULL, -- The specific currency generated for this payslip
  `exchange_rate_to_base` DECIMAL(10, 4) DEFAULT 1.0000, -- Audit/reporting baseline
  `gross_amount` DECIMAL(15, 2) NOT NULL,
  `net_amount` DECIMAL(15, 2) NOT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- RBAC (Roles and Permissions)
-- --------------------------------------------------------

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NULL COMMENT 'Maps the login credential to an active employee profile. Can be null for system-only Super Admins.',
  `username` VARCHAR(50) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `api_token` VARCHAR(255) NULL UNIQUE,
  `last_login_utc` TIMESTAMP NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `user_roles` (
  `user_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- ATTENDANCE MANAGEMENT (Phase 8 - Liam's Logic)
-- --------------------------------------------------------

CREATE TABLE `attendance_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL COMMENT 'Master anchor for daily reporting',
  `check_in_utc` TIMESTAMP NULL COMMENT 'Optional based on office.is_time_tracking_enabled',
  `check_out_utc` TIMESTAMP NULL,
  `source` ENUM('web', 'mobile', 'biometric', 'manual') DEFAULT 'web',
  `status` ENUM('present', 'absent', 'half_day', 'late', 'on_leave', 'public_holiday', 'weekend') DEFAULT 'present',
  `metadata` JSON NULL COMMENT 'IP or Geolocation tracing data',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `attendance_policies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `office_id` INT NOT NULL,
  `shift_start` TIME DEFAULT '08:00:00',
  `shift_end` TIME DEFAULT '17:00:00',
  `grace_period_mins` INT DEFAULT 15,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`office_id`) REFERENCES `offices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- LEAVE & HOLIDAY MANAGEMENT (Phase 7 - Olivia's Logic)
-- --------------------------------------------------------

CREATE TABLE `holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `office_id` INT NOT NULL,
  `holiday_date` DATE NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `is_recurring` BOOLEAN DEFAULT FALSE COMMENT 'If TRUE, occurs every year on this date',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`office_id`) REFERENCES `offices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `leave_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL COMMENT 'e.g., Annual, Sick, Maternity',
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `is_paid` BOOLEAN DEFAULT TRUE,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `office_leave_policies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `office_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `default_days_per_year` DECIMAL(5, 2) NOT NULL,
  `carry_forward_allowed` BOOLEAN DEFAULT FALSE,
  `is_calendar_days` BOOLEAN DEFAULT FALSE COMMENT 'If FALSE, ignores weekends during leave calculation',
  `weekend_definition` JSON NULL COMMENT 'Array like ["Saturday", "Sunday"] to localize logic per office',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`office_id`) REFERENCES `offices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `leave_balances` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `year` INT NOT NULL,
  `allocated_days` DECIMAL(5, 2) NOT NULL,
  `used_days` DECIMAL(5, 2) DEFAULT 0.00,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `emp_leave_year` (`employee_id`, `leave_type_id`, `year`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `leave_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` DECIMAL(5, 2) NOT NULL COMMENT 'Calculated post weekend exclusion if applicable',
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
  `approved_by_id` INT NULL COMMENT 'Manager who approved the request',
  `manager_comment` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- FINANCIAL & GLOBAL SETTINGS (Advanced Controls)
-- --------------------------------------------------------

CREATE TABLE `exchange_rates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `from_currency` VARCHAR(3) NOT NULL,
  `to_currency` VARCHAR(3) NOT NULL,
  `rate` DECIMAL(15, 6) NOT NULL,
  `effective_date` DATE NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `rate_pair_date` (`from_currency`, `to_currency`, `effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `global_settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `category` VARCHAR(50) DEFAULT 'general',
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default settings
INSERT INTO `global_settings` (`setting_key`, `setting_value`, `category`) VALUES 
('payroll_attendance_linkage', 'off', 'payroll'),
('base_currency', 'KES', 'financial');

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
