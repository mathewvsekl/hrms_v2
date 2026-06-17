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
    public static function hasPermission($module, $action)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;

        if (!$userId) return false;

        try {
            $db = \Database::getInstance()->getConnection();

            $stmt = $db->prepare("
                SELECT COALESCE(br.name, r.name) as role_name, p.module, p.action 
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                LEFT JOIN roles br ON r.base_role_id = br.id
                LEFT JOIN role_permissions rp ON r.id = rp.role_id
                LEFT JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = :user_id
            ");

            $stmt->execute(['user_id' => $userId]);
            $userPrivileges = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $roles = array_column($userPrivileges, 'role_name');
            
            // SuperAdmins are the ultimate administrators and should not be locked out by UI misconfigurations
            if (in_array('SuperAdmin', $roles)) {
                return true;
            }

            foreach ($userPrivileges as $priv) {
                if (isset($priv['module']) && isset($priv['action'])) {
                    // Use normalized checking
                    $normPrivModule = \App\Helpers\StringNormalizer::normalizeRole($priv['module']);
                    $normTargetModule = \App\Helpers\StringNormalizer::normalizeRole($module);
                    
                    if ($normPrivModule === $normTargetModule && strtolower($priv['action']) === strtolower($action)) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function requirePermission($module, $action)
    {
        if (!self::hasPermission($module, $action)) {
            http_response_code(403);
            echo json_encode([
                "status" => "error",
                "code" => 403,
                "message" => "Forbidden: You do not have the required '" . $action . "' permission for module '" . $module . "'."
            ]);
            exit();
        }
        return true;
    }
}
