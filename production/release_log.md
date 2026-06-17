# Release Log: HRMS Production v2.8.7
**Release Date:** 2026-05-20
**Baseline:** v2.8.6
**Metadata:** International country code phone number selection across all employee and company contact fields.

## Core Enhancements (v2.8.7)
- **Country Code Phone Input Component:** Introduced a new reusable `PhoneInput` UI component featuring a flag dropdown for selecting international country dial codes combined with a local number text input. The component auto-parses and reconstructs stored phone strings in `+[code] [number]` format.
- **Automatic Default Country Detection:** Pre-selects the dial code based on context — company registered country for Company Settings, employee's primary company country for Employee Profile edits, and the logged-in HR user's country for the Onboarding wizard.
- **Company Details Integration (`CompanyDetail.jsx`):** Replaced the plain `Contact Phone` text input with the new `PhoneInput` component.
- **Employee Profile Integration (`EmployeeProfile.jsx`):** Replaced `Work Phone` and `Personal Contact Number` plain text inputs in edit mode with `PhoneInput`.
- **Onboarding Wizard Integration (`Onboarding.jsx`):** Replaced both phone fields in Step 1 with `PhoneInput`, defaulting to the logged-in HR user's country.
- **No Database Schema Changes:** Phone numbers stored as consolidated strings (e.g. `+971 501234567`) in existing `VARCHAR(30)` fields. No migration required.

---

# Release Log: HRMS Production v2.8.6
**Release Date:** 2026-05-18
**Baseline:** v2.8.5
**Metadata:** Timezone standardization for monthly attendance grid and manual log saving, composite primary key handling, and late detection offset calculations.

## Core Enhancements (v2.8.6)
- **Timezone Standardization:** Standardized the monthly attendance grid view and manual log saving mechanisms to use the country's local timezone instead of UTC, ensuring consistency for international offices.
- **Composite Primary Key Query Fix:** Resolved database errors on `employee_companies` operations by correcting code queries to recognize the composite structure (`employee_id`, `company_id`) instead of referencing a non-existent single `id` column.
- **Late Detection Offset Alignment:** Aligned late check-in detection to compare times within the same timezone context, eliminating false late flags caused by mismatching UTC and local time offsets.

---


# Release Log: HRMS Production v2.8.5
**Release Date:** 2026-05-18
**Baseline:** v2.8.4
**Metadata:** Personal contact options, dynamic attendance report colors, and concurrent performance stress audit.

## Core Enhancements (v2.8.5)
- **Personal Contact Details:** Added dedicated options to store, edit, and view employee personal emails and personal contact numbers on profiles and during the onboarding lifecycle.
- **Dynamic Attendance Colors:** Refactored the Monthly Attendance Report grid and its legend to dynamically render status highlights using real-time custom `color_hex` settings returned from the backend.
- **50-User Concurrency Audit:** Executed serial baseline and parallel multi-threaded load tests for 50 concurrent logged-in users under active RBAC virtualization, compiling extensive latency metrics and database optimizations.

---

# Release Log: HRMS Production v2.8.4

**Release Date:** 2026-05-16
**Baseline:** v2.8.3
**Metadata:** UI/UX refinements, avatar resolution, and data scoping.

## Core Enhancements (v2.8.4)
- **Avatar Resolution:** Fixed 404 errors for employee avatars by standardizing path resolution and fallback logic.
- **Date Format Standardization:** Standardized all date displays (DOB, Hire Date, Document dates) in the Employee Profile to `dd/M/yyyy` format.
- **Dashboard Navigation:** Implemented React Router `navigate` for seamless in-app redirects in the "Action Required" card, eliminating full page reloads.
- **Policy Scoping:** Enhanced document visibility by integrating country and company-level scoping for Policies and Reference Documents on the Employee Profile.
- **Dashboard Stability:** Resolved `summary is not defined` ReferenceError in the Dashboard component and synchronized pending approvals hydration.

---

# Release Log: HRMS Production v2.8.3
**Release Date:** 2026-05-15
**Baseline:** v2.8.2
**Metadata:** Global timezone standardization and reporting audit.

## Core Enhancements (v2.8.3)
- **Timezone Synchronization:** Implemented "UTC Storage / Local Display" architecture across Attendance and Leave modules to ensure accurate regional reporting.
- **Data Sanitization:** Removed erroneous trailing zeros from company names in dashboard and employee profile interfaces.
- **Reporting Hardening:** Optimized SQL aggregation for headcount reporting, integrating `is_primary` filtering and global admin failsafes.

---

