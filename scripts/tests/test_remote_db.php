<?php
require_once 'config/config.php';
require_once 'config/database.php';

echo "Testing connection to environment: " . ACTIVE_ENVIRONMENT . "\n";

try {
    $db = Database::getInstance()->getConnection();
    if ($db instanceof ProxyPDO) {
        echo "Successfully initialized ProxyPDO (Path C)\n";
    }
    
    $result = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Successfully connected to remote database!\n";
    echo "Total users in remote database: " . $result . "\n";
} catch (Exception $e) {
    echo "Error connecting to remote database: " . $e->getMessage() . "\n";
}
