<?php
require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

echo "Seeding default appraisal template...\n";

try {
    // 1. Check if template already exists
    $stmt = $db->query("SELECT COUNT(*) FROM appraisal_templates");
    if ($stmt->fetchColumn() > 0) {
        echo "Template already exists. Skipping seed.\n";
        exit;
    }

    // 2. Insert Template
    $db->exec("INSERT INTO appraisal_templates (name, description) VALUES ('Standard Annual Appraisal', 'Default annual performance review template including KPIs and 10 mandatory soft skills.')");
    $templateId = $db->lastInsertId();

    // 3. Insert Soft Skills
    $softSkills = [
        'Communication', 'Teamwork', 'Problem Solving', 'Time Management', 'Adaptability',
        'Leadership', 'Work Ethic', 'Critical Thinking', 'Conflict Resolution', 'Emotional Intelligence'
    ];

    foreach ($softSkills as $index => $skill) {
        $stmt = $db->prepare("INSERT INTO template_questions (template_id, section, question_text, display_order) VALUES (?, 'B_SOFT_SKILL', ?, ?)");
        $stmt->execute([$templateId, $skill, $index + 1]);
    }

    // 4. Insert some default Section D/E questions for HR/Managers
    $db->prepare("INSERT INTO template_questions (template_id, section, question_text, display_order) VALUES (?, 'D_MANAGER', 'Manager Recommendation & Summary', 100)")->execute([$templateId]);
    $db->prepare("INSERT INTO template_questions (template_id, section, question_text, display_order) VALUES (?, 'E_HR', 'HR Final Comments & Increment Eligibility', 200)")->execute([$templateId]);

    echo "Seeding complete. Template ID: $templateId\n";

} catch (Exception $e) {
    echo "Seeding error: " . $e->getMessage() . "\n";
}