# Release Log: HRMS Production v2.8.2
**Release Date:** 2026-05-15
**Baseline:** v2.8.1
**Metadata:** Security hardening and RBAC access control fixes.

## Core Enhancements (v2.8.2)
- **RBAC Enforcement:** Hardened backend RoleMiddleware and frontend route protection to prevent unauthorized administrative access.
- **Security Audit:** Conducted a comprehensive audit of identity-aware "Hard-Deny" policies and sensitive data masking.
- **Session Hydration:** Optimized session state persistence to ensure consistent RBAC context across all API calls.

---

# Release Log: HRMS Production v2.8.1
**Release Date:** 2026-05-15
**Baseline:** v2.7.7
**Metadata:** Onboarding refinements and Compliance de-coupling.

## Core Enhancements (v2.8.1)
- **Compliance Decoupling:** Dropped global `tin_number` and `nssf_number` columns from the `employees` table. Country-specific compliance is now handled exclusively via the Custom Fields engine.
- **Onboarding UI Refinements:** Streamlined the onboarding process with a dynamic "Compliance & Banking" section that hides for specific entities (Avantgarde Enterprises) or when data is absent.
- **Audit Integration:** Added `onboarding` module support to the centralized `approval_history` system.
- **Production Build:** Optimized frontend assets with a new production build and selective packaging.

---

# Release Log: HRMS Production v2.7.7
**Release Date:** 2026-05-14
**Baseline:** v2.7.6
**Metadata:** Added Company Reference Documents module.

## Core Enhancements (v2.7.7)
- **Reference Documents:** Implemented the `company_documents` table and backend controllers to support system-wide Policies, Laws, and Manuals.
- **Scoping Logic:** Integrated country and company-level scoping for reference documents, ensuring employees only see relevant organizational materials.

---

# Release Log: HRMS Production v2.7.6
**Release Date:** 2026-05-14
**Baseline:** v2.7.5
**Metadata:** Hotfix for database schema index duplication.

## Core Enhancements (v2.7.6)
- **Schema Resilience:** Consolidated standalone `CREATE INDEX` statements into `CREATE TABLE IF NOT EXISTS` blocks. This prevents "Duplicate key name" errors when re-running the schema on existing databases.

---

# Release Log: HRMS Production v2.7.5
**Release Date:** 2026-05-14
**Baseline:** v2.7.4
**Metadata:** Standardized notifications and global directory integration.

## Core Enhancements (v2.7.5)
- **UI Standardization:** Migrated all remaining modules to `useNotificationStore`, eliminating browser-native alerts.
- **Global Employee Directory:** Implemented country-wise grouping and global visibility for the employee directory.
- **RBAC Optimization:** Empowered HR Assistant role with operational access to Leave Management.
- **Document Management:** Integrated Policies into sidebar and implemented attachment preview modal.

---

# Release Log: HRMS Production v2.7.4
**Release Date:** 2026-05-13
**Baseline:** v2.7.3
**Metadata:** Mailgun integration for reliable email notifications.

## Core Enhancements (v2.7.4)
- **Mailgun Integration:** Replaced standard SMTP with Mailgun REST API for improved deliverability.
- **Fallback System:** Implemented a robust CURL-based fallback mechanism for email notifications.

---

# Release Log: HRMS Production v2.7.3
**Release Date:** 2026-05-13
**Baseline:** v2.7.2
**Metadata:** Hotfix for document type flexibility.

## Core Enhancements (v2.7.3)
- **Document Type Fix:** Converted `document_type` from a restricted ENUM to a flexible `VARCHAR(100)` to support all frontend document classifications (Passport, Visa, etc.) without data truncation errors.

---

# Release Log: HRMS Production v2.7.2
**Release Date:** 2026-05-13
**Baseline:** v2.7.1
**Metadata:** Hotfix for document management and schema integrity.

## Core Enhancements (v2.7.2)
- **Document Management Fix:** Added the missing `expiry_date` column to the `employee_documents` table to resolve upload errors.
- **Schema Optimization:** Consolidated redundant table definitions in the master `DATABASE_SCHEMA.sql` to ensure long-term maintenance stability.

---

# Release Log: HRMS Production v2.7.1
**Release Date:** 2026-05-13
**Baseline:** v2.7.0
**Metadata:** Appraisal system refinements and company metadata expansion.

## Core Enhancements (v2.7.1)
- **Appraisal Workflow Refinements:** Added support for office-specific appraisal cycles and multi-level approval deadlines.
- **Appraisal Approvals:** Implemented a dedicated `appraisal_approvals` table for granular tracking of the performance review workflow.
- **Company Branding:** Expanded the `companies` table with `contact_phone` and `contact_email` for better organizational metadata.

