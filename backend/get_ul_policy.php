<?php
require_once 'config/database.php';
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM company_leave_policies WHERE leave_type_id IN (SELECT id FROM leave_types WHERE code = 'UL')");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
