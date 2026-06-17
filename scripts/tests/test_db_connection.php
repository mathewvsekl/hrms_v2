<?php
/**
 * HRMS V2 Database Connection Tester
 * Upload this to your public_html folder to verify Hestia CP DB settings.
 */

header('Content-Type: text/plain');

// Adjust these to match your Hestia CP setup
$host = 'localhost';
$db   = 'Admin_anedins_hrms_agi'; 
$user = 'Admin_admin_anedins_hrms_agi'; 
$pass = 'dxzWW?EAYaC9gE|o';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

echo "Testing connection to $db at $host...\n";

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     echo "SUCCESS: Connected to the database successfully!\n";
     
     // Check if tables exist
     $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
     if (empty($tables)) {
         echo "WARNING: Connection worked, but the database is EMPTY. Please import DATABASE_SCHEMA.sql.\n";
     } else {
         echo "Found " . count($tables) . " tables.\n";
     }

} catch (\PDOException $e) {
     echo "ERROR: Connection failed: " . $e->getMessage() . "\n";
     echo "\nChecklist:\n";
     echo "1. Is the DB name and User correct (including Hestia prefix)?\n";
     echo "2. Is the password correct?\n";
     echo "3. Is MariaDB running on the server?\n";
}