---

# Release Log: HRMS Production v2.7.0
**Release Date:** 2026-05-13
**Baseline:** v2.6.9
**Metadata:** Leave cancellation workflow hardening and status soft-deletion.

## Core Enhancements (v2.7.0)
- **Leave Cancellation:** Implemented `cancellation_reason` for structured leave termination requests.
- **Attendance Soft-Delete:** Added `is_deleted` flag to `office_attendance_status_definitions` for historical data preservation.
- **Security:** Hardened multi-company isolation for leave type definitions.

---

# Release Log: HRMS Production v2.6.9
**Release Date:** 2026-05-13
**Baseline:** v2.6.8
**Metadata:** Hotfix release for leave management and notification systems.

## Core Enhancements (v2.6.9)
- **Leave Management Fixes:** Resolved SQL "Column not found" errors by adding `remarks` and `attachment_path` to `leave_requests`.
- **UI/UX Fixes:** Corrected "NaN Days" display in leave preview by fixing frontend data binding.
- **System Reliability:** Fixed Fatal Error in `NotificationHelper` caused by undefined method call to `MailHelper`.

---

# Release Log: HRMS Production v2.6.8
**Release Date:** 2026-05-12
**Baseline:** v2.6.7
**Metadata:** Production build with Global Admin access and identity-aware security.

## Core Enhancements (v2.6.8)
- **Global Administrative Access:** Refactored `verifyDataScope` to grant unrestricted geographic access to Admin and SuperAdmin roles.
- **Identity-Aware Security:** Implemented "Hard-Deny" policy for administrative endpoints when in "Employee View" mode via `X-View-Mode`.
- **Leave/Attendance Synchronization:** Standardized weekend and holiday identification for accurate working-day calculations.
- **User Type Duplication Fix:** Enforced strict hierarchical security and consolidated role-checking logic.

---

# Release Log: HRMS Production v2.6.7
**Release Date:** 2026-05-12
**Baseline:** v2.6.6
**Metadata:** Production build with company isolation and dashboard refinements.

## Core Enhancements (v2.6.7)
- **Company Leave Policy Isolation:** Enforced strict `company_id` scoping for leave types and balances.
- **Dashboard Holiday Sync:** Automated public holiday status synchronization on dashboard load.
- **Performance Optimization:** Added database indexes for attendance, leave, and appraisal modules to improve reporting speed.
- **Infrastructure:** Finalized production build scripts for atomic deployment.

---

# Release Log: HRMS Production v2.6.0
**Release Date:** 2026-04-01
**Baseline:** v2.3.0 (Restored)
**Metadata:** System Restoration & Baseline Re-alignment.

## Executive Summary
This release (v2.6.0) serves as a critical restoration point for the HRMS V2 platform. Following a regression in the experimental ISO-standardized attendance architecture (v2.4.0/v2.5.0), this build restores the system to the stable v2.3.0 baseline while ensuring data integrity on the production server.

## Core Actions (v2.6.0)
- **Attendance System Restoration:** Reverted all attendance modules and logic to use generic legacy keys (`present`, `absent`, etc.).
- **Production Data Normalization:** Included a data migration patch (`migration_patch_v2.6.0_rollback.sql`) to convert all ISO-prefixed status codes in the production database back to their generic strings.
- **UI/UX Reversion:** Restored the "Attendance Log" and "Monthly Report" interfaces to the standardized stable layout.
- **Schema Cleanup:** Reverted the `countries` table to the v2.3.0 schema (dropped redundant `iso2_code` column).

---

# Release Log: HRMS Production v2.5.0
**Release Date:** 2026-04-01
**Baseline:** v2.4.0
**Metadata:** Production build finalizing Multi-Company attendance and Monthly Reporting.

## Core Enhancements (v2.5.0)
- **Finalized Multi-Company Attendance ISO Standard:** Full integration of country-specific status codes (e.g., `PR_UG`, `PR_KE`, `PR_GH`) across manual entries, bulk logs, and leave synchronization.
- **Monthly Attendance Reporting Grid:** Premium, grid-based UI for monthly attendance tracking with status-specific color coding and horizontal scrolling.
- **Enhanced Data Isolation:** Reinforced `verifyDataScope` across Attendance and Leave modules to ensure strict multi-company privacy.
- **Optimized Leave Calculation:** Improved `calculateLeaveDays` and `recalculateBalances` with better support for calendar-day policies versus working-day policies.

---

# Release Log: HRMS Production v2.4.0
**Release Date:** 2026-04-01
**Baseline:** v2.3.0
**Metadata:** Production build with Multi-Company attendance and leave branding.

