<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("DESCRIBE designations");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo $e->getMessage();
}
