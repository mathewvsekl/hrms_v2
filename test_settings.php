<?php
require 'backend/config/database.php';
$db = Database::getInstance()->getConnection();
$res = $db->query("SELECT setting_key, setting_value FROM global_settings")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
