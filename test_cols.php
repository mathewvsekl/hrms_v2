<?php
require 'backend/config/database.php';
$pdo = \Database::getInstance()->getConnection();
$stmt = $pdo->query('SHOW COLUMNS FROM companies');
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($cols);
?>
