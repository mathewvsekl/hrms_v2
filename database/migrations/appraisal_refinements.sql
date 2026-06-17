ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS selected_offices JSON NULL AFTER status;
ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS employee_deadline DATE NULL AFTER selected_offices;
ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS manager_deadline DATE NULL AFTER employee_deadline;
ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS hr_deadline DATE NULL AFTER manager_deadline;
CREATE TABLE IF NOT EXISTS appraisal_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appraisal_id INT NOT NULL,
    approver_id INT NOT NULL,
    status ENUM('pending', 'approved', 'returned') DEFAULT 'pending',
    comment TEXT,
    step_order INT DEFAULT 0,
    created_at_utc TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appraisal_id) REFERENCES employee_appraisals(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
