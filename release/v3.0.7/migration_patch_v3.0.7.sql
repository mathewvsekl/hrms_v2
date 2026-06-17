-- --------------------------------------------------------
-- COMPLETE PAYROLL & SALARY FIX PATCH
-- Run this entire script on your production database to completely
-- resolve all missing tables and column errors.
-- --------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create payroll_components (if missing)
CREATE TABLE IF NOT EXISTS `payroll_components` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `type` ENUM('EARNING', 'DEDUCTION') NOT NULL,
  `computation_type` ENUM('FIXED', 'PERCENTAGE', 'FORMULA') NOT NULL DEFAULT 'FIXED',
  `value` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `formula` TEXT NULL,
  `company_id` INT NULL,
  `country_id` INT NULL,
  `is_statutory` TINYINT(1) DEFAULT 0,
  `is_non_taxable` TINYINT(1) DEFAULT 0,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `display_in_payslip` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create tax_slabs (if missing)
CREATE TABLE IF NOT EXISTS `tax_slabs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `component_id` INT NOT NULL,
    `effective_date` DATE NULL,
    `min_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `max_amount` DECIMAL(15,2) NULL,
    `tax_type` VARCHAR(50) NOT NULL DEFAULT 'PERCENTAGE',
    `percentage` DECIMAL(5,2) NULL,
    `fixed_amount` DECIMAL(15,2) NULL,
    `company_id` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`component_id`) REFERENCES `payroll_components`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Drop and Recreate employee_salary_components (Fixes 'employee_id' Error)
DROP TABLE IF EXISTS `employee_salary_components`;
CREATE TABLE `employee_salary_components` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `component_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `effective_date` DATE NOT NULL,
    `currency_code` VARCHAR(3) NOT NULL DEFAULT 'UGX',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`component_id`) REFERENCES `payroll_components`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `emp_comp_date_unique` (`employee_id`, `component_id`, `effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Drop and Recreate payroll_records (Fixes 'pr.month' Error)
DROP TABLE IF EXISTS `salary_advances`;
DROP TABLE IF EXISTS `payroll_records`;
CREATE TABLE `payroll_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `month` INT NOT NULL,
    `year` INT NOT NULL,
    `basic_pay` DECIMAL(15,2) DEFAULT 0.00,
    `earnings_json` JSON NULL,
    `commissions` DECIMAL(15,2) DEFAULT 0.00,
    `other_earnings` DECIMAL(15,2) DEFAULT 0.00,
    `gross_chargeable_income` DECIMAL(15,2) DEFAULT 0.00,
    `paye_deduction` DECIMAL(15,2) DEFAULT 0.00,
    `nssf_employee_deduction` DECIMAL(15,2) DEFAULT 0.00,
    `nssf_employer_contribution` DECIMAL(15,2) DEFAULT 0.00,
    `deductions_json` JSON NULL,
    `advance_deductions` DECIMAL(15,2) DEFAULT 0.00,
    `net_pay` DECIMAL(15,2) DEFAULT 0.00,
    `status` VARCHAR(50) DEFAULT 'Draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Drop and Recreate salary_structures (Fixes 'commissions'/'other_earnings' missing errors)
-- NOTE: The UI relies on this table for the employee salary profile base settings.
DROP TABLE IF EXISTS `salary_structures`;
CREATE TABLE `salary_structures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `base_salary` DECIMAL(15, 2) NOT NULL,
  `commissions` DECIMAL(15, 2) DEFAULT 0.00,
  `other_earnings` DECIMAL(15, 2) DEFAULT 0.00,
  `currency_code` VARCHAR(3) NOT NULL,
  `effective_date` DATE NOT NULL,
  `end_date` DATE NULL COMMENT 'Null means active. Used to preserve historical salary changes.',
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'UTC Normalized',
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Create salary_advances (Fixes 'salary_advances doesn't exist' Error)
CREATE TABLE IF NOT EXISTS `salary_advances` (
  `id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `date_requested` date NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `deducted_in_payroll_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `deducted_in_payroll_id` (`deducted_in_payroll_id`),
  CONSTRAINT `salary_advances_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_advances_ibfk_2` FOREIGN KEY (`deducted_in_payroll_id`) REFERENCES `payroll_records` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
ALTER TABLE employees ADD COLUMN tin_no VARCHAR(50) DEFAULT NULL, ADD COLUMN nssf_no VARCHAR(50) DEFAULT NULL;
