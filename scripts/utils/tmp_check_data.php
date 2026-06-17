<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM employees LIMIT 1");
    echo "Employee: " . json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
    $stmt = $db->query("SELECT * FROM designations LIMIT 1");
    echo "Designation: " . json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo $e->getMessage();
}
