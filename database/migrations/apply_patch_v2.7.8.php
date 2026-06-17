<?php
/**
 * Internal patch applier for v2.7.8
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $sql = file_get_contents(__DIR__ . '/release/migration_patch_v2.7.8.sql');
    
    // Split by semicolon for execution
    $queries = explode(';', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if ($query) {
            $db->exec($query);
        }
    }
    
    echo "PATCH_APPLIED_SUCCESSFULLY";
} catch (Exception $e) {
    echo "PATCH_ERROR: " . $e->getMessage();
}
