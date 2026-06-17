<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$queries = [
    "ALTER TABLE payslips ADD COLUMN company_id INT NULL AFTER employee_id",
    "ALTER TABLE salary_advances ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER amount",
    "ALTER TABLE employee_salary_components ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER effective_date",
    "ALTER TABLE salary_structures ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER other_earnings",
    "ALTER TABLE payroll_components ADD COLUMN is_income_tax TINYINT(1) DEFAULT 0 AFTER is_non_taxable",
    "ALTER TABLE payroll_components ADD COLUMN round_off TINYINT(1) DEFAULT 0 AFTER is_income_tax",
    "ALTER TABLE company_leave_policies ADD COLUMN `year` INT(4) NOT NULL DEFAULT 2026 AFTER `company_id`"
];

echo "<h2>Executing Database Migrations</h2>";
echo "<ul>";

foreach ($queries as $query) {
    try {
        $db->exec($query);
        echo "<li style='color: green;'><strong>Success:</strong> {$query}</li>";
    } catch (Exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'Duplicate column name') !== false) {
            echo "<li style='color: gray;'><strong>Skipped (Already exists):</strong> {$query}</li>";
        } else {
            echo "<li style='color: red;'><strong>Error:</strong> {$msg} (Query: {$query})</li>";
        }
    }
}

echo "</ul>";
echo "<p><strong>Migration script finished.</strong></p>";
