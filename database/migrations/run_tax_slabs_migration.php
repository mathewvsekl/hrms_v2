<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS tax_slabs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_id INT NOT NULL,
    effective_date DATE NULL,
    min_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    max_amount DECIMAL(15,2) NULL,
    tax_type VARCHAR(50) NOT NULL DEFAULT 'PERCENTAGE',
    percentage DECIMAL(5,2) NULL,
    fixed_amount DECIMAL(15,2) NULL,
    company_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (component_id) REFERENCES payroll_components(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $db->exec($sql);
    echo "Tax slabs table created successfully!\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
