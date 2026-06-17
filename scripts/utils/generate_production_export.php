<?php
require_once 'config/database.php';

// Force file download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="production_export_v1.6.6.sql"');

$db = \Database::getInstance()->getConnection();
$db->exec("SET NAMES utf8mb4");

echo "-- HRMS V2 Production Configuration Export (v1.6.6)\n";
echo "-- Contains complete schema and local configuration settings\n";
echo "-- Excludes dummy transactional data (employees, attendance, appraisals)\n";
echo "-- Includes pure Super Admin credential for initial login\n\n";

echo "SET FOREIGN_KEY_CHECKS = 0;\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "START TRANSACTION;\n\n";

// List of tables to export configuration DATA for (No dummy data)
$configTables = [
    'countries', 'public_holidays', 'companies', 'company_custom_fields',
    'departments', 'designations', 'roles', 'permissions', 'role_permissions',
    'holidays', 'leave_types', 'company_leave_policies', 'global_settings',
    'appraisal_templates', 'template_questions',
    'office_attendance_configs', 'office_weekly_schedules', 'office_attendance_status_definitions'
];

$stmt = $db->query("SHOW TABLES");
$tables = [];
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    echo "-- --------------------------------------------------------\n";
    echo "-- Table structure for table `$table`\n";
    echo "-- --------------------------------------------------------\n\n";
    
    // 1. Export Schema
    echo "DROP TABLE IF EXISTS `$table`;\n";
    $createStmt = $db->query("SHOW CREATE TABLE `$table`");
    $createRow = $createStmt->fetch(PDO::FETCH_NUM);
    echo $createRow[1] . ";\n\n";

    // 2. Export Data (if it's a config table)
    if (in_array($table, $configTables)) {
        $rowsStmt = $db->query("SELECT * FROM `$table`");
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            echo "-- Data for table `$table`\n";
            $inserts = [];
            foreach ($rows as $row) {
                // Escape values
                $values = array_map(function($val) use ($db) {
                    if ($val === null) return 'NULL';
                    return $db->quote($val);
                }, array_values($row));
                
                $inserts[] = "(" . implode(', ', $values) . ")";
            }
            
            // Chunk inserts slightly if large, but simple batch is fine for config
            $chunked = array_chunk($inserts, 100);
            foreach ($chunked as $chunk) {
                echo "INSERT INTO `$table` (`" . implode("`, `", array_keys($rows[0])) . "`) VALUES \n";
                echo implode(",\n", $chunk) . ";\n";
            }
            echo "\n";
        }
    }
}

// 3. Inject only the root Super Admin Data
echo "-- --------------------------------------------------------\n";
echo "-- Injecting ROOT Super Admin Configuration\n";
echo "-- --------------------------------------------------------\n\n";

echo "INSERT INTO `users` (`id`, `employee_id`, `username`, `password_hash`, `is_active`) VALUES\n";
echo "(1, NULL, 'mathew.vsekl@gmail.com', '$2y$12\$jDRfytCw9NO4FaecsDjqK.N5aQyfvothqGvxAK5YDBg3TFdO8LZfy', 1);\n\n";

echo "INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES\n";
echo "(1, 1);\n\n"; // Role 1 is SUPER_ADMIN based on config dump mapping

echo "COMMIT;\n";
echo "SET FOREIGN_KEY_CHECKS = 1;\n";
exit;
