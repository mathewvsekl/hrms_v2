<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Autoloader.php';
\Core\Autoloader::register();

try {
    $ls = new \App\Services\LeaveService();
    $count = $ls->generateSystemDraftLeaves();
    echo "Generated: $count\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
