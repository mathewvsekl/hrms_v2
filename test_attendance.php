<?php
require 'backend/app/config/config.php';
require 'backend/app/Core/Database.php';
$db = \Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM attendance_logs ORDER BY attendance_date DESC LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
