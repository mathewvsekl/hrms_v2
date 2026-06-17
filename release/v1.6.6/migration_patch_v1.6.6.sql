-- HRMS V2 - Migration Patch v1.6.6
-- Contains Appraisal Module Refinements and Mandatory Template Seeding

-- 1. Schema Alterations (From appraisal_refinements.sql)
ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS selected_offices JSON NULL AFTER status;
ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS employee_deadline DATE NULL AFTER selected_offices;
ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS manager_deadline DATE NULL AFTER employee_deadline;
ALTER TABLE appraisal_cycles ADD COLUMN IF NOT EXISTS hr_deadline DATE NULL AFTER manager_deadline;

CREATE TABLE IF NOT EXISTS appraisal_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appraisal_id INT NOT NULL,
    approver_id INT NOT NULL,
    status ENUM('pending', 'approved', 'returned') DEFAULT 'pending',
    comment TEXT,
    step_order INT DEFAULT 0,
    created_at_utc TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appraisal_id) REFERENCES employee_appraisals(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Data Seeding (From seed_appraisal_template.php)
-- Insert Template if it doesn't already exist
INSERT INTO appraisal_templates (name, description)
SELECT 'Standard Annual Appraisal', 'Default annual performance review template including KPIs and 10 mandatory soft skills.'
WHERE NOT EXISTS (SELECT 1 FROM appraisal_templates WHERE name = 'Standard Annual Appraisal');

-- Retrieve Template ID
SET @TemplateId = (SELECT id FROM appraisal_templates WHERE name = 'Standard Annual Appraisal' LIMIT 1);

-- Insert Soft Skills B_SOFT_SKILL Questions
INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Communication', 1
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Communication');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Teamwork', 2
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Teamwork');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Problem Solving', 3
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Problem Solving');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Time Management', 4
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Time Management');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Adaptability', 5
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Adaptability');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Leadership', 6
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Leadership');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Work Ethic', 7
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Work Ethic');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Critical Thinking', 8
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Critical Thinking');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Conflict Resolution', 9
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Conflict Resolution');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'B_SOFT_SKILL', 'Emotional Intelligence', 10
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND question_text = 'Emotional Intelligence');

-- Insert Manager and HR questions
INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'D_MANAGER', 'Manager Recommendation & Summary', 100
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND section = 'D_MANAGER');

INSERT INTO template_questions (template_id, section, question_text, display_order)
SELECT @TemplateId, 'E_HR', 'HR Final Comments & Increment Eligibility', 200
WHERE NOT EXISTS (SELECT 1 FROM template_questions WHERE template_id = @TemplateId AND section = 'E_HR');

