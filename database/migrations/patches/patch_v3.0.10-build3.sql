-- Patch v3.0.10-build3
-- Adding missing columns to payroll_components

ALTER TABLE payroll_components ADD COLUMN is_income_tax TINYINT(1) DEFAULT 0 AFTER is_non_taxable;
ALTER TABLE payroll_components ADD COLUMN round_off TINYINT(1) DEFAULT 0 AFTER is_income_tax;
