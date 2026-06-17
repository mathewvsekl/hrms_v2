<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2', 'root', '');
$stmt = $db->query('DESCRIBE salary_advances');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
