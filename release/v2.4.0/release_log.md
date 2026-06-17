# HRMS V2 Release Log - v2.4.0
**Date:** 2026-04-01
**Version:** v2.4.0 (Production)

## New Features & Enhancements
- **Multi-Company Attendance Status Codes:** Unified status code generation with `[DISPLAY]_[ISO]` format to ensure cross-company uniqueness.
- **Leave Type Branding:** Added `display_code` and `company_id` to `leave_types` for better company-specific leave management and dashboard visualization.
- **Attendance UI Optimization:** Integrated high-resolution SVG flags from FlagCDN for country-specific attendance logging.
- **Centralized Approval History:** Established a unified audit trail for all approval workflows (Leave, Appraisals, Attendance).
- **Multi-Office Admin Access:** Enhanced visibility for administrators to manage records across different office locations.

## Database Changes
- Added `display_code` to `office_attendance_status_definitions`.
- Added `company_id` and `display_code` to `leave_types`.
- Created `approval_history` table for centralized auditing.

## Build Information
- **Build Number:** 2400
- **Environment:** Production
- **Package:** `HRMS_V2_v2.4.0_FULL.zip`
- **Executor:** Despatcher Agent (coordinated by Orion)
