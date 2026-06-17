<?php
// scripts/generate_daily_leaves.php

// Define explicit base paths
define('BASE_PATH', dirname(__DIR__));

// Load .env variables
require_once BASE_PATH . '/app/Core/Env.php';
\App\Core\Env::load(BASE_PATH . '/.env');

// Initialize Autoloader & Database
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

echo "Starting daily leave draft generation...\n";

try {
    $leaveService = new \App\Services\LeaveService();
    // Generates for all companies/employees
    $draftsCreated = $leaveService->generateSystemDraftLeaves(); 
    
    echo "Success: $draftsCreated draft leave requests were generated.\n";
} catch (\Exception $e) {
    echo "Error generating leaves: " . $e->getMessage() . "\n";
    exit(1);
}
