<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

try {
    $ls = new \App\Services\LeaveService();
    $reqs = $ls->fetchRequests([], [], true);
    if (count($reqs) > 0) {
        print_r($reqs[0]);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
