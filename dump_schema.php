<?php
$host = '127.0.0.1';
$db   = 'hrms_v2';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$tables = [];
$stmt = $pdo->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

$schema = "";
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $schema .= $row[1] . ";\n\n";
}

file_put_contents('database/migrations/DATABASE_SCHEMA.sql', $schema);
echo "Schema dumped successfully to database/migrations/DATABASE_SCHEMA.sql.\n";
