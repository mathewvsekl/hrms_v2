<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance();
    $sql = file_get_contents(__DIR__ . '/patches/patch_v3.0.1.sql');
    
    if($sql) {
        $db->exec($sql);
        echo "Patch v3.0.1 applied successfully.\n";
    } else {
        echo "Patch file not found.\n";
    }
} catch (PDOException $e) {
    echo "Error applying patch: " . $e->getMessage() . "\n";
}
