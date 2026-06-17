<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Helpers/NotificationHelper.php';
require_once __DIR__ . '/app/Helpers/ApprovalHelper.php';
require_once __DIR__ . '/app/Helpers/DateHelper.php';
require_once __DIR__ . '/app/Services/LeaveService.php';

try {
    $ls = new \App\Services\LeaveService();
    $count = $ls->generateSystemDraftLeaves();
    
    echo "Successfully generated $count drafts.\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
