<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Dumping employee_companies table:\n";
    $stmt = $db->query("SELECT * FROM employee_companies LIMIT 50");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $row) {
        print_r($row);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
