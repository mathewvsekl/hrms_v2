<?php

namespace App\Middleware;

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
        // 1. Session check for standard cookie-based auth
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {

            // 2. Token check for API-based auth (Bearer Tokens)
            $headers = array_change_key_case(getallheaders(), CASE_LOWER);
            $authHeader = $headers['authorization'] ?? '';

            if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
                header("Content-Type: application/json; charset=UTF-8");
                http_response_code(401);
                echo json_encode([
                    'status' => 'error',
                    'code' => 401,
                    'message' => 'Unauthorized Access. Please login.'
                ]);
                exit();
            }

            // 3. Validate Bearer token against database `users`
            $token = substr($authHeader, 7);


            try {
                $db = \Database::getInstance()->getConnection();
                
                // 3. Validate Token and Fetch Full Geographic Context
                $stmt = $db->prepare("
                    SELECT u.id, u.is_active, u.employee_id, ec.company_id, co.country_id 
                    FROM users u 
                    LEFT JOIN employees e ON u.employee_id = e.id
                    LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                    LEFT JOIN companies co ON ec.company_id = co.id
                    WHERE u.api_token = :token LIMIT 1
                ");
                $stmt->execute(['token' => $token]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$user) {
                    http_response_code(401);
                    echo json_encode(["status" => "error", "code" => 401, "message" => "Invalid token."]);
                    exit();
                }

                if ($user['is_active'] != 1) {
                    http_response_code(403);
                    echo json_encode(["status" => "error", "code" => 403, "message" => "Account is suspended."]);
                    exit();
                }

                // Inject full session context for RBAC-aware controllers
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['scope_employee_id'] = $user['employee_id'];
                $_SESSION['scope_company_id'] = $user['company_id'];
                $_SESSION['scope_country_id'] = $user['country_id'];

                // Noah Audit Fix: Dynamic RBAC Role Lookup
                $roleStmt = $db->prepare("
                    SELECT r.name 
                    FROM roles r
                    JOIN user_roles ur ON r.id = ur.role_id
                    WHERE ur.user_id = ? LIMIT 1
                ");
                $roleStmt->execute([$user['id']]);
                $role = $roleStmt->fetchColumn() ?: 'EMPLOYEE';
                $_SESSION['user_role'] = $role;

                // Noah Audit Fix: Populate Associated Companies for Multi-Office visibility
                if ($user['employee_id']) {
                    $assocStmt = $db->prepare("SELECT company_id FROM employee_companies WHERE employee_id = ? AND is_active = 1");
                    $assocStmt->execute([$user['employee_id']]);
                    $_SESSION['associated_company_ids'] = $assocStmt->fetchAll(\PDO::FETCH_COLUMN);
                } else {
                    $_SESSION['associated_company_ids'] = $user['company_id'] ? [$user['company_id']] : [];
                }

            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode([
                    "status" => "error",
                    "code" => 500,
                    "message" => "Security subsystem fault."
                ]);
                exit();
            }
        }
        // Passed security check
        return true;
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
            $stmt = $db->prepare("SELECT u.id, u.employee_id, u.username, r.name as role 
                                 FROM users u 
                                 LEFT JOIN user_roles ur ON u.id = ur.user_id
                                 LEFT JOIN roles r ON ur.role_id = r.id
                                 WHERE u.id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user)
                return null;

            // Simple role mapping fallback if roles table is not used consistently
            if (!$user['role']) {
                $user['role'] = ($user['id'] == 1) ? 'SuperAdmin' : 'Employee';
            }

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }
}
