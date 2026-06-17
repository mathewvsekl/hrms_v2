<?php
require_once 'c:\Users\AneeshMathew\HRMS V2\config\database.php';
$db = Database::getInstance()->getConnection();

$output = "--- Table: leave_types ---\n";
$stmt = $db->query("DESCRIBE leave_types");
$output .= print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true);

$output .= "\n--- Sample Data: leave_types ---\n";
$stmt = $db->query("SELECT * FROM leave_types LIMIT 10");
$output .= print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true);

$output .= "\n--- Table: office_attendance_configs ---\n";
$stmt = $db->query("DESCRIBE office_attendance_configs");
$output .= print_r($stmt->fetchAll(PDO::FETCH_ASSOC), true);

file_put_contents('db_dump.txt', $output);
echo "Dumped to db_dump.txt\n";
