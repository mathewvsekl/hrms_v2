<?php
require 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();

    echo "--- Countries Schema ---\n";
    $stmt = $db->query("DESCRIBE countries");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n--- First 5 Countries Data ---\n";
    $stmt = $db->query("SELECT * FROM countries LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n--- Attendance Definitions Suffixes Check ---\n";
    $stmt = $db->query("SELECT status_key, COUNT(*) FROM office_attendance_status_definitions GROUP BY status_key LIMIT 10");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
