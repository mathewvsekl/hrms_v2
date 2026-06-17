<?php
require 'backend/config/database.php';
$pdo = \Database::getInstance()->getConnection();
$stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'logo_url'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
