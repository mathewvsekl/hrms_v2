<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8', 'root', '');
$stmt = $db->query("SELECT payload FROM request_logs ORDER BY id DESC LIMIT 1"); // Wait, does request_logs exist? No.
