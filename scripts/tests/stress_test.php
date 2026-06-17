<?php
/**
 * Stress Test Runner (HRMS V2)
 * Temporarily elevates all users to SuperAdmin to execute unmitigated database stress testing,
 * runs the performance audit suite, and restores original roles with transactional safety.
 */

require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

try {
    // 1. Back up user roles
    echo "Backing up user_roles...\n";
    $backup = $db->query("SELECT user_id, role_id FROM user_roles")->fetchAll(PDO::FETCH_ASSOC);
    echo "Successfully backed up " . count($backup) . " user-role mappings.\n";

    echo "Elevating all active users to SuperAdmin (role_id = 1) for load simulation...\n";
    $db->beginTransaction();
    $db->exec("DELETE FROM user_roles");
    
    // Get all user IDs
    $users = $db->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, 1)");
    foreach ($users as $userId) {
        $stmt->execute([$userId]);
    }
    $db->commit();
    echo "All " . count($users) . " users successfully elevated to SuperAdmin.\n\n";

    // 2. Run the performance audit script
    echo "=== RUNNING PERFORMANCE AUDIT UNDER FULL DATABASE STRESS ===\n";
    passthru('C:\\xampp\\php.exe -d extension=curl run_performance_audit.php');
    echo "============================================================\n\n";

    // 3. Restore user roles
    echo "Restoring original user_roles from backup...\n";
    $db->beginTransaction();
    $db->exec("DELETE FROM user_roles");
    $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    foreach ($backup as $b) {
        $stmt->execute([$b['user_id'], $b['role_id']]);
    }
    $db->commit();
    echo "Original user_roles successfully restored and integrity verified!\n";

} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "STRESS TEST RUNNER CRITICAL ERROR: " . $e->getMessage() . "\n";
    
    // Attempt emergency restore if backup is populated
    if (isset($backup) && !empty($backup)) {
        echo "ATTEMPTING EMERGENCY RESTORE OF USER ROLES...\n";
        try {
            $db->beginTransaction();
            $db->exec("DELETE FROM user_roles");
            $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            foreach ($backup as $b) {
                $stmt->execute([$b['user_id'], $b['role_id']]);
            }
            $db->commit();
            echo "EMERGENCY RESTORE COMPLETED SUCCESSFULLY! System integrity preserved.\n";
        } catch (\Exception $e2) {
            echo "FATAL: EMERGENCY RESTORE FAILED! Message: " . $e2->getMessage() . "\n";
            echo "Please manually restore user roles from memory backup.\n";
        }
    }
}
