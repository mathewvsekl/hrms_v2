-- ================================================================
-- HRMS V2 Migration Patch v1.8.4
-- Date: 2026-03-27
-- Cumulative updates from v1.8.3
-- ================================================================

-- ----------------------------------------------------------------
-- 1. Appraisal System Configuration Moved to Top
-- ----------------------------------------------------------------

-- ----------------------------------------------------------------
-- 2. Appraisal System Configuration
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `appraisal_system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NULL,
    `category` VARCHAR(50) DEFAULT 'general',
    `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `department_kpi_requirements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_id` INT NOT NULL,
    `min_kpis` INT DEFAULT 3,
    `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed initial data
INSERT IGNORE INTO `appraisal_system_settings` (`setting_key`, `setting_value`, `category`) VALUES 
('default_min_kpis_global', '3', 'kpi'),
('soft_skills_criteria', '["Communication", "Teamwork & Collaboration", "Accountability & Ownership", "Professional Conduct", "Adaptability & Flexibility", "Initiative & Proactiveness", "Problem Solving Ability", "Time Management & Discipline", "Quality & Attention to Detail"]', 'soft_skills'),
('rating_system_mapping', '[{"rating": 1, "stars": 5, "label": "Outstanding"}, {"rating": 2, "stars": 4, "label": "Strong"}, {"rating": 3, "stars": 3, "label": "Effective"}, {"rating": 4, "stars": 2, "label": "Developing"}, {"rating": 5, "stars": 1, "label": "Below Expectations"}]', 'rating');

-- ----------------------------------------------------------------
-- 3. KPI Configuration Table
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `employee_kpi_configs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `kpi_name` VARCHAR(255) NOT NULL COMMENT 'KRA / Objective Name',
    `target_description` TEXT NULL COMMENT 'Detailed target or goal description',
    `weightage` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Optional weightage percentage',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit Log for KPI Changes (Optional but high-quality)
CREATE TABLE IF NOT EXISTS `kpi_config_audit` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `kpi_config_id` INT NOT NULL,
    `changed_by_id` INT NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`kpi_config_id`) REFERENCES `employee_kpi_configs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
