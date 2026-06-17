<?php
require_once __DIR__ . '/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    
    echo "1. Total Active Employees:\n";
    echo $db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn() . "\n\n";

    echo "2. Active Employees with Company Association:\n";
    echo $db->query("SELECT COUNT(DISTINCT e.id) FROM employees e JOIN employee_companies ec ON e.id = ec.employee_id WHERE e.status = 'active' AND ec.is_active = 1")->fetchColumn() . "\n\n";

    echo "3. Active Employees WITHOUT Company Association:\n";
    echo $db->query("SELECT COUNT(*) FROM employees e WHERE e.status = 'active' AND NOT EXISTS (SELECT 1 FROM employee_companies ec WHERE e.id = ec.employee_id AND ec.is_active = 1)")->fetchColumn() . "\n\n";

    echo "4. Detailed Regional Stats (Current Logic):\n";
    $query = "
        SELECT cn.name as country, e.status, COUNT(*) as count
        FROM employees e
        LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_active = 1
        LEFT JOIN companies comp ON ec.company_id = comp.id
        LEFT JOIN countries cn ON comp.country_id = cn.id
        GROUP BY cn.name, e.status
    ";
    $stmt = $db->query($query);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
