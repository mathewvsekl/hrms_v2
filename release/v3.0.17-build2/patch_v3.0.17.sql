-- Patch v3.0.17
-- Salary Advances & Installments Updates

ALTER TABLE salary_advances ADD COLUMN installment_amount DECIMAL(10,2) NULL AFTER amount;
ALTER TABLE salary_advances ADD COLUMN deducted_amount DECIMAL(10,2) DEFAULT 0.00 AFTER installment_amount;
ALTER TABLE salary_advances ADD COLUMN deduction_start_date DATE NULL AFTER installment_amount;

CREATE TABLE IF NOT EXISTS salary_advance_installments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    salary_advance_id INT NOT NULL,
    payroll_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    deduction_date DATE NULL,
    remaining_balance DECIMAL(15,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE salary_advances MODIFY COLUMN status ENUM('Pending','Reviewed','Approved','Rejected','Partially Deducted','Deducted','Cancelled') DEFAULT 'Pending';
