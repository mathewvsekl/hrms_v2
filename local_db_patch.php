<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=hrms_v2', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$queries = [
    "ALTER TABLE employee_companies ADD COLUMN include_in_payroll TINYINT(1) DEFAULT 0 AFTER is_primary",
    "ALTER TABLE payroll_records ADD COLUMN company_id INT NULL DEFAULT NULL AFTER employee_id",
    "ALTER TABLE payroll_records ADD COLUMN reporting_currency VARCHAR(10) DEFAULT 'AED' AFTER base_salary",
    "ALTER TABLE payroll_records ADD COLUMN exchange_rate DECIMAL(10,4) DEFAULT 1.0000 AFTER reporting_currency",
    "ALTER TABLE salary_advances ADD COLUMN attachment VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE salary_advances ADD COLUMN manager_comment TEXT DEFAULT NULL"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "Executed: $sql\n";
    } catch (Exception $e) {
        echo "Error or already exists: " . $e->getMessage() . "\n";
    }
}
echo "All done!\n";
