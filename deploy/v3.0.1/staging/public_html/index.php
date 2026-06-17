<?php

// Strictly enforce typed logic for enterprise readiness
declare(strict_types=1);
date_default_timezone_set('UTC');

// Define explicit base paths first
define('BASE_PATH', __DIR__);

// Determine Root Path for externalized folders (config, public, storage, tmp, .env)
$upOneLevel = dirname(__DIR__);
if (@is_dir($upOneLevel . '/private/config')) {
    // HevistaCP / DirectAdmin private folder bypass
    define('ROOT_PATH', $upOneLevel);
} elseif (@file_exists($upOneLevel . '/config/database.php')) {
    define('ROOT_PATH', $upOneLevel);
} else {
    define('ROOT_PATH', __DIR__);
}

// Support an external CDN domain if it exists (e.g. assets.anedins.com)
$cdnPath = dirname(ROOT_PATH) . '/assets.anedins.com/public_html';

// Support placing folders inside the 'private' directory (which is permitted by open_basedir)
if (is_dir($cdnPath)) {
    define('PUBLIC_DIR_PATH', $cdnPath);
    define('CONFIG_PATH', ROOT_PATH . '/private/config');
    define('STORAGE_PATH', ROOT_PATH . '/private/storage');
    define('TMP_PATH', ROOT_PATH . '/private/tmp');
} elseif (is_dir(ROOT_PATH . '/private/config')) {
    define('CONFIG_PATH', ROOT_PATH . '/private/config');
    define('STORAGE_PATH', ROOT_PATH . '/private/storage');
    define('TMP_PATH', ROOT_PATH . '/private/tmp');
    define('PUBLIC_DIR_PATH', ROOT_PATH . '/private/public');
} else {
    define('CONFIG_PATH', ROOT_PATH . '/config');
    define('STORAGE_PATH', ROOT_PATH . '/storage');
    define('TMP_PATH', ROOT_PATH . '/tmp');
    define('PUBLIC_DIR_PATH', ROOT_PATH . '/public');
}

// Auto-generate symlink for the public folder so Nginx can serve static files flawlessly
// while allowing the user to completely wipe public_html during updates.
$publicSymlink = BASE_PATH . '/public';
if (!file_exists($publicSymlink) && is_dir(PUBLIC_DIR_PATH) && PUBLIC_DIR_PATH !== $publicSymlink) {
    @symlink(PUBLIC_DIR_PATH, $publicSymlink);
}

// Load .env variables
require_once BASE_PATH . '/app/Core/Env.php';
\App\Core\Env::load(ROOT_PATH . '/.env');

// DEBUG: Force-enable error display for EVERYTHING temporarily
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', TMP_PATH . '/php_errors.log');

// Set JSON error handler for API requests
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => "PHP Error: $errstr in $errfile on line $errline"]);
            exit;
        }
    }
    // Return false to let standard PHP error handling process it (it will be logged since log_errors=1)
    return false;
});
set_exception_handler(function($e) {
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "PHP Exception: " . $e->getMessage()]);
        exit;
    }
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => "PHP Fatal: {$error['message']} in {$error['file']}:{$error['line']}"]);
            exit;
        }
    }
});

// ASSET SERVING SAFETY NET: Handle direct file requests for /public/
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$cleanUri = parse_url($requestUri, PHP_URL_PATH);
$normalizedUri = str_replace(['/HRMS%20V2/', '/HRMS V2/'], '/', $cleanUri);
$normalizedUri = urldecode($normalizedUri);

if (strpos($normalizedUri, '/public/') === 0) {
    // Strip '/public' from the URI to append it to the dynamic PUBLIC_DIR_PATH
    $subPath = substr($normalizedUri, 7); 
    $filePath = realpath(PUBLIC_DIR_PATH . $subPath);
    
    // We must ensure the resolved file path is inside the PUBLIC_DIR_PATH
    $basePath = str_replace('\\', '/', realpath(PUBLIC_DIR_PATH));
    $resolvedPath = $filePath ? str_replace('\\', '/', $filePath) : '';
    
    if ($resolvedPath && is_file($resolvedPath) && stripos($resolvedPath, $basePath) === 0) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimes = [
            'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
            'svg' => 'image/svg+xml', 'css' => 'text/css', 'js' => 'application/javascript'
        ];
        if (isset($mimes[$ext])) header('Content-Type: ' . $mimes[$ext]);
        // Optimization: Allow browser caching for static public assets
        header("Cache-Control: public, max-age=86400"); // 24 hours
        header("X-Content-Type-Options: nosniff");
        header("Content-Disposition: inline; filename=\"" . basename($filePath) . "\"");
        readfile($filePath);
        exit;
    }
}

