<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->query("
SELECT esc.id, esc.currency_code, pc.company_id 
FROM employee_salary_components esc 
JOIN payroll_components pc ON esc.component_id = pc.id 
WHERE esc.employee_id = 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
