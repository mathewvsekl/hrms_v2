-- HRMS V2 - Migration Patch v2.7.0
-- Purpose: Add support for employee document storage and registry

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 1. Create Employee Documents Table
CREATE TABLE IF NOT EXISTS `employee_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `document_type` VARCHAR(100) DEFAULT 'other',
  `expiry_date` DATE NULL COMMENT 'Expiry date for time-sensitive documents',
  `uploaded_by_id` INT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Add Index for performance
CREATE INDEX idx_emp_docs_emp_id ON employee_documents (employee_id);

-- 3. Update System Version
UPDATE `global_settings` SET `setting_value` = 'v2.7.0' WHERE `setting_key` = 'system_version';
INSERT IGNORE INTO `global_settings` (`setting_key`, `setting_value`, `category`) VALUES ('system_version', 'v2.7.0', 'system');

COMMIT;
