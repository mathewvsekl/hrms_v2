-- --------------------------------------------------------
-- SAFE PAYROLL & SALARY FIX PATCH FOR V3.0.7
-- Run this script on your production database. 
-- It is NON-DESTRUCTIVE and will preserve existing records.
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
    `country_id` INT NULL,
    `company_id` INT NULL,
    `min_amount` DECIMAL(15,2) NOT NULL,
    `max_amount` DECIMAL(15,2) NULL,
    `fixed_tax` DECIMAL(15,2) DEFAULT 0.00,
    `percentage` DECIMAL(5,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create employee_salary_components (if missing)
CREATE TABLE IF NOT EXISTS `employee_salary_components` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `component_id` INT NOT NULL,
  `amount` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`component_id`) REFERENCES `payroll_components`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Safe ALTER for payroll_records (Preserves Data)
-- Add missing columns individually. (Ignore errors if they already exist in your MySQL version)
ALTER TABLE `payroll_records` ADD COLUMN `earnings_json` JSON NULL;
ALTER TABLE `payroll_records` ADD COLUMN `deductions_json` JSON NULL;
ALTER TABLE `payroll_records` ADD COLUMN `advance_deductions` DECIMAL(15,2) DEFAULT 0.00;

-- 5. Safe ALTER for salary_structures
ALTER TABLE `salary_structures` ADD COLUMN `commissions` DECIMAL(15, 2) DEFAULT 0.00;
ALTER TABLE `salary_structures` ADD COLUMN `other_earnings` DECIMAL(15, 2) DEFAULT 0.00;

-- 6. Create salary_advances (if missing)
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
