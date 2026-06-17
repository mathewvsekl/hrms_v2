# Release Log: HRMS Production v2.8.6
**Release Date:** 2026-05-18
**Baseline:** v2.8.5
**Metadata:** Timezone standardization for monthly attendance grid and manual log saving, composite primary key handling, and late detection offset calculations.

## Core Enhancements (v2.8.6)
- **Timezone Standardization:** Standardized the monthly attendance grid view and manual log saving mechanisms to use the country's local timezone instead of UTC, ensuring consistency for international offices.
- **Composite Primary Key Query Fix:** Resolved database errors on `employee_companies` operations by correcting code queries to recognize the composite structure (`employee_id`, `company_id`) instead of referencing a non-existent single `id` column.
- **Late Detection Offset Alignment:** Aligned late check-in detection to compare times within the same timezone context, eliminating false late flags caused by mismatching UTC and local time offsets.
