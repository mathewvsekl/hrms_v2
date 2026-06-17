# HRMS V2 - Release v1.6.10 (MASTER CONSOLIDATION)
Date: 2026-03-25

This package contains all updates required to bring a v1.6.8 or v1.6.9 instance to the latest v1.6.10 state.

## Contents
1. `migration_patch_v1.6.10_CONSOLIDATED.sql`: Master database patch.
2. Updated Frontend: `src/pages/Dashboard.jsx`, `src/pages/Assets.jsx`, `src/pages/Admin.jsx`.
3. Updated Backend: `app/Controllers/AttendanceController.php`, `app/Controllers/EmployeeController.php`.

## Highlights
- **Dynamic Dashboard**: Full attendance health visualization with custom status colors and automated milestones.
- **Assets Module**: Support for multi-company inventory and country-based filtering.
- **Manager Filtering**: New hierarchy logic restricting reporting managers based on designation level.
- **Standardized RBAC**: Canonical role names enforced across the system.

## Deployment Steps
1. Execute `migration_patch_v1.6.10_CONSOLIDATED.sql` on the production database.
2. Deploy the latest `app/Controllers` files to the server.
3. Rebuild and deploy the `frontend` (`npm run build`).
