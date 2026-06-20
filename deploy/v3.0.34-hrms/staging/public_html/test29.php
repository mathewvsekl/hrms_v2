<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("SELECT * FROM appraisal_ratings WHERE appraisal_id = 153");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $db->query("SELECT * FROM appraisal_comments WHERE appraisal_id = 153");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
