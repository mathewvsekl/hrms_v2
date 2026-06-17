<?php
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

$pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
$stmt = $pdo->query("SELECT e.first_name, e.last_name, r.name as role FROM employees e JOIN users u ON u.employee_id = e.id JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON ur.role_id = r.id WHERE e.first_name LIKE '%Atim%' OR e.last_name LIKE '%Atim%';");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
