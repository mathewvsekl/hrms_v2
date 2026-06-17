<?php
require 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';
$db = Database::getInstance()->getConnection();

// Let's get the salary components for employee 1
$stmt = $db->query("SELECT * FROM employee_salary_components WHERE employee_id = 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
