-- Migration: Create company_documents table
-- Created At: 2026-05-14

CREATE TABLE IF NOT EXISTS `company_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `document_name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NOT NULL COMMENT 'e.g., Policy, Law, Manual',
    `file_path` VARCHAR(255) NOT NULL,
    `company_id` INT NULL COMMENT 'NULL for global/multi-company',
    `country_id` INT NULL COMMENT 'NULL for global/cross-country',
    `uploaded_by_id` INT NULL,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
