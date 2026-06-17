<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM employees WHERE first_name LIKE '%Aneesh%' OR last_name LIKE '%Aneesh%' LIMIT 1");
    $stmt->execute();
    $emp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$emp) {
        echo "Aneesh not found.\n";
        exit;
    }
    $emp_id = $emp['id'];
    echo "Employee ID: " . $emp_id . "\n";
    
    // Check logs
    $stmt = $db->prepare("SELECT id, attendance_date, status, is_manually_modified FROM attendance_logs WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 10");
    $stmt->execute([$emp_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Logs:\n";
    print_r($logs);
    
    // Check Leave types
    $stmt = $db->prepare("SELECT clp.company_id, lt.id, lt.code 
            FROM leave_types lt
            JOIN company_leave_policies clp ON lt.id = clp.leave_type_id");
    $stmt->execute();
    echo "Mapped Leave Types:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Check Leave Requests
    $stmt = $db->prepare("SELECT id, start_date, end_date, status, total_days FROM leave_requests WHERE employee_id = ? ORDER BY id DESC LIMIT 5");
    $stmt->execute([$emp_id]);
    echo "Leave Requests:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
