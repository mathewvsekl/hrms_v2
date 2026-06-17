<?php
// Temporary index.php for running migrations
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Silence open_basedir warnings from file_exists
$upOneLevel = dirname(__DIR__);

$configPath = '';
if (@file_exists($upOneLevel . '/config/config.php')) {
    $configPath = $upOneLevel . '/config/config.php';
} elseif (@file_exists($upOneLevel . '/private/config/config.php')) {
    $configPath = $upOneLevel . '/private/config/config.php';
} elseif (@file_exists(__DIR__ . '/config/config.php')) {
    $configPath = __DIR__ . '/config/config.php';
}

if ($configPath) {
    require_once $configPath;
} else {
    die("Cannot find config.php file in private/config or config folders.");
}

$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$db   = defined('DB_NAME') ? DB_NAME : '';
$user = defined('DB_USER') ? DB_USER : '';
$pass = defined('DB_PASS') ? DB_PASS : '';
$port = defined('DB_PORT') ? DB_PORT : '3306';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("DB Connection failed: " . $e->getMessage());
}

$queries = [
    "CREATE TABLE IF NOT EXISTS `salary_advance_installments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `salary_advance_id` int(11) NOT NULL,
      `payroll_id` int(11) NOT NULL,
      `amount` decimal(15,2) NOT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "ALTER TABLE payslips ADD COLUMN company_id INT NULL AFTER employee_id",
    "ALTER TABLE salary_advances ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER amount",
    "ALTER TABLE employee_salary_components ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER effective_date",
    "ALTER TABLE salary_structures ADD COLUMN currency_code VARCHAR(3) DEFAULT 'UGX' AFTER other_earnings",
    "ALTER TABLE payroll_components ADD COLUMN is_income_tax TINYINT(1) DEFAULT 0 AFTER is_non_taxable",
    "ALTER TABLE payroll_components ADD COLUMN round_off TINYINT(1) DEFAULT 0 AFTER is_income_tax",
    "ALTER TABLE company_leave_policies ADD COLUMN `year` INT(4) NOT NULL DEFAULT 2026 AFTER `company_id`",
    "ALTER TABLE salary_advances ADD COLUMN installment_amount DECIMAL(15,2) NULL AFTER amount",
    "ALTER TABLE salary_advances ADD COLUMN deduction_start_date DATE NULL AFTER installment_amount",
    "ALTER TABLE salary_advance_installments ADD COLUMN deduction_date DATE NULL AFTER amount",
    "ALTER TABLE salary_advance_installments ADD COLUMN remaining_balance DECIMAL(15,2) NULL AFTER deduction_date"
];

echo "<h2>Executing Database Migrations</h2><ul>";
foreach ($queries as $query) {
    try {
        $pdo->exec($query);
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
echo "</ul><p><strong>Migration script finished. Please restore your original index.php file!</strong></p>";
