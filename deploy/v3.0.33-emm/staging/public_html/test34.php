<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("SELECT id, kra_name, question_id FROM appraisal_ratings WHERE question_id IS NULL");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
