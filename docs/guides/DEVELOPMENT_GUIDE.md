# HRMS V2 - Step-by-Step Development Guide

This guide breaks down the `PROJECT_ROADMAP.md` into an actionable, sequential development execution plan. By following these steps, you will establish a solid foundation and incrementally deliver fully functional modules for the Enterprise HRMS system.

---

## Step 1: Foundation & Custom Framework Setup (Phases 0 & 1)
Since the system relies on a native LAMP stack (PHP + MySQL + JS) without heavy third-party frameworks, the first step is building a robust, lightweight custom MVC architecture.

1. **Environment Initialization:**
   - Install local server (Laragon, XAMPP, or Docker).
   - Set up the Git repository and standard `.gitignore`.
2. **Directory Structure:**
   - Create directories: `/app` (Controllers, Models, Middleware), `/views`, `/config`, `/public`, `/routes`, and `/storage`.
3. **Core Engine:**
   - **Router:** Create an `index.php` entry point that intercepts URIs and dispatches requests to Controllers.
   - **Database Wrapper:** Implement a singleton pattern using PDO (`config/database.php`) for secure, reusable database queries.
   - **Environment Manager:** Setup `.env` parsing to manage secrets and db credentials.
   - **Base Controller/Model:** Create parent classes with helper methods (e.g., JSON response formatters, basic CRUD operations).

## Step 2: Security & Authentication (Phases 2 & 3)
Security forms the backbone of any HRMS.

1. **Database Schema:**
   - Create tables: `users`, `roles`, `permissions`, `role_permissions`, `user_roles`.
2. **Auth Mechanisms:**
   - Build a secure login form. Integrate `password_hash()` and `password_verify()`.
   - Implement session tokens, session-timeout logic, and invalid-login lockout for brute-force protection.
3. **RBAC Middleware:**
   - Write a core middleware function that checks if the logged-in user possesses the required permission to access specific routes or UI elements.

## Step 3: Enterprise Structure & Global Configs (Phases 4 & 5)
Before adding employees, the organizational structure must be defined.

1. **Organizational Schema Engine:**
   - Build models and CRUD views for `countries`, `offices`, `departments`, and `teams`.
   - Implement hierarchical parent-child relationships for managers and departments.
2. **Super Admin Configuration Engine:**
   - Create a `system_settings` table (key-value parameters) for dynamic configs (e.g., default currency, timezone per office).
   - Build a **Custom Fields Engine**: Allow admins to dynamically add custom data collection fields for Employee profiles.

## Step 4: Employee Lifecycle & Onboarding (Phases 6 & 7)
1. **Employee Master Database:**
   - Build the `employees` table encompassing personal, professional, and financial details.
   - Implement a modular frontend for Employee Profiles (tabs for general info, documents, bank details, contracts).
2. **Automated Onboarding:**
   - Create an onboarding wizard/portal for new hires to submit documents safely.
   - Automatically assign checklists (e.g., IT equipment assignment) upon profile creation.

## Step 5: Time, Attendance & Leaves (Phases 8 & 9)
1. **Attendance Tracking:**
   - Build UI for Employee Clock-In/Clock-Out.
   - For backend tracking: Ensure multi-timezone logic relies on UTC, converting to the local office's timezone on render.
   - Build scripts to calculate total hours worked.
2. **Leave Management:**
   - Create tables for `leave_types`, `leave_requests`, `leave_balances`.
   - Implement an approval workflow (Pending -> Manager Approved -> HR Approved).
   - Deduct balances smoothly and handle cross-month leave overlaps.

## Step 6: Payroll & Financials (Phases 10 & 11)
Design the payroll engine logically to consume attendance and leave data.

1. **Payroll Structure Engine:**
   - Create dynamic interfaces to define Earnings (Basic, HRA, Allowances) and Deductions (Tax, PF).
2. **Salary Advance Requests:**
   - Build request-and-approval workflows for advances. Inject approved advances directly into the employee's monthly deduction pool.
3. **Monthly Processing Module:**
   - Create batch scripts that calculate gross to net salary per office/country rules based on days present.
   - Render and store immutable PDF Payslips.

## Step 7: Appraisals & Offboarding (Phases 12 & 13)
1. **Performance Management:**
   - Design dynamic appraisal forms using the Custom Fields Engine.
   - Enable multi-tier ratings (Self-Assessment -> Manager Rating).
2. **Offboarding Processes:**
   - Implement resignation triggers leading to automated workflows: disabling system access, finalizing exit checklists (asset returns), and triggering the final settlement payroll run.

## Step 8: Reporting & Auditing (Phases 14, 15 & 16)
1. **Reporting Engine:**
   - Build exportable datatables (CSV/Excel/PDF formats) for active headcount, attendance anomalies, and payroll summaries.
2. **Audit Trails:**
   - Hook into the core Base Model to automatically log every `INSERT`, `UPDATE`, and `DELETE` event (tracking `user_id`, `table_name`, `old_value`, `new_value`, `timestamp`).
3. **Testing:**
   - Run AI-simulated unit tests to ensure permission routing, payroll calculations, and timezone offsets are perfect.

## Step 9: Final Deployment (Phase 17)
1. **Optimization:** Verify database table indexing. Refactor heavy queries.
2. **Server Setup:** Prepare DirectAdmin setup, provision SSL domains, set up automated daily database dumps to secure block storage.
3. **Launch:** Deploy via Git pull to the live production LAMP environment.

---
**Developer/AI Pro-Tip:** Build iteratively. Treat the core MVC engine and Authentication/RBAC (Steps 1 & 2) as non-negotiable prerequisites. You cannot safely build the payroll or tracking modules without a solid routing and user identity backbone.
