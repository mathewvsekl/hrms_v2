<?php

namespace App\Core;

/**
 * Base Controller
 * Houses generic functions for API outputs and parameter checks
 */
class Controller
{
    protected $isInternal = false;

    public function setInternal($value = true)
    {
        $this->isInternal = $value;
        return $this;
    }
    /**
     * Internal helper to build the standardized response structure
     */
    protected function buildResponse($data, $httpStatus = 200, $message = '')
    {
        return [
            'status' => ($httpStatus >= 200 && $httpStatus < 300) ? 'success' : 'error',
            'code' => $httpStatus,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Terminate the executing request and return a structured JSON response
     */
    public function jsonResponse($data = null, $httpStatus = 200, $message = "")
    {
        $response = $this->buildResponse($data, $httpStatus, $message);

        if ($this->isInternal) {
            return new class($response) {
                private $res;
                public function __construct($r) { $this->res = $r; }
                public function getContent() { return json_encode($this->res); }
                public function getData() { return $this->res['data']; }
                public function getStatusCode() { return $this->res['code']; }
            };
        }

        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code($httpStatus);
        
        // Final guard against accidental output (warnings, notices, etc) breaking JSON
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        echo json_encode($response);
        exit(); // Halt all further script execution for safety
    }

    /**
     * Parse raw incoming JSON payloads or form-data strictly to associative arrays
     */
    protected function getJsonPayload()
    {
        $payload = file_get_contents('php://input');
        
        // If PHP input stream is empty, check if $_POST was already populated by the server (e.g. form-data or pre-parsed JSON)
        if (empty($payload)) {
            if (!empty($_POST)) return $_POST;
            return [];
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback: If it's not valid JSON, it might be raw POST data
            if (!empty($_POST)) return $_POST;
            return [];
        }

        return $decoded;
    }

    /**
     * Enforce geographic Context Scope boundaries
     * 
     * @param int|null $targetOfficeId The Office ID of the payload or target record
     * @param int|null $targetCountryId The Country ID of the payload or target record
     * @param int|null $targetEmployeeId The Employee ID being mutated
     */
    /**
     * Enforce geographic Context Scope boundaries
     * 
     * @param int|null $targetOfficeId The Office ID of the payload or target record
     * @param int|null $targetCountryId The Country ID of the payload or target record
     * @param int|null $targetEmployeeId The Employee ID being mutated
     */
    protected static $userRoles = null;

    protected function verifyDataScope($targetOfficeId = null, $targetCountryId = null, $targetEmployeeId = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->jsonResponse(null, 401, "No active session context for tracking.");
        }

        try {
            if (self::$userRoles === null) {
                $db = \Database::getInstance()->getConnection();
                // Fetch the exact role name, or base role name if it's a cloned role
                $stmt = $db->prepare("
                    SELECT COALESCE(br.name, r.name) as name 
                    FROM user_roles ur 
                    JOIN roles r ON ur.role_id = r.id 
                    LEFT JOIN roles br ON r.base_role_id = br.id
                    WHERE ur.user_id = ?
                ");
                $stmt->execute([$userId]);
                self::$userRoles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }
            $roles = self::$userRoles;

            $isSuperAdmin = in_array('SuperAdmin', $roles);
            $isAdmin = in_array('Admin', $roles);
            $isGlobalAdmin = $isSuperAdmin || $isAdmin || in_array('HR Manager', $roles);

            if ($isSuperAdmin) {
                return true;
            }

            // Hierarchy Enforcement: Non-SuperAdmins cannot touch SuperAdmins
            if ($targetEmployeeId !== null && $_SERVER['REQUEST_METHOD'] !== 'GET') {
                $db = \Database::getInstance()->getConnection();
                $hierStmt = $db->prepare("
                    SELECT 1 FROM user_roles ur 
                    JOIN roles r ON ur.role_id = r.id 
                    LEFT JOIN roles br ON r.base_role_id = br.id
                    JOIN users u ON ur.user_id = u.id
                    WHERE u.employee_id = ? AND COALESCE(br.name, r.name) = 'SuperAdmin'
                ");
                $hierStmt->execute([$targetEmployeeId]);
                if ($hierStmt->fetch()) {
                    $this->jsonResponse(null, 403, "Hierarchy Violation: Insufficient permissions to access Super Admin records.");
                }
            }

            if ($isAdmin) {
                return true; // Admins have access to all countries without geographic restrictions
            }

            $viewMode = $_SERVER['HTTP_X_VIEW_MODE'] ?? 'admin';
            $myCompanyId = $_SESSION['scope_company_id'] ?? null;
            $myCountryId = $_SESSION['scope_country_id'] ?? null;
            $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;

            if ($viewMode === 'admin') {
                $hasAdminRole = count(array_intersect(['SuperAdmin', 'Admin', 'HR Manager', 'CountryManager', 'HRAssistant', 'Office HRAssistant'], $roles)) > 0;
                if (!$hasAdminRole) {
                    $viewMode = 'employee';
                }
            }

            // Check Scopes based on exact base roles
            if (in_array('HR Manager', $roles) || in_array('CountryManager', $roles) || in_array('HRAssistant', $roles) || in_array('Office HRAssistant', $roles)) {
                $myCompanyIds = $_SESSION['associated_company_ids'] ?? [$myCompanyId];
                
                if ($targetOfficeId !== null && !in_array($targetOfficeId, $myCompanyIds)) {
                    $this->jsonResponse(null, 403, "Context Scope Violation: Target Company ID out of bounds.");
                }

                if ($targetEmployeeId !== null && $_SERVER['REQUEST_METHOD'] !== 'GET') {
                    $db = \Database::getInstance()->getConnection();
                    $placeholders = implode(',', array_fill(0, count($myCompanyIds), '?'));
                    $stmt = $db->prepare("SELECT 1 FROM employee_companies WHERE employee_id = ? AND company_id IN ($placeholders) AND is_primary = 1 AND is_active = 1 LIMIT 1");
                    $stmt->execute(array_merge([$targetEmployeeId], $myCompanyIds));
                    if (!$stmt->fetchColumn()) {
                        $this->jsonResponse(null, 403, "Context Scope Violation: Target Employee's Primary Company is out of bounds.");
                    }
                }
                return true;
            }

            // Employee Scope strictly to themselves
            if (in_array('Employee', $roles) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
                if ($targetEmployeeId !== null && $targetEmployeeId != $myEmployeeId) {
                    $this->jsonResponse(null, 403, "Context Scope Violation: You can only act on your own data.");
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->jsonResponse(null, 500, "Scope Enforcer Fault: " . $e->getMessage());
        }
    }

    /**
     * Check if current session user is a Super Admin
     */
    protected function isSuperAdmin()
    {
        $this->loadRoles();
        return in_array('SuperAdmin', self::$userRoles);
    }

    /**
     * Check if current session user is a Global Admin
     * Global Admins have unrestricted geographic access (SuperAdmin, Admin, HR Manager).
     */
    protected function isGlobalAdmin()
    {
        $this->loadRoles();
        return in_array('SuperAdmin', self::$userRoles) || in_array('Admin', self::$userRoles) || in_array('HR Manager', self::$userRoles);
    }

    /**
     * Check if user has Global or Regional (Country) Admin scope
     */
    protected function hasGlobalOrRegionalScope()
    {
        $this->loadRoles();
        return $this->isGlobalAdmin() || in_array('CountryManager', self::$userRoles);
    }

    /**
     * Check if user has at least Entity (Company/Office) Admin scope
     */
    protected function hasEntityScope()
    {
        $this->loadRoles();
        return $this->hasGlobalOrRegionalScope() || in_array('HRAssistant', self::$userRoles) || in_array('Office HRAssistant', self::$userRoles);
    }

    /**
     * Helper to load roles efficiently.
     */
    private function loadRoles()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (self::$userRoles === null) {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                self::$userRoles = [];
                return;
            }
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT COALESCE(br.name, r.name) as name 
                FROM user_roles ur 
                JOIN roles r ON ur.role_id = r.id 
                LEFT JOIN roles br ON r.base_role_id = br.id
                WHERE ur.user_id = ?
            ");
            $stmt->execute([$userId]);
            self::$userRoles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }
    }

    /**
     * Apply timezone conversion to a list of items
     */
    protected function applyTimezones(&$items, $timezone, $columns = ['created_at_utc', 'updated_at_utc', 'read_at_utc', 'last_login_utc'])
    {
        if (empty($items)) return;
        
        $isList = isset($items[0]);
        $data = $isList ? $items : [$items];

        foreach ($data as &$item) {
            foreach ($columns as $col) {
                if (isset($item[$col])) {
                    $localKey = str_replace('_utc', '_local', $col);
                    $displayKey = str_replace('_utc', '_display', $col);
                    
                    $item[$localKey] = \App\Helpers\DateHelper::toLocal($item[$col], $timezone, 'Y-m-d H:i:s');
                    $item[$displayKey] = \App\Helpers\DateHelper::toLocal($item[$col], $timezone, 'd/M/Y, h:i A');
                }
            }
        }

        if (!$isList) {
            $items = $data[0];
        } else {
            $items = $data;
        }
    }

    /**
     * Safely execute a callback, catching exceptions and returning a fallback value.
     */
    protected function safeCall($callback, $fallback = null, $module = 'unknown')
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $logFile = (defined('STORAGE_PATH') ? STORAGE_PATH : (defined('ROOT_PATH') ? ROOT_PATH : BASE_PATH) . '/storage') . '/system_errors.log';
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Module: $module - " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            return $fallback;
        }
    }
}
