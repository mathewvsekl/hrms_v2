<?php
/**
 * HRMS V2 - Maya Audit Simulation Script (v1.6.6)
 * 
 * This script simulates various user roles and attempts to access/modify data
 * across organizational boundaries to verify RBAC and Data Isolation.
 */

// Bootstrapping
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Core/Controller.php';

// Load Helpers
if (file_exists(BASE_PATH . '/app/Helpers/CustomFieldValidator.php')) {
    require_once BASE_PATH . '/app/Helpers/CustomFieldValidator.php';
}

// Load Controllers
require_once BASE_PATH . '/app/Controllers/EmployeeController.php';
require_once BASE_PATH . '/app/Controllers/AttendanceController.php';
require_once BASE_PATH . '/app/Controllers/RbacController.php';
require_once BASE_PATH . '/app/Controllers/OrganizationController.php';

// Mock Controller to capture JSON responses and manage session state
class MayaMockController extends \App\Core\Controller {
    public $session = [];
    public $lastResponse = null;

    protected function jsonResponse($data, $httpStatus = 200, $message = '') {
        $this->lastResponse = [
            'status' => ($httpStatus >= 200 && $httpStatus < 300) ? 'success' : 'error',
            'code' => $httpStatus,
            'message' => $message,
            'data' => $data
        ];
        return $this->lastResponse;
    }

    public function setContext($userId, $roles, $companyId = null, $countryId = null, $employeeId = null) {
        $this->session = [
            'user_id' => $userId,
            'user_role' => $roles[0] ?? 'EMPLOYEE',
            'scope_company_id' => $companyId,
            'scope_country_id' => $countryId,
            'scope_employee_id' => $employeeId
        ];
        // Inject into global session for verifyDataScope
        $_SESSION = $this->session;
    }
}

// Specialized Mocks for Controllers
class MayaEmployeeController extends \App\Controllers\EmployeeController {
    use MayaMockTrait;
}

class MayaAttendanceController extends \App\Controllers\AttendanceController {
    use MayaMockTrait;
}

trait MayaMockTrait {
    public $lastResponse = null;
    protected function jsonResponse($data, $httpStatus = 200, $message = '') {
        $this->lastResponse = [
            'code' => $httpStatus,
            'message' => $message,
            'data' => $data
        ];
        return $this->lastResponse;
    }
}

function run_test($name, $callback) {
    echo "TEST: $name ... ";
    try {
        $result = $callback();
        if ($result === true) {
            echo "\033[32mPASSED\033[0m\n";
        } else {
            echo "\033[31mFAILED\033[0m ($result)\n";
        }
    } catch (Exception $e) {
        echo "\033[31mERROR\033[0m (" . $e->getMessage() . ")\n";
    }
}

// Start Session
if (session_status() === PHP_SESSION_NONE) session_start();

echo "=== HRMS V2 Maya Audit Simulation (v1.6.6) ===\n\n";

$emp = new MayaEmployeeController();
$att = new MayaAttendanceController();

// TEST 1: Employee Data Leakage check (Nova's suspicion)
run_test("Employee Read Leakage (Unscoped)", function() use ($emp) {
    // Setup regular employee context (Company 1)
    $_SESSION['user_id'] = 99;
    $_SESSION['scope_company_id'] = 1;
    $_SESSION['scope_employee_id'] = 305;
    
    // Attempt to read another employee (ID 301)
    $emp->getEmployee(301);
    // Based on code analysis, this SHOULD succeed because verifyDataScope is commented out in EmployeeController::getEmployee
    if ($emp->lastResponse['code'] === 200) {
        return "Leak Detected: Regular employee can read any profile via API.";
    }
    return true;
});

// TEST 2: Attendance Boundary Check (Country Manager)
run_test("Attendance Boundary Enforced (Country Manager)", function() use ($att) {
    // Setup Country Manager (Country 1)
    $_SESSION['user_id'] = 88;
    $_SESSION['scope_country_id'] = 1;
    
    // Attempt to fetch logs for a company in Country 2 (assume ID 99 doesn't exist in Country 1)
    // To be precise, we'd need to check DB, but we test the logic trigger
    $att->getLogs(['company_id' => 9999, 'date' => '2026-03-25']);
    
    if ($att->lastResponse['code'] === 403) {
        return true;
    }
    return "Failed: Country Manager was not blocked from out-of-bounds Company ID.";
});

// TEST 3: RBAC Role Assignment Restriction
run_test("RBAC Security Enforcement (Non-SuperAdmin cannot create SuperAdmin)", function() use ($emp) {
    $_SESSION['user_id'] = 77; // HR Manager
    // Note: isSuperAdmin() in Controller.php checks DB, so this test might fail without proper seed
    // We assume user 77 is NOT in user_roles as ID 1 (Super Admin)
    
    $payload = [
        'first_name' => 'Evil',
        'last_name' => 'Admin',
        'email' => 'evil@example.com',
        'company_ids' => [1],
        'role_id' => 1 // Super Admin ID
    ];
    
    $emp->save($payload);
    
    if ($emp->lastResponse['code'] === 403) {
        return true;
    }
    return "Security Vulnerability: Non-SuperAdmin created a SuperAdmin user.";
});

echo "\nAudit Simulation Summary: Done.\n";
