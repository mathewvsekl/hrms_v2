<?php
require 'app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/.env');
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$tables = ['departments', 'designations', 'companies'];
$schema = [];
foreach ($tables as $t) {
    try {
        $stmt = $db->query("DESCRIBE $t");
        $schema[$t] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
}
echo json_encode($schema);
