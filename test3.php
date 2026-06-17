<?php
require 'c:\Users\AneeshMathew\HRMS V2\backend\config\database.php';
$db = Database::getInstance()->getConnection();
$identifier = 'mathew.vsekl@gmail.com';
$stmt = $db->prepare("
            SELECT u.id, u.is_active, u.employee_id, ec.company_id, co.country_id, co.timezone as company_timezone, e.first_name, e.last_name, dg.title as designation 
            FROM users u 
            LEFT JOIN employees e ON u.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            LEFT JOIN companies co ON ec.company_id = co.id
            LEFT JOIN designations dg ON e.designation_id = dg.id
            WHERE u.username = ? OR e.email = ?
            LIMIT 1
");
$stmt->execute([$identifier, $identifier]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
