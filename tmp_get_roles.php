<?php
require 'backend/config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query('SELECT id, name FROM roles');
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($roles, JSON_PRETTY_PRINT);
