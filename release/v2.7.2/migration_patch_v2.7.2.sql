-- HRMS V2 Migration Patch v2.7.2
-- Generated on 2026-05-13
-- Focus: Hotfix for Employee Documents Expiry Date

-- IMPORTANT: Ensure you have selected the correct database before running this script.
-- Example: USE hrms_v2; 

-- 1. Add missing expiry_date column to employee_documents
-- This fixes the SQLSTATE[42S22] Column not found error during document upload
ALTER TABLE employee_documents ADD COLUMN IF NOT EXISTS expiry_date DATE NULL AFTER document_type;
