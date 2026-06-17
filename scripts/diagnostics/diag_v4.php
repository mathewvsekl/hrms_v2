<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "1. Counting all employees:\n";
    $stmt = $db->query("SELECT COUNT(*) FROM employees");
    echo "Count: " . $stmt->fetchColumn() . "\n\n";

    echo "2. Checking roles for User 2 (Aneesh):\n";
    $stmt = $db->prepare("SELECT r.id, r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = 2");
    $stmt->execute();
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n3. Testing Headcount Query (from EmployeeController):\n";
    $companyFilter = " AND ec.company_id IN (1)";
    $query = "
        SELECT e.status as label, COUNT(*) as count
        FROM employees e
        INNER JOIN employee_companies ec ON e.id = ec.employee_id $companyFilter AND ec.is_active = 1
        WHERE 1=1
        GROUP BY e.status
    ";
    $stmt = $db->query($query);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n4. Testing Country Stats Query:\n";
    $queryCountry = "
        SELECT cn.name as label, cn.iso_code as extra, cn.id as id, COUNT(*) as count
        FROM employees e
        INNER JOIN employee_companies ec ON e.id = ec.employee_id $companyFilter AND ec.is_active = 1
        JOIN companies comp ON ec.company_id = comp.id
        JOIN countries cn ON comp.country_id = cn.id
        WHERE 1=1
        GROUP BY cn.name, cn.iso_code, cn.id
    ";
    $stmt = $db->query($queryCountry);
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
