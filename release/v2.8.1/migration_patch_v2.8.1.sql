-- Migration Patch v2.8.1 (Consolidated)
-- Release Date: 2026-05-15
-- Objective: Support onboarding module, decoupling compliance, and adding role descriptions.

-- 1. Support Onboarding in Approval History
-- If the column exists, we just modify the enum.
ALTER TABLE `approval_history` MODIFY COLUMN `module` ENUM('leave', 'appraisal', 'attendance', 'onboarding') NOT NULL;

-- 2. Add Job Description to Employees (for Role Details)
-- Using a check-safe approach (SQL doesn't have IF NOT EXISTS for columns easily in raw SQL, 
-- but this script is intended for a single run on a v2.7.7 baseline).
ALTER TABLE `employees` ADD COLUMN IF NOT EXISTS `job_description` TEXT NULL COMMENT 'Brief employment details/role description' AFTER `employment_type`;

-- 3. De-normalize country-specific fields (TIN, NSSF)
-- Dropping global columns to favor Custom Fields engine for country-specific compliance.
ALTER TABLE `employees` DROP COLUMN IF EXISTS `tin_number`;
ALTER TABLE `employees` DROP COLUMN IF EXISTS `nssf_number`;

-- 4. Ensure Approval History has comment field
-- (Already exists in master but added here for safety on older dbs)
-- ALTER TABLE `approval_history` ADD COLUMN IF NOT EXISTS `comment` TEXT NULL AFTER `action`;
