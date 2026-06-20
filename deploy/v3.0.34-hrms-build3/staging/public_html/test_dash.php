<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
define('BASE_PATH', dirname(__DIR__));
define('ROOT_PATH', dirname(__DIR__));
define('STORAGE_PATH', dirname(__DIR__) . '/storage');
define('CONFIG_PATH', dirname(__DIR__) . '/config');
define('PUBLIC_DIR_PATH', dirname(__DIR__) . '/public');
define('TMP_PATH', dirname(__DIR__) . '/tmp');

require_once ROOT_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$_SESSION = [
    'user_id' => 1,
    'user_role' => 'SUPERADMIN',
    'employee_id' => 1
];

try {
    $c = new \App\Controllers\DashboardController();
    $c->setInternal(true);
    $res = $c->getSummary();
    echo "SUCCESS\n";
    print_r($res->getData());
} catch (\Throwable $e) {
    echo "FATAL ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
}
