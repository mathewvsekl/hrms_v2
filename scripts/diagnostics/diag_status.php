<?php
require_once __DIR__ . '/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    echo "Employee Statuses:\n";
    print_r($db->query("SELECT status, COUNT(*) FROM employees GROUP BY status")->fetchAll(PDO::FETCH_ASSOC));
    
    echo "\nUAE Employees Statuses:\n";
    $query = "
        SELECT e.status, COUNT(*) 
        FROM employees e 
        JOIN employee_companies ec ON e.id = ec.employee_id 
        JOIN companies c ON ec.company_id = c.id 
        JOIN countries cn ON c.country_id = cn.id 
        WHERE cn.name = 'United Arab Emirates' AND ec.is_active = 1
        GROUP BY e.status
    ";
    print_r($db->query($query)->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo "Error: " . $e->getMessage(); }
