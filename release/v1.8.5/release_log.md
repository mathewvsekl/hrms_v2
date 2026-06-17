# Release Notes
**Version**: v1.8.5 (STABLE — CUMULATIVE SYNC)
**Date**: 2026-03-28
**Build**: 1805
**Baseline**: v1.8.1

## Changes (v1.8.1 → v1.8.5):

### Database Migration (RUN FIRST)
- Run `migration_patch_v1.8.5.sql` via phpMyAdmin BEFORE deploying backend.
- **v1.8.2 Sync**: Attendance auto-persistence tables and audit logging schemas.
- **v1.8.4 Sync**: Appraisal System settings, Department KPI requirements, and Performance evaluation tables.
- **v1.8.5 Update**: Adds `color_code` to `leave_types` for dynamic calendar visualization.

### Backend (Core Feature Updates)
- **Appraisal Module**: Full performance evaluation cycle management, KPI configurations, and department-level criteria.
- **Attendance & Leave**: Dynamic color status definitions. Corrected weekend/holiday calculation logic.
- **Employee Management**: Soft-deactivation of company associations (`is_active` flag). Automatic leave balance synchronization.
- **Multi-Tenancy**: Enhanced data isolation across all controllers (`verifyDataScope()`).

### Frontend (User Interface)
- **Visual Intelligence**: Attendance grid and leave calendars are now color-coded based on status/type definitions.
- **Appraisal Interface**: New administrative dashboard for KPI management and performance tracking.
- **Attendance Log**: UX improvements for selecting statuses and individual row-level persistence.
- **Standardization**: System-wide date formatting (DD MMM YYYY) and unified design tokens.

### Security & Internal
- Zero-trust RBAC enforcement on all endpoints.
- Removed nationality-specific compliance labels.
- Standardized country data in Assets module.
