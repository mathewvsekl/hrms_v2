<?php
define('BASE_PATH', __DIR__);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Controller.php';
require_once __DIR__ . '/app/Controllers/ExportController.php';

// Mock session/auth for testing
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = 1;
// Mock that user is a SuperAdmin to bypass scope checks for now
// Or mock the specific company scope
$_SESSION['scope_company_id'] = 1;

// Manual DB check to see if we can connect
try {
    $db = \Database::getInstance()->getConnection();
    echo "DB Connected.\n";
} catch (Exception $e) {
    echo "DB Connection Failed: " . $e->getMessage() . "\n";
    exit;
}

$controller = new class extends \App\Controllers\ExportController {
    // Override jsonResponse to not exit for testing
    protected function jsonResponse($data, $httpStatus = 200, $message = '') {
        echo "JSON RESPONSE ($httpStatus): $message\n";
        echo json_encode($data) . "\n";
    }
};

// Mock GET params
$_GET['company_id'] = 1;
$_GET['start_date'] = '2026-01-01';
$_GET['end_date'] = '2026-03-28';
$_GET['modules'] = ['attendance'];

try {
    echo "Starting Export Test...\n";
    $controller->exportData();
} catch (Throwable $e) {
    echo "UNCAUGHT ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "TRACE: " . $e->getTraceAsString() . "\n";
}
