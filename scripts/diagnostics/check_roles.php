<?php
require 'app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/.env');
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT u.id, u.username, r.name as role FROM users u LEFT JOIN user_roles ur ON u.id = ur.user_id LEFT JOIN roles r ON ur.role_id = r.id");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
