<?php
define('BASE_PATH', __DIR__);
require_once 'config/database.php';
require_once 'app/Core/Autoloader.php';
\App\Core\Autoloader::register();

try {
    $db = Database::getInstance()->getConnection();
    $leaveService = new \App\Services\LeaveService();
    
    echo "Starting generateSystemDraftLeaves...\n";
    $drafts = $leaveService->generateSystemDraftLeaves(1, 1);
    echo "Drafts created: " . $drafts . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
