<?php
require_once 'config/database.php';

try {
    $db = \Database::getInstance()->getConnection();

    // Mapping of dynamic prefixes
    $prefixes = [
        'PR_SYS' => 'PR_',
        'AB_SYS' => 'AB_',
        'WH_SYS' => 'WH_',
        'OS_SYS' => 'OS_',
        'TR_SYS' => 'TR_',
        'WE_SYS' => 'WE_',
        'PH_SYS' => 'PH_',
        'HO_SYS' => 'HO_'
    ];

    // Get all companies and their ISO codes
    $stmt = $db->query("SELECT comp.id, c.iso_code FROM companies comp JOIN countries c ON comp.country_id = c.id");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Starting ISO-specific migration...\n\n";

    foreach ($companies as $comp) {
        $cid = $comp['id'];
        $iso = $comp['iso_code'];

        foreach ($prefixes as $old => $prefix) {
            $new = $prefix . $iso;

            // 1. Update office_attendance_configs
            $stmtConfigs = $db->prepare("UPDATE office_attendance_configs SET status = ? WHERE status = ? AND company_id = ?");
            $stmtConfigs->execute([$new, $old, $cid]);

            // 2. Update attendance_logs
            $stmtLogs = $db->prepare("
                UPDATE attendance_logs 
                SET status = ? 
                WHERE status = ? 
                AND employee_id IN (SELECT employee_id FROM employee_companies WHERE company_id = ?)
            ");
            $stmtLogs->execute([$new, $old, $cid]);

            // 3. Update office_weekly_schedules
            $stmtSchedules = $db->prepare("UPDATE office_weekly_schedules SET status = ? WHERE status = ? AND company_id = ?");
            $stmtSchedules->execute([$new, $old, $cid]);

            if ($stmtLogs->rowCount() > 0 || $stmtConfigs->rowCount() > 0 || $stmtSchedules->rowCount() > 0) {
                echo "Company $cid ($iso): Migrated '$old' -> '$new'.\n";
            }
        }
    }

    echo "\nAll Universal _SYS Defaults have been successfully migrated to Country-Specific ISO Codes.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
}
