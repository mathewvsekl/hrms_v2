<?php
define('BASE_PATH', __DIR__);

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $dsn = "mysql:host={$host};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    echo "Connecting to MySQL server at {$host}...\n";
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // List all databases
    $dbs = $pdo->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Databases found: " . implode(", ", $dbs) . "\n\n";

    foreach ($dbs as $dbName) {
        if (in_array($dbName, ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
            continue;
        }

        try {
            $pdo->exec("USE `$dbName`");
            
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('employees', $tables)) {
                echo "Database '{$dbName}' has 'employees' table.\n";
                
                // Check if personal_email exists
                $stmt = $pdo->query("SHOW COLUMNS FROM `employees` LIKE 'personal_email'");
                $hasEmail = $stmt->fetch();
                if (!$hasEmail) {
                    echo "  -> Adding 'personal_email' column...\n";
                    $pdo->exec("ALTER TABLE `employees` ADD COLUMN `personal_email` VARCHAR(100) NULL AFTER `email`");
                    echo "  -> Added successfully.\n";
                } else {
                    echo "  -> 'personal_email' already exists.\n";
                }
                
                // Check if personal_phone exists
                $stmt = $pdo->query("SHOW COLUMNS FROM `employees` LIKE 'personal_phone'");
                $hasPhone = $stmt->fetch();
                if (!$hasPhone) {
                    echo "  -> Adding 'personal_phone' column...\n";
                    $pdo->exec("ALTER TABLE `employees` ADD COLUMN `personal_phone` VARCHAR(30) NULL AFTER `phone`");
                    echo "  -> Added successfully.\n";
                } else {
                    echo "  -> 'personal_phone' already exists.\n";
                }
            }
        } catch (Exception $e) {
            echo "Skipping/Error on database '{$dbName}': " . $e->getMessage() . "\n";
        }
    }

    echo "\nRobust migration completed successfully!\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
