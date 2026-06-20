<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("DELETE FROM appraisal_ratings WHERE question_id IS NULL AND (kra_name IS NULL OR kra_name = '')");
echo "Deleted " . $stmt->rowCount() . " corrupt rows.";
