# Release Notes
**Version**: v1.6.0
**Date**: 2026-03-22

## Modules Included:
- Organization (Countries, Companies, Dept, Desig)
- Employee Onboarding (Drafts & Workflows)
- Appraisals
- RBAC (Role Based Access Control)
- Authentication (Fixed & Stabilized)

## Changes:
- [CRITICAL] Added getallheaders polyfill to resolve 500 errors on Nginx/Production servers.
- [MINOR] Integrated RBAC role assignment into the Employee Onboarding wizard.
- [MINOR] Added ability to alter RBAC roles directly from the Employee Profile UI.
- [PATCH] Verified frontend development server stability (localhost:5173).
- [PATCH] Established baseline project state for HRMS V2.
- [BETA] Integrated new david_deployment_executor yaml agent structure.
- [PATCH] Added Global ErrorBoundary and 404 handler to prevent blank white pages.
- [BUGFIX] Corrected Onboarding navigation and API response mapping to prevent crashes.
- Updated Organization panel UI to function as an accordion (all sections closed by default).
- Added contact phone and email to company configuration.

## Database Changes:
- permissions table seeded with system modules and actions.
- user_roles table now explicitly populated during employee creation.
- seed_permissions.sql created for initial RBAC setup.
