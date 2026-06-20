<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query('SHOW CREATE TABLE appraisal_comments');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
