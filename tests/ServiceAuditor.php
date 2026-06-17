<?php

require_once __DIR__ . '/../app/Core/Autoloader.php';
define('BASE_PATH', realpath(__DIR__ . '/../'));
\App\Core\Autoloader::register();
require_once __DIR__ . '/../config/database.php';

use App\Services\EmployeeService;
use App\Services\AssetService;

echo "HRMS V2 Service Logic Auditor\n";
echo "=============================\n\n";

$employeeService = new EmployeeService();
$assetService = new AssetService();

// 1. Test Hierarchy Cycle Detection
echo "Testing Hierarchy Cycle Detection: ";
$isCycle = $employeeService->checkHierarchyCycle(1, 1); // Self-reference
if ($isCycle) {
    echo "PASS (Detected Self-Reference)\n";
} else {
    echo "FAIL (Missed Self-Reference)\n";
}

// 2. Test Asset Availability Logic (Mock-like check)
echo "Testing Asset Availability Logic: ";
try {
    // We expect this to fail if asset 999 doesn't exist or is not available
    $assetService->allocateAsset(9999, 1, []);
    echo "FAIL (Allowed allocation of non-existent asset)\n";
} catch (\Exception $e) {
    echo "PASS (Prevented allocation: " . $e->getMessage() . ")\n";
}

echo "\nAuditor Complete.\n";
