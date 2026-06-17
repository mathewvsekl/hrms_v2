ALTER TABLE employee_companies ADD COLUMN include_in_payroll TINYINT(1) DEFAULT 0 AFTER is_primary;
UPDATE employee_companies SET include_in_payroll = 1 WHERE is_primary = 1;

ALTER TABLE payroll_records ADD COLUMN reporting_currency VARCHAR(10) NULL AFTER net_pay;
ALTER TABLE payroll_records ADD COLUMN exchange_rate DECIMAL(10,2) NULL AFTER reporting_currency;
ALTER TABLE payroll_records ADD COLUMN company_id INT NOT NULL DEFAULT 1 AFTER employee_id;

ALTER TABLE payslips ADD COLUMN company_id INT NULL AFTER employee_id;

ALTER TABLE salary_advances ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER amount;
ALTER TABLE employee_salary_components ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER effective_date;
ALTER TABLE salary_structures ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER other_earnings;

ALTER TABLE payroll_components ADD COLUMN is_income_tax TINYINT(1) DEFAULT 0 AFTER is_non_taxable;
ALTER TABLE payroll_components ADD COLUMN round_off TINYINT(1) DEFAULT 0 AFTER is_income_tax;

ALTER TABLE company_leave_policies ADD COLUMN `year` INT(4) NOT NULL DEFAULT 2026 AFTER `company_id`;
