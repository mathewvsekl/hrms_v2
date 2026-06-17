<?php
/**
 * Seeding Script for Performance Testing (HRMS V2)
 * Ensures at least 55 active users with valid API tokens exist in the database.
 */

require_once 'config/database.php';
$db = \Database::getInstance()->getConnection();

try {
    // 1. Get current count
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    $currentCount = (int)$stmt->fetchColumn();
    echo "Current user count: $currentCount\n";

    if ($currentCount >= 55) {
        echo "We already have $currentCount users, no seeding necessary for 50-user load simulation.\n";
        exit;
    }

    $needed = 55 - $currentCount;
    echo "Seeding $needed new employees and users...\n";

    // Get a valid company and department
    $companyId = $db->query("SELECT id FROM companies LIMIT 1")->fetchColumn();
    $departmentId = $db->query("SELECT id FROM departments LIMIT 1")->fetchColumn();
    $designationId = $db->query("SELECT id FROM designations LIMIT 1")->fetchColumn();

    if (!$companyId || !$departmentId || !$designationId) {
        throw new Exception("Prerequisites missing: companies, departments, or designations table is empty.");
    }

    $db->beginTransaction();

    for ($i = 1; $i <= $needed; $i++) {
        $empCode = "PERF_EMP_" . str_pad((string)($currentCount + $i), 4, "0", STR_PAD_LEFT);
        $username = "perf_user_" . ($currentCount + $i) . "@perf-testing.com";
        $token = hash('sha256', "perf_token_secret_" . ($currentCount + $i) . "_" . time());

        // Insert employee
        $empStmt = $db->prepare("
            INSERT INTO employees (
                employee_code, department_id, designation_id, first_name, last_name, email, hire_date, status
            ) VALUES (?, ?, ?, ?, ?, ?, '2026-01-01', 'active')
        ");
        $empStmt->execute([
            $empCode,
            $departmentId,
            $designationId,
            "PerfFirst" . ($currentCount + $i),
            "PerfLast" . ($currentCount + $i),
            $username
        ]);

        $employeeId = $db->lastInsertId();

        // Map to primary company
        $compStmt = $db->prepare("
            INSERT INTO employee_companies (employee_id, company_id, is_primary, is_active)
            VALUES (?, ?, 1, 1)
        ");
        $compStmt->execute([$employeeId, $companyId]);

        // Create user account
        $userStmt = $db->prepare("
            INSERT INTO users (employee_id, username, password_hash, api_token, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $hashedPass = password_hash('password123', PASSWORD_DEFAULT);
        $userStmt->execute([$employeeId, $username, $hashedPass, $token]);

        $userId = $db->lastInsertId();

        // Assign Employee Role (Role ID = 6)
        $roleStmt = $db->prepare("
            INSERT INTO user_roles (user_id, role_id)
            VALUES (?, 6)
        ");
        $roleStmt->execute([$userId]);
    }

    $db->commit();
    echo "Seeding completed successfully! Total users now: 55.\n";

} catch (\Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERROR during seeding: " . $e->getMessage() . "\n";
    exit(1);
}
