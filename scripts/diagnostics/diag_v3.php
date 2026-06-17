<?php
// Simple DB connection for diagnostics
try {
    $db = new PDO('mysql:host=localhost;dbname=hrms_v2', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "--- ROLES ---\n";
    $roles = $db->query("SELECT id, name FROM roles")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roles as $role) {
        echo "ID: {$role['id']} | Name: {$role['name']}\n";
    }

    echo "\n--- USERS (Top 10) ---\n";
    $users = $db->query("
        SELECT u.id, u.username, e.first_name, e.last_name 
        FROM users u 
        JOIN employees e ON u.employee_id = e.id 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "ID: {$user['id']} | Username: {$user['username']} | Name: {$user['first_name']} {$user['last_name']}\n";
    }

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
?>