/**
 * Polyfill for getallheaders() for non-Apache environments (Nginx/FPM)
 */
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

/**
 * Avantgarde HRMS - Custom MVC Router 
 * The single global entry point intercepting and routing API calls based on URIs.
 */

// Load Core Infrastructure files
require_once CONFIG_PATH . '/database.php';

// Initialize Autoloader
require_once BASE_PATH . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

// Normalize Request URI early for consistent routing and security checks
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


$requestMethod = $_SERVER['REQUEST_METHOD'];
if (strpos($requestUri, '/index.php') === 0) {
    $requestUri = substr($requestUri, 10);
}
// Handle common subdirectories and ensure a leading slash
$requestUri = str_replace(['/HRMS%20V2/', '/HRMS V2/'], '/', $requestUri);
if (empty($requestUri) || $requestUri === '') {
    $requestUri = '/';
}
if ($requestUri[0] !== '/') {
    $requestUri = '/' . $requestUri;
}
// API Security Layer: Ensure session context is hydrated for all async calls
if (strpos($requestUri, '/api/') === 0) {
    $publicRoutes = ['/api/auth/login', '/api/login', '/api/auth/request-otp', '/api/auth/verify-otp', '/api/logout'];
    $isPublicRoute = false;
    foreach ($publicRoutes as $route) {
        if (strpos($requestUri, $route) === 0) {
            $isPublicRoute = true;
            break;
        }
    }
    if (!$isPublicRoute) {
        \App\Middleware\AuthMiddleware::protect();
    }
}

// SSL Enforcement for Production Mobile API Readiness
\App\Middleware\SecurityMiddleware::enforceSSL();

// Hard-Deny Policy: Intercept restricted system paths regardless of RBAC
\App\Middleware\SecurityMiddleware::interceptRestrictedPaths();

// Basic CORS Handling for Single Page Apps (Onboarding Frontend)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($requestMethod == 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Super Simple Router Map
 * 
 * Maps HTTP constraints to class execution.
 */
