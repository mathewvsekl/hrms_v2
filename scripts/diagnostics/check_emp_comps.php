<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT esc.*, pc.name, pc.type FROM employee_salary_components esc JOIN payroll_components pc ON esc.component_id = pc.id");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
