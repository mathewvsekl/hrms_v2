-- HRMS V2 - Production Seeding Fix (v4)
-- Seed Leave Types only (Schema repair for check_in_utc is confirmed as complete)

START TRANSACTION;

-- Seed Standard Leave Types
INSERT IGNORE INTO `leave_types` (`name`, `code`, `is_paid`, `gender_restriction`) VALUES 
('Annual Leave', 'AL', 1, 'none'),
('Sick Leave', 'SL', 1, 'none'),
('Maternity Leave', 'ML', 1, 'female'),
('Paternity Leave', 'PL', 1, 'male'),
('Unpaid Leave', 'UL', 0, 'none'),
('Compassionate Leave', 'CL', 1, 'none'),
('Study Leave', 'STL', 1, 'none');

COMMIT;
