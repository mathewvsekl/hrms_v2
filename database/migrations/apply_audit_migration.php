<?php
require_once __DIR__ . '/app/Core/Autoloader.php';
define('BASE_PATH', __DIR__);
\App\Core\Autoloader::register();
require_once __DIR__ . '/config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    $sql = file_get_contents(__DIR__ . '/database/migrations/20260516_create_audit_logs.sql');
    $db->exec($sql);
    echo "Migration applied successfully: audit_logs table created.\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
