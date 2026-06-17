<?php
require_once __DIR__ . '/backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, name, address, contact_email, contact_phone FROM companies");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($companies);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
