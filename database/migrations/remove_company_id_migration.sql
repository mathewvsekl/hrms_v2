-- Migration: Remove company_id from departments
-- This migration decouples Departments from Companies.

-- Note: The foreign key name 'departments_ibfk_1' is the auto-generated MariaDB default.
-- If this fails, please check the exact constraint name using:
-- SHOW CREATE TABLE departments;

ALTER TABLE `departments`
DROP FOREIGN KEY `departments_ibfk_1`;

ALTER TABLE `departments`
DROP COLUMN `company_id`;
