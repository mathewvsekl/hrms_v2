<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM users LIMIT 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
