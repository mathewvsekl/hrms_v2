<?php

namespace App\Middleware;

use App\Helpers\RoleConstants;

/**
 * Global Security Gatekeeper (RBAC & Session Enforcer)
 */
class AuthMiddleware
{
    /**
     * Intercepts API requests to verify active authorization.
     * Halts execution (401) if invalid.
     */
    public static function protect()
    {
        // 1. Session start if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $db = \Database::getInstance()->getConnection();

        // 2. Handle Token Authentication (Bearer or Query String)
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $authHeader = $headers['authorization'] ?? '';
        $token = '';

        if (!empty($authHeader) && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
        } elseif (!empty($_GET['token'])) {
            $token = $_GET['token'];
        }

        if (!empty($token)) {
            $hashedToken = hash('sha256', $token);
            
            // Check if token matches either the hashed version (new) or plaintext (legacy fallback)
            $stmt = $db->prepare("SELECT id FROM users WHERE api_token = ? OR api_token = ? LIMIT 1");
            $stmt->execute([$hashedToken, $token]);
            $u = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($u) {
                $_SESSION['user_id'] = $u['id'];
            } else {
                return self::unauthorizedResponse("Invalid or expired token.");
            }
        } else {
            return self::unauthorizedResponse("CSRF Protection: Missing Authorization Bearer token.");
        }

        // 3. Final Check: Is User Logged In?
        if (!isset($_SESSION['user_id'])) {
            return self::unauthorizedResponse("Unauthorized Access. Please login.");
        }

        // 4. Robust Session Hydration (Audit & Recovery)
        // If critical context is missing, re-populate from DB to prevent 'Zero-Data' regressions
        if (!isset($_SESSION['associated_company_ids']) || !isset($_SESSION['role_id']) || !isset($_SESSION['scope_country_id'])) {
            try {
                $stmt = $db->prepare("
                    SELECT u.id, u.is_active, u.employee_id, ec.company_id, co.country_id 
                    FROM users u 
                    LEFT JOIN employees e ON u.employee_id = e.id
                    LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                    LEFT JOIN companies co ON ec.company_id = co.id
                    WHERE u.id = ? LIMIT 1
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($user && $user['is_active'] == 1) {
                    $_SESSION['scope_employee_id'] = $user['employee_id'];
                    $_SESSION['scope_company_id'] = $user['company_id'];
                    $_SESSION['scope_country_id'] = $user['country_id'];

                    // Role Refresh — store canonical role ID as authoritative source
                    $roleStmt = $db->prepare("SELECT COALESCE(r.base_role_id, r.id) as canonical_id, r.name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ? LIMIT 1");
                    $roleStmt->execute([$user['id']]);
                    $roleRow = $roleStmt->fetch(\PDO::FETCH_ASSOC);
                    $_SESSION['role_id'] = (int)($roleRow['canonical_id'] ?? RoleConstants::EMPLOYEE);
                    $_SESSION['user_role'] = $roleRow['name'] ?? 'Employee'; // Kept for backward compat / display

                    // Company Association Refresh
                    if ($user['employee_id']) {
                        $assocStmt = $db->prepare("SELECT company_id FROM employee_companies WHERE employee_id = ? AND is_active = 1");
                        $assocStmt->execute([$user['employee_id']]);
                        $_SESSION['associated_company_ids'] = $assocStmt->fetchAll(\PDO::FETCH_COLUMN);
                    } else {
                        $_SESSION['associated_company_ids'] = $user['company_id'] ? [$user['company_id']] : [];
                    }
                }
            } catch (\Exception $e) {
                // Fail silently but log if possible
                error_log("Session Hydration Fault: " . $e->getMessage());
            }
        }

        return true;
    }

    private static function unauthorizedResponse($message)
    {
        error_log("AuthMiddleware 401: " . $message);
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(401);
        echo json_encode(['status' => 'error', 'code' => 401, 'message' => $message]);
        exit();
    }

    /**
     * Get authenticated user details
     */
    public static function getUser()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT u.id, u.employee_id, u.username, r.name as role, r.id as role_id 
                                 FROM users u 
                                 LEFT JOIN user_roles ur ON u.id = ur.user_id
                                 LEFT JOIN roles r ON ur.role_id = r.id
                                 WHERE u.id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user)
                return null;

            // Fallback: use RoleConstants for ID assignment if role is missing
            if (!$user['role']) {
                $user['role'] = ($user['id'] == 1) ? 'SuperAdmin' : 'Employee';
            }
            if (!$user['role_id']) {
                $user['role_id'] = ($user['id'] == 1) ? RoleConstants::SUPER_ADMIN : RoleConstants::EMPLOYEE;
            }

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }
}
