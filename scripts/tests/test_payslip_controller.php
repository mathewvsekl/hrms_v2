<?php
require_once __DIR__ . '/app/Core/Controller.php';
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Controllers/PayslipController.php';

try {
    $controller = new \App\Controllers\PayslipController();
    $controller->getEmployeePayslips();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
