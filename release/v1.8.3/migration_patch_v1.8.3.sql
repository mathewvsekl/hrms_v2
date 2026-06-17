-- HRMS V2 Migration Patch v1.8.3
-- Purpose: Add color coding to leave types for better calendar visualization

ALTER TABLE leave_types 
ADD COLUMN IF NOT EXISTS color_code VARCHAR(10) DEFAULT '#6b7280' AFTER gender_restriction;

ALTER TABLE leave_requests
ADD COLUMN IF NOT EXISTS updated_at_utc TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER approved_by_id;

-- Seed default colors for standard leave types
UPDATE leave_types SET color_code = '#3B82F6' WHERE code = 'AL'; -- Annual Leave (Blue)
UPDATE leave_types SET color_code = '#F59E0B' WHERE code = 'SL'; -- Sick Leave (Amber)
UPDATE leave_types SET color_code = '#10B981' WHERE code = 'CL'; -- Casual Leave (Emerald)
UPDATE leave_types SET color_code = '#EC4899' WHERE code = 'ML'; -- Maternity Leave (Pink)
UPDATE leave_types SET color_code = '#8B5CF6' WHERE code = 'PL'; -- Paternity Leave (Violet)
UPDATE leave_types SET color_code = '#EF4444' WHERE code = 'UL'; -- Unpaid Leave (Red)

-- If codes are full names instead of slugs, handle both
UPDATE leave_types SET color_code = '#3B82F6' WHERE name LIKE '%Annual%';
UPDATE leave_types SET color_code = '#F59E0B' WHERE name LIKE '%Sick%';
UPDATE leave_types SET color_code = '#10B981' WHERE name LIKE '%Casual%';
UPDATE leave_types SET color_code = '#EC4899' WHERE name LIKE '%Maternity%';
UPDATE leave_types SET color_code = '#8B5CF6' WHERE name LIKE '%Paternity%';
UPDATE leave_types SET color_code = '#EF4444' WHERE name LIKE '%Unpaid%';
