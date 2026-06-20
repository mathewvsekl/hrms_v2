<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("SELECT id, kra_name, created_at_utc FROM appraisal_ratings WHERE appraisal_id = 163");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
