<?php
require_once 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    
    // Check advances
    $stmt = $db->query("SELECT * FROM salary_advances");
    $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Total advances in DB: " . count($advances) . "\n";
    print_r($advances);

    // Check employee 1
    if (count($advances) > 0) {
        $empId = $advances[0]['employee_id'];
        $stmt = $db->prepare("SELECT * FROM employee_companies WHERE employee_id = ?");
        $stmt->execute([$empId]);
        $ec = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Employee Companies for employee_id = $empId:\n";
        print_r($ec);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
