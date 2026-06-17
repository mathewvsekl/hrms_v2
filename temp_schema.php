<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=hrms_v2', 'root', '');
$q = $pdo->query('SHOW COLUMNS FROM salary_advances');
print_r($q->fetchAll(PDO::FETCH_ASSOC));
