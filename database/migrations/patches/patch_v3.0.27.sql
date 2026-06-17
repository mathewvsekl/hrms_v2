-- Companies
ALTER TABLE `companies` MODIFY `logo_url` LONGTEXT;

-- Employee Salary Components
ALTER TABLE `employee_salary_components` MODIFY `amount` DECIMAL(15,2) DEFAULT NULL;

-- Payroll Records (Formatting modifications only)
ALTER TABLE `payroll_records` MODIFY `basic_pay` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `payroll_records` MODIFY `gross_chargeable_income` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `payroll_records` MODIFY `paye_deduction` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `payroll_records` MODIFY `nssf_employee_deduction` DECIMAL(15,2) DEFAULT NULL;

-- Salary Structures
ALTER TABLE `salary_structures` MODIFY `base_salary` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `salary_structures` MODIFY `commissions` DECIMAL(15,2) DEFAULT NULL;
ALTER TABLE `salary_structures` MODIFY `other_earnings` DECIMAL(15,2) DEFAULT NULL;

-- Tax Slabs
ALTER TABLE `tax_slabs` ADD COLUMN `personal_relief` DECIMAL(10,2) DEFAULT '0.00' AFTER `updated_at`;
