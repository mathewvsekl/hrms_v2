<?php
require_once __DIR__ . '/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch()) {
        echo current($row) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
