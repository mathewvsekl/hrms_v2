<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();
echo "Total Active: " . $db->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn() . "\n";
echo "Total Global Admins: " . $db->query("SELECT COUNT(*) FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE r.name IN ('SUPERADMIN', 'SUPER_ADMIN')")->fetchColumn() . "\n";
