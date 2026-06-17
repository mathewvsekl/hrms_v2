-- Migration: Configure Attendance and Leave Types
-- Date: 2026-03-22

-- 1. Update Attendance Status ENUM
ALTER TABLE `attendance_logs` MODIFY COLUMN `status` ENUM('present', 'absent', 'half_day', 'late', 'on_leave', 'public_holiday', 'weekend', 'training', 'on_site', 'work_from_home') DEFAULT 'present';

-- 2. Update Leave Types Table
ALTER TABLE `leave_types` ADD COLUMN `gender_restriction` ENUM('male', 'female', 'none') DEFAULT 'none' AFTER `is_paid`;

-- 3. Seed/Update Leave Types
INSERT INTO `leave_types` (`name`, `code`, `is_paid`, `gender_restriction`) VALUES 
('Casual Leave', 'CL', 1, 'none'),
('Sick Leave', 'SL', 1, 'none'),
('Annual Leave', 'AL', 1, 'none'),
('Maternity Leave', 'ML', 1, 'female'),
('Paternity Leave', 'PL', 1, 'male'),
('Bereavement / Compassionate Leave', 'BCL', 1, 'none')
ON DUPLICATE KEY UPDATE 
`name` = VALUES(`name`), 
`is_paid` = VALUES(`is_paid`), 
`gender_restriction` = VALUES(`gender_restriction`);
