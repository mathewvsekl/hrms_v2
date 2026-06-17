<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    // Get last logged in user
    $stmt = $db->query("
        SELECT u.username, u.api_token, e.first_name, e.last_name, e.designation_id, dg.title as designation 
        FROM users u 
        LEFT JOIN employees e ON u.employee_id = e.id 
        LEFT JOIN designations dg ON e.designation_id = dg.id 
        ORDER BY u.last_login_utc DESC LIMIT 5
    ");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
