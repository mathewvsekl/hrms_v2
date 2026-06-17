<?php
define('BASE_PATH', __DIR__);
define('CONFIG_PATH', __DIR__ . '/config');
require_once __DIR__ . '/app/Core/Env.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Autoloader.php';
require_once __DIR__ . '/app/Core/Database.php';

try {
    $db = \Database::getInstance()->getConnection();
    
    // Add year
    try {
        $db->exec("ALTER TABLE appraisal_cycles ADD COLUMN year INT NULL AFTER name");
        echo "Added year column.\n";
    } catch (\Exception $e) { echo "Year column might exist: " . $e->getMessage() . "\n"; }

    // Add period
    try {
        $db->exec("ALTER TABLE appraisal_cycles ADD COLUMN period VARCHAR(50) NULL AFTER frequency");
        echo "Added period column.\n";
    } catch (\Exception $e) { echo "Period column might exist: " . $e->getMessage() . "\n"; }

    // Add management_deadline
    try {
        $db->exec("ALTER TABLE appraisal_cycles ADD COLUMN management_deadline DATE NULL AFTER hr_deadline");
        echo "Added management_deadline column.\n";
    } catch (\Exception $e) { echo "Management deadline column might exist: " . $e->getMessage() . "\n"; }

    echo "Schema update completed successfully.\n";
} catch (\Exception $e) {
    echo "Error connecting to DB: " . $e->getMessage() . "\n";
}
