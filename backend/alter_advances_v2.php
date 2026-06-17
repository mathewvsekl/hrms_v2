<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();

try {
    $db->exec("ALTER TABLE salary_advances ADD COLUMN attachment VARCHAR(255) DEFAULT NULL");
    echo "Added attachment column.\n";
} catch (Exception $e) {
    echo "Error adding attachment: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE salary_advances ADD COLUMN manager_comment TEXT DEFAULT NULL");
    echo "Added manager_comment column.\n";
} catch (Exception $e) {
    echo "Error adding manager_comment: " . $e->getMessage() . "\n";
}
