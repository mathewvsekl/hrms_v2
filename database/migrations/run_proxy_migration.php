<?php
define('DB_HOST', 'proxy');
require_once __DIR__ . '/config/database.php';
$db = Database::getInstance()->getConnection();

try {
    $db->query("DROP TABLE IF EXISTS employee_salary_components");
    echo "Table dropped on PROXY.\n";
} catch (Exception $e) {
    echo "Drop failed: " . $e->getMessage() . "\n";
}

$sql2 = "
CREATE TABLE IF NOT EXISTS employee_salary_components (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    component_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    effective_date DATE NOT NULL,
    currency_code VARCHAR(3) NOT NULL DEFAULT 'UGX',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (component_id) REFERENCES payroll_components(id) ON DELETE CASCADE,
    UNIQUE KEY emp_comp_date_unique (employee_id, component_id, effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
try {
    $db->query($sql2);
    echo "Table recreated successfully on PROXY!\n";
} catch (Exception $e2) {
    echo "Recreation failed: " . $e2->getMessage() . "\n";
}
