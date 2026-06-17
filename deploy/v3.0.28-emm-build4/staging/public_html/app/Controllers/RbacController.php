<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * RbacController
 * 
 * Manages Roles, Permissions, and their mappings.
 */
class RbacController extends Controller
{
    /** GET /api/rbac/roles */
    public function listRoles()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT * FROM roles ORDER BY name ASC");
            $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Super Admin Restriction: Invisible to everyone except other Super Admins
            if (!$this->isSuperAdmin()) {
                $roles = array_filter($roles, function ($role) {
                    $normName = strtoupper($role['name']);
                    return $normName !== 'SUPER_ADMIN' && $normName !== 'SUPERADMIN';
                });
                // Re-index array for clean JSON output
                $roles = array_values($roles);
            }

            return $this->jsonResponse($roles);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    public function listPermissions()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Normalize case for Assets and Configuration and remove duplicates
            $db->exec("UPDATE permissions SET module = 'Assets' WHERE LOWER(module) = 'assets'");
            $db->exec("UPDATE permissions SET module = 'Configuration' WHERE LOWER(module) = 'configuration'");
            $db->exec("DELETE p1 FROM permissions p1 INNER JOIN permissions p2 WHERE p1.id > p2.id AND p1.module = p2.module AND p1.action = p2.action");

            // Auto-insert standard actions for ALL modules
            $stmtModules = $db->query("SELECT DISTINCT module FROM permissions");
            $allModules = $stmtModules->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('Assets', $allModules)) $allModules[] = 'Assets';
            if (!in_array('Configuration', $allModules)) $allModules[] = 'Configuration';

            $actions = ['view', 'create', 'edit', 'delete', 'approve', 'configuration'];
            foreach ($allModules as $m) {
                foreach ($actions as $a) {
                    $stmt = $db->prepare('SELECT id FROM permissions WHERE module = ? AND action = ?');
                    $stmt->execute([$m, $a]);
                    if (!$stmt->fetch()) {
                        $ins = $db->prepare('INSERT INTO permissions (module, action) VALUES (?, ?)');
                        $ins->execute([$m, $a]);
                    }
                }
            }

            $stmt = $db->query("SELECT * FROM permissions ORDER BY module ASC, action ASC");
            $perms = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->jsonResponse($perms);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** GET /api/rbac/roles/{id}/permissions */
    public function getRolePermissions($roleId)
    {
        try {
            // Security Enforcement: Only Super Admins can access Super Admin role permissions (ID 1)
            if (!$this->isSuperAdmin() && $roleId == 1) {
                return $this->jsonResponse(null, 403, "Access Denied: You cannot view Super Admin role permissions.");
            }

            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);
            $perms = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            return $this->jsonResponse($perms);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** PUT /api/rbac/roles/{id}/permissions */
    public function updateRolePermissions($roleId)
    {
        $data = $this->getJsonPayload();
        $permissionIds = $data['permission_ids'] ?? [];

        // Security Enforcement: Only Super Admins can modify Super Admin role permissions (ID 1)
        if (!$this->isSuperAdmin() && $roleId == 1) {
            return $this->jsonResponse(null, 403, "Access Denied: You cannot modify Super Admin role permissions.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Wipe existing
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$roleId]);

            // 2. Insert new
            if (!empty($permissionIds)) {
                $insertStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($permissionIds as $pId) {
                    $insertStmt->execute([$roleId, $pId]);
                }
            }

            $db->commit();
            return $this->jsonResponse(null, 200, "Permissions updated successfully.");
        } catch (\Exception $e) {
            if ($db->inTransaction())
                $db->rollBack();
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** POST /api/rbac/roles */
    public function createRole()
    {
        if (!$this->isGlobalAdmin()) {
            return $this->jsonResponse(null, 403, "Access Denied: Insufficient permissions to create roles.");
        }
        $data = $this->getJsonPayload();
        $name = $data['name'] ?? '';
        $baseRoleId = $data['base_role_id'] ?? null;

        if (empty($name)) {
            return $this->jsonResponse(null, 400, "Role name is required.");
        }
        if (empty($baseRoleId)) {
            return $this->jsonResponse(null, 400, "base_role_id is required to create a custom role.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Create the new role with base_role_id
            $stmt = $db->prepare("INSERT INTO roles (name, base_role_id) VALUES (?, ?)");
            $stmt->execute([$name, $baseRoleId]);
            $newRoleId = $db->lastInsertId();

            // 2. Deep Copy permissions from base role
            $copyStmt = $db->prepare("
                INSERT INTO role_permissions (role_id, permission_id)
                SELECT ?, permission_id FROM role_permissions WHERE role_id = ?
            ");
            $copyStmt->execute([$newRoleId, $baseRoleId]);

            $db->commit();
            return $this->jsonResponse(['id' => $newRoleId], 201, "Role created successfully.");
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** DELETE /api/rbac/roles/{id} */
    public function deleteRole($id)
    {
        // Protected System Roles: IDs 1-6 (SuperAdmin, Admin, HRManager, CountryManager, HRAssistant, Employee)
        $protectedIds = [1, 2, 3, 4, 5, 6];
        if (in_array($id, $protectedIds)) {
            return $this->jsonResponse(null, 403, "Access Denied: This is a protected system role and cannot be deleted.");
        }

        // Only Global Admins (Admin or SuperAdmin) can delete custom roles
        if (!$this->isGlobalAdmin()) {
            return $this->jsonResponse(null, 403, "Access Denied: Insufficient permissions to delete roles.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Delete permission mappings first
            $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $stmt->execute([$id]);

            // 2. Delete the role
            $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
            return $this->jsonResponse(null, 200, "Role deleted successfully.");
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return $this->jsonResponse(null, 500, "Delete failed: " . $e->getMessage());
        }
    }
}
