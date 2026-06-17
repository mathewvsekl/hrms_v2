-- --------------------------------------------------------
-- Migration Patch: v3.0.32-hrms-appraisal
-- Description: Implementation of Avantgarde Annual Appraisal V2 Schema
-- Includes: Customizable templates, soft skills, workflow matrices, cycles, landmarks, appraisals, returns, and letters.
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `appraisal_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `min_kpis` INT DEFAULT 5,
  `max_kpis` INT DEFAULT 10,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_template_soft_skills` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `skill_name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `rating_scale_max` INT DEFAULT 10,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_approval_matrices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `step_order` INT NOT NULL,
  `role_required` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_cycles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `template_id` INT NOT NULL,
  `year` INT NOT NULL,
  `frequency` ENUM('Yearly', 'Half-yearly', 'Quarterly') NOT NULL DEFAULT 'Yearly',
  `status` ENUM('draft', 'active', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
  `start_date` DATE,
  `end_date` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_cycle_landmarks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cycle_id` INT NOT NULL,
  `employee_submission_deadline` DATE,
  `manager_review_deadline` DATE,
  `hr_review_deadline` DATE,
  `management_approval_deadline` DATE,
  FOREIGN KEY (`cycle_id`) REFERENCES `appraisal_cycles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `cycle_id` INT NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'draft',
  `overall_kpi_rating` DECIMAL(3,2),
  `overall_soft_skills_rating` DECIMAL(3,2),
  `final_rating` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`cycle_id`) REFERENCES `appraisal_cycles`(`id`) ON DELETE CASCADE,
  INDEX (`employee_id`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_kpis` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `kra` TEXT,
  `achievements` TEXT,
  `employee_rating` INT,
  `manager_rating` INT,
  `manager_comments` TEXT,
  FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_soft_skills_ratings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `skill_id` INT NOT NULL,
  `employee_rating` INT,
  `manager_rating` INT,
  FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`skill_id`) REFERENCES `appraisal_template_soft_skills`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_summaries` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `overall_summary` TEXT,
  `challenges_faced` TEXT,
  `areas_of_improvement` TEXT,
  `training_required` TEXT,
  FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_manager_reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `overall_achievement` TEXT,
  `final_overall_rating` INT,
  `recommendations` TEXT,
  FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_hr_reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `soft_skills_post_mapping` DECIMAL(5,2),
  `overall_kpi_post_calibration` INT,
  `hr_observations` TEXT,
  `final_performance_rating` INT,
  `eligible_increment` TINYINT(1) DEFAULT 0,
  `eligible_bonus` TINYINT(1) DEFAULT 0,
  `special_notes` TEXT,
  FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_workflow_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `performed_by` INT NOT NULL,
  `comments` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `appraisal_letters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `file_path` VARCHAR(500),
  `status` ENUM('Draft', 'Published', 'Acknowledged') DEFAULT 'Draft',
  `old_salary` DECIMAL(15,2),
  `new_salary` DECIMAL(15,2),
  `published_at` TIMESTAMP NULL,
  `acknowledged_at` TIMESTAMP NULL,
  FOREIGN KEY (`appraisal_id`) REFERENCES `appraisals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