function dispatchRoute($method, $uri)
{
    error_log("Avantgarde HRMS Dispatch: $method $uri");
    // Dashboard Consolidated Route
    if (strpos($uri, '/api/dashboard/summary') === 0) {
        \App\Middleware\RoleMiddleware::requirePermission('Dashboard', 'view');
        if (file_exists(BASE_PATH . '/app/Controllers/DashboardController.php')) {
            require_once BASE_PATH . '/app/Controllers/DashboardController.php';
            return (new \App\Controllers\DashboardController())->getSummary();
        }
    }

    // Payroll Routes
    if (strpos($uri, '/api/payroll/summary') === 0) {
        if ($method === 'GET') {
            return (new \App\Controllers\PayrollController())->getSummary();
        }
    }

    // Appraisal / Performance Routes
    if (strpos($uri, '/api/appraisals/stats') === 0) {
        if ($method === 'GET') {
            return (new \App\Controllers\AppraisalController())->getStats();
        }
    }

    // Base Redirects for cleaner navigation
    if ($uri === '/' || $uri === '/dashboard') {
        header('Location: /public/index.html');
        exit();
    }
    // API: POST /api/login (Public)
    if ($method === 'POST' && strpos($uri, '/api/login') === 0) {
        $controller = new \App\Controllers\AuthController();
        return call_user_func([$controller, 'login']);
    }

    // API: GET /api/search
    if ($method === 'GET' && strpos($uri, '/api/search') === 0) {
        $controller = new \App\Controllers\SearchController();
        return call_user_func([$controller, 'search']);
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

    // ── ORGANIZATION API ──────────────────────────────────────
    // Helper: extract numeric IDs from URI
    $orgParts = explode('/', trim($uri, '/'));
    $orgId = is_numeric(end($orgParts)) ? end($orgParts) : null;

    // For nested resources like /offices/{id}/custom_fields/{field_id}
    $numericIds = [];
    foreach ($orgParts as $part) {
        if (is_numeric($part))
            $numericIds[] = $part;
    }
    $firstNumericId = $numericIds[0] ?? null;
    $lastNumericId = end($numericIds) ?: null;

    // RBAC Permissions
    if (strpos($uri, '/api/rbac/') === 0) {
        \App\Middleware\RoleMiddleware::requirePermission('rbac', 'manage');
        $controller = new \App\Controllers\RbacController();
        if ($uri === '/api/rbac/roles' && $method === 'GET')
            return call_user_func([$controller, 'listRoles']);
        if ($uri === '/api/rbac/roles' && $method === 'POST')
            return call_user_func([$controller, 'createRole']);
        if ($uri === '/api/rbac/permissions' && $method === 'GET')
            return call_user_func([$controller, 'listPermissions']);

        // Roles/{id}/permissions
        if (preg_match('/\/api\/rbac\/roles\/(\d+)\/permissions/', $uri, $matches)) {
            $roleId = $matches[1];
            if ($method === 'GET')
                return call_user_func([$controller, 'getRolePermissions'], $roleId);
            if ($method === 'PUT')
                return call_user_func([$controller, 'updateRolePermissions'], $roleId);
        }

        // Roles/{id}
        if (preg_match('/\/api\/rbac\/roles\/(\d+)$/', $uri, $matches) && $method === 'DELETE') {
            return call_user_func([$controller, 'deleteRole'], $matches[1]);
        }
    }

    // Custom Fields (Nested under Companies)
    if (preg_match('/\/api\/organization\/companies\/(\d+)\/custom_fields/', $uri, $matches)) {
        if ($method !== 'GET') {
            \App\Middleware\RoleMiddleware::requirePermission('organization', 'manage');
        }
        $companyId = $matches[1];
        $fieldId = (count($numericIds) > 1) ? $lastNumericId : null;
        $controller = new \App\Controllers\CustomFieldController();

        if ($method === 'GET' && !$fieldId)
            return call_user_func([$controller, 'index'], $companyId);
        if ($method === 'POST')
            return call_user_func([$controller, 'store'], $companyId);
        if ($method === 'PUT' && $fieldId)
            return call_user_func([$controller, 'update'], $companyId, $fieldId);
        if ($method === 'DELETE' && $fieldId)
            return call_user_func([$controller, 'destroy'], $companyId, $fieldId);
    }

    // Countries
    if (strpos($uri, '/api/organization/countries') === 0) {
        if ($method !== 'GET') {
            \App\Middleware\RoleMiddleware::requirePermission('organization', 'manage');
        }
        $controller = new \App\Controllers\OrganizationController();
        if ($method === 'GET')
            return call_user_func([$controller, 'listCountries']);
        if ($method === 'POST')
            return call_user_func([$controller, 'createCountry']);
        if ($method === 'PUT' && $orgId)
            return call_user_func([$controller, 'updateCountry'], $orgId);
        if ($method === 'DELETE' && $orgId)
            return call_user_func([$controller, 'deleteCountry'], $orgId);
    }

    // Companies
    if (strpos($uri, '/api/organization/companies') === 0 && strpos($uri, 'custom_fields') === false) {
        if ($method !== 'GET') {
            \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'edit');
        }
        $controller = new \App\Controllers\OrganizationController();
        if ($method === 'GET') {
            if ($orgId) {
                return call_user_func([$controller, 'getCompany'], $orgId);
            }
            return call_user_func([$controller, 'listCompanies']);
        }
        if ($method === 'POST')
            return call_user_func([$controller, 'createCompany']);
        if ($method === 'PUT' && $orgId)
            return call_user_func([$controller, 'updateCompany'], $orgId);
        if ($method === 'DELETE' && $orgId)
            return call_user_func([$controller, 'deleteCompany'], $orgId);
    }

    // Departments
    if (strpos($uri, '/api/organization/departments') === 0) {
        if ($method !== 'GET') {
            \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'edit');
        }
        $controller = new \App\Controllers\OrganizationController();
        if ($method === 'GET')
            return call_user_func([$controller, 'listDepartments']);
        if ($method === 'POST')
            return call_user_func([$controller, 'createDepartment']);
        if ($method === 'PUT' && $orgId)
            return call_user_func([$controller, 'updateDepartment'], $orgId);
        if ($method === 'DELETE' && $orgId)
            return call_user_func([$controller, 'deleteDepartment'], $orgId);
    }

    // Designations
    if (strpos($uri, '/api/organization/designations') === 0) {
        if ($method !== 'GET') {
            \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'edit');
        }
        $controller = new \App\Controllers\OrganizationController();
        if ($method === 'GET')
            return call_user_func([$controller, 'listDesignations']);
        if ($method === 'POST')
            return call_user_func([$controller, 'createDesignation']);
        if ($method === 'PUT' && $orgId)
            return call_user_func([$controller, 'updateDesignation'], $orgId);
        if ($method === 'DELETE' && $orgId)
            return call_user_func([$controller, 'deleteDesignation'], $orgId);
    }

    // Global Settings
    if (strpos($uri, '/api/organization/settings') === 0) {
        if ($method !== 'GET') {
            \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'edit');
        }
        $controller = new \App\Controllers\OrganizationController();
        if ($method === 'GET')
            return call_user_func([$controller, 'listSettings']);
        if ($method === 'POST')
            return call_user_func([$controller, 'updateSetting']);
    }

    // Exchange Rates
    if (strpos($uri, '/api/organization/exchange-rates') === 0) {
        if ($method !== 'GET') {
            \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'edit');
        }
        $controller = new \App\Controllers\OrganizationController();
        if ($method === 'GET')
            return call_user_func([$controller, 'listExchangeRates']);
        if ($method === 'POST')
            return call_user_func([$controller, 'createExchangeRate']);
        if ($method === 'PUT' && $orgId)
            return call_user_func([$controller, 'updateExchangeRate'], $orgId);
        if ($method === 'DELETE' && $orgId)
            return call_user_func([$controller, 'deleteExchangeRate'], $orgId);
    }

    // API: POST /api/employees/upload_photo (HIGH PRIORITY)
    if ($method === 'POST' && strpos($uri, '/api/employees/upload_photo') === 0) {
        $controller = new \App\Controllers\EmployeeController();
        $employeeId = $_POST['employee_id'] ?? null;
        return call_user_func([$controller, 'uploadProfilePhoto'], $employeeId);
    }
    if ($method === 'DELETE' && strpos($uri, '/api/employees/upload_photo') === 0) {
        $controller = new \App\Controllers\EmployeeController();
        $employeeId = $_GET['employee_id'] ?? null;
        return call_user_func([$controller, 'deleteProfilePhoto'], $employeeId);
    }

    // API: POST /api/employees/onboard
    if ($method === 'POST' && strpos($uri, '/api/employees/onboard') === 0) {
        $controller = new \App\Controllers\EmployeeController();
        return call_user_func([$controller, 'onboard']); // Assuming new onboard entrypoint
    }

    // API: POST /api/employees/{id}/send-welcome-email
    if ($method === 'POST' && preg_match('/\/api\/employees\/(\d+)\/send-welcome-email/', $uri, $matches)) {
        $controller = new \App\Controllers\EmployeeController();
        return call_user_func([$controller, 'sendWelcomeEmail'], $matches[1]);
    }

    // API: POST /api/employees (Onboard/Save)
    if ($method === 'POST' && strpos($uri, '/api/employees') === 0) {
        \App\Middleware\RoleMiddleware::requirePermission('employees', 'create');
        $controller = new \App\Controllers\EmployeeController();
        $requestData = json_decode(file_get_contents('php://input'), true);
        return call_user_func([$controller, 'save'], $requestData);
    }

    // API: GET /api/employees (list or single)
    if ($method === 'GET' && strpos($uri, '/api/employees') === 0) {
        $controller = new \App\Controllers\EmployeeController();
        $parts = explode('/', trim($uri, '/'));
        $id = end($parts);
        if (is_numeric($id)) {
            call_user_func([$controller, 'getEmployee'], $id);
        } else {
            call_user_func([$controller, 'listEmployees']);
        }
    }

    // API: GET /api/employees/{id}/onboarding-history
    if ($method === 'GET' && preg_match('/\/api\/employees\/(\d+)\/onboarding-history/', $uri, $matches)) {
        $controller = new \App\Controllers\EmployeeController();
        return call_user_func([$controller, 'getOnboardingHistory'], $matches[1]);
    }

    // API: PUT /api/employees/{id}
    if ($method === 'PUT' && strpos($uri, '/api/employees') === 0) {
        \App\Middleware\RoleMiddleware::requirePermission('employees', 'edit');
        $controller = new \App\Controllers\EmployeeController();
        $parts = explode('/', trim($uri, '/'));
        $id = end($parts);
        $requestData = json_decode(file_get_contents('php://input'), true);
        if (is_numeric($id)) {
            $requestData['id'] = $id;
        }
        return call_user_func([$controller, 'updateEmployee'], $requestData);
    }

    // API: GET /api/companies/templates
    if ($method === 'GET' && strpos($uri, '/api/companies/templates') === 0) {
        $controller = new \App\Controllers\CompanyController();
        // Extract ID if provided via /api/companies/templates/X
        $parts = explode('/', trim($uri, '/'));
        $id = end($parts);
        if (is_numeric($id)) {
            return call_user_func([$controller, 'getTemplate'], $id);
        }
        return call_user_func([$controller, 'getTemplate']);
    }

    // API: GET /api/custom_fields?company_id=X
    if ($method === 'GET' && strpos($uri, '/api/custom_fields') === 0) {
        $controller = new \App\Controllers\CustomFieldController();
        $companyId = $_GET['company_id'] ?? null;
        if ($companyId) {
            $result = call_user_func([$controller, 'index'], $companyId);
            echo json_encode($result);
            exit();
        }
    }

    // API: CONTRACTS
    if (strpos($uri, '/api/contracts') === 0) {
        $controller = new \App\Controllers\ContractController();
        if ($method === 'GET') {
            $employeeId = $_GET['employee_id'] ?? null;
            return call_user_func([$controller, 'getEmployeeContracts'], $employeeId);
        } elseif ($method === 'POST') {
            $requestData = json_decode(file_get_contents('php://input'), true);
            return call_user_func([$controller, 'createContract'], $requestData);
        } elseif ($method === 'DELETE') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return call_user_func([$controller, 'deleteContract'], $id);
        }
    }

    // API: DOCUMENTS
    if (strpos($uri, '/api/documents') === 0) {
        $controller = new \App\Controllers\DocumentController();
        if ($method === 'GET') {
            $employeeId = $_GET['employee_id'] ?? null;
            return call_user_func([$controller, 'getEmployeeDocuments'], $employeeId);
        } elseif ($method === 'POST') {
            $employeeId = $_POST['employee_id'] ?? null;
            return call_user_func([$controller, 'uploadDocument'], $employeeId);
        } elseif ($method === 'PUT') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return call_user_func([$controller, 'updateDocument'], $id);
        } elseif ($method === 'DELETE') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return call_user_func([$controller, 'deleteDocument'], $id);
        }
    }

    // API: PAYSLIPS
    if (strpos($uri, '/api/payslips') === 0) {
        $controller = new \App\Controllers\PayslipController();
        if ($method === 'GET') {
            if (strpos($uri, '/api/payslips/download') === 0) {
                return call_user_func([$controller, 'downloadPayslip']);
            }
            $employeeId = $_GET['employee_id'] ?? null;
            return call_user_func([$controller, 'getEmployeePayslips'], $employeeId);
        } elseif ($method === 'POST') {
            return call_user_func([$controller, 'uploadPayslip']);
        } elseif ($method === 'DELETE') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return call_user_func([$controller, 'deletePayslip'], $id);
        }
    }

    // API: COMPANY DOCUMENTS (Reference Documents / Policies)
    if (strpos($uri, '/api/company-documents') === 0) {
        $controller = new \App\Controllers\CompanyDocumentController();
        if ($method === 'GET') {
            if (strpos($uri, '/api/company-documents/all') === 0) {
                return call_user_func([$controller, 'getAllDocuments']);
            }
            return call_user_func([$controller, 'getDocuments']);
        } elseif ($method === 'POST') {
            return call_user_func([$controller, 'uploadDocument']);
        } elseif ($method === 'PUT') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return call_user_func([$controller, 'updateDocument'], $id);
        } elseif ($method === 'DELETE') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return call_user_func([$controller, 'deleteDocument'], $id);
        }
    }

    // API: ATTENDANCE
    if (strpos($uri, '/api/attendance') === 0) {
        $controller = new \App\Controllers\AttendanceController();
        if ($method === 'GET') {
            if (strpos($uri, '/api/attendance/summary') === 0) {
                return call_user_func([$controller, 'getEmployeeSummary'], $_GET);
            }
            if (strpos($uri, '/api/attendance/history') === 0) {
                return call_user_func([$controller, 'getAuditHistory'], $_GET);
            }
            if (strpos($uri, '/api/attendance/countries') === 0) {
                return call_user_func([$controller, 'getCountries']);
            }
            if (strpos($uri, '/api/attendance/office-configs') === 0) {
                return call_user_func([$controller, 'getOfficeConfigs'], $_GET);
            }
            if (strpos($uri, '/api/attendance/statuses') === 0) {
                return call_user_func([$controller, 'getAttendanceStatuses'], $_GET);
            }
            if (strpos($uri, '/api/attendance/weekly-schedules') === 0) {
                return call_user_func([$controller, 'getWeeklySchedules'], $_GET);
            }
            if (strpos($uri, '/api/attendance/monthly-report') === 0) {
                return call_user_func([$controller, 'getMonthlyReport'], $_GET);
            }
            if (strpos($uri, '/api/attendance/export-monthly') === 0) {
                return call_user_func([$controller, 'exportMonthlyReport'], $_GET);
            }
            if (strpos($uri, '/api/attendance/grid') === 0) {
                return call_user_func([$controller, 'getGridLogs'], $_GET);
            }
            return call_user_func([$controller, 'getLogs'], $_GET);

        } elseif ($method === 'POST') {
            if (strpos($uri, '/api/attendance/office-config') === 0) {
                \App\Middleware\RoleMiddleware::requirePermission('Attendance', 'configure');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'saveOfficeConfig'], $requestData);
            }
            if (strpos($uri, '/api/attendance/weekly-schedule') === 0) {
                \App\Middleware\RoleMiddleware::requirePermission('Attendance', 'configure');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'saveWeeklySchedule'], $requestData);
            }
            if (strpos($uri, '/api/attendance/status-definitions') === 0) {
                \App\Middleware\RoleMiddleware::requirePermission('Attendance', 'configure');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'saveAttendanceStatusDefinition'], $requestData);
            }
            if (strpos($uri, '/api/attendance/grid-save') === 0) {
                \App\Middleware\RoleMiddleware::requirePermission('Attendance', 'configure');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'saveGridEntries'], $requestData);
            }
            if (strpos($uri, '/api/attendance/bulk') === 0) {
                \App\Middleware\RoleMiddleware::requirePermission('Attendance', 'create');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'saveBulkEntry'], $requestData);
            }

            if (strpos($uri, '/api/attendance/submit') === 0) {
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'submitLogs'], $requestData);
            }
            if (strpos($uri, '/api/attendance/review') === 0) {
                \App\Middleware\RoleMiddleware::requirePermission('Attendance', 'approve');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'reviewLogs'], $requestData);
            }
            \App\Middleware\RoleMiddleware::requirePermission('Attendance', 'create');
            $requestData = json_decode(file_get_contents('php://input'), true);
            return call_user_func([$controller, 'saveManualEntry'], $requestData);
        } elseif ($method === 'DELETE') {
            if (strpos($uri, '/api/attendance/status-definitions') === 0) {
                $parts = explode('/', trim($uri, '/'));
                $id = end($parts);
                return call_user_func([$controller, 'deleteAttendanceStatusDefinition'], ['id' => $id, 'company_id' => $_GET['company_id'] ?? null]);
            }
            return call_user_func([$controller, 'deleteLog'], $_GET);
        }

    }

    // API: LEAVE & HOLIDAYS
    if (strpos($uri, '/api/leave') === 0 || strpos($uri, '/api/holidays') === 0) {
        $controller = new \App\Controllers\LeaveController();

        // Specific sub-resources for /api/leave and /api/holidays
        if (strpos($uri, '/api/holidays') === 0 || strpos($uri, '/api/leave/holidays') === 0) {
            if ($method === 'GET') {
                return call_user_func([$controller, 'getHolidays'], $_GET['company_id'] ?? null);
            } elseif ($method === 'POST') {
                \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'edit');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'addHoliday'], $requestData);
            } elseif ($method === 'DELETE') {
                \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'delete');
                $parts = explode('/', trim($uri, '/'));
                $id = end($parts);
                return call_user_func([$controller, 'deleteHoliday'], $id);
            }
        }

        if (strpos($uri, '/api/leave/approve') === 0 && $method === 'POST') {
            $requestData = json_decode(file_get_contents('php://input'), true);
            return call_user_func([$controller, 'approveRequest'], $requestData);
        }
        if (strpos($uri, '/api/leave/reject') === 0 && $method === 'POST') {
            $requestData = json_decode(file_get_contents('php://input'), true);
            return call_user_func([$controller, 'rejectRequest'], $requestData);
        }
        if (strpos($uri, '/api/leave/balances') === 0 && $method === 'GET') {
            return call_user_func([$controller, 'getBalances'], $_GET['employee_id'] ?? null);
        }
        if (strpos($uri, '/api/leave/recalculate') === 0 && $method === 'POST') {
            $requestData = json_decode(file_get_contents('php://input'), true);
            return call_user_func([$controller, 'recalculate'], $requestData);
        }

        if (strpos($uri, '/api/leave/preview') === 0 && $method === 'GET') {
            return call_user_func([$controller, 'previewLeaveDays'], $_GET);
        }
        if (strpos($uri, '/api/leave/types') === 0) {
            if ($method === 'GET') {
                return call_user_func([$controller, 'getLeaveTypes'], $_GET);
            } elseif ($method === 'POST') {
                \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'edit');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'addLeaveType'], $requestData);
            } elseif ($method === 'PUT') {
                \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'edit');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'updateLeaveType'], $requestData);
            } elseif ($method === 'DELETE') {
                $parts = explode('/', trim($uri, '/'));
                $id = end($parts);
                return call_user_func([$controller, 'deleteLeaveType'], $id);
            }
        }

        if (strpos($uri, '/api/leave/policies') === 0) {
            if ($method === 'GET') {
                return call_user_func([$controller, 'getPolicies'], $_GET['company_id'] ?? null);
            } elseif ($method === 'POST') {
                \App\Middleware\RoleMiddleware::requirePermission('Configuration', 'edit');
                $requestData = json_decode(file_get_contents('php://input'), true);
                return call_user_func([$controller, 'savePolicy'], $requestData);
            }
        }

        // Default Leave Request handling
        if ($uri === '/api/leave' || $uri === '/api/leave/') {
            if ($method === 'GET') {
                return call_user_func([$controller, 'getRequests'], $_GET);
            } elseif ($method === 'POST') {
                $requestData = json_decode(file_get_contents('php://input'), true);
                if (empty($requestData)) {
                    // Support for multipart/form-data (for file uploads)
                    $requestData = $_POST;
                    if (isset($requestData['segments']) && is_string($requestData['segments'])) {
                        $requestData['segments'] = json_decode($requestData['segments'], true);
                    }
                }
                return call_user_func([$controller, 'submitRequest'], $requestData);
            }
        }

        if (strpos($uri, '/api/leave/request-cancel') === 0 && $method === 'POST') {
            $requestData = json_decode(file_get_contents('php://input'), true);
            // Fallback for form-encoded or pre-parsed data in certain environments
            if (empty($requestData)) $requestData = $_POST;
            return call_user_func([$controller, 'requestCancellation'], $requestData);
        }

        if (strpos($uri, '/api/leave/admin-cancel') === 0 && $method === 'POST') {
            $requestData = json_decode(file_get_contents('php://input'), true);
            return call_user_func([$controller, 'adminCancel'], $requestData);
        }
    }

    // API: APPRAISALS SETTINGS
    if (strpos($uri, '/api/appraisals/settings') === 0) {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');
        $controller = new \App\Controllers\AppraisalController();
        if ($method === 'GET') {
            return call_user_func([$controller, 'getSystemSettings']);
        } elseif ($method === 'POST') {
            $requestData = json_decode(file_get_contents('php://input'), true);
            return call_user_func([$controller, 'saveSystemSettings'], $requestData);
        }
    }

    // API: APPRAISALS MASS DEACTIVATE
    if (strpos($uri, '/api/appraisals/mass-deactivate') === 0) {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');
        $controller = new \App\Controllers\AppraisalController();
        if ($method === 'POST') {
            $requestData = json_decode(file_get_contents('php://input'), true);
            return call_user_func([$controller, 'massDeactivate'], $requestData);
        }
    }

    // API: APPRAISALS
    if (strpos($uri, '/api/appraisals') === 0) {
        $controller = new \App\Controllers\AppraisalController();
        if ($method === 'GET') {
            $parts = explode('/', trim($uri, '/'));
            $action = end($parts);
            if ($action === 'template') {
                return call_user_func([$controller, 'getTemplate'], $_GET['id'] ?? null);
            }
            if (is_numeric($action)) {
                return call_user_func([$controller, 'getAppraisal'], $action);
            }
            return call_user_func([$controller, 'listAppraisals'], $_GET);
        } elseif ($method === 'POST') {
            $parts = explode('/', trim($uri, '/'));
            $action = end($parts);
            $requestData = json_decode(file_get_contents('php://input'), true);

            if ($action === 'submit-manager') {
                return call_user_func([$controller, 'submitToManager'], $parts[count($parts) - 2], $requestData);
            } elseif ($action === 'submit-hr') {
                return call_user_func([$controller, 'submitToHR'], $parts[count($parts) - 2], $requestData);
            } elseif ($action === 'finalize') {
                return call_user_func([$controller, 'finalize'], $parts[count($parts) - 2], $requestData);
            } elseif ($action === 'draft') {
                return call_user_func([$controller, 'saveDraft'], $requestData);
            } elseif ($action === 'initiate') {
                return call_user_func([$controller, 'initiateCycle'], $requestData);
            } elseif ($action === 'withdraw') {
                return call_user_func([$controller, 'withdrawCycle'], $parts[count($parts) - 2]);
            } elseif ($action === 'approve') {
                return call_user_func([$controller, 'approveAppraisal'], $parts[count($parts) - 2], $requestData);
            } elseif ($action === 'return') {
                return call_user_func([$controller, 'returnAppraisal'], $parts[count($parts) - 2], $requestData);
            }

            return call_user_func([$controller, 'saveDraft'], $requestData);
        }
    }

    // API: ASSETS
    if (strpos($uri, '/api/assets') === 0) {
        $controller = new \App\Controllers\AssetController();
        if ($method === 'GET') {
            $parts = explode('/', trim($uri, '/'));
            $action = end($parts);
            if (strpos($uri, '/api/assets/employee') === 0) {
                return call_user_func([$controller, 'employeeAssets'], $action);
            }
            return call_user_func([$controller, 'index']);
        } elseif ($method === 'POST') {
            if (strpos($uri, '/api/assets/allocate') === 0) {
                return call_user_func([$controller, 'allocate']);
            }
            if (strpos($uri, '/api/assets/deallocate') === 0) {
                return call_user_func([$controller, 'deallocate']);
            }
            return call_user_func([$controller, 'store']);
        } elseif ($method === 'PUT') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return call_user_func([$controller, 'update'], $id);
        } elseif ($method === 'DELETE') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return call_user_func([$controller, 'destroy'], $id);
        }
    }

    // API: EXPORTS
    if (strpos($uri, '/api/export/data') !== false) {
        $controller = new \App\Controllers\ExportController();
        if ($method === 'GET') {
            return call_user_func([$controller, 'exportData']);
        }
    }

    // API: NOTIFICATIONS
    if (strpos($uri, '/api/notifications') === 0) {
        $controller = new \App\Controllers\NotificationController();
        if ($method === 'GET') {
            return call_user_func([$controller, 'index'], $_GET);
        } elseif ($method === 'POST') {
            if (strpos($uri, '/api/notifications/mark-all-read') !== false) {
                return call_user_func([$controller, 'markAllAsRead']);
            }
            if (strpos($uri, '/api/notifications/mark-read') !== false) {
                $requestData = json_decode(file_get_contents('php://input'), true);
                if (empty($requestData)) $requestData = $_POST;
                return call_user_func([$controller, 'markAsRead'], $requestData);
            }
        } elseif ($method === 'DELETE') {
            $parts = explode('/', trim($uri, '/'));
            $id = end($parts);
            return call_user_func([$controller, 'delete'], $id);
        }
    }


    // API: SEARCH
    if ($method === 'GET' && strpos($uri, '/api/search') === 0) {
        $controller = new \App\Controllers\SearchController();
        return call_user_func([$controller, 'search']);
    }

    // Default Fallback
    error_log("404 Hit. URI: " . $uri . " Method: " . $method);
    file_put_contents(TMP_PATH . '/404_debug.log', "404 Hit. URI: " . $uri . " Method: " . $method . "\n", FILE_APPEND);
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'code' => 404,
        'message' => 'API Route Not Found or Unsupported Method.',
        'debug_uri' => $uri
    ]);
    exit();
}

// Execute logic loop
dispatchRoute($requestMethod, $requestUri);
