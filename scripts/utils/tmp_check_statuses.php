<?php
require 'config/config.php';
$db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$stmt = $db->query("SELECT DISTINCT status FROM attendance_logs");
$statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Current statuses in attendance_logs:\n";
print_r($statuses);
?>
