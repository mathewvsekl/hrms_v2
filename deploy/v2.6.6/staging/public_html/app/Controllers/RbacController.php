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

    /** GET /api/rbac/permissions */
    public function listPermissions()
    {
        try {
            $db = \Database::getInstance()->getConnection();
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
        $data = $this->getJsonPayload();
        if (empty($data['name'])) {
            return $this->jsonResponse(null, 400, "Role name is required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO roles (name) VALUES (?)");
            $stmt->execute([$data['name']]);
            return $this->jsonResponse(['id' => $db->lastInsertId()], 201, "Role created successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** DELETE /api/rbac/roles/{id} */
    public function deleteRole($id)
    {
        // Don't allow deleting Super Admin or Basic roles if needed
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(null, 200, "Role deleted successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Delete failed: " . $e->getMessage());
        }
    }
}
