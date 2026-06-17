CREATE TABLE IF NOT EXISTS payroll_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    computation_type VARCHAR(50) NOT NULL DEFAULT 'FIXED',
    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    formula TEXT NULL,
    company_id INT NULL,
    country_id INT NULL,
    is_statutory TINYINT(1) DEFAULT 0,
    is_non_taxable TINYINT(1) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Active',
    display_in_payslip TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS employee_salary_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salary_structure_id INT NOT NULL,
    component_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (salary_structure_id) REFERENCES salary_structures(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES payroll_components(id) ON DELETE CASCADE,
    UNIQUE KEY emp_comp_unique (salary_structure_id, component_id)
);

-- Safely add JSON columns to payroll_records if they don't exist
-- Note: MySQL < 8.0 doesn't support 'ADD COLUMN IF NOT EXISTS', 
-- so in raw MySQL this might throw an error if run twice. 
-- We recommend using a PHP migration script to catch this exception, or running it once.
ALTER TABLE payroll_records
ADD COLUMN earnings_json JSON NULL AFTER gross_amount;

ALTER TABLE payroll_records
ADD COLUMN deductions_json JSON NULL AFTER net_amount;
