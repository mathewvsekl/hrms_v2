<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8mb4', 'root', '');
$stmt = $db->prepare('SELECT * FROM users WHERE username LIKE "%joseph%"');
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $db->prepare('SELECT * FROM employees WHERE email LIKE "%joseph%"');
$stmt2->execute();
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
