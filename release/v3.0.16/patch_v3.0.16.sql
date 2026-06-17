-- Combined patch for v3.0.16 since last deployed v3.0.10-build4

-- Changes from payroll components update
ALTER TABLE payroll_components ADD COLUMN is_income_tax TINYINT(1) DEFAULT 0 AFTER is_non_taxable;
ALTER TABLE payroll_components ADD COLUMN round_off TINYINT(1) DEFAULT 0 AFTER is_income_tax;

-- Changes from company leave policies year update
ALTER TABLE company_leave_policies ADD COLUMN `year` INT(4) NOT NULL DEFAULT 2026 AFTER `company_id`;
