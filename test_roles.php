<?php
require 'backend/vendor/autoload.php';
require 'backend/config/database.php';
$pdo = \Database::getInstance()->getConnection();
$stmt = $pdo->query('SELECT name FROM roles');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
?>
