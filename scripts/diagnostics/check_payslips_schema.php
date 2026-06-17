<?php
require_once __DIR__ . '/app/Core/Database.php';

try {
    $db = \Database::getInstance()->getConnection();
    $stmt = $db->query("DESCRIBE payslips");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo $column['Field'] . " - " . $column['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
