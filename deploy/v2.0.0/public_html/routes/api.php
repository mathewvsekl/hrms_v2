<?php
declare(strict_types=1);

/**
 * Avantgarde HRMS - API Router
 * Extracted from index.php for V2.0.0 Release
 */

function dispatchRoute($method, $uri)
{
    // API: POST /api/login (Public)
    if ($method === 'POST' && strpos($uri, '/api/login') === 0) {
        $controller = new \App\Controllers\AuthController();
        return call_user_func([$controller, 'login']);
    }

    // API: POST /api/logout
    if ($method === 'POST' && strpos($uri, '/api/logout') === 0) {
        $controller = new \App\Controllers\AuthController();
        return call_user_func([$controller, 'logout']);
    }

    // API: POST /api/auth/request-otp
    if ($method === 'POST' && strpos($uri, '/api/auth/request-otp') === 0) {
        $controller = new \App\Controllers\AuthController();
        return call_user_func([$controller, 'requestOTP']);
    }

    // API: POST /api/auth/verify-otp
    if ($method === 'POST' && strpos($uri, '/api/auth/verify-otp') === 0) {
        $controller = new \App\Controllers\AuthController();
        return call_user_func([$controller, 'verifyOTP']);
    }

    // --- PROTECTED ROUTES BELOW ---
    \App\Middleware\AuthMiddleware::protect();

    // Helper: extract numeric IDs from URI
    $orgParts = explode('/', trim($uri, '/'));
    $numericIds = [];
    foreach ($orgParts as $part) {
        if (is_numeric($part)) $numericIds[] = $part;
    }
    $orgId = is_numeric(end($orgParts)) ? end($orgParts) : null;
    $firstNumericId = $numericIds[0] ?? null;
    $lastNumericId = end($numericIds) ?: null;

    // RBAC Permissions
    if (strpos($uri, '/api/rbac/') === 0) {
        \App\Middleware\RoleMiddleware::requirePermission('rbac', 'manage');
        $controller = new \App\Controllers\RbacController();
        if ($uri === '/api/rbac/roles' && $method === 'GET') return call_user_func([$controller, 'listRoles']);
        if ($uri === '/api/rbac/roles' && $method === 'POST') return call_user_func([$controller, 'createRole']);
        if ($uri === '/api/rbac/permissions' && $method === 'GET') return call_user_func([$controller, 'listPermissions']);

        if (preg_match('/\/api\/rbac\/roles\/(\d+)\/permissions/', $uri, $matches)) {
            $roleId = $matches[1];
            if ($method === 'GET') return call_user_func([$controller, 'getRolePermissions'], $roleId);
            if ($method === 'PUT') return call_user_func([$controller, 'updateRolePermissions'], $roleId);
        }
        if (preg_match('/\/api\/rbac\/roles\/(\d+)$/', $uri, $matches) && $method === 'DELETE') {
            return call_user_func([$controller, 'deleteRole'], $matches[1]);
        }
    }

    // Custom Fields
    if (preg_match('/\/api\/organization\/companies\/(\d+)\/custom_fields/', $uri, $matches)) {
        if ($method !== 'GET') \App\Middleware\RoleMiddleware::requirePermission('organization', 'manage');
        $companyId = $matches[1];
        $fieldId = (count($numericIds) > 1) ? $lastNumericId : null;
        $controller = new \App\Controllers\CustomFieldController();
        if ($method === 'GET' && !$fieldId) return call_user_func([$controller, 'index'], $companyId);
        if ($method === 'POST') return call_user_func([$controller, 'store'], $companyId);
        if ($method === 'PUT' && $fieldId) return call_user_func([$controller, 'update'], $companyId, $fieldId);
        if ($method === 'DELETE' && $fieldId) return call_user_func([$controller, 'destroy'], $companyId, $fieldId);
    }

    // Organization (Countries, Companies, Depts, Designations, etc.)
    $orgController = new \App\Controllers\OrganizationController();
    if (strpos($uri, '/api/organization/countries') === 0) {
        if ($method !== 'GET') \App\Middleware\RoleMiddleware::requirePermission('organization', 'manage');
        if ($method === 'GET') return call_user_func([$orgController, 'listCountries']);
        if ($method === 'POST') return call_user_func([$orgController, 'createCountry']);
        if ($method === 'PUT' && $orgId) return call_user_func([$orgController, 'updateCountry'], $orgId);
        if ($method === 'DELETE' && $orgId) return call_user_func([$orgController, 'deleteCountry'], $orgId);
    }
    if (strpos($uri, '/api/organization/companies') === 0 && strpos($uri, 'custom_fields') === false) {
        if ($method !== 'GET') \App\Middleware\RoleMiddleware::requirePermission('organization', 'manage');
        if ($method === 'GET') return $orgId ? call_user_func([$orgController, 'getCompany'], $orgId) : call_user_func([$orgController, 'listCompanies']);
        if ($method === 'POST') return call_user_func([$orgController, 'createCompany']);
        if ($method === 'PUT' && $orgId) return call_user_func([$orgController, 'updateCompany'], $orgId);
        if ($method === 'DELETE' && $orgId) return call_user_func([$orgController, 'deleteCompany'], $orgId);
    }
    if (strpos($uri, '/api/organization/departments') === 0) {
        if ($method === 'GET') return call_user_func([$orgController, 'listDepartments']);
        if ($method === 'POST') return call_user_func([$orgController, 'createDepartment']);
        if ($method === 'PUT' && $orgId) return call_user_func([$orgController, 'updateDepartment'], $orgId);
        if ($method === 'DELETE' && $orgId) return call_user_func([$orgController, 'deleteDepartment'], $orgId);
    }
    if (strpos($uri, '/api/organization/designations') === 0) {
        if ($method === 'GET') return call_user_func([$orgController, 'listDesignations']);
        if ($method === 'POST') return call_user_func([$orgController, 'createDesignation']);
        if ($method === 'PUT' && $orgId) return call_user_func([$orgController, 'updateDesignation'], $orgId);
        if ($method === 'DELETE' && $orgId) return call_user_func([$orgController, 'deleteDesignation'], $orgId);
    }
    if (strpos($uri, '/api/organization/settings') === 0) {
        if ($method === 'GET') return call_user_func([$orgController, 'listSettings']);
        if ($method === 'POST') return call_user_func([$orgController, 'updateSetting']);
    }

    // Employees
    $empController = new \App\Controllers\EmployeeController();
    if (strpos($uri, '/api/employees') === 0) {
        if ($method === 'POST' && strpos($uri, '/api/employees/onboard') === 0) return call_user_func([$empController, 'onboard']);
        if ($method === 'POST') {
            \App\Middleware\RoleMiddleware::requirePermission('employees', 'create');
            return call_user_func([$empController, 'save'], json_decode(file_get_contents('php://input'), true));
        }
        if ($method === 'GET') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return is_numeric($id) ? call_user_func([$empController, 'getEmployee'], $id) : call_user_func([$empController, 'listEmployees']);
        }
        if ($method === 'PUT') {
            \App\Middleware\RoleMiddleware::requirePermission('employees', 'edit');
            $data = json_decode(file_get_contents('php://input'), true);
            $parts = explode('/', trim($uri, '/')); $id = end($parts);
            if (is_numeric($id)) $data['id'] = $id;
            return call_user_func([$empController, 'updateEmployee'], $data);
        }
    }

    // Attendance
    if (strpos($uri, '/api/attendance') === 0) {
        $attController = new \App\Controllers\AttendanceController();
        if ($method === 'GET') {
            if (strpos($uri, '/api/attendance/summary') === 0) return call_user_func([$attController, 'getEmployeeSummary'], $_GET);
            if (strpos($uri, '/api/attendance/history') === 0) return call_user_func([$attController, 'getAuditHistory'], $_GET);
            if (strpos($uri, '/api/attendance/grid') === 0) return call_user_func([$attController, 'getGridLogs'], $_GET);
            return call_user_func([$attController, 'getLogs'], $_GET);
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            if (strpos($uri, '/api/attendance/submit') === 0) return call_user_func([$attController, 'submitLogs'], $data);
            if (strpos($uri, '/api/attendance/grid-save') === 0) return call_user_func([$attController, 'saveGridEntries'], $data);
            return call_user_func([$attController, 'saveManualEntry'], $data);
        }
    }

    // Leave & Holidays
    if (strpos($uri, '/api/leave') === 0 || strpos($uri, '/api/holidays') === 0) {
        $leaveController = new \App\Controllers\LeaveController();
        if ($method === 'GET') {
            if (strpos($uri, '/api/leave/balances') === 0) return call_user_func([$leaveController, 'getBalances'], $_GET['employee_id'] ?? null);
            return call_user_func([$leaveController, 'getRequests'], $_GET);
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            return call_user_func([$leaveController, 'submitRequest'], $data);
        }
    }

    // Appraisals
    if (strpos($uri, '/api/appraisals') === 0) {
        $aprController = new \App\Controllers\AppraisalController();
        if ($method === 'GET') return call_user_func([$aprController, 'listAppraisals'], $_GET);
        if ($method === 'POST') return call_user_func([$aprController, 'saveDraft'], json_decode(file_get_contents('php://input'), true));
    }

    // Notifications
    if (strpos($uri, '/api/notifications') === 0) {
        $notController = new \App\Controllers\NotificationController();
        if ($method === 'GET') return call_user_func([$notController, 'index'], $_GET);
        if ($method === 'POST' && strpos($uri, '/api/notifications/mark-read') === 0) return call_user_func([$notController, 'markAsRead'], json_decode(file_get_contents('php://input'), true));
    }

    // Assets
    if (strpos($uri, '/api/assets') === 0) {
        $assetController = new \App\Controllers\AssetController();
        if ($method === 'GET') return call_user_func([$assetController, 'index']);
        if ($method === 'POST') return call_user_func([$assetController, 'store']);
    }

    // Default Fallback
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(404);
    echo json_encode(['status' => 'error', 'code' => 404, 'message' => 'API Route Not Found.']);
    exit();
}
