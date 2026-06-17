<?php
require 'backend/config/database.php';
$pdo = \Database::getInstance()->getConnection();
$stmt = $pdo->query("SELECT name FROM roles");
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($roles);
?>