## Core Enhancements (v2.4.0)
- **Multi-Company Attendance Status Codes:** Unified status code generation with `[DISPLAY]_[ISO]` format.
- **Leave Type Branding:** Added `display_code` and `company_id` to `leave_types` for optimized multi-company leave management.
- **Attendance UI Optimization:** High-resolution SVG flag integration (FlagCDN) for localized attendance views.
- **Centralized Approval History:** Unified audit trail for all workflow actions (Leave, Appraisal, Attendance).
- **Multi-Office Visibility:** Enhanced admin scoping for managing remote office data.

---

# Release Log: HRMS Production v2.3.0
**Release Date:** 2026-03-31
**Baseline:** v2.2.1
**Metadata:** stabilizes recent enhancements for production deployment.

## Core Enhancements (v2.3.0)
- **Production Readiness**: Consolidated all v2.2.x features into a stable production-ready build.
- **Deployment Automation**: Updated `build_deploy.ps1` and `Despatcher.mf` for consistent CI/CD execution.

---

# Release Log: HRMS Production v2.2.1
**Release Date:** 2026-03-29
**Baseline:** v2.2.0

## Core Enhancements (v2.2.1)
- **Email Notification System**: Full integration with PHPMailer for automated Leave and Appraisal alerts.
- **Multi-Office Access**: Normalized administrator visibility across multiple office locations for appraisal and leave management.
- **Standardization**: Updated `DateHelper` for unified date handling across all backend modules.

---

# Release Log: HRMS Production v2.2.0
**Release Date:** 2026-03-29
**Baseline:** v2.1.0

## Core Enhancements (v2.2.0)
- **Centralized Approval History**: Implemented a unified audit trail for all approval-based workflows (Leave, Appraisal, Attendance).
- **Global Search**: Integration of a powerful cross-module search functionality.
- **Enhanced Leave Management**: Added mandatory cancellation reasons and multi-status request tracking.

---

# Release Log: HRMS Production v1.8.3
**Release Date:** 2026-03-27
**Baseline:** v1.8.2 SYNC
**Metadata:** requires_migration = true | rollback_safe = true

## Core Enhancements (v1.8.3)

### 1. Leave & Attendance Integration Fixes
- **Leave Color Coding**: Added `color_code` to `leave_types` for dynamic calendar status visualization.
- **Balance Synchronization**: Fixed `recalculateBalances` to accurately count `attendance_logs` by matching both slug codes (AL, ML) and full type names.
- **Frontend Configuration**: Added a color picker to the Leave Categories configuration UI in `Leave.jsx`.
- **Employee Profile**: Updated calendar and legend to use real-time colors from the backend for better visualization.
- **Critical Fix**: Added missing `updated_at_utc` column to `leave_requests` to fix 500 errors during leave approval.

---

# Release Log: HRMS Production v1.8.2
**Release Date:** 2026-03-26
**Baseline:** v1.8.1 SYNC
**Metadata:** requires_migration = true | rollback_safe = true

## Core Enhancements (v1.8.2)

### 1. Attendance Auto-Persistence & Refinements
- **Intelligent Status Defaults**: Automatically fills status based on Leave > Holiday > Weekend > **"Present"** priority.
- **Improved Persistence UX**:
    - **Individual Save**: Added per-row floppy disk icon for single-record overrides.
    - **Bulk Save**: "Save All Entries" for mass persistence of suggested defaults.
- **Office Architect Configuration**: New UI for office-specific standard workdays and weekend selection.
- **Automated Persistence**: Daily cron script for end-of-day auto-finalisation of attendance logs.
- **Audit Logging**: Enhanced tracking for `is_default_applied`, `is_manually_modified`, and `actor_type`.

---

# Release Log: HRMS Production v1.8.1
**Release Date:** 2026-03-26
**Baseline:** v1.8.0 SYNC
**Metadata:** requires_migration = true | rollback_safe = true

## Core Enhancements (v1.8.1)


### 1. Dynamic Leave Balance Synchronisation
- **Real-time Attendance Sync**: Implemented a backend engine to calculate leave usage directly from attendance logs.
- **Administrative Control**: Added "Sync Balances" buttons for individual and company-wide balance recalculations.
- **Gender-Aware Filtering**: Balanced leave bars now respect gender restrictions (e.g., Maternity leave is hidden for male employees).
- **Calendar Color Integration**: Leave type colors from the company configuration are now correctly displayed on the attendance calendar.

---

# Release Log: HRMS Production v1.8.0


---
**Deployment Artifacts Prepared by Antigravity (Advanced Coding Agent)**
