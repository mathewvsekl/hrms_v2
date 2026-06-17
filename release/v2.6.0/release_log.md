# Release Log: HRMS Production v2.6.0
**Release Date:** 2026-04-01
**Baseline:** v2.3.0 (Restored)
**Metadata:** System Restoration & Baseline Re-alignment.

## Executive Summary
This release (v2.6.0) serves as a critical restoration point for the HRMS V2 platform. Following a regression in the experimental ISO-standardized attendance architecture (v2.4.0/v2.5.0), this build restores the system to the stable v2.3.0 baseline while ensuring data integrity on the production server.

## Core Actions (v2.6.0)
- **Attendance System Restoration:** Reverted all attendance modules and logic to use established legacy keys (`present`, `absent`, `work_from_home`, etc.).
- **Production Data Normalization:** Included a data migration patch (`migration_patch_v2.6.0_rollback.sql`) to convert all ISO-prefixed status codes in the production database back to their generic strings.
- **UI/UX Reversion:** Restored the "Attendance Log" and "Monthly Report" interfaces to the standardized stable layout, removing experimental "Architect" locking mechanisms.
- **Schema Cleanup:** Reverted the `countries` table to the v2.3.0 schema (dropped redundant `iso2_code` column).
- **Core Stability:** Re-validated all leave calculation and balance synchronization logic against the restored baseline.

## Deployment Instructions
1. Execute `release/v2.6.0/migration_patch_v2.6.0_rollback.sql` on the production database.
2. Deploy the `deploy/v2.6.0/public_html.zip` package to the server.
3. Clear server-side application cache if applicable.
