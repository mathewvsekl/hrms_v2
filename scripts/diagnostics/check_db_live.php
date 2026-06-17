<?php
require_once __DIR__ . '/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $c = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $ec = $db->query("SELECT COUNT(*) FROM employee_companies")->fetchColumn();
    $u = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "DB_COUNT: employees=$c, employee_companies=$ec, users=$u\n";
} catch (Exception $e) { echo "DB_ERROR: " . $e->getMessage(); }
