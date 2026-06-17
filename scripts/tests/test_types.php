<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, name FROM leave_types");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    $stmt2 = $db->query("SELECT leave_type_id FROM company_leave_policies WHERE company_id = 1");
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
