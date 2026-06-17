<?php
/**
 * Utility to fill missing API tokens and roles for all existing users.
 * Ensures we have a full suite of authenticated users.
 */

require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

try {
    // 1. Fetch users with missing or empty api_tokens
    $stmt = $db->query("SELECT id, username FROM users WHERE api_token IS NULL OR api_token = ''");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($users) . " users with missing API tokens.\n";

    $db->beginTransaction();

    foreach ($users as $u) {
        $token = hash('sha256', "perf_token_secret_" . $u['id'] . "_" . time() . "_" . rand(1, 1000));
        $update = $db->prepare("UPDATE users SET api_token = ? WHERE id = ?");
        $update->execute([$token, $u['id']]);
        echo "Assigned token to user: " . $u['username'] . "\n";
    }

    // 2. Fetch users with no roles in user_roles
    $stmt = $db->query("SELECT id, username FROM users WHERE id NOT IN (SELECT DISTINCT user_id FROM user_roles)");
    $rolelessUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found " . count($rolelessUsers) . " users with missing roles.\n";

    foreach ($rolelessUsers as $u) {
        // Assign default Employee role (role_id = 6)
        $roleInsert = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, 6)");
        $roleInsert->execute([$u['id']]);
        echo "Assigned Employee role (ID 6) to user: " . $u['username'] . "\n";
    }

    $db->commit();
    echo "Update completed successfully!\n";

    // Verify final count
    $finalCount = $db->query("
        SELECT COUNT(DISTINCT u.id) 
        FROM users u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE u.api_token IS NOT NULL AND u.api_token != ''
    ")->fetchColumn();
    echo "Total unique authenticated users with roles: $finalCount\n";

} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR during update: " . $e->getMessage() . "\n";
    exit(1);
}
