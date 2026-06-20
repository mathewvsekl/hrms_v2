<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("DESCRIBE appraisal_comments");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
