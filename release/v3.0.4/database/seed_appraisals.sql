-- Seed Appraisal Data
USE hrms_v2;

-- Clear old data to avoid duplicates if re-run
DELETE FROM appraisal_ratings;
DELETE FROM appraisal_comments;
DELETE FROM employee_appraisals;
DELETE FROM template_questions;
DELETE FROM appraisal_templates;
DELETE FROM appraisal_cycles;

-- 1. Create a Cycle
INSERT INTO appraisal_cycles (name, start_date, end_date, status) VALUES 
('Annual Appraisal Cycle 2025', '2025-01-01', '2025-12-31', 'active');

SET @cycle_id = LAST_INSERT_ID();

-- 2. Create a Template
INSERT INTO appraisal_templates (name, description) VALUES 
('Standard Corporate Performance Review', 'Annual assessment for all full-time employees covering KRAs and core competencies.');

SET @template_id = LAST_INSERT_ID();

-- 3. Add Template Questions (Section B: Core Competencies)
INSERT INTO template_questions (template_id, question_text, section, display_order) VALUES 
(@template_id, 'Client Satisfaction & Relationship Management', 'B_KPI', 1),
(@template_id, 'Technical Knowledge & Professionalism', 'B_KPI', 2),
(@template_id, 'Team Work & Interpersonal Skills', 'B_SOFT_SKILL', 3),
(@template_id, 'Communication & Reporting', 'B_SOFT_SKILL', 4),
(@template_id, 'Initiative & Leadership', 'B_SOFT_SKILL', 5);

-- 4. Create an appraisal for a sample employee (Solomon Kiwunda - ID 310)
-- 305 is Aneesh (Manager)
INSERT INTO employee_appraisals (employee_id, manager_id, cycle_id, template_id, status) VALUES 
(310, 305, @cycle_id, @template_id, 'draft');
