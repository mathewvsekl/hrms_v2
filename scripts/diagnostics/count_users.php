<?php
require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

// Count total users
$userCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
echo "Total Users: $userCount\n";

// Count total employees
$employeeCount = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
echo "Total Employees: $employeeCount\n";

// List user roles
$roles = $db->query("SELECT r.name, COUNT(*) as cnt FROM user_roles ur JOIN roles r ON ur.role_id = r.id GROUP BY r.name")->fetchAll(PDO::FETCH_ASSOC);
echo "\nUser Roles Distribution:\n";
print_r($roles);

// Fetch a few active users with api_token if applicable
$hasApiTokenCol = $db->query("SHOW COLUMNS FROM users LIKE 'api_token'")->fetch();
if ($hasApiTokenCol) {
    echo "\nFound api_token column in users table!\n";
    $users = $db->query("SELECT id, username, api_token FROM users WHERE api_token IS NOT NULL AND api_token != '' LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    print_r($users);
} else {
    echo "\nNo api_token column in users table.\n";
    $users = $db->query("SELECT id, username FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    print_r($users);
}
