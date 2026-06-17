<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();
try {
    $stmt = $db->query('DESCRIBE salary_advances');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
