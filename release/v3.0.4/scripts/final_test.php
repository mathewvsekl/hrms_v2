<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "--- HRMS V2 Final Diagnostic ---\n";
echo "Environment: " . (defined('ACTIVE_ENVIRONMENT') ? ACTIVE_ENVIRONMENT : 'UNDEFINED') . "\n";
echo "URL: " . PROXY_URL . "\n";

try {
    $db = Database::getInstance()->getConnection();
    echo "Connection: SUCCESS\n";

    // Test Employees
    $stmt = $db->query("SELECT COUNT(*) FROM employees");
    $empCount = $stmt->fetchColumn();
    echo "Employees: SUCCESS (Total: $empCount)\n";

    // Test Attendance Today
    $today = date('Y-m-d');
    $stmt = $db->query("SELECT COUNT(*) FROM attendance WHERE date = '$today'");
    $attCount = $stmt->fetchColumn();
    echo "Attendance Today: SUCCESS (Total: $attCount)\n";

    echo "\n--- ALL SYSTEMS OK ---\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "FILE: " . $e->getFile() . " (Line " . $e->getLine() . ")\n";
}
