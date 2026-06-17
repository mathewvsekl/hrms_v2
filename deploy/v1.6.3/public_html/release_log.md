# Release Notes
**Version**: v1.6.3 (GOLDEN_AUDITED)
**Date**: 2026-03-23

## 🛡️ [SECURITY] Audit Remediation:
- **RBAC Enforcement**: Hardened index.php routing to explicitly call RoleMiddleware for all Admin/Manager modules.
- **Data Boundary Safety**: Injected scope validation (erifyDataScope) into Employee and Attendance controllers to prevent cross-company access.
- **API Stability**: Resolved critical JSON corruption bug caused by redundant session_start() notices.
- **Debug Cleanup**: Decommissioned public /api/debug/otp-logs endpoint.

## 📦 Modules Included:
- Organization (Multi-Country/Multi-Company Setup)
- Employee Management (Secured Profile & Onboarding)
- RBAC Security Layer (Verified & Enforced)
- Attendance Tracking (Scope-Validated)
- Leave Management (Remediated Baseline)
