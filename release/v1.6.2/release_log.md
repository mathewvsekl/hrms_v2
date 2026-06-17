# Release Notes
**Version**: v1.6.2 (AUDITED)
**Date**: 2026-03-22

## [REMEDIATION] Audit Fixes:
- **Leave Module**: Refactored LeaveController to sync with company_id and company_leave_policies.
- **Database Schema**: Unified approved_by_id foreign keys in leave_requests to reference users(id).
- **Core Stability**: Resolved 500 errors in Leave request submission and corrected holiday parameter mapping.

## Modules Included:
- Organization (Countries, Companies, Dept, Desig)
- Employee Onboarding (Drafts & Workflows)
- Appraisals
- RBAC (Role Based Access Control)
- Authentication (Fixed & Stabilized)
- Leave Management (Remediated)

## Previous Changes (v1.6.1):
- [CRITICAL] Added getallheaders polyfill to resolve 500 errors on Nginx/Production servers.
- [BUGFIX] Corrected Onboarding navigation and API response mapping to prevent crashes.
