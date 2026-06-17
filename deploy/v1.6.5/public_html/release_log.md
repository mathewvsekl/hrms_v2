# Release Notes
**Version**: v1.6.5 (AUDITED_ALIGNMENT)
**Date**: 2026-03-24

## 🛡️ [SECURITY] Audit Remediation & Enhancements:
- **Attendance Architectural Upgrade**: Modularized status handling to support custom office-level definitions.
- **Approval Workflow**: Integrated draft/submit/approve cycle for attendance logs with full Audit History tracking.
- **Weekly Schedules**: Implemented dynamic office-level daily defaults (e.g., Weekend/Workday) to replace hardcoded logic.
- **Grid Stability**: Refactored getGridLogs to resolve 500 Internal Server Errors caused by missing schema definitions.
- **RBAC Enforcement**: Hardened index.php routing to explicitly call RoleMiddleware for all Admin/Manager modules.
- **Data Boundary Safety**: Injected scope validation (erifyDataScope) into Employee and Attendance controllers.
- **API Stability**: Resolved critical JSON corruption bug caused by redundant session_start() notices.

## 📦 Modules Included:
- Organization (Multi-Country/Multi-Company Setup)
- Employee Management (Secured Profile & Onboarding)
- RBAC Security Layer (Verified & Enforced)
- Attendance Tracking (Scope-Validated)
- Leave Management (Remediated Baseline)
