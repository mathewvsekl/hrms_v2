-- HRMS V2 - Foundation Data Seeder
-- Initializes global RBAC roles and the root fallback Super Admin

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- 1. Seed the 5-Tier Operational Roles
INSERT IGNORE INTO `roles` (`id`, `name`) VALUES
(1, 'SUPER_ADMIN'),
(2, 'HR_MANAGER'),
(3, 'COUNTRY_MANAGER'),
(4, 'HR_ASSISTANT'),
(5, 'EMPLOYEE');


-- 2. Seed the Root Super Admin Credential (mathew.vsekl@gmail.com / admin123)
-- The password_hash represents 'admin123' passed through PHP's password_hash(..., PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `employee_id`, `username`, `password_hash`, `is_active`) VALUES
(1, NULL, 'mathew.vsekl@gmail.com', '$2y$12$jDRfytCw9NO4FaecsDjqK.N5aQyfvothqGvxAK5YDBg3TFdO8LZfy', 1)
ON DUPLICATE KEY UPDATE `username` = 'mathew.vsekl@gmail.com';

-- 3. Bind the Root Credential to the SUPER_ADMIN Role matrix
INSERT IGNORE INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1);

COMMIT;
