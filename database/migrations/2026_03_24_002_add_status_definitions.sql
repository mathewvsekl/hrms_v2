-- Migration: Add office_attendance_status_definitions table
-- Date: 2026-03-24
-- Supports custom attendance status labels and colors per company.

CREATE TABLE IF NOT EXISTS `office_attendance_status_definitions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `status_key` VARCHAR(50) NOT NULL COMMENT 'e.g., work_from_home, on_site',
  `status_label` VARCHAR(100) NOT NULL COMMENT 'e.g., Work From Home',
  `color_code` VARCHAR(20) DEFAULT '#3b82f6',
  `is_default` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `company_status_key` (`company_id`, `status_key`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
