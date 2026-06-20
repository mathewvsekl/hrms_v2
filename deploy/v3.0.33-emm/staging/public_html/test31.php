<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("SELECT id, first_name, last_name, reporting_manager_id FROM employees WHERE id = 16 OR id = 17 OR id = 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
