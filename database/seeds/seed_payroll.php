<?php
require_once 'config/database.php';
try {
    $db = Database::getInstance()->getConnection();
    
    // Seed PAYE and NSSF
    $stmt = $db->prepare("
        INSERT INTO payroll_components (name, type, computation_type, value, country_id, is_statutory, status)
        VALUES 
        ('NSSF (5% Employee)', 'DEDUCTION', 'PERCENTAGE', 5.00, 1, 1, 'Active'),
        ('PAYE (Uganda URA)', 'DEDUCTION', 'FORMULA', 0.00, 1, 1, 'Active')
    ");
    $stmt->execute();
    echo "Seed successful!";
} catch (Exception $e) {
    echo "Seed failed: " . $e->getMessage();
}
