# Release Notes

## Version: v1.5.6 (2026-03-22)
**Status**: Orion Level 1 - Foundational Security & Auth
**Modules**: Organization, Employee, Onboarding, RBAC, Authentication

### Changes:
- [FIX] AuthController: Resolved PDO parameter count mismatch.
- [FIX] AuthController: Added missing `MailHelper` dependency for OTP flow.
- [FIX] Controller: Resolved critical 500 error in `verifyDataScope` by updating legacy `offices` table references to `companies`.
- [SECURITY] Hardened OTP verification flow with bcrypt hashing.
- [DOCS] Generated formal Noah Audit Report for Level 1 compliance.

### Database:
- [PATCH] Added `user_otps` table to master schema.
- [FIX] Updated `Controller` to reference `companies` and `countries`.

---

## Version: v1.5.5 (2026-03-21)
**Modules**: Leave, Attendance, Organization
**Changes**:
- [FEATURE] Leave Configuration: Added gender-specific restrictions for Maternity/Paternity leave.
- [UPDATE] Attendance: Expanded attendance status ENUM to include remote/on-site types.
- [DB] Migrated `leave_types` and `attendance_logs` schema.

---

## Version: v1.5.4 (Earlier)
**Modules**: Base Framework, RBAC Initial
**Changes**:
- [INIT] Core HRMS framework with multi-company support.
- [RBAC] Initial implementation of Roles and Permissions.
