<?php
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();
print_r($db->query('SELECT * FROM leave_types')->fetchAll(PDO::FETCH_ASSOC));
