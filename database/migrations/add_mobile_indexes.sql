-- Migration: Add Mobile Performance Indexes
-- Created: 2026-05-06
-- Focus: Speeding up frequently queried columns for mobile app response times (<300ms goal)

-- Attendance Logs: Primary filter for mobile dashboard
CREATE INDEX idx_attendance_date ON attendance_logs (attendance_date);
CREATE INDEX idx_attendance_employee ON attendance_logs (employee_id, attendance_date);

-- Leave Requests: Status filtering and date sorting
CREATE INDEX idx_leave_status ON leave_requests (status);
CREATE INDEX idx_leave_dates ON leave_requests (start_date, end_date);
CREATE INDEX idx_leave_employee ON leave_requests (employee_id, status);

-- Notifications: Unread counts and chronologial fetching
CREATE INDEX idx_notification_user_unread ON notifications (user_id, is_read);
CREATE INDEX idx_notification_created ON notifications (created_at_utc);

-- Users: Ensure API token lookups are extremely fast
-- (Already has UNIQUE which is an index, but explicit for clarity)
CREATE INDEX idx_user_api_token ON users (api_token);

-- Employee Context: Fast lookups for profile fetching
CREATE INDEX idx_employee_code ON employees (employee_code);
CREATE INDEX idx_employee_email ON employees (email);
