<?php
require 'app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/.env');
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN basic_pay > 0 THEN 1 ELSE 0 END) as with_pay FROM employees WHERE status = 'Active'");
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
