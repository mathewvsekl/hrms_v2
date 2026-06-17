-- HRMS Multi-Country, Multi-Company, Multi-Currency Database Schema
-- Last Updated: 2026-05-15 (v2.8.1) production-ready
-- IMPORTANT: Select your database first (e.g., USE hrms_v2;)
-- Designed by: Sofia (Data Architect) | Validated by: Noah (Logic Auditor) | Coordinated by: Nova (System Architect)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- CORE ORGANIZATION MAPPING
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `countries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `iso_code` VARCHAR(3) NOT NULL,
  `currency_code` VARCHAR(3) NOT NULL, -- Core requirement: Multi-Currency base
  `default_timezone` VARCHAR(50) NOT NULL,
  `tax_formula_config` JSON NULL DEFAULT NULL COMMENT 'JSON structure defining the tax rules/formulas',
  `pension_formula_config` JSON NULL DEFAULT NULL COMMENT 'JSON structure defining the pension rules/formulas',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `public_holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `country_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL COMMENT 'e.g., Diwali, Independence Day, Eid Al Fitr',
  `holiday_date` DATE NOT NULL,
  `year` INT NOT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `office_attendance_configs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `config_date` DATE NOT NULL,
  `status` VARCHAR(50) NOT NULL COMMENT 'Dynamic status key matching office_attendance_status_definitions or system defaults',
  `remarks` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_office_date` (`company_id`, `config_date`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `office_weekly_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
  `status` VARCHAR(50) NOT NULL,
  `remarks` TEXT NULL,
  `is_default_applied` TINYINT(1) DEFAULT 0,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `company_day` (`company_id`, `day_of_week`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `office_attendance_status_definitions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `status_key` VARCHAR(50) NOT NULL COMMENT 'e.g., work_from_home, on_site',
  `status_label` VARCHAR(100) NOT NULL COMMENT 'e.g., Work From Home',
  `color_code` VARCHAR(20) DEFAULT '#3b82f6',
  `is_default` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `company_status_key` (`company_id`, `status_key`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `country_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `address` TEXT,
  `contact_phone` VARCHAR(30) NULL,
  `contact_email` VARCHAR(100) NULL,
  `logo_url` VARCHAR(255) DEFAULT 'default_logo.png',
  `timezone` VARCHAR(50) NOT NULL,
  `attendance_mode` VARCHAR(50) DEFAULT 'time_based',
  `is_time_tracking_enabled` BOOLEAN DEFAULT FALSE COMMENT 'Super Admin toggle for strict clock-in/out vs macro daily status',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Soft delete for historical integrity',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `designations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `level` INT DEFAULT 0 COMMENT 'Integer ranking for hierarchy tracking',
  `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Soft delete for historical integrity',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- MULTI-COMPANY CUSTOM FIELDS ENGINE
-- --------------------------------------------------------
-- Requirement: Support Custom Fields per company

CREATE TABLE IF NOT EXISTS `company_custom_fields` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `field_key` VARCHAR(50) NOT NULL COMMENT 'JSON key reference for custom_data',
  `field_name` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text', 'number', 'date', 'boolean', 'dropdown', 'json_array') NOT NULL,
  `field_options` JSON NULL DEFAULT NULL COMMENT 'JSON array of options for dropdowns',
  `display_order` INT DEFAULT 0,
  `is_required` BOOLEAN DEFAULT FALSE,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- EMPLOYEES
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_code` VARCHAR(50) UNIQUE NOT NULL,
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
  `bank_account_no` VARCHAR(50) NULL,
  `bank_name` VARCHAR(100) NULL,
  `profile_image_path` VARCHAR(255) NULL,
  `employment_type` VARCHAR(50) NOT NULL DEFAULT 'full_time',
  `job_description` TEXT NULL COMMENT 'Brief employment details/role description',
  `status` VARCHAR(50) DEFAULT 'active',
  `hire_date` DATE NOT NULL,
  `custom_data` JSON NULL DEFAULT NULL COMMENT 'JSON object storing company-specific custom field values',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  INDEX `idx_employees_dept` (`department_id`),
  INDEX `idx_employees_desig` (`designation_id`),
  INDEX `idx_employees_manager` (`reporting_manager_id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`designation_id`) REFERENCES `designations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reporting_manager_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- EMPLOYEE DOCUMENTS & REGISTRY
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `employee_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `document_type` VARCHAR(100) DEFAULT 'other',
  `expiry_date` DATE NULL COMMENT 'Expiry date for time-sensitive documents',
  `uploaded_by_id` INT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `company_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) DEFAULT 'Policy',
  `file_path` VARCHAR(255) NOT NULL,
  `company_id` INT NULL,
  `country_id` INT NULL,
  `uploaded_by_id` INT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- MANY-TO-MANY RELATIONSHIP BETWEEN EMPLOYEES AND COMPANIES
CREATE TABLE IF NOT EXISTS `employee_companies` (
  `employee_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `is_primary` BOOLEAN DEFAULT FALSE,
  `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Soft-deactivation flag; FALSE = historically deactivated, data preserved',
  `deactivated_at_utc` TIMESTAMP NULL DEFAULT NULL COMMENT 'UTC timestamp when this link was deactivated',
  PRIMARY KEY (`employee_id`, `company_id`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- --------------------------------------------------------
-- EMPLOYEE CONTRACTS
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `employee_contracts` (
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

CREATE TABLE IF NOT EXISTS `salary_structures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `base_salary` DECIMAL(15, 2) NOT NULL,
  `currency_code` VARCHAR(3) NOT NULL, -- Supports multi-currency specifically for the structure
  `effective_date` DATE NOT NULL,
  `end_date` DATE NULL COMMENT 'Null means active. Used to preserve historical salary changes.',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_runs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `month` INT NOT NULL,
  `year` INT NOT NULL,
  `status` ENUM('draft', 'processing', 'completed', 'paid') DEFAULT 'draft',
  `processed_at_utc` TIMESTAMP NULL COMMENT 'UTC Normalized',
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payroll_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `payroll_run_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `currency_code` VARCHAR(3) NOT NULL, -- The specific currency generated for this payslip
  `exchange_rate_to_base` DECIMAL(10, 4) DEFAULT 1.0000, -- Audit/reporting baseline
  `gross_amount` DECIMAL(15, 2) NOT NULL,
  `net_amount` DECIMAL(15, 2) NOT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- RBAC (Roles and Permissions)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
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

CREATE TABLE IF NOT EXISTS `user_otps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `otp_code` VARCHAR(255) NOT NULL, -- Hashed for security
  `expires_at` TIMESTAMP NOT NULL,
  `is_used` BOOLEAN DEFAULT FALSE,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `roles` (`id`, `name`) VALUES 
(1, 'SuperAdmin'), 
(2, 'Admin'), 
(3, 'HRManager'), 
(4, 'CountryManager'), 
(5, 'HRAssistant'), 
(6, 'Employee');

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- ATTENDANCE MANAGEMENT (Phase 8 - Liam's Logic)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `company_id` INT NULL COMMENT 'Linkage to specific office context',
  `attendance_date` DATE NOT NULL COMMENT 'Master anchor for daily reporting',
  `check_in_utc` TIMESTAMP NULL,
  `check_out_utc` TIMESTAMP NULL,
  `source` VARCHAR(50) DEFAULT 'web',
  `status` VARCHAR(50) DEFAULT 'present' COMMENT 'Master anchor for daily reporting',
  `approval_status` VARCHAR(50) DEFAULT 'approved',
  `submitted_by_id` INT NULL,
  `approved_by_id` INT NULL,
  `remarks` TEXT NULL,
  `metadata` JSON NULL COMMENT 'IP or Geolocation tracing data',
  `is_default_applied` TINYINT(1) DEFAULT 0,
  `is_manually_modified` TINYINT(1) DEFAULT 0,
  `actor_type` VARCHAR(50) DEFAULT 'user',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_attendance_emp_date` (`employee_id`, `attendance_date`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`submitted_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`approved_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance_audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `attendance_log_id` INT NOT NULL,
  `changed_by_id` INT NOT NULL,
  `old_values` JSON NULL,
  `new_values` JSON NULL,
  `change_reason` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`attendance_log_id`) REFERENCES `attendance_logs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `attendance_policies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `shift_start` TIME DEFAULT '08:00:00',
  `shift_end` TIME DEFAULT '17:00:00',
  `grace_period_mins` INT DEFAULT 15,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- LEAVE & HOLIDAY MANAGEMENT (Phase 7 - Olivia's Logic)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `holiday_date` DATE NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `is_recurring` BOOLEAN DEFAULT FALSE COMMENT 'If TRUE, occurs every year on this date',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NULL COMMENT 'Linkage to company for custom balances',
  `name` VARCHAR(50) NOT NULL COMMENT 'e.g., Annual, Sick, Maternity',
  `code` VARCHAR(20) NOT NULL,
  `is_paid` BOOLEAN DEFAULT TRUE,
  `gender_restriction` ENUM('male', 'female', 'none') DEFAULT 'none',
  `color_code` VARCHAR(20) DEFAULT '#3b82f6',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_company_code` (`company_id`, `code`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `company_leave_policies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `default_days_per_year` DECIMAL(5, 2) NOT NULL,
  `carry_forward_allowed` BOOLEAN DEFAULT FALSE,
  `is_calendar_days` BOOLEAN DEFAULT FALSE COMMENT 'If FALSE, ignores weekends during leave calculation',
  `weekend_definition` JSON NULL COMMENT 'Array like ["Saturday", "Sunday"] to localize logic per company',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leave_balances` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `year` INT NOT NULL,
  `allocated_days` DECIMAL(5, 2) NOT NULL,
  `used_days` DECIMAL(5, 2) DEFAULT 0.00,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `emp_leave_year` (`employee_id`, `leave_type_id`, `year`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` DECIMAL(5, 2) NOT NULL COMMENT 'Calculated post weekend exclusion if applicable',
  `status` ENUM('pending', 'approved', 'rejected', 'cancel_requested', 'cancelled') DEFAULT 'pending',
  `approved_by_id` INT NULL COMMENT 'Manager who approved the request',
  `request_group_id` VARCHAR(50) NULL,
  `manager_comment` TEXT NULL,
  `remarks` TEXT NULL,
  `attachment_path` VARCHAR(255) NULL,
  `cancellation_reason` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`request_group_id`),
  INDEX `idx_leave_emp_status` (`employee_id`, `status`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`approved_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- FINANCIAL & GLOBAL SETTINGS (Advanced Controls)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `exchange_rates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `from_currency` VARCHAR(3) NOT NULL,
  `to_currency` VARCHAR(3) NOT NULL,
  `rate` DECIMAL(15, 6) NOT NULL,
  `effective_date` DATE NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `rate_pair_date` (`from_currency`, `to_currency`, `effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `global_settings` (
  `setting_key` VARCHAR(100) PRIMARY KEY,
  `setting_value` TEXT NOT NULL,
  `category` VARCHAR(50) DEFAULT 'general',
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default settings
INSERT IGNORE INTO `global_settings` (`setting_key`, `setting_value`, `category`) VALUES 
('payroll_attendance_linkage', 'off', 'payroll'),
('base_currency', 'KES', 'financial');

-- --------------------------------------------------------
-- APPRAISAL & PERFORMANCE MANAGEMENT (Victor's Logic)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `appraisal_cycles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL COMMENT 'e.g., Annual Appraisal 2025',
  `frequency` VARCHAR(50) DEFAULT 'Annual',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('draft', 'active', 'closed') DEFAULT 'draft',
  `selected_offices` JSON NULL,
  `employee_deadline` DATE NULL,
  `manager_deadline` DATE NULL,
  `hr_deadline` DATE NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `appraisal_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `appraisal_id` INT NOT NULL,
    `approver_id` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'returned') DEFAULT 'pending',
    `comment` TEXT,
    `step_order` INT DEFAULT 0,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approver_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `appraisal_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `template_questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `section` ENUM('B_KPI', 'B_SOFT_SKILL', 'C_SUMMARY', 'D_MANAGER', 'E_HR') NOT NULL,
  `question_text` TEXT NOT NULL,
  `is_mandatory` BOOLEAN DEFAULT TRUE,
  `display_order` INT DEFAULT 0,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_appraisals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `manager_id` INT NULL COMMENT 'Snapshot of reporting manager at creation time',
  `cycle_id` INT NOT NULL,
  `template_id` INT NOT NULL,
  `status` ENUM('draft', 'manager_review', 'hr_review', 'finalized') DEFAULT 'draft',
  `eligible_for_increment` BOOLEAN NULL COMMENT 'Determined by HR in Section E',
  `eligible_for_bonus` BOOLEAN NULL COMMENT 'Determined by HR in Section E',
  `final_rating` DECIMAL(4, 2) NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_appraisal_cycle_status` (`cycle_id`, `status`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`manager_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`cycle_id`) REFERENCES `appraisal_cycles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `appraisal_ratings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `kra_name` VARCHAR(255) NULL COMMENT 'For dynamic KPIs in Section B',
  `achievements` TEXT NULL COMMENT 'Employee provided achievements',
  `question_id` INT NULL COMMENT 'Maps to template_questions for predefined soft skills',
  `employee_rating` DECIMAL(4, 2) NULL,
  `manager_rating` DECIMAL(4, 2) NULL,
  `hr_adjusted_rating` DECIMAL(4, 2) NULL,
  `manager_comment` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `template_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `appraisal_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `section` VARCHAR(50) NOT NULL COMMENT 'e.g., Section_C_Challenges, Section_D_Recommendation',
  `author_id` INT NOT NULL COMMENT 'Employee who wrote this comment (can be self, manager, or HR)',
  `comment_text` TEXT NOT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- NOTIFICATION SYSTEM (Phase 15 - Security & Audit)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `data` JSON NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_at_utc` TIMESTAMP NULL DEFAULT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- SYSTEM AUDIT: APPROVAL HISTORY
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `approval_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `module` ENUM('leave', 'appraisal', 'attendance', 'onboarding') NOT NULL,
    `reference_id` INT NOT NULL COMMENT 'ID from leave_requests, employee_appraisals, or attendance_logs',
    `actor_id` INT NOT NULL COMMENT 'users.id of the person who performed the action',
    `action` VARCHAR(50) NOT NULL COMMENT 'submitted, approved, rejected, returned, cancelled, finalized',
    `comment` TEXT NULL,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_module_ref` (`module`, `reference_id`),
    FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

COMMIT;

-- Seed Standard Annual Appraisal Template
INSERT IGNORE INTO appraisal_templates (name, description) VALUES ('Standard Annual Appraisal', 'Default annual performance review template including KPIs and 10 mandatory soft skills.');
SET @TemplateId = LAST_INSERT_ID();

INSERT IGNORE INTO template_questions (template_id, section, question_text, display_order) VALUES 
(@TemplateId, 'B_SOFT_SKILL', 'Communication', 1),
(@TemplateId, 'B_SOFT_SKILL', 'Teamwork', 2),
(@TemplateId, 'B_SOFT_SKILL', 'Problem Solving', 3),
(@TemplateId, 'B_SOFT_SKILL', 'Time Management', 4),
(@TemplateId, 'B_SOFT_SKILL', 'Adaptability', 5),
(@TemplateId, 'B_SOFT_SKILL', 'Leadership', 6),
(@TemplateId, 'B_SOFT_SKILL', 'Work Ethic', 7),
(@TemplateId, 'B_SOFT_SKILL', 'Critical Thinking', 8),
(@TemplateId, 'B_SOFT_SKILL', 'Conflict Resolution', 9),
(@TemplateId, 'B_SOFT_SKILL', 'Emotional Intelligence', 10),
(@TemplateId, 'D_MANAGER', 'Manager Recommendation & Summary', 100),
(@TemplateId, 'E_HR', 'HR Final Comments & Increment Eligibility', 200);

-- -- General Performance improvements for JOINS (Consolidated into CREATE TABLE blocks)
COMMIT;
