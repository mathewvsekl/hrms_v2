<?php
$host = '127.0.0.1';
$db   = 'hrms_v2';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("ALTER TABLE tax_slabs ADD COLUMN personal_relief DECIMAL(10,2) DEFAULT 0;");
    echo "Column added successfully.\n";
} catch (\PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
         echo "Column already exists.\n";
    } else {
         throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}
