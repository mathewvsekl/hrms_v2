<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SHOW CREATE TABLE salary_advances");
print_r($stmt->fetch());
