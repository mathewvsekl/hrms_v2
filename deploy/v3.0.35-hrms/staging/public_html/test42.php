<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
// I will just manually run the query that failed to see if it works now!
try {
    $db->prepare("INSERT INTO template_questions (template_id, section, question_text, description, display_order) VALUES (?, 'B_SOFT_SKILL', ?, ?, ?)")
       ->execute([14, 'Communication', 'Desc', 1]);
    echo "Success!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
