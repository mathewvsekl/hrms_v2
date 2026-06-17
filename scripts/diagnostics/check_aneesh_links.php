<?php
require_once __DIR__ . '/app/Core/Database.php';

$db = \Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT e.id, e.first_name, e.last_name, ec.company_id, ec.is_primary, ec.is_active, c.name as company_name, c.country_id
    FROM employees e
    LEFT JOIN employee_companies ec ON e.id = ec.employee_id
    LEFT JOIN companies c ON ec.company_id = c.id
    WHERE e.first_name LIKE 'Aneesh%' OR e.last_name LIKE 'Mathew%'
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
