<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getInstance()->getConnection();

// Let's fix the base_role_id for Office HR Assistant
$db->exec("UPDATE roles SET base_role_id = 5 WHERE id = 59");

// Let's also ensure any other copied roles that have base_role_id = id but are > 6, get fixed if their name implies it
$db->exec("UPDATE roles SET base_role_id = 5 WHERE name LIKE '%HR%Assistant%' AND id > 6");
$db->exec("UPDATE roles SET base_role_id = 3 WHERE name LIKE '%HR%Manager%' AND id > 6");
$db->exec("UPDATE roles SET base_role_id = 4 WHERE name LIKE '%Country%Manager%' AND id > 6");

echo "DB Fixed\n";
