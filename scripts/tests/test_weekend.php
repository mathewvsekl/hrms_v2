<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

try {
    $db = \Database::getInstance()->getConnection();
    $ls = new \App\Services\LeaveService();
    
    // Check 11/04/2026 and 12/04/2026
    $reflection = new ReflectionClass($ls);
    $method = $reflection->getMethod('isWeekendOrHoliday');
    
    $companyId = 1; // Assuming primary company
    
    echo "Company ID: $companyId\n";
    $d11 = $method->invoke($ls, $companyId, '2026-04-11');
    $d12 = $method->invoke($ls, $companyId, '2026-04-12');
    
    echo "2026-04-11 is weekend: " . ($d11 ? 'yes' : 'no') . "\n";
    echo "2026-04-12 is weekend: " . ($d12 ? 'yes' : 'no') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
