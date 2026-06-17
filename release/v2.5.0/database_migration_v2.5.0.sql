-- HRMS V2.5.0 Database Migration Patch
-- Description: Standardizes attendance status codes to use ISO-prefixed keys (e.g., PR_UG, AB_KE)
-- Prepared by: Antigravity (Advanced Coding Agent) 
-- Date: 2026-04-01

START TRANSACTION;

-- 1. Create a temporary mapping table for ISO codes per employee
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_employee_iso AS
SELECT 
    ec.employee_id,
    c.iso_code
FROM 
    employee_companies ec
JOIN 
    companies comp ON ec.company_id = comp.id
JOIN 
    countries c ON comp.country_id = c.id
WHERE 
    ec.is_primary = 1 AND ec.is_active = 1;

-- 2. Update legacy string-based statuses to ISO-prefixed format
-- Mapping: present -> PR, absent -> AB, late -> LT, half_day -> HD, on_leave -> OL, training -> TR, on_site -> OS, work_from_home -> WH

UPDATE attendance_logs al
JOIN tmp_employee_iso tei ON al.employee_id = tei.employee_id
SET al.status = CONCAT('PR_', tei.iso_code)
WHERE al.status = 'present';

UPDATE attendance_logs al
JOIN tmp_employee_iso tei ON al.employee_id = tei.employee_id
SET al.status = CONCAT('AB_', tei.iso_code)
WHERE al.status = 'absent';

UPDATE attendance_logs al
JOIN tmp_employee_iso tei ON al.employee_id = tei.employee_id
SET al.status = CONCAT('LT_', tei.iso_code)
WHERE al.status = 'late';

UPDATE attendance_logs al
JOIN tmp_employee_iso tei ON al.employee_id = tei.employee_id
SET al.status = CONCAT('HD_', tei.iso_code)
WHERE al.status = 'half_day';

UPDATE attendance_logs al
JOIN tmp_employee_iso tei ON al.employee_id = tei.employee_id
SET al.status = CONCAT('OL_', tei.iso_code)
WHERE al.status = 'on_leave';

UPDATE attendance_logs al
JOIN tmp_employee_iso tei ON al.employee_id = tei.employee_id
SET al.status = CONCAT('TR_', tei.iso_code)
WHERE al.status = 'training';

UPDATE attendance_logs al
JOIN tmp_employee_iso tei ON al.employee_id = tei.employee_id
SET al.status = CONCAT('OS_', tei.iso_code)
WHERE al.status = 'on_site';

UPDATE attendance_logs al
JOIN tmp_employee_iso tei ON al.employee_id = tei.employee_id
SET al.status = CONCAT('WH_', tei.iso_code)
WHERE al.status = 'work_from_home';

-- 3. Update existing _SYS suffixed statuses
UPDATE attendance_logs al
JOIN tmp_employee_iso tei ON al.employee_id = tei.employee_id
SET al.status = REPLACE(al.status, '_SYS', CONCAT('_', tei.iso_code))
WHERE al.status LIKE '%_SYS';

-- 4. Cleanup
DROP TEMPORARY TABLE IF EXISTS tmp_employee_iso;

COMMIT;

-- Verification query
-- SELECT DISTINCT status FROM attendance_logs;
