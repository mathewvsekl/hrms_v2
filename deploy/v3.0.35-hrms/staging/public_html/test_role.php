<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2', 'root', '');
$stmt = $db->query("SELECT u.id as user_id, u.username, e.first_name, e.last_name, r.name as role_name FROM users u LEFT JOIN employees e ON u.employee_id = e.id JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON ur.role_id = r.id;");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
