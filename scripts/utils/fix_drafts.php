<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

try {
    $db = \Database::getInstance()->getConnection();
    $ls = new \App\Services\LeaveService();
    $count = $ls->generateSystemDraftLeaves();
    
    echo "Generated $count system draft leaves with company-specific leave_type_id.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
