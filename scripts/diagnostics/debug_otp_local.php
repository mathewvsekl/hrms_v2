<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "--- HRMS V2 Diagnostic Tool (Local DB Test) ---\n";

$environments = require 'config/environments.php';
$config = $environments['local'];

echo "Testing Database Host: " . $config['db_host'] . "\n";
echo "Database Name: " . $config['db_name'] . "\n";

try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset={$config['db_charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    echo "Attempting Connection...\n";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
    echo "Connection Successful!\n";

    echo "\nListing users table count...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    echo "User count: " . $stmt->fetchColumn() . "\n";

} catch (Throwable $e) {
    echo "\n!!! CONNECTION FAILED !!!\n";
    echo $e->getMessage() . "\n";
}
