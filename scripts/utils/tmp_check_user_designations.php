<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT u.username, e.first_name, e.last_name, dg.title as designation 
        FROM users u 
        LEFT JOIN employees e ON u.employee_id = e.id
        LEFT JOIN designations dg ON e.designation_id = dg.id
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($users, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
