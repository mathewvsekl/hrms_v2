<?php

namespace App\Helpers;

/**
 * RoleConstants — Single Source of Truth for Role IDs
 * 
 * Maps directly to the `roles` table primary keys.
 * All authorization decisions MUST use these IDs, NEVER role name strings.
 * 
 * Custom roles (ID > 6) use `base_role_id` to inherit from these canonical roles.
 * Use resolveUserRoleIds() to always get the canonical (base) role ID.
 */
final class RoleConstants
{
    // ─── Canonical Role IDs (match `roles.id` in DB) ───
    public const SUPER_ADMIN     = 1;
    public const ADMIN           = 2;
    public const HR_MANAGER      = 3;
    public const COUNTRY_MANAGER = 4;
    public const HR_ASSISTANT    = 5;
    public const EMPLOYEE        = 6;

    // ─── Protected System Role IDs (cannot be deleted) ───
    public const PROTECTED_IDS = [1, 2, 3, 4, 5, 6];

    // ─── Role Groups (for multi-role access checks) ───
    
    /** SuperAdmin + Admin — unrestricted global access */
    public const GLOBAL_ADMINS = [self::SUPER_ADMIN, self::ADMIN];

    /** SuperAdmin + Admin + HR Manager — management-tier access */
    public const MANAGEMENT = [self::SUPER_ADMIN, self::ADMIN, self::HR_MANAGER];

    /** All roles that can see across multiple offices/companies */
    public const MULTI_OFFICE = [
        self::SUPER_ADMIN, self::ADMIN, self::HR_MANAGER,
        self::HR_ASSISTANT, self::COUNTRY_MANAGER
    ];

    /** All administrative roles (everything except Employee) */
    public const ALL_ADMIN = [
        self::SUPER_ADMIN, self::ADMIN, self::HR_MANAGER,
        self::COUNTRY_MANAGER, self::HR_ASSISTANT
    ];

    /** Roles with payroll access */
    public const PAYROLL_ACCESS = [
        self::SUPER_ADMIN, self::ADMIN, self::HR_MANAGER, self::HR_ASSISTANT
    ];

    /**
     * Check if a single role ID belongs to a specific group.
     */
    public static function isInGroup(int $roleId, array $group): bool
    {
        return in_array($roleId, $group, true);
    }

    /**
     * Check if ANY of the given role IDs belong to a specific group.
     */
    public static function anyInGroup(array $roleIds, array $group): bool
    {
        return !empty(array_intersect($roleIds, $group));
    }

    /**
     * Resolve the canonical (base) role IDs for a user.
     * Handles custom roles by resolving through base_role_id.
     * 
     * @param int $userId
     * @return int[] Array of canonical role IDs
     */
    public static function resolveUserRoleIds(int $userId): array
    {
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COALESCE(r.base_role_id, r.id) as canonical_role_id 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Get the primary (first) canonical role ID for a user.
     * Falls back to EMPLOYEE if no role is assigned.
     */
    public static function getPrimaryRoleId(int $userId): int
    {
        $roleIds = self::resolveUserRoleIds($userId);
        return $roleIds[0] ?? self::EMPLOYEE;
    }
}
