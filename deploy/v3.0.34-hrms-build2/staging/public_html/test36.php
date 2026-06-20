<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("DESCRIBE template_questions");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt = $db->query("SELECT * FROM template_questions LIMIT 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
