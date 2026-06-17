<?php
require_once __DIR__ . '/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    
    echo "1. Checking duplicates in employee_companies:\n";
    $query = "
        SELECT employee_id, COUNT(*) as assignment_count
        FROM employee_companies
        WHERE is_active = 1
        GROUP BY employee_id
        HAVING assignment_count > 1
        LIMIT 10
    ";
    $stmt = $db->query($query);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n2. Sample assignments for Employee 8 (Aneesh):\n";
    $stmt = $db->prepare("
        SELECT ec.*, c.name as company_name, cn.name as country_name
        FROM employee_companies ec
        JOIN companies c ON ec.company_id = c.id
        JOIN countries cn ON c.country_id = cn.id
        WHERE ec.employee_id = 8 AND ec.is_active = 1
    ");
    $stmt->execute();
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
