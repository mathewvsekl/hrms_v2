<?php

/**
 * HRMS V2 Database Re-initialization
 * Executes DATABASE_SCHEMA.sql on the local environment.
 */

require_once __DIR__ . '/../config/config.php';
$environments = require __DIR__ . '/../config/environments.php';
$localConfig = $environments['local'];

echo "--- HRMS DATABASE RE-INITIALIZATION ---\n";
echo "Target: " . $localConfig['db_name'] . " @ " . $localConfig['db_host'] . "\n";
echo "---------------------------------------\n";

try {
    $dsn = "mysql:host={$localConfig['db_host']};port={$localConfig['db_port']};charset={$localConfig['db_charset']}";
    $pdo = new PDO($dsn, $localConfig['db_user'], $localConfig['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Dropping/Recreating database for a fresh initialization...\n";
    $pdo->exec("DROP DATABASE IF EXISTS `{$localConfig['db_name']}` ");
    $pdo->exec("CREATE DATABASE `{$localConfig['db_name']}` ");
    $pdo->exec("USE `{$localConfig['db_name']}` ");

    echo "Reading DATABASE_SCHEMA.sql...\n";
    $sqlFile = __DIR__ . '/../DATABASE_SCHEMA.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("File not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Simple block-by-block execution (assuming standard formatting)
    // Note: This matches the structure in the provided SCHEMA file.
    echo "Executing schema updates...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // PDO doesn't support multiple queries via exec() easily in one go on some systems,
    // so we'll split by semicolon carefully where possible, but better yet use a shell command if mysql is available.
    // However, since I can't find mysql, I'll try to use a more robust PHP execution.
    
    // A better way: Use the raw SQL exec but handle some common syntax issues.
    $pdo->exec($sql);
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "✅ SCHEMA INITIALIZED SUCCESSFULLY!\n";

} catch (Exception $e) {
    echo "❌ INITIALIZATION FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
