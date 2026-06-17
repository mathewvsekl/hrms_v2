<?php
require_once __DIR__ . '/../config/database.php'; // Path might differ depending on environment

try {
    // We adjust the path just in case we are run from public or terminal directly
    // This assumes standard Database::getInstance() is available
    if (file_exists(__DIR__ . '/../../backend/config/database.php')) {
        require_once __DIR__ . '/../../backend/config/database.php';
    }

    $db = Database::getInstance();
    $sql = file_get_contents(__DIR__ . '/patches/patch_v3.0.8.sql');
    
    if($sql) {
        $db->exec($sql);
        echo "Patch v3.0.8 applied successfully.\n";
    } else {
        echo "Patch file not found.\n";
    }
} catch (PDOException $e) {
    echo "Error applying patch: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
