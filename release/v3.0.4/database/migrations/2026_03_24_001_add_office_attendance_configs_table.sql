-- Migration: Add office_attendance_configs table
-- Date: 2026-03-24

CREATE TABLE IF NOT EXISTS `office_attendance_configs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `config_date` DATE NOT NULL,
  `status` ENUM('present', 'absent', 'half_day', 'late', 'on_leave', 'public_holiday', 'weekend', 'training', 'on_site', 'work_from_home') NOT NULL,
  `remarks` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_office_date` (`company_id`, `config_date`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
