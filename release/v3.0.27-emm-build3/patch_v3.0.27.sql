-- Companies
ALTER TABLE `companies` MODIFY `logo_url` LONGTEXT;

-- Company Leave Policies
ALTER TABLE `company_leave_policies` ADD COLUMN `year` INT DEFAULT NULL AFTER `leave_type_id`;

-- Employee Companies
ALTER TABLE `employee_companies` ADD COLUMN `include_in_payroll` TINYINT(1) DEFAULT '0' AFTER `is_primary`;

-- Employee Salary Components
ALTER TABLE `employee_salary_components` MODIFY `amount` DECIMAL(15,2) DEFAULT NULL;

-- Payroll Components
ALTER TABLE `payroll_components` ADD COLUMN `is_income_tax` TINYINT(1) DEFAULT '0' AFTER `is_non_taxable`;
ALTER TABLE `payroll_components` ADD COLUMN `round_off` TINYINT(1) DEFAULT '0' AFTER `is_income_tax`;

-- Payroll Records
ALTER TABLE `payroll_records` ADD COLUMN `company_id` INT NOT NULL DEFAULT '1' AFTER `employee_id`;
ALTER TABLE `payroll_records` ADD COLUMN `reporting_currency` VARCHAR(10) DEFAULT NULL AFTER `net_pay`;
ALTER TABLE `payroll_records` ADD COLUMN `exchange_rate` DECIMAL(10,2) DEFAULT NULL AFTER `reporting_currency`;
ALTER TABLE `payroll_records` MODIFY `basic_pay` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `payroll_records` MODIFY `gross_chargeable_income` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `payroll_records` MODIFY `paye_deduction` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `payroll_records` MODIFY `nssf_employee_deduction` DECIMAL(15,2) DEFAULT NULL;

-- Payslips
ALTER TABLE `payslips` ADD COLUMN `company_id` INT DEFAULT NULL AFTER `employee_id`;

-- Salary Advance Installments
CREATE TABLE IF NOT EXISTS `salary_advance_installments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `salary_advance_id` int NOT NULL,
  `payroll_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `deduction_date` date DEFAULT NULL,
  `remaining_balance` decimal(15,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Salary Advances
ALTER TABLE `salary_advances` ADD COLUMN `installment_amount` DECIMAL(10,2) DEFAULT NULL AFTER `amount`;
ALTER TABLE `salary_advances` ADD COLUMN `deduction_start_date` DATE DEFAULT NULL AFTER `installment_amount`;
ALTER TABLE `salary_advances` ADD COLUMN `deducted_amount` DECIMAL(10,2) DEFAULT '0.00' AFTER `deduction_start_date`;
ALTER TABLE `salary_advances` ADD COLUMN `currency_code` VARCHAR(3) DEFAULT 'UGX' AFTER `deducted_amount`;
ALTER TABLE `salary_advances` MODIFY `status` ENUM('Pending','Reviewed','Approved','Rejected','Partially Deducted','Deducted','Cancelled') DEFAULT 'Pending';
ALTER TABLE `salary_advances` ADD COLUMN `reason` TEXT AFTER `created_at`;
ALTER TABLE `salary_advances` ADD COLUMN `reviewed_by` INT DEFAULT NULL AFTER `reason`;
ALTER TABLE `salary_advances` ADD COLUMN `reviewed_at` TIMESTAMP NULL DEFAULT NULL AFTER `reviewed_by`;
ALTER TABLE `salary_advances` ADD COLUMN `approved_by` INT DEFAULT NULL AFTER `reviewed_at`;
ALTER TABLE `salary_advances` ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `approved_by`;
ALTER TABLE `salary_advances` ADD COLUMN `attachment` VARCHAR(255) DEFAULT NULL AFTER `approved_at`;
ALTER TABLE `salary_advances` ADD COLUMN `manager_comment` TEXT AFTER `attachment`;

-- Salary Structures
ALTER TABLE `salary_structures` MODIFY `base_salary` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `salary_structures` MODIFY `commissions` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `salary_structures` MODIFY `other_earnings` DECIMAL(15,2) DEFAULT NULL;

-- Tax Slabs
ALTER TABLE `tax_slabs` ADD COLUMN `personal_relief` DECIMAL(10,2) DEFAULT '0.00' AFTER `updated_at`;
