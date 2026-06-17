<?php
require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "INSERT INTO appraisal_cycles (name, frequency, start_date, end_date, status, selected_offices, employee_deadline, manager_deadline, hr_deadline) 
            VALUES (:name, :frequency, :start_date, :end_date, 'active', :offices, :emp_dl, :mgr_dl, :hr_dl)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'name' => 'Annual Appraisal 2025',
        'frequency' => 'Annual',
        'start_date' => '2025-01-01',
        'end_date' => '2025-12-31',
        'offices' => json_encode([]),
        'emp_dl' => '2026-01-07',
        'mgr_dl' => '2026-01-14',
        'hr_dl' => '2026-01-21'
    ]);
    echo "Success! ID: " . $db->lastInsertId() . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
