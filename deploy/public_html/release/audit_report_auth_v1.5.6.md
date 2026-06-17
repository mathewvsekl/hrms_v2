# MODULE REVIEW REPORT (Orion Level 1)

**Module Name**: Authentication (OTP & Session)
**Auditor Personality**: Noah (Logic & Workflow Auditor)
**Date**: 2026-03-22
**Status**: APPROVED with recommendations

## 1. Logic Issues
- **PDO Parameter Binding (RESOLVED)**: Previous version had a mismatch in `AuthController::login` where positional parameters were mixed with named placeholders. This has been corrected to use strictly named parameters (`:usr`).
- **Dependency Missing (RESOLVED)**: `MailHelper` class was being called without a `use` statement. Rectified by adding `use App\Helpers\MailHelper;`.

## 2. Workflow Issues
- **OTP Hashing (IMPROVED)**: OTP codes are now hashed using `PASSWORD_BCRYPT` before storage in `user_otps`, preventing plaintext exposure in the database.
- **Session Cleanup**: The `verifyOTP` method correctly invalidates old unused OTPs for the user before issuing a new one.

## 3. Data Structure Issues
- **Table Missing (RESOLVED)**: The `user_otps` table was missing from the master schema. It has been added to `DATABASE_SCHEMA.sql` and the versioned SQL export.
- **Geographic Context Logic**: The `Controller::verifyDataScope` was found referencing a legacy `offices` table. This has been updated to `companies` to match the current schema.

## 4. Cross-Module Dependencies
- **Employee-User Link**: The Auth flow correctly validates if an employee is `active` before allowing OTP generation or Login.
- **Company Context**: Primary company membership is now correctly retrieved during login and stored in session for scope enforcement.

## 5. Practical HR Concerns
- **OTP Retrieval**: For development, logs are directed to `/tmp/mail_log.txt`. A debug route `/api/debug/otp-logs` has been provided to facilitate testing without an SMTP server.

## 6. Recommended Action
- **PROCEED**: The Authentication module is now logically sound and follows the foundational requirements of Orion Level 1.
- **FUTURE**: Once Appraisal module development resumes, a separate audit must be conducted for its reporting hierarchies.

**Severity**: LOW (All previous HIGH issues resolved)
<b>Audit Signature</b>: Noah_2026_03_22_1.5.6
