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
    protected static $userRoles = null;

    /**
     * Enforce geographic Context Scope boundaries
     * 
     * @param int|null $targetOfficeId The Office ID of the payload or target record
     * @param int|null $targetCountryId The Country ID of the payload or target record
     * @param int|null $targetEmployeeId The Employee ID being mutated
     */
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
            // Fetch roles once per request
            if (self::$userRoles === null) {
                $db = \Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
                $stmt->execute([$userId]);
                $rawRoles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                self::$userRoles = array_map('strtoupper', $rawRoles);
            }
            $roles = self::$userRoles;

            $isSuperAdmin = in_array('SUPERADMIN', $roles) || in_array('SUPER_ADMIN', $roles);
            $isAdmin = in_array('ADMIN', $roles);

            if ($isSuperAdmin) {
                return true;
            }

            // RBAC Hierarchy Enforcement: Non-SuperAdmins cannot touch SuperAdmins
            // We allow GET (read) for public profile visibility
            if ($targetEmployeeId !== null && $_SERVER['REQUEST_METHOD'] !== 'GET') {
                $db = \Database::getInstance()->getConnection();
                $hierStmt = $db->prepare("
                    SELECT 1 FROM user_roles ur 
                    JOIN roles r ON ur.role_id = r.id 
                    JOIN users u ON ur.user_id = u.id
                    WHERE u.employee_id = ? AND (UPPER(r.name) = 'SUPERADMIN' OR UPPER(r.name) = 'SUPER_ADMIN')
                ");
                $hierStmt->execute([$targetEmployeeId]);
                if ($hierStmt->fetch()) {
                    $this->jsonResponse(null, 403, "Hierarchy Violation: Insufficient permissions to access Super Admin records.");
                }
            }

            if ($isAdmin) {
                return true; // Admins have access to all countries without geographic restrictions
            }


            // Retrieve the active bounded context from the session
            // Security Enforcement: Intent-Aware Access Control (Admin vs Employee View)
            $viewMode = $_SERVER['HTTP_X_VIEW_MODE'] ?? 'admin';

            $myCompanyId = $_SESSION['scope_company_id'] ?? null;
            $myCountryId = $_SESSION['scope_country_id'] ?? null;
            $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;

            // Multi-Office Roles: bound to their associated companies
            if ($viewMode === 'admin') {
                if (!in_array('SUPERADMIN', $roles) && !in_array('SUPER_ADMIN', $roles) && !in_array('ADMIN', $roles) && !in_array('HRMANAGER', $roles) && !in_array('HR_MANAGER', $roles) && !in_array('HR MANAGER', $roles) && !in_array('HRASSISTANT', $roles) && !in_array('HR_ASSISTANT', $roles) && !in_array('HR ASSISTANT', $roles) && !in_array('COUNTRYMANAGER', $roles) && !in_array('COUNTRY_MANAGER', $roles) && !in_array('COUNTRY MANAGER', $roles)) {
                    // Noah Audit Fix: Auto-fallback to employee view if user lacks admin roles
                    $viewMode = 'employee';
                }
            }

            if (in_array('HRMANAGER', $roles) || in_array('HR_MANAGER', $roles) || in_array('HR MANAGER', $roles) || in_array('HRASSISTANT', $roles) || in_array('HR_ASSISTANT', $roles) || in_array('HR ASSISTANT', $roles) || in_array('COUNTRY MANAGER', $roles) || in_array('COUNTRY_MANAGER', $roles) || in_array('COUNTRYMANAGER', $roles)) {
                $myCompanyIds = $_SESSION['associated_company_ids'] ?? [$myCompanyId];
                
                // If target office is provided, verify it's in our allowed list
                if ($targetOfficeId !== null && !in_array($targetOfficeId, $myCompanyIds)) {
                    $this->jsonResponse(null, 403, "Context Scope Violation: Target Company ID out of bounds.");
                }

                // If target employee is provided, verify their PRIMARY company is in our allowed list
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

            // Normal Employees are bound strictly to themselves for mutations
            // We allow GET (read) to support public profile visibility
            if (in_array('EMPLOYEE', $roles) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
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
        return $this->hasAnyRole(['SUPER_ADMIN', 'SUPERADMIN']);
    }

    /**
     * Check if current session user is a Global Admin (Admin or SuperAdmin)
     * Global Admins have unrestricted geographic access.
     */
    protected function isGlobalAdmin()
    {
        return $this->hasAnyRole(['SUPER_ADMIN', 'SUPERADMIN', 'ADMIN']);
    }


    /**
     * Check if the current user has a specific role
     * @param string $roleName Case-insensitive role name
     */
    protected function hasRole($roleName)
    {
        return $this->hasAnyRole([$roleName]);
    }

    /**
     * Check if the current user has any of the specified roles
     * @param array $roleNames Array of case-insensitive role names
     */
    protected function hasAnyRole(array $roleNames)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) return false;

        try {
            if (self::$userRoles === null) {
                $db = \Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    SELECT r.name 
                    FROM user_roles ur 
                    JOIN roles r ON ur.role_id = r.id 
                    WHERE ur.user_id = ?
                ");
                $stmt->execute([$userId]);
                self::$userRoles = array_map('strtoupper', $stmt->fetchAll(\PDO::FETCH_COLUMN));
            }
            $userRoles = self::$userRoles;
            $targetRoles = array_map('strtoupper', $roleNames);

            foreach ($userRoles as $userRole) {
                foreach ($targetRoles as $targetRole) {
                    if ($userRole === $targetRole) return true;
                    
                    // Handle prefixed variants implicitly (e.g. "OFFICE HRASSISTANT" matching "HRASSISTANT")
                    $cleanTarget = str_replace([' ', '_'], '', $targetRole);
                    $cleanUser = str_replace([' ', '_'], '', $userRole);
                    
                    if ($cleanUser === $cleanTarget) return true;
                    if (strpos($cleanUser, 'OFFICE' . $cleanTarget) !== false) return true;
                    if (strpos($cleanUser, 'GLOBAL' . $cleanTarget) !== false) return true;
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
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
}
