<?php
require 'backend/config/database.php';
$db = Database::getInstance()->getConnection();

try {
    // Check if base_role_id exists
    $stmt = $db->query("SHOW COLUMNS FROM roles LIKE 'base_role_id'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $db->exec("ALTER TABLE roles ADD COLUMN base_role_id INT NULL");
        $db->exec("ALTER TABLE roles ADD CONSTRAINT fk_roles_base_role FOREIGN KEY (base_role_id) REFERENCES roles(id) ON DELETE SET NULL");
        echo "Added base_role_id column.\n";
    }

    // Update HRManager to HR Manager
    $db->exec("UPDATE roles SET name = 'HR Manager' WHERE name = 'HRManager'");
    
    // Set base_role_id to their own ID for base roles
    $db->exec("UPDATE roles SET base_role_id = id WHERE name IN ('SuperAdmin', 'Admin', 'HR Manager', 'CountryManager', 'HRAssistant', 'Office HRAssistant', 'Employee')");
    
    echo "Roles table upgraded successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
