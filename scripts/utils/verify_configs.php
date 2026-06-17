<?php
// Verification script for updated getOfficeConfigs endpoint
require_once 'c:\Users\AneeshMathew\HRMS V2\config\database.php';
require_once 'c:\Users\AneeshMathew\HRMS V2\app\Core\Controller.php';
require_once 'c:\Users\AneeshMathew\HRMS V2\app\Controllers\AttendanceController.php';

use App\Controllers\AttendanceController;

$controller = new AttendanceController();

echo "--- Testing getOfficeConfigs for Company 1 ---\n";
$_GET['company_id'] = 1;
$response = $controller->getOfficeConfigs($_GET);
$data = json_decode($response->getContent(), true);

if (isset($data['data'])) {
    echo "Found " . count($data['data']) . " configurations.\n";
    foreach ($data['data'] as $cfg) {
        echo "Date: {$cfg['config_date']}, Status: {$cfg['status']}, Company: {$cfg['company_name']}\n";
    }
} else {
    echo "Error: " . ($data['message'] ?? 'Unknown error') . "\n";
}

echo "\n--- Testing getOfficeConfigs for Country 1 ---\n";
unset($_GET['company_id']);
$_GET['country_id'] = 1;
$_GET['date'] = date('Y-m-d');
$response = $controller->getOfficeConfigs($_GET);
$data = json_decode($response->getContent(), true);

if (isset($data['data'])) {
    echo "Found " . count($data['data']) . " configurations for today in country 1.\n";
} else {
    echo "Error fetching by country/date.\n";
}
