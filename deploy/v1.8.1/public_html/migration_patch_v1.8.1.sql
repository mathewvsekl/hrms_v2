-- ================================================================
-- HRMS V2 - Production Migration Patch v1.8.1
-- DYNAMIC LEAVE BALANCE & CALENDAR SYNC
-- Baseline: v1.8.0 | Target: v1.8.1
-- Date: 2026-03-26
-- ================================================================

START TRANSACTION;

-- ========================================================
-- 1. LEAVE TYPES: GENDER RESTRICTION
-- ========================================================
-- Support for gender-specific leave (e.g., Maternity/Paternity)
ALTER TABLE leave_types 
ADD COLUMN IF NOT EXISTS gender_restriction ENUM('none', 'male', 'female') DEFAULT 'none';

-- ========================================================
-- 2. CALENDAR COLORS FOR LEAVE TYPES
-- ========================================================
-- Ensure leave types can have specific colors in the calendar.
-- (This column should already exist in office_attendance_status_definitions, 
-- but we ensure it matches the leave codes).

-- Update existing leave types with default gender restrictions if needed
UPDATE leave_types SET gender_restriction = 'female' WHERE code LIKE '%maternity%';
UPDATE leave_types SET gender_restriction = 'male' WHERE code LIKE '%paternity%';

COMMIT;
