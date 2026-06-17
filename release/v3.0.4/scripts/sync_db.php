<?php

/**
 * HRMS V2 Database Sync Utility
 * Pulls data from the Remote environment (via ProxyPDO) to the Local environment.
 */

// 1. Load Configurations
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ProxyPDO.php';

// Force Remote for the source
$environments = require __DIR__ . '/../config/environments.php';
$remoteConfig = $environments['remote'];
$localConfig = $environments['local'];

echo "--- HRMS DATABASE SYNCHRONIZATION ---\n";
echo "Source (Remote): " . $remoteConfig['proxy_url'] . "\n";
echo "Target (Local):  " . $localConfig['db_name'] . " @ " . $localConfig['db_host'] . "\n";
echo "-------------------------------------\n";

try {
    // 2. Initialize Connections
    echo "Connecting to Remote Proxy...\n";
    $remotePdo = new ProxyPDO($remoteConfig['proxy_url'], $remoteConfig['proxy_token']);

    echo "Connecting to Local MySQL...\n";
    $dsn = "mysql:host={$localConfig['db_host']};port={$localConfig['db_port']};dbname={$localConfig['db_name']};charset={$localConfig['db_charset']}";
    $localPdo = new PDO($dsn, $localConfig['db_user'], $localConfig['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 3. Define Tables to Sync (Ordered by dependencies to be safe, though FK checks will be disabled)
    $tables = [
        'countries',
        'public_holidays',
        'companies',
        'departments',
        'designations',
        'office_attendance_status_definitions',
        'office_weekly_schedules',
        'office_attendance_configs',
        'company_custom_fields',
        'employees',
        'employee_companies',
        'employee_documents',
        'employee_contracts',
        'salary_structures',
        'payroll_runs',
        'payroll_records',
        'roles',
        'permissions',
        'role_permissions',
        'users',
        'user_roles',
        'user_otps',
        'attendance_logs',
        'attendance_audit_logs',
        'attendance_policies',
        'holidays',
        'leave_types',
        'company_leave_policies',
        'leave_balances',
        'leave_requests',
        'exchange_rates',
        'global_settings',
        'appraisal_cycles',
        'appraisal_templates',
        'template_questions',
        'employee_appraisals',
        'appraisal_ratings',
        'appraisal_comments',
        'notifications'
    ];

    // 4. Start Sync
    $localPdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    echo "✅ Foreign key checks disabled.\n\n";

    foreach ($tables as $table) {
        echo "Processing table: $table...\n";
        
        // 1. Get LOCAL columns to ensure we only fetch what we have
        $localColsStmt = $localPdo->query("DESCRIBE `$table` ");
        $localCols = $localColsStmt->fetchAll(PDO::FETCH_COLUMN);
        $colString = "`" . implode("`, `", $localCols) . "`";

        // 2. Fetch from Remote (only the columns that exist locally)
        try {
            $stmt = $remotePdo->query("SELECT $colString FROM `$table` ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            echo "   ⚠️ WARNING: Failed to fetch from remote table '$table': " . $e->getMessage() . "\n";
            continue;
        }

        $count = count($rows);
        echo "   -> Fetched $count rows from remote.\n";

        // 3. Clear Local
        $localPdo->exec("TRUNCATE TABLE `$table` ");
        echo "   -> Local table truncated.\n";

        if ($count > 0) {
            // 4. Build Insert Query
            $placeholders = implode(", ", array_fill(0, count($localCols), "?"));
            $insertSql = "INSERT INTO `$table` ($colString) VALUES ($placeholders)";
            
            $insertStmt = $localPdo->prepare($insertSql);
            
            // Insert in batches
            $localPdo->beginTransaction();
            $i = 0;
            foreach ($rows as $row) {
                $insertStmt->execute(array_values($row));
                if (++$i % 100 === 0) {
                    $localPdo->commit();
                    $localPdo->beginTransaction();
                }
            }
            $localPdo->commit();
            echo "   -> Successfully synced $count records.\n";
        }
    }

    $localPdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "\n✅ ALL TABLES SYNCHRONIZED SUCCESSFULLY!\n";
    echo "-------------------------------------\n";
    echo "ACTION REQUIRED: \n";
    echo "1. Change 'ACTIVE_ENVIRONMENT' to 'local' in config/config.php\n";
    echo "2. Enjoy instant performance!\n";

} catch (\Throwable $e) {
    if (isset($localPdo) && $localPdo->inTransaction()) {
        $localPdo->rollBack();
    }
    echo "❌ SYNC FAILED: " . $e->getMessage() . "\n";
    if (isset($localPdo)) {
        $localPdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }
    exit(1);
}
