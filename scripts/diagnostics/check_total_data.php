<?php
require_once __DIR__ . '/app/Core/Database.php';

$db = \Database::getInstance()->getConnection();
$stmt = $db->query("
    SELECT c.name as country_name, COUNT(e.id) as emp_count
    FROM employees e
    JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
    JOIN companies co ON ec.company_id = co.id
    JOIN countries c ON co.country_id = c.id
    GROUP BY c.name
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
