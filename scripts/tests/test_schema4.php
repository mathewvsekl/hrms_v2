<?php
require 'app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/.env');
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("DESCRIBE salary_structures");
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
