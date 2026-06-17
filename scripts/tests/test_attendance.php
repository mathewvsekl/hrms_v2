<?php
$db = new PDO('mysql:host=localhost;dbname=hrms_v2;charset=utf8mb4', 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $db->prepare('SELECT * FROM attendance_logs WHERE employee_id = 2 AND attendance_date IN ("2026-05-20", "2026-05-21")');
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Logs:\n";
print_r($logs);

$stmtLt = $db->query("SELECT id, code FROM leave_types");
$leaveTypes = $stmtLt->fetchAll(PDO::FETCH_ASSOC);
echo "Leave Types:\n";
print_r($leaveTypes);

$stmtLr = $db->prepare('SELECT * FROM leave_requests WHERE employee_id = 2');
$stmtLr->execute();
$lrs = $stmtLr->fetchAll(PDO::FETCH_ASSOC);
echo "Leave Requests:\n";
print_r($lrs);
