<?php
require_once 'c:/Users/AneeshMathew/HRMS V2/backend/config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if table exists
    $stmt = $db->query("SHOW TABLES LIKE 'salary_advances'");
    if ($stmt->rowCount() == 0) {
        echo "Table salary_advances does not exist. Creating...\n";
        $db->exec("
            CREATE TABLE salary_advances (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency_code VARCHAR(3) DEFAULT 'UGX',
                date_requested DATE NOT NULL,
                status VARCHAR(20) DEFAULT 'Pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (employee_id) REFERENCES employees(id)
            )
        ");
        echo "Table created.\n";
    } else {
        echo "Table salary_advances already exists.\n";
    }

    // Now check if advances are fetched properly for company 1
    $companyId = 1;
    $stmt = $db->prepare("
        SELECT sa.*, e.first_name, e.last_name, e.employee_code 
        FROM salary_advances sa 
        JOIN employees e ON sa.employee_id = e.id 
        JOIN employee_companies ec ON e.id = ec.employee_id
        WHERE ec.company_id = ? AND ec.is_primary = 1
        ORDER BY sa.created_at DESC
    ");
    $stmt->execute([$companyId]);
    $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($advances) . " advances for company 1.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
