<?php
require 'backend/config/database.php';
$pdo = \Database::getInstance()->getConnection();
$pdo->exec("ALTER TABLE companies MODIFY COLUMN logo_url LONGTEXT NULL");
echo "Column modified successfully.";
?>
