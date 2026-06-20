-- --------------------------------------------------------
-- Migration Patch: v3.0.34-hrms
-- Description: Implementation of Performance Appraisal System (Full-Stack) for HRMS
-- --------------------------------------------------------

-- 1. Create Appraisal System Settings
CREATE TABLE IF NOT EXISTS `appraisal_system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `category` VARCHAR(50) DEFAULT 'general',
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Create Department KPI Requirements
CREATE TABLE IF NOT EXISTS `department_kpi_requirements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NOT NULL,
  `min_kpis` INT DEFAULT 3,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`department_id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Create Appraisal Templates
CREATE TABLE IF NOT EXISTS `appraisal_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Create Template Questions
CREATE TABLE IF NOT EXISTS `template_questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `section` ENUM('B_KPI', 'B_SOFT_SKILL', 'C_SUMMARY', 'D_MANAGER', 'E_HR') NOT NULL,
  `question_text` TEXT NOT NULL,
  `is_mandatory` TINYINT(1) DEFAULT 1,
  `display_order` INT DEFAULT 0,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`template_id`),
  FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Create Appraisal Cycles
CREATE TABLE IF NOT EXISTS `appraisal_cycles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL COMMENT 'e.g., Annual Appraisal 2025',
  `frequency` VARCHAR(50) DEFAULT 'Annual',
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `status` ENUM('draft', 'active', 'closed') DEFAULT 'draft',
  `selected_offices` LONGTEXT NULL,
  `employee_deadline` DATE DEFAULT NULL,
  `manager_deadline` DATE DEFAULT NULL,
  `hr_deadline` DATE DEFAULT NULL,
  `management_deadline` DATE DEFAULT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Create Employee Appraisals
CREATE TABLE IF NOT EXISTS `employee_appraisals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `manager_id` INT DEFAULT NULL COMMENT 'Snapshot of reporting manager at creation time',
  `cycle_id` INT NOT NULL,
  `template_id` INT NOT NULL,
  `status` ENUM('draft', 'l1_review', 'l2_review', 'l3_review', 'hr_calibration', 'finalized', 'withdrawn', 'rejected') DEFAULT 'draft',
  `eligible_for_increment` TINYINT(1) DEFAULT NULL COMMENT 'Determined by HR in Section E',
  `eligible_for_bonus` TINYINT(1) DEFAULT NULL COMMENT 'Determined by HR in Section E',
  `final_rating` DECIMAL(4,2) DEFAULT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`employee_id`),
  INDEX (`manager_id`),
  INDEX (`cycle_id`),
  INDEX (`template_id`),
  INDEX `idx_appraisal_cycle_status` (`cycle_id`, `status`),
  FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`manager_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`cycle_id`) REFERENCES `appraisal_cycles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`template_id`) REFERENCES `appraisal_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Create Appraisal Ratings
CREATE TABLE IF NOT EXISTS `appraisal_ratings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `kra_name` VARCHAR(255) NULL COMMENT 'For dynamic KPIs in Section B',
  `achievements` TEXT NULL COMMENT 'Employee provided achievements',
  `question_id` INT DEFAULT NULL COMMENT 'Maps to template_questions for predefined soft skills',
  `employee_rating` DECIMAL(4,2) DEFAULT NULL,
  `manager_rating` DECIMAL(4,2) DEFAULT NULL,
  `hr_adjusted_rating` DECIMAL(4,2) DEFAULT NULL,
  `manager_comment` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`appraisal_id`),
  INDEX (`question_id`),
  FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `template_questions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Create Appraisal Comments
CREATE TABLE IF NOT EXISTS `appraisal_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `section` VARCHAR(50) NOT NULL COMMENT 'e.g., Section_C_Challenges, Section_D_Recommendation',
  `author_id` INT NOT NULL COMMENT 'Employee who wrote this comment (can be self, manager, or HR)',
  `comment_text` TEXT NOT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`appraisal_id`),
  INDEX (`author_id`),
  FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Create Appraisal Approvals
CREATE TABLE IF NOT EXISTS `appraisal_approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `approver_id` INT NOT NULL,
  `status` ENUM('pending', 'approved', 'returned') DEFAULT 'pending',
  `comment` TEXT NULL,
  `step_order` INT DEFAULT 0,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`appraisal_id`),
  INDEX (`approver_id`),
  FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approver_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 10. Create Approval History
