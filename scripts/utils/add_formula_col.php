<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$db->exec("ALTER TABLE payroll_components ADD COLUMN formula TEXT NULL DEFAULT NULL AFTER value");
echo "Added formula column to payroll_components\n";
