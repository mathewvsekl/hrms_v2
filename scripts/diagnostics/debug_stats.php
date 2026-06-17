<?php
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Controller.php';
require_once __DIR__ . '/app/Controllers/EmployeeController.php';

// Mock session for Aneesh Mathew (User 2, Employee 1)
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['scope_employee_id'] = 1;
$_SESSION['scope_company_id'] = 1;
$_SESSION['scope_country_id'] = 1;
$_SESSION['user_role'] = 'COUNTRY MANAGER';
$_SESSION['associated_company_ids'] = [1];

$empController = new \App\Controllers\EmployeeController();
$empController->setInternal();
$stats = $empController->getDashboardStats()->getData();

echo "Dashboard Stats for Aneesh:\n";
print_r($stats);
