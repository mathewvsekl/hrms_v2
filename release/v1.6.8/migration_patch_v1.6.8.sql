-- HRMS V2 - Migration Patch v1.6.8
-- Upgrades database from v1.6.6 Baseline to v1.6.8 Audited Stable
-- Includes Appraisal Refinements, Security Scoping Base, and UI Assets

-- 0. RBAC Role Standardization (Sync with production)
-- Ensure canonical roles exist
INSERT IGNORE INTO `roles` (`name`) VALUES ('SuperAdmin'), ('Admin'), ('HRManager'), ('CountryManager'), ('HRAssistant'), ('Employee');

-- Migrate users from legacy names to canonical ones before cleanup
SET @SuperAdminId = (SELECT id FROM `roles` WHERE name = 'SuperAdmin' LIMIT 1);
SET @AdminId = (SELECT id FROM `roles` WHERE name = 'Admin' LIMIT 1);
SET @HRManagerId = (SELECT id FROM `roles` WHERE name = 'HRManager' LIMIT 1);
SET @EmployeeId = (SELECT id FROM `roles` WHERE name = 'Employee' LIMIT 1);

UPDATE `user_roles` SET role_id = @SuperAdminId WHERE role_id IN (SELECT id FROM `roles` WHERE name IN ('Super Admin', 'SUPER_ADMIN') AND name != 'SuperAdmin');
UPDATE `user_roles` SET role_id = @HRManagerId WHERE role_id IN (SELECT id FROM `roles` WHERE name IN ('HR Manager') AND name != 'HRManager');

-- Clean up legacy roles
DELETE FROM `roles` WHERE name IN ('Super Admin', 'SUPER_ADMIN', 'HR Manager') AND name NOT IN ('SuperAdmin', 'HRManager');

-- 1. Company UI Enhancements
ALTER TABLE `companies` ADD COLUMN IF NOT EXISTS `logo_url` VARCHAR(255) DEFAULT 'default_logo.png' AFTER `contact_email`;
ALTER TABLE `companies` ADD COLUMN IF NOT EXISTS `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. Department Decoupling
-- Note: Optional if already decoupled in 1.6.6-beta
ALTER TABLE `departments` ADD COLUMN IF NOT EXISTS `updated_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 3. Appraisal Module - Advanced Scoping & Deadlines
ALTER TABLE `appraisal_cycles` ADD COLUMN IF NOT EXISTS `frequency` VARCHAR(50) DEFAULT 'Annual' AFTER `name`;
ALTER TABLE `appraisal_cycles` ADD COLUMN IF NOT EXISTS `selected_offices` JSON NULL AFTER `status`;
ALTER TABLE `appraisal_cycles` ADD COLUMN IF NOT EXISTS `employee_deadline` DATE NULL AFTER `selected_offices`;
ALTER TABLE `appraisal_cycles` ADD COLUMN IF NOT EXISTS `manager_deadline` DATE NULL AFTER `employee_deadline`;
ALTER TABLE `appraisal_cycles` ADD COLUMN IF NOT EXISTS `hr_deadline` DATE NULL AFTER `manager_deadline`;

-- 4. Appraisal Approvals Flow
CREATE TABLE IF NOT EXISTS `appraisal_approvals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `appraisal_id` INT NOT NULL,
    `approver_id` INT NOT NULL,
    `status` ENUM('pending', 'approved', 'returned') DEFAULT 'pending',
    `comment` TEXT,
    `step_order` INT DEFAULT 0,
    `created_at_utc` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`appraisal_id`) REFERENCES `employee_appraisals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approver_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Standard Data Seeding
-- Standard Annual Appraisal Template
INSERT INTO `appraisal_templates` (name, description)
SELECT 'Standard Annual Appraisal', 'Default annual performance review template including KPIs and 10 mandatory soft skills.'
WHERE NOT EXISTS (SELECT 1 FROM `appraisal_templates` WHERE name = 'Standard Annual Appraisal');

-- Retrieve Template ID for sub-seeding
SET @TemplateId = (SELECT id FROM `appraisal_templates` WHERE name = 'Standard Annual Appraisal' LIMIT 1);

-- Soft Skill Questions
INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Communication', 1
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Communication');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Teamwork', 2
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Teamwork');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Problem Solving', 3
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Problem Solving');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Time Management', 4
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Time Management');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Adaptability', 5
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Adaptability');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Leadership', 6
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Leadership');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Work Ethic', 7
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Work Ethic');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Critical Thinking', 8
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Critical Thinking');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Conflict Resolution', 9
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Conflict Resolution');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Emotional Intelligence', 10
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND question_text = 'Emotional Intelligence');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'D_MANAGER', 'Manager Recommendation & Summary', 100
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND section = 'D_MANAGER');

INSERT INTO `template_questions` (template_id, section, question_text, display_order)
SELECT @TemplateId, 'E_HR', 'HR Final Comments & Increment Eligibility', 200
WHERE NOT EXISTS (SELECT 1 FROM `template_questions` WHERE template_id = @TemplateId AND section = 'E_HR');

-- 6. Audit Logging
INSERT INTO `global_settings` (setting_key, setting_value, category)
VALUES ('last_migration_version', 'v1.6.8', 'system')
ON DUPLICATE KEY UPDATE setting_value = 'v1.6.8';

COMMIT;
