<?php

namespace App\Middleware;

use App\Core\Controller;

/**
 * Advanced Role-Based Access Control Gatekeeper
 * 
 * Executes after AuthMiddleware. Checks if the authenticated user
 * possesses the required permission matrix for a specific endpoint.
 */
class RoleMiddleware extends Controller
{
    /**
     * Checks if the active user has a specific permission.
     * 
     * @param string $module e.g., 'employees', 'leave_policies'
     * @param string $action e.g., 'view', 'create', 'edit', 'delete'
     */
    public static function requirePermission($module, $action)
    {
        // Require active session context (AuthMiddleware must run first)
        session_start();
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Unauthorized: No active session context."]);
            exit();
        }

        try {
            $db = \Database::getInstance()->getConnection();

            // 1. Fetch User Roles & Permissions
            $stmt = $db->prepare("
                SELECT r.name as role_name, p.module, p.action 
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                LEFT JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = :user_id
            ");

            $stmt->execute(['user_id' => $userId]);
            $userPrivileges = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 2. Global Bypass for Super Admins
            $isSuperAdmin = array_search('SUPER_ADMIN', array_column($userPrivileges, 'role_name')) !== false;
            if ($isSuperAdmin) {
                return true; // Super admins bypass all granular permission checks
            }

            // 3. Scan for Explicit Permission Match
            $hasPermission = false;
            foreach ($userPrivileges as $priv) {
                if ($priv['module'] === $module && $priv['action'] === $action) {
                    $hasPermission = true;
                    break;
                }
            }

            // 4. Deny Access if neither condition met
            if (!$hasPermission) {
                http_response_code(403);
                echo json_encode([
                    "status" => "error",
                    "code" => 403,
                    "message" => "Forbidden: You do not have the required '" . $action . "' permission for module '" . $module . "'."
                ]);
                exit();
            }

            return true;

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "code" => 500, "message" => "RBAC Engine Fault: " . $e->getMessage()]);
            exit();
        }
    }
}
