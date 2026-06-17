<?php
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();
$sql1 = "
CREATE TABLE IF NOT EXISTS payroll_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    computation_type VARCHAR(50) NOT NULL DEFAULT 'FIXED',
    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    formula TEXT NULL,
    company_id INT NULL,
    country_id INT NULL,
    is_statutory TINYINT(1) DEFAULT 0,
    is_non_taxable TINYINT(1) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'Active',
    display_in_payslip TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);";

$sql2 = "
CREATE TABLE IF NOT EXISTS employee_salary_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salary_structure_id INT NOT NULL,
    component_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY emp_comp_unique (salary_structure_id, component_id)
);";

$sql3 = "ALTER TABLE payroll_records ADD COLUMN earnings_json JSON NULL;";
$sql4 = "ALTER TABLE payroll_records ADD COLUMN deductions_json JSON NULL;";

try {
    $db->exec($sql1);
    $db->exec($sql2);
    try { $db->exec($sql3); } catch (Exception $e) {}
    try { $db->exec($sql4); } catch (Exception $e) {}
    echo "Migration completed successfully!";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
