<?php
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $sql = file_get_contents('seed_rbac.sql');
    
    // Split by semicolon but be careful with subqueries etc (manual split is safer for simple scripts)
    // However, PDO exec doesn't support multiple statements in one call easily depending on driver.
    // I'll use the raw PDO connection to execute the whole block if possible, or split.
    
    $db->exec($sql);
    echo "RBAC Configuration Applied Successfully!\n";
    
    // Verification
    $stmt = $db->query("SELECT name FROM roles");
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Current Roles: " . implode(", ", $roles) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
