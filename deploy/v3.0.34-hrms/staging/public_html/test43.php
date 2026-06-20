<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
try {
    $db->exec("ALTER TABLE template_questions ADD COLUMN rating_scale_max INT DEFAULT 5");
    echo "Added rating_scale_max column successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
