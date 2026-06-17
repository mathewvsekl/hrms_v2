AVANTGARDE HRMS SYSTEM SPECIFICATION

Core Features
-------------
Multi Country (20+)
Multi Company
Multi Currency
Multi Timezone

Modules
-------
Employee Management (Multi-Company)
Attendance
Leave
Travel
Payroll
Salary Advance
Appraisals
Onbboarding
Offboarding

Architecture
------------
RBAC
Custom Company Fields
Independent Payroll
Manual Attendance

Deployment
----------
HevistaCP on Digital Ocean Droplet
PHP backend
MySQL database

Deployment Structure (Reference: v3.0.0)
---------------------------------------
- Web Root: `public_html/` (Contains `index.php`, frontend assets, and `app/` core logic)
- Secure Storage: `private/` (Contains `config`, `storage`, `tmp`, and `public` uploads)
- Security: Bypasses `open_basedir` restrictions by utilizing HevistaCP's permitted `private` directory, ensuring sensitive credentials are never accessible from the public web root.