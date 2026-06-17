<?php
define('BASE_PATH', __DIR__);

// Load .env variables
require_once BASE_PATH . '/app/Core/Env.php';
\App\Core\Env::load(BASE_PATH . '/.env');

// Require config
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';

try {
    echo "ACTIVE_ENVIRONMENT constant: " . (defined('ACTIVE_ENVIRONMENT') ? ACTIVE_ENVIRONMENT : 'UNDEFINED') . "\n";
    echo "DB_HOST constant: " . (defined('DB_HOST') ? DB_HOST : 'UNDEFINED') . "\n";
    echo "DB_NAME constant: " . (defined('DB_NAME') ? DB_NAME : 'UNDEFINED') . "\n";

    $db = Database::getInstance()->getConnection();
    
    // Get actual connected database
    $dbNameActual = $db->query("SELECT DATABASE()")->fetchColumn();
    echo "Actual Connected Database: " . $dbNameActual . "\n";

    // Show columns
    $stmt = $db->prepare("DESCRIBE employees");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== EMPLOYEES COLUMNS IN ACTIVE DB ===\n";
    $hasPersonalEmail = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'personal_email') {
            $hasPersonalEmail = true;
        }
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }

    echo "Has personal_email? " . ($hasPersonalEmail ? "YES" : "NO") . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
