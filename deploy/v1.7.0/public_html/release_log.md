# Release Notes
**Version**: v1.7.0 (STABLE — FULL FEATURE SYNC)
**Date**: 2026-03-26
**Build**: 1700
**Baseline**: v1.6.13

## Changes (v1.7.0 — Cumulative from v1.6.11 → v1.7.0):

### Database Migration (RUN FIRST)
- Run `migration_patch_v1.7.0.sql` via phpMyAdmin BEFORE deploying backend
- Ensures attendance status definitions exist for color-coded UI
- Seeds leave types if missing (for leave configuration linkage)
- Adds `Attendance.configure` permission for admin roles
- Ensures `designations.level` column for hierarchy filtering

### Backend (Code Sync — All Controllers Updated)
- **LeaveController**: `savePolicy()` now syncs leave balances to all employees in company. Leave configuration is fully linked to employee profiles.
- **AttendanceController**: `getGridLogs()` returns NULL instead of 'present' for unrecorded days. `getAttendanceStatuses()` returns color codes for progress bar rendering. Editable status definitions support.
- **EmployeeController**: Soft-deactivation on company links (`is_active` flag). Auto-allocates leave balances on onboarding. `listEmployees()` filters by `is_active = 1`.
- **AuthController**: All `employee_companies` joins filter by `is_active = 1`
- **AppraisalController**: `initiateCycle()` filters by `is_active = 1`

### Frontend (Code Sync — Full UI Update)
- **Attendance Log**: Default status shows "Select Status" (not "Present")
- **Attendance Grid**: Color-coded progress bars using status definition colors
- **Employee Profile**: Active/deactivated company labels, secondary company UI
- **Admin Config**: Editable attendance status definitions (label + color)
- **Leave Management**: Full leave policy configuration and employee balance sync
- **Date Inputs**: Standardized DD/MM/YYYY format across all modules
- **Assets Module**: Country-based navigation tabs
- **Reporting Manager**: Hierarchy filtering by designation level

### Security (v1.6.11 Cumulative)
- Removed all hardcoded emergency bypasses
- Mandatory multi-tenant data isolation on all controllers
- `verifyDataScope()` enforced on all CRUD operations

### Compliance (v1.6.12 Cumulative)
- Removed nationality-based labels (TIN/NSSF) from profile
- Compliance fields are now strictly office-defined via custom fields
