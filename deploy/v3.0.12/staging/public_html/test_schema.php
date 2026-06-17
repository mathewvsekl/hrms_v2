<?php
require '../config/database.php';
$db = Database::getInstance()->getConnection();
try {
    $db->exec("ALTER TABLE company_leave_policies ADD COLUMN year INT DEFAULT NULL AFTER leave_type_id");
    $db->exec("UPDATE company_leave_policies SET year = YEAR(CURRENT_DATE) WHERE year IS NULL");
    echo "Success: added year column";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
