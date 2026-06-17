<?php
require_once __DIR__ . '/../config/database.php';

$db = Database::getInstance()->getConnection();

try {
    $db->exec("ALTER TABLE salary_advances ADD COLUMN installment_amount DECIMAL(10,2) NULL AFTER amount");
    $db->exec("ALTER TABLE salary_advances ADD COLUMN deducted_amount DECIMAL(10,2) DEFAULT 0.00 AFTER installment_amount");
    echo "Added installment_amount and deducted_amount to salary_advances.\n";
} catch (Exception $e) {
    echo "Error adding columns: " . $e->getMessage() . "\n";
}

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS salary_advance_installments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            salary_advance_id INT NOT NULL,
            payroll_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Created salary_advance_installments table.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}

try {
    $db->exec("ALTER TABLE salary_advances MODIFY COLUMN status ENUM('Pending','Reviewed','Approved','Rejected','Partially Deducted','Deducted','Cancelled') DEFAULT 'Pending'");
    echo "Modified status enum to include 'Partially Deducted'.\n";
} catch (Exception $e) {
    echo "Error modifying enum: " . $e->getMessage() . "\n";
}

echo "Done.\n";