CREATE TABLE IF NOT EXISTS `approval_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module` ENUM('leave', 'appraisal', 'attendance', 'onboarding') NOT NULL,
  `reference_id` INT NOT NULL COMMENT 'ID from leave_requests, employee_appraisals, or attendance_logs',
  `actor_id` INT NOT NULL COMMENT 'users.id of the person who performed the action',
  `action` VARCHAR(50) NOT NULL COMMENT 'submitted, approved, rejected, returned, cancelled, finalized',
  `comment` TEXT NULL,
  `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_module_ref` (`module`, `reference_id`),
  INDEX (`actor_id`),
  FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 11. Create Appraisal Approval Matrices
CREATE TABLE IF NOT EXISTS `appraisal_approval_matrices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `step_order` INT NOT NULL,
  `role_required` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Create Appraisal Letters
CREATE TABLE IF NOT EXISTS `appraisal_letters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appraisal_id` INT NOT NULL,
  `status` ENUM('Draft', 'Published', 'Acknowledged') DEFAULT 'Draft',
  `old_salary` DECIMAL(10,2) NULL,
  `new_salary` DECIMAL(10,2) NULL,
  `letter_content` TEXT NULL,
  `published_at` TIMESTAMP NULL,
  `acknowledged_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`appraisal_id`),
  FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Seed Initial System Settings
INSERT IGNORE INTO `appraisal_system_settings` (`setting_key`, `setting_value`, `category`) VALUES 
('default_min_kpis_global', '3', 'kpi'),
('soft_skills_criteria', '["Communication", "Teamwork & Collaboration", "Accountability & Ownership", "Professional Conduct", "Adaptability & Flexibility", "Initiative & Proactiveness", "Problem Solving Ability", "Time Management & Discipline", "Quality & Attention to Detail"]', 'soft_skills'),
('rating_system_mapping', '[{"rating": 1, "stars": 5, "label": "Outstanding"}, {"rating": 2, "stars": 4, "label": "Strong"}, {"rating": 3, "stars": 3, "label": "Effective"}, {"rating": 4, "stars": 2, "label": "Developing"}, {"rating": 5, "stars": 1, "label": "Below Expectations"}]', 'rating');

-- 14. Seed Default Template (if not exists)
INSERT INTO `appraisal_templates` (`id`, `name`, `description`) 
SELECT 1, 'Standard Annual Appraisal', 'Default annual performance review template including KPIs and 10 mandatory soft skills.'
FROM dual WHERE NOT EXISTS (SELECT 1 FROM `appraisal_templates` WHERE `id` = 1);

-- 15. Seed Template Questions (if not exists)
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 1, 1, 'B_SOFT_SKILL', 'Communication', 1 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 1);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 2, 1, 'B_SOFT_SKILL', 'Teamwork', 2 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 2);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 3, 1, 'B_SOFT_SKILL', 'Problem Solving', 3 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 3);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 4, 1, 'B_SOFT_SKILL', 'Time Management', 4 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 4);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 5, 1, 'B_SOFT_SKILL', 'Adaptability', 5 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 5);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 6, 1, 'B_SOFT_SKILL', 'Leadership', 6 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 6);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 7, 1, 'B_SOFT_SKILL', 'Work Ethic', 7 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 7);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 8, 1, 'B_SOFT_SKILL', 'Critical Thinking', 8 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 8);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 9, 1, 'B_SOFT_SKILL', 'Conflict Resolution', 9 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 9);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 10, 1, 'B_SOFT_SKILL', 'Emotional Intelligence', 10 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 10);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 11, 1, 'D_MANAGER', 'Manager Recommendation & Summary', 100 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 11);
INSERT INTO `template_questions` (`id`, `template_id`, `section`, `question_text`, `display_order`) 
SELECT 12, 1, 'E_HR', 'HR Final Comments & Increment Eligibility', 200 FROM dual WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE `id` = 12);
