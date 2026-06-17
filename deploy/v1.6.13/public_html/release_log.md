# Release Notes
**Version**: v1.6.13 (STABLE)
**Date**: 2026-03-26

## Changes (v1.6.13):
### Database Migration (REQUIRED)
- Added `is_active` (BOOLEAN DEFAULT TRUE) column to `employee_companies`
- Added `deactivated_at_utc` (TIMESTAMP NULL) column to `employee_companies`
- Run `migration_patch_v1.6.13.sql` BEFORE deploying backend

### Backend (5 Controllers Updated)
- **EmployeeController**: `updateEmployee()` now soft-deactivates removed company links instead of deleting them. Reactivation supported.
- **AuthController**: All `employee_companies` joins filter by `is_active = 1`
- **AttendanceController**: All `employee_companies` joins filter by `is_active = 1`
- **LeaveController**: All `employee_companies` joins and subqueries filter by `is_active = 1`
- **AppraisalController**: `initiateCycle()` employee_companies join filters by `is_active = 1`

### Frontend
- **EmployeeProfile**: Active companies show Primary/Secondary labels. Deactivated companies shown via toggle. Edit mode has clearer secondary company UI.

### Cumulative
- Includes all v1.6.12 compliance refactor changes
- Includes all v1.6.11 security hardening changes
