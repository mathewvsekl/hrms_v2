<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("SELECT id FROM template_questions");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
