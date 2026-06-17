<?php
require 'config/database.php';
$db = Database::getInstance()->getConnection();

$sql = "CREATE TABLE IF NOT EXISTS tax_slabs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NULL,
    min_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    max_amount DECIMAL(15,2) NULL,
    base_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    excess_over DECIMAL(15,2) NOT NULL DEFAULT 0,
    additional_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    additional_excess_over DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
)";
$db->exec($sql);

// Check if any slabs exist, if not, insert the default Uganda slabs
$stmt = $db->query("SELECT COUNT(*) FROM tax_slabs");
if ($stmt->fetchColumn() == 0) {
    $insert = $db->prepare("INSERT INTO tax_slabs (min_amount, max_amount, base_amount, percentage, excess_over, additional_percentage, additional_excess_over) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    // Slab 1: Up to 235k
    $insert->execute([0, 235000, 0, 0, 0, 0, 0]);
    // Slab 2: 235,001 to 335,000
    $insert->execute([235001, 335000, 0, 10, 235000, 0, 0]);
    // Slab 3: 335,001 to 410,000
    $insert->execute([335001, 410000, 10000, 20, 335000, 0, 0]);
    // Slab 4: 410,001 to 10M
    $insert->execute([410001, 10000000, 25000, 30, 410000, 0, 0]);
    // Slab 5: Over 10M
    $insert->execute([10000001, null, 25000, 30, 410000, 10, 10000000]);
}

echo "tax_slabs created and seeded.\n";
