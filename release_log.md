# HRMS V2 Release Log

## [v3.0.17-build2] - 2026-06-07
### Security
- **CRITICAL:** Removed all standalone PHP execution scripts (`alter_*.php`, `migrate_*.php`, `test*.php`) from the `backend/public` directory to enforce the Single Entry Point MVC architecture and prevent RBAC bypasses.

### Added
- Created `salary_advance_installments` table.
- Added `installment_amount`, `deducted_amount`, and `deduction_start_date` to `salary_advances`.
- Packaged missing structural database changes into `patch_v3.0.17.sql`.
- Added standalone migration capability (`migrate_standalone.php` - *Note: moved out of public directory*).

### Fixed
- Fixed `Failed opening required` fatal error for `Env.php` on the live server by correcting the path resolution in `index.php`.
- Corrected salary configuration and TIN updates in the React frontend (`SalaryAdvances.jsx`, `EmployeeAdvances.jsx`, `Payroll.jsx`).
