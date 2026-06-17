-- Migration Patch v2.8.5
-- Objective: Add options to record, view, and edit personal email and contact number on the employee profile
-- Requirement: Add personal_email and personal_phone columns to employees table.

ALTER TABLE `employees` ADD COLUMN `personal_email` VARCHAR(100) NULL AFTER `email`;
ALTER TABLE `employees` ADD COLUMN `personal_phone` VARCHAR(30) NULL AFTER `phone`;
