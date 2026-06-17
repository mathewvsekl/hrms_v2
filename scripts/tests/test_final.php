<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

try {
    $db = \Database::getInstance()->getConnection();
    
    // Delete all existing draft leaves so we can regenerate them cleanly
    $db->exec("DELETE FROM leave_requests WHERE status = 'draft'");
    echo "Deleted existing drafts.\n";

    // Re-run the draft generation logic
    $ls = new \App\Services\LeaveService();
    $count = $ls->generateSystemDraftLeaves();
    echo "Regenerated: $count draft groups.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
