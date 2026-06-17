<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();

$db->exec("DROP TABLE IF EXISTS tax_slabs");

$sql = "CREATE TABLE tax_slabs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component_id INT NULL,
    company_id INT NULL,
    min_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    max_amount DECIMAL(15,2) NULL,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES payroll_components(id) ON DELETE CASCADE
)";
$db->exec($sql);

$insert = $db->prepare("INSERT INTO tax_slabs (min_amount, max_amount, percentage) VALUES (?, ?, ?)");

// Slab 1: Up to 235k
$insert->execute([0, 235000, 0]);
// Slab 2: 235,001 to 335,000
$insert->execute([235000, 335000, 10]);
// Slab 3: 335,001 to 410,000
$insert->execute([335000, 410000, 20]);
// Slab 4: 410,001 to 10M
$insert->execute([410000, 10000000, 30]);
// Slab 5: Over 10M
$insert->execute([10000000, null, 40]);

echo "tax_slabs recreated and seeded with standard marginal format.\n";
