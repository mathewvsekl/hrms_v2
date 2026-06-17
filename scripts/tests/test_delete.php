<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();
try {
    $stmtDel = $db->prepare("DELETE FROM employee_salary_components WHERE employee_id = ? AND effective_date = ?");
    $stmtDel->execute([1, '2026-05-01']);
    echo "Delete worked!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
