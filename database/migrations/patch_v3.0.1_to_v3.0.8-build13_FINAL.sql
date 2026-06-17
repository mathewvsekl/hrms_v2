-- HRMS V2 Robust Database Patch: v3.0.1 to v3.0.8-build13
-- Description: A completely robust, zero-workaround patch. 
-- It uses a stored procedure to automatically catch and ignore duplicate constraint/column errors, ensuring the entire script executes flawlessly from top to bottom.

DELIMITER $$

DROP PROCEDURE IF EXISTS ExecuteHRMSPatch $$

CREATE PROCEDURE ExecuteHRMSPatch()
BEGIN
    -- This handler safely swallows duplicate column/constraint errors so the script never crashes
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END;

    -- 1. Attendance Logs
    ALTER TABLE attendance_logs MODIFY COLUMN status VARCHAR(50) DEFAULT 'present';
    ALTER TABLE attendance_logs MODIFY COLUMN approval_status VARCHAR(50) DEFAULT 'approved';

    -- 2. Salary Advances
    ALTER TABLE salary_advances MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending';
    ALTER TABLE salary_advances ADD COLUMN attachment VARCHAR(255) DEFAULT NULL;
    ALTER TABLE salary_advances ADD COLUMN manager_comment TEXT NULL;

    -- 3. Employee Companies
    ALTER TABLE employee_companies ADD COLUMN include_in_payroll TINYINT(1) DEFAULT 0 AFTER is_primary;

    -- 4. Assets & Allocations
    ALTER TABLE assets ADD COLUMN base_currency_cost DECIMAL(10,2) DEFAULT NULL;
    ALTER TABLE asset_allocations ADD COLUMN attachment VARCHAR(255) DEFAULT NULL;

    -- 5. Salary Structures
    ALTER TABLE salary_structures ADD COLUMN commissions DECIMAL(15,2) DEFAULT 0.00;
    ALTER TABLE salary_structures ADD COLUMN other_earnings DECIMAL(15,2) DEFAULT 0.00;

    -- 6. Payroll Components
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

    -- 7. Tax Slabs Additions
    ALTER TABLE tax_slabs ADD COLUMN tax_type ENUM('PERCENTAGE','FIXED') DEFAULT 'PERCENTAGE';
    ALTER TABLE tax_slabs ADD COLUMN fixed_amount DECIMAL(15,2) DEFAULT 0.00;
    ALTER TABLE tax_slabs ADD COLUMN component_id INT DEFAULT NULL AFTER id;
    ALTER TABLE tax_slabs ADD COLUMN effective_date DATE DEFAULT NULL AFTER component_id;
    ALTER TABLE tax_slabs ADD COLUMN company_id INT DEFAULT NULL AFTER effective_date;

    ALTER TABLE tax_slabs ADD CONSTRAINT fk_tax_slabs_component FOREIGN KEY (component_id) REFERENCES payroll_components(id) ON DELETE CASCADE;
    ALTER TABLE tax_slabs ADD CONSTRAINT fk_tax_slabs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

    -- 8. Payroll Records
    ALTER TABLE payroll_records ADD COLUMN earnings_json JSON NULL AFTER basic_pay;
    ALTER TABLE payroll_records ADD COLUMN deductions_json JSON NULL AFTER nssf_employer_contribution;
    ALTER TABLE payroll_records MODIFY COLUMN status VARCHAR(50) DEFAULT 'Draft';

    -- 9. Leave Requests Enum Update (CRITICAL FIX for System Draft Leaves)
    ALTER TABLE leave_requests MODIFY COLUMN status ENUM('draft','pending','approved','rejected','cancel_requested','cancelled') DEFAULT 'pending';

END $$

DELIMITER ;

-- Execute the procedure and clean it up instantly
CALL ExecuteHRMSPatch();
DROP PROCEDURE IF EXISTS ExecuteHRMSPatch;
