<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT u.username, e.id as emp_id, ec.company_id, ec.is_primary, ec.is_active, dg.title 
        FROM users u 
        LEFT JOIN employees e ON u.employee_id = e.id 
        LEFT JOIN employee_companies ec ON e.id = ec.employee_id 
        LEFT JOIN designations dg ON e.designation_id = dg.id 
        WHERE u.username LIKE 'aneesh%'
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
