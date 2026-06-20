<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query('SELECT * FROM appraisal_ratings WHERE appraisal_id = 147');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
