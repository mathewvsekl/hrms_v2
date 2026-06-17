<?php
header('Content-Type: text/plain');

try {
    require __DIR__ . '/../config/database.php';
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query('DESCRIBE employee_salary_components');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    print_r($cols);
    echo "\n\nDONE.";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
