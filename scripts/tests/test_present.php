<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

try {
    $db = \Database::getInstance()->getConnection();
    
    // delete drafts
    $db->query("DELETE FROM leave_requests WHERE status = 'draft'");
    
    $ls = new \App\Services\LeaveService();
    $count = $ls->generateSystemDraftLeaves();
    
    echo "Generated $count drafts.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
