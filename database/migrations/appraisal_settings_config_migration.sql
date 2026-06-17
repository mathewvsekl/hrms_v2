-- Migration: Appraisal System Configuration
-- Date: 2026-03-27

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
