<?php
session_start();
$_SESSION['user_id'] = 2; // Aneesh
$_SESSION['user_role'] = 'SUPERADMIN';
$_SESSION['scope_company_id'] = 1;

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Controller.php';
require_once __DIR__ . '/app/Controllers/EmployeeController.php';

use App\Controllers\EmployeeController;

try {
    $emp = new EmployeeController();
    $res = $emp->getDashboardStats();
    echo "RESULT:\n";
    print_r($res->getData());
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
