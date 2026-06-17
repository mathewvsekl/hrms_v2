-- HRMS V2 - Sample Data Seeder (Company Schema)
-- Run this AFTER DATABASE_SCHEMA.sql and seed_init.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- 1. Seed Departments (no longer linked to companies)
INSERT IGNORE INTO `departments` (`id`, `name`) VALUES
(1, 'Human Resources'),
(2, 'Engineering'),
(3, 'Operations'),
(4, 'Finance');

-- 2. Seed Designations
INSERT IGNORE INTO `designations` (`id`, `department_id`, `title`, `level`) VALUES
(1, 1, 'HR Manager', 3),
(2, 2, 'Senior Software Engineer', 2),
(3, 3, 'Operations Coordinator', 1),
(4, 4, 'Finance Lead', 3),
(5, 3, 'Country Manager', 4);

-- 3. Seed Employees (no office_id column anymore)
INSERT IGNORE INTO `employees` (`id`, `employee_code`, `department_id`, `designation_id`, `first_name`, `last_name`, `email`, `phone`, `employment_type`, `status`, `hire_date`) VALUES
(1, 'EMP-001', 1, 1, 'Joseph', 'Okello', 'joseph@example.com', '+256700123456', 'full_time', 'active', '2024-01-15'),
(2, 'EMP-002', 2, 2, 'Atim', 'Grace', 'grace@example.com', '+256700654321', 'full_time', 'active', '2023-06-20'),
(3, 'EMP-003', 3, 3, 'Raj', 'Patel', 'raj@example.com', '+971501234567', 'full_time', 'active', '2024-02-10'),
(4, 'EMP-004', 4, 4, 'Sarah', 'Wanjiku', 'sarah@example.com', '+254711223344', 'full_time', 'active', '2023-11-05'),
(305, 'VUEMP008', 3, 5, 'Aneesh', 'Opolot-Senior', 'aneesh@vsekl.com', '+256700111222', 'full_time', 'active', '2022-01-10'),
(310, 'VUEMP018', 3, 5, 'Solomon', 'Kiwunda', 'solomon@vsekl.com', '+256700333444', 'full_time', 'active', '2022-06-15');

-- 4. Employee-Company Associations (many-to-many with primary)
INSERT IGNORE INTO `employee_companies` (`employee_id`, `company_id`, `is_primary`) VALUES
(1, 3, 1),   -- Joseph -> Uganda (primary)
(2, 3, 1),   -- Grace -> Uganda (primary)
(3, 1, 1),   -- Raj -> Dubai (primary)
(4, 2, 1),   -- Sarah -> Kenya (primary)
(305, 3, 1), -- Aneesh -> Uganda (primary)
(305, 1, 0), -- Aneesh -> Dubai (affiliated)
(310, 3, 1), -- Solomon -> Uganda (primary)
(310, 1, 0); -- Solomon -> Dubai (affiliated)

-- 5. Create User Accounts for Sample Employees
-- Password for all is 'password123'
INSERT IGNORE INTO `users` (`id`, `employee_id`, `username`, `password_hash`, `is_active`) VALUES
(2, 1, 'joseph@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(3, 2, 'grace@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(4, 3, 'raj@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(5, 305, 'aneesh@vsekl.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(6, 310, 'solomon@vsekl.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- 6. Assign Roles
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES
(2, 2), -- Joseph is HR_MANAGER
(3, 5), -- Grace is EMPLOYEE
(4, 5), -- Raj is EMPLOYEE
(5, 3), -- Aneesh is COUNTRY_MANAGER
(6, 5); -- Solomon is EMPLOYEE

-- 7. Global Settings
INSERT IGNORE INTO `global_settings` (`setting_key`, `setting_value`) VALUES
('company_name', 'Avantgarde Inc FZCO');

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
