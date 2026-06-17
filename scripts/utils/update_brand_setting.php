<?php
require_once __DIR__ . '/config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    // 1. Update company_name setting
    $stmt = $db->prepare("UPDATE global_settings SET setting_value = 'Avantgarde HRMS' WHERE setting_key = 'company_name'");
    $stmt->execute();
    echo "company_name updated successfully.\n";

    // 2. Fetch all for confirmation
    $stmt = $db->query("SELECT * FROM global_settings WHERE setting_key = 'company_name'");
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "New company_name: " . ($setting['setting_value'] ?? 'NOT FOUND') . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
