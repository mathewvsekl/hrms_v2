-- ================================================================
-- PATCH: Soft-Deactivation for employee_companies
-- Version: v1.6.12
-- Date: 2026-03-26
-- Author: Sofia (Data Architect) via Orion Orchestrator
-- ================================================================
-- Purpose: Add is_active and deactivated_at_utc columns to support
--          soft-deactivation instead of destructive DELETE on
--          company-employee unlinking.
-- ================================================================

ALTER TABLE `employee_companies`
  ADD COLUMN `is_active` BOOLEAN DEFAULT TRUE COMMENT 'Soft-deactivation flag; FALSE = historically deactivated, data preserved',
  ADD COLUMN `deactivated_at_utc` TIMESTAMP NULL DEFAULT NULL COMMENT 'UTC timestamp when this link was deactivated';

-- Backfill existing rows as active
UPDATE `employee_companies` SET `is_active` = TRUE WHERE `is_active` IS NULL;

-- ================================================================
-- VERIFICATION: Run this to confirm the patch applied correctly
-- SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_NAME = 'employee_companies'
--   AND COLUMN_NAME IN ('is_active', 'deactivated_at_utc');
-- ================================================================
