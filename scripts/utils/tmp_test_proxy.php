<?php
require_once __DIR__ . '/config/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    if ($db instanceof ProxyPDO) {
        echo "Using ProxyPDO.\n";
    } else {
        echo "Using direct PDO.\n";
    }
    
    $stmt = $db->query("SELECT 1 as test_col");
    $result = $stmt->fetch();
    
    echo "Connection successful. Result: " . print_r($result, true) . "\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
