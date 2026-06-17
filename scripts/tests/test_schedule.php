<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

try {
    $db = \Database::getInstance()->getConnection();
    
    $stmt = $db->query("SELECT * FROM office_weekly_schedules WHERE company_id = 1");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
