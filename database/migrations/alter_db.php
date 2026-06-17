<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$db->exec("ALTER TABLE tax_slabs ADD COLUMN tax_type ENUM('PERCENTAGE', 'FIXED') DEFAULT 'PERCENTAGE' AFTER max_amount, ADD COLUMN fixed_amount DECIMAL(15,2) DEFAULT 0.00 AFTER percentage");
echo "Done";
