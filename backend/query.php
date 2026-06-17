<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT name, type, computation_type, is_non_taxable, is_income_tax FROM payroll_components");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
