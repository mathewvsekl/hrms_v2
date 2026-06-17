-- HRMS V2 Migration Patch v2.7.3
-- Generated on 2026-05-13
-- Focus: Hotfix for Employee Documents Type Truncation

-- IMPORTANT: Ensure you have selected the correct database before running this script.
-- Example: USE hrms_v2; 

-- 1. Convert document_type from ENUM to VARCHAR(100)
-- This fixes the 'Data truncated for column document_type' error when uploading 
-- documents with types like 'Passport', 'Visa', etc. that weren't in the old ENUM list.
ALTER TABLE employee_documents MODIFY COLUMN document_type VARCHAR(100) DEFAULT 'other';
