-- HRMS V2 Migration Patch v2.6.7
-- Generated on 2026-05-12

-- 1. Performance Indexing Optimizations
-- Optimize Attendance Reporting
CREATE INDEX idx_attendance_emp_date ON attendance_logs (employee_id, attendance_date);

-- Optimize Leave Management filtering
CREATE INDEX idx_leave_emp_status ON leave_requests (employee_id, status);

-- Optimize Appraisal Cycle lookups
CREATE INDEX idx_appraisal_cycle_status ON employee_appraisals (cycle_id, status);

-- General Performance improvements for JOINS
CREATE INDEX idx_employees_dept ON employees (department_id);
CREATE INDEX idx_employees_desig ON employees (designation_id);
CREATE INDEX idx_employees_manager ON employees (reporting_manager_id);
