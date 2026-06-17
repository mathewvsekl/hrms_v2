# Release Notes
**Version**: v1.6.4 (AUDITED_ALIGNMENT)
**Date**: 2026-03-23

## 🛡️ [SECURITY] Audit Remediation:
- **Attendance Mode Alignment**: Fixed critical 500 error where frontend sent invalid values (strict, lexible). Reverted to 	ime_based & status_based to match schema.
- **RBAC Enforcement**: Hardened index.php routing to explicitly call RoleMiddleware for all Admin/Manager modules.
- **Data Boundary Safety**: Injected scope validation (erifyDataScope) into Employee and Attendance controllers.
- **API Stability**: Resolved critical JSON corruption bug caused by redundant session_start() notices.

## 📦 Modules Included:
- Organization (Multi-Country/Multi-Company Setup)
- Employee Management (Secured Profile & Onboarding)
- RBAC Security Layer (Verified & Enforced)
- Attendance Tracking (Scope-Validated)
- Leave Management (Remediated Baseline)
