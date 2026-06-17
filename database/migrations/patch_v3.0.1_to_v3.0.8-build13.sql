-- HRMS V2 Database Patch: v3.0.1 to v3.0.8-build13
-- Description: Complete unified patch including all fixes for Payroll, Attendance, Leaves, and Salary Structures.
-- Instructions: Run this file directly in phpMyAdmin on the target database.

-- 1. Attendance Logs Additions
ALTER TABLE attendance_logs MODIFY COLUMN status VARCHAR(50) DEFAULT 'present';
ALTER TABLE attendance_logs MODIFY COLUMN approval_status VARCHAR(50) DEFAULT 'approved';

-- 2. Salary Advances Additions
ALTER TABLE salary_advances MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending';
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) DEFAULT NULL;
ALTER TABLE salary_advances ADD COLUMN IF NOT EXISTS manager_comment TEXT NULL;

-- 3. Employee Companies Additions
ALTER TABLE employee_companies ADD COLUMN IF NOT EXISTS include_in_payroll TINYINT(1) DEFAULT 0 AFTER is_primary;

-- 4. Assets Additions
ALTER TABLE assets ADD COLUMN IF NOT EXISTS base_currency_cost DECIMAL(10,2) DEFAULT NULL;

-- 5. Asset Allocations Additions
ALTER TABLE asset_allocations ADD COLUMN IF NOT EXISTS attachment VARCHAR(255) DEFAULT NULL;

-- 6. Salary Structures Additions
ALTER TABLE salary_structures ADD COLUMN IF NOT EXISTS commissions DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE salary_structures ADD COLUMN IF NOT EXISTS other_earnings DECIMAL(15,2) DEFAULT 0.00;

-- 7. Payroll Components Table
CREATE TABLE IF NOT EXISTS payroll_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('EARNING', 'DEDUCTION') NOT NULL,
    computation_type ENUM('FIXED', 'PERCENTAGE', 'FORMULA') NOT NULL DEFAULT 'FIXED',
    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    formula TEXT NULL,
    company_id INT NULL,
    country_id INT NULL,
    is_statutory TINYINT(1) DEFAULT 0,
    is_non_taxable TINYINT(1) DEFAULT 0,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    display_in_payslip TINYINT(1) DEFAULT 1,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Tax Slabs Additions (Payroll Architect Fix)
ALTER TABLE tax_slabs ADD COLUMN IF NOT EXISTS tax_type ENUM('PERCENTAGE','FIXED') DEFAULT 'PERCENTAGE';
ALTER TABLE tax_slabs ADD COLUMN IF NOT EXISTS fixed_amount DECIMAL(15,2) DEFAULT 0.00;
ALTER TABLE tax_slabs ADD COLUMN IF NOT EXISTS component_id INT DEFAULT NULL AFTER id;
ALTER TABLE tax_slabs ADD COLUMN IF NOT EXISTS effective_date DATE DEFAULT NULL AFTER component_id;
ALTER TABLE tax_slabs ADD COLUMN IF NOT EXISTS company_id INT DEFAULT NULL AFTER effective_date;

-- Note: In MySQL, you cannot conditionally add constraints with IF NOT EXISTS.
-- If these constraints fail because they already exist, it is perfectly safe to ignore them.
ALTER TABLE tax_slabs ADD CONSTRAINT fk_tax_slabs_component FOREIGN KEY (component_id) REFERENCES payroll_components(id) ON DELETE CASCADE;
ALTER TABLE tax_slabs ADD CONSTRAINT fk_tax_slabs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

-- 9. Payroll Records Additions
ALTER TABLE payroll_records ADD COLUMN IF NOT EXISTS earnings_json JSON NULL AFTER basic_pay;
ALTER TABLE payroll_records ADD COLUMN IF NOT EXISTS deductions_json JSON NULL AFTER nssf_employer_contribution;
ALTER TABLE payroll_records MODIFY COLUMN status VARCHAR(50) DEFAULT 'Draft';

-- 10. Leave Requests Enum Update (CRITICAL FIX for System Draft Leaves)
ALTER TABLE leave_requests MODIFY COLUMN status ENUM('draft','pending','approved','rejected','cancel_requested','cancelled') DEFAULT 'pending';
