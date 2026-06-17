-- HRMS V2 - Migration Patch v2.7.1 (Hotfix)
-- Purpose: Add missing expiry_date column to employee_documents

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- Add expiry_date if not exists (using a simple ALTER, if it fails it's likely already there)
ALTER TABLE `employee_documents` 
ADD COLUMN `expiry_date` DATE NULL COMMENT 'Expiry date for time-sensitive documents' 
AFTER `document_type`;

COMMIT;
