<?php
$_SERVER['DOCUMENT_ROOT'] = __DIR__;
define('BASE_PATH', __DIR__);

require_once __DIR__ . '/config/database.php';

try {
    $db = \Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT DISTINCT status FROM attendance_logs");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
