<?php
require __DIR__.'/../app/Core/Database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('DESCRIBE salary_advances');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
