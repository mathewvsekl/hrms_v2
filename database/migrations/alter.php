<?php
require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();
try {
    $stmt = $db->prepare("UPDATE leave_requests SET origin = 'system' WHERE remarks LIKE '%System-Generated%'");
    $stmt->execute();
    echo "Updated " . $stmt->rowCount() . " records to origin = 'system'.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
