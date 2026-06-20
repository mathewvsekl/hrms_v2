<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("SHOW COLUMNS FROM employee_appraisals LIKE 'status'");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

$stmt2 = $db->query("SELECT DISTINCT status FROM employee_appraisals");
print_r($stmt2->fetchAll(PDO::FETCH_COLUMN));
