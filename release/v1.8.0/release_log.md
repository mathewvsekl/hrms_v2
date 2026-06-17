# Release Log: HRMS Production v1.8.0
**Release Date:** 2026-03-26
**Baseline:** v1.7.0 SYNC

## Core Enhancements

### 1. Executive Dashboard Rebranding
- **Strategic Nomenclature**: Re-aligned UI labels for executive-level reporting.
    - Updated "Dashboard" to "HR Administration Centre".
    - Updated "Employees" to "Human Capital Base".
    - Updated "Attendance" to "Workforce Readiness".
- **Refined Metrics**: The "At Work" percentage now reflects a comprehensive set of statuses including `on_site`, `work_from_home`, `late`, and `present` against the active headcount.

### 2. Attendance & Calendar UX
- **Navigation**: Month-to-month calendar navigation is now active on the Employee Profile.
- **Precision Reporting**: Weekend counts are now dynamically excluded from attendance summary stat boxes to provide more accurate operational insights.
- **Status Integration**: Holidays and weekend configurations are fully integrated into the visual calendar logic.

### 3. Multi-Company Data Integrity (Soft-Deactivation)
- **Non-Destructive Management**: Transitioned from hard-delete to soft-deactivation for employee-company associations.
- **History Preservation**: The `employee_companies` junction now utilizes `is_active` and `deactivated_at_utc` flags, ensuring auditable historical data when employees transfer between offices.

### 4. Security & Multi-Tenancy
- **Audit Alignment**: Re-verified RBAC protocols and mandatory data scope isolation in `AttendanceController` and `EmployeeController` as per Noah Logic Audit standards.

---
**Deployment Artifacts Prepared by Antigravity (Advanced Coding Agent)**
