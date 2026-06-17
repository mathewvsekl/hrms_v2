<?php
require_once 'app/Core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "--- Roles Table ---\n";
    $roles = $db->query("SELECT id, name FROM roles")->fetchAll(PDO::FETCH_ASSOC);
    print_r($roles);
    
    echo "\n--- Users Table (Top 5) ---\n";
    $users = $db->query("SELECT u.id, u.username, e.first_name, e.last_name FROM users u JOIN employees e ON u.employee_id = e.id LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($users);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
