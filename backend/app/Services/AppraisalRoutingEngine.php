<?php

namespace App\Services;

/**
 * AppraisalRoutingEngine
 * 
 * Handles dynamic role resolution, active delegation lookup (with cycle safeguards),
 * and dynamic fallback routing for the HRMS V2 Appraisal module.
 */
class AppraisalRoutingEngine
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Resolves the actor ID (employee_id) for a given appraisal and required role.
     * If the direct lookup returns null, it triggers the fallback sequence.
     * If an actor is found, it evaluates any active delegations.
     */
    public function resolveApprover(int $appraisalId, string $role): int
    {
        error_log("AppraisalRoutingEngine: Resolving approver for appraisal $appraisalId and role '$role'");

        // 1. Fetch appraisal and employee metadata
        $stmtInfo = $this->db->prepare("
            SELECT ea.id, ea.employee_id, ea.cycle_id, ea.manager_id as snapshot_manager_id,
                   e.department_id, e.designation_id, e.reporting_manager_id,
                   ec.company_id
            FROM employee_appraisals ea
            JOIN employees e ON ea.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            WHERE ea.id = ?
        ");
        $stmtInfo->execute([$appraisalId]);
        $info = $stmtInfo->fetch(\PDO::FETCH_ASSOC);

        if (!$info) {
            throw new \Exception("Appraisal not found: $appraisalId", 404);
        }

        $companyId = (int)($info['company_id'] ?? 1);
        $cycleId = (int)$info['cycle_id'];

        // 2. Resolve the role directly
        $actorId = $this->resolveRoleDirectly($role, $info);

        // 3. Fallback routing if resolved actor is null
        if ($actorId === null) {
            error_log("AppraisalRoutingEngine: Primary role '$role' resolved to null. Triggering fallback hierarchy.");
            $actorId = $this->resolveFallbackActor($appraisalId, $cycleId, $companyId, $info);
        }

        // 4. Delegation check
        if ($actorId !== null) {
            $actorId = $this->resolveDelegation($actorId);
        }

        // 5. Ultimate safeguard - find first Admin/SuperAdmin if everything is null
        if ($actorId === null) {
            error_log("AppraisalRoutingEngine: WARNING - All lookups returned null. Falling back to global system administrator.");
            $stmtGlobalAdmin = $this->db->query("
                SELECT u.employee_id 
                FROM users u 
                JOIN user_roles ur ON u.id = ur.user_id 
                JOIN employees e ON u.employee_id = e.id
                WHERE ur.role_id IN (1, 2) AND u.is_active = 1 AND e.status = 'active'
                LIMIT 1
            ");
            $actorId = $stmtGlobalAdmin->fetchColumn() ? (int)$stmtGlobalAdmin->fetchColumn() : null;

            if ($actorId === null) {
                // If even the DB has no admins, we return the employee's own manager or throw
                throw new \Exception("Routing engine failure: No valid approver or system administrator could be resolved.", 500);
            }
        }

        return (int)$actorId;
    }

    /**
     * Resolves the primary role for the employee without fallbacks.
     */
    private function resolveRoleDirectly(string $role, array $info): ?int
    {
        $role = strtoupper(trim($role));
        $employeeId = (int)$info['employee_id'];
        $departmentId = $info['department_id'] ? (int)$info['department_id'] : null;
        $companyId = (int)($info['company_id'] ?? 1);

        switch ($role) {
            case 'EMPLOYEE':
            case 'SELF':
                return $employeeId;

            case 'L1_MANAGER':
            case 'REPORTING_MANAGER':
                // Check current manager, fallback to snapshot manager
                return $info['reporting_manager_id'] ? (int)$info['reporting_manager_id'] : 
                       ($info['snapshot_manager_id'] ? (int)$info['snapshot_manager_id'] : null);

            case 'L2_MANAGER':
                $l1 = $info['reporting_manager_id'] ?: $info['snapshot_manager_id'];
                if ($l1) {
                    $stmt = $this->db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
                    $stmt->execute([$l1]);
                    $l2 = $stmt->fetchColumn();
                    return $l2 ? (int)$l2 : null;
                }
                return null;

            case 'L3_MANAGER':
                $l1 = $info['reporting_manager_id'] ?: $info['snapshot_manager_id'];
                if ($l1) {
                    $stmt = $this->db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
                    $stmt->execute([$l1]);
                    $l2 = $stmt->fetchColumn();
                    if ($l2) {
                        $stmt->execute([$l2]);
                        $l3 = $stmt->fetchColumn();
                        return $l3 ? (int)$l3 : null;
                    }
                }
                return null;

            case 'DEPARTMENT_HEAD':
            case 'HOD':
                // Resolve department head:
                // 1. Search for any employee in the same department whose designation has level <= 4 and title contains Manager/Head/Director/Lead.
                if ($departmentId) {
                    $stmtDeptHead = $this->db->prepare("
                        SELECT e.id 
                        FROM employees e
                        JOIN designations d ON e.designation_id = d.id
                        WHERE e.department_id = ? AND d.level <= 4 
                          AND (d.title LIKE '%Manager%' OR d.title LIKE '%Head%' OR d.title LIKE '%Director%' OR d.title LIKE '%Lead%')
                          AND e.status = 'active'
                        ORDER BY d.level ASC, e.id ASC
                        LIMIT 1
                    ");
                    $stmtDeptHead->execute([$departmentId]);
                    $deptHead = $stmtDeptHead->fetchColumn();
                    if ($deptHead) return (int)$deptHead;
                }
                // 2. Fall back to employee's L2 manager
                $l1 = $info['reporting_manager_id'] ?: $info['snapshot_manager_id'];
                if ($l1) {
                    $stmt = $this->db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
                    $stmt->execute([$l1]);
                    $l2 = $stmt->fetchColumn();
                    return $l2 ? (int)$l2 : null;
                }
                return null;

            case 'BUSINESS_UNIT_HEAD':
            case 'BU_HEAD':
                // Find Country Manager, General Manager, or Level <= 3 Director in same company
                $stmtBU = $this->db->prepare("
                    SELECT e.id 
                    FROM employees e
                    JOIN designations d ON e.designation_id = d.id
                    JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                    WHERE ec.company_id = ? AND d.level <= 3
                      AND (d.title LIKE '%Country Manager%' OR d.title LIKE '%General Manager%' OR d.title LIKE '%Director%' OR d.title LIKE '%CFO%' OR d.title LIKE '%CEO%')
                      AND e.status = 'active'
                    ORDER BY d.level ASC, e.id ASC
                    LIMIT 1
                ");
                $stmtBU->execute([$companyId]);
                $buHead = $stmtBU->fetchColumn();
                return $buHead ? (int)$buHead : null;

            case 'HR_BP':
            case 'HR_MANAGER':
                // Find HR Manager/Assistant in the company
                $stmtHR = $this->db->prepare("
                    SELECT e.id 
                    FROM employees e
                    JOIN users u ON e.id = u.employee_id
                    JOIN user_roles ur ON u.id = ur.user_id
                    JOIN roles r ON ur.role_id = r.id
                    JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                    WHERE ec.company_id = ? AND r.id IN (3, 5) AND e.status = 'active'
                    ORDER BY r.id ASC, e.id ASC
                    LIMIT 1
                ");
                $stmtHR->execute([$companyId]);
                $hrId = $stmtHR->fetchColumn();
                if ($hrId) return (int)$hrId;

                // Fallback: look for designation containing HR
                $stmtHRDesig = $this->db->prepare("
                    SELECT e.id
                    FROM employees e
                    JOIN designations d ON e.designation_id = d.id
                    JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                    WHERE ec.company_id = ? AND (d.title LIKE '%HR%' OR d.title LIKE '%Human Resource%') AND e.status = 'active'
                    ORDER BY d.level ASC, e.id ASC
                    LIMIT 1
                ");
                $stmtHRDesig->execute([$companyId]);
                $hrId = $stmtHRDesig->fetchColumn();
                return $hrId ? (int)$hrId : null;

            case 'HR_ADMIN':
            case 'HR_HEAD':
            case 'CHRO':
                // HR Manager or Global Admins
                $stmtHRHead = $this->db->prepare("
                    SELECT e.id 
                    FROM employees e
                    JOIN users u ON e.id = u.employee_id
                    JOIN user_roles ur ON u.id = ur.user_id
                    JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                    WHERE ec.company_id = ? AND ur.role_id IN (2, 3) AND e.status = 'active'
                    ORDER BY ur.role_id ASC, e.id ASC
                    LIMIT 1
                ");
                $stmtHRHead->execute([$companyId]);
                $hrHeadId = $stmtHRHead->fetchColumn();
                return $hrHeadId ? (int)$hrHeadId : null;

            case 'BOARD_MEMBER':
            case 'BOARD_DIRECTOR':
                // Designation level <= 2 or Admin/SuperAdmin
                $stmtBoard = $this->db->prepare("
                    SELECT e.id 
                    FROM employees e
                    JOIN designations d ON e.designation_id = d.id
                    JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                    WHERE ec.company_id = ? AND d.level <= 2 AND e.status = 'active'
                    ORDER BY d.level ASC, e.id ASC
                    LIMIT 1
                ");
                $stmtBoard->execute([$companyId]);
                $boardId = $stmtBoard->fetchColumn();
                if ($boardId) return (int)$boardId;

                // Fallback to Admin/SuperAdmin users
                $stmtAdminUser = $this->db->query("
                    SELECT u.employee_id 
                    FROM users u
                    JOIN user_roles ur ON u.id = ur.user_id
                    WHERE ur.role_id IN (1, 2) AND u.is_active = 1 AND u.employee_id IS NOT NULL
                    ORDER BY ur.role_id ASC
                    LIMIT 1
                ");
                $adminId = $stmtAdminUser->fetchColumn();
                return $adminId ? (int)$adminId : null;

            case 'SUPER_ADMIN':
            case 'SYSTEM_HR_ADMIN':
                // Super Admin
                $stmtSuper = $this->db->query("
                    SELECT u.employee_id 
                    FROM users u 
                    JOIN user_roles ur ON u.id = ur.user_id 
                    WHERE ur.role_id = 1 AND u.is_active = 1 AND u.employee_id IS NOT NULL 
                    LIMIT 1
                ");
                $superId = $stmtSuper->fetchColumn();
                return $superId ? (int)$superId : null;

            default:
                return null;
        }
    }

    /**
     * Resolves the fallback actor based on the fallback hierarchy sequence.
     */
    private function resolveFallbackActor(int $appraisalId, int $cycleId, int $companyId, array $info): ?int
    {
        // 1. Try to load fallback from cycle snapshot
        $stmt = $this->db->prepare("
            SELECT role 
            FROM snapshot_fallback_hierarchies 
            WHERE cycle_id = ? AND company_id = ? 
            ORDER BY sequence_order ASC
        ");
        $stmt->execute([$cycleId, $companyId]);
        $fallbackRoles = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // 2. If empty, load fallback from global settings
        if (empty($fallbackRoles)) {
            $stmt = $this->db->prepare("
                SELECT role 
                FROM appraisal_fallback_hierarchies 
                WHERE company_id = ? 
                ORDER BY sequence_order ASC
            ");
            $stmt->execute([$companyId]);
            $fallbackRoles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        // 3. Absolute default if table is completely unpopulated
        if (empty($fallbackRoles)) {
            $fallbackRoles = ['L1_MANAGER', 'L2_MANAGER', 'DEPARTMENT_HEAD', 'HR_MANAGER', 'SUPER_ADMIN'];
        }

        // Evaluate roles sequentially
        foreach ($fallbackRoles as $fallbackRole) {
            $actorId = $this->resolveRoleDirectly($fallbackRole, $info);
            if ($actorId !== null) {
                // Verify the employee is active
                $stmtCheck = $this->db->prepare("SELECT status FROM employees WHERE id = ?");
                $stmtCheck->execute([$actorId]);
                $status = $stmtCheck->fetchColumn();
                if ($status === 'active') {
                    error_log("AppraisalRoutingEngine: Resolved fallback actor ID $actorId for role '$fallbackRole'");
                    return $actorId;
                }
            }
        }

        return null;
    }

    /**
     * Resolves time-bound approvals delegation with a circular delegation check.
     */
    public function resolveDelegation(int $actorId): int
    {
        $currentActorId = $actorId;
        $visited = [$currentActorId];
        $depth = 0;
        $maxDepth = 10;
        $today = date('Y-m-d');

        while ($depth < $maxDepth) {
            $stmt = $this->db->prepare("
                SELECT delegatee_id 
                FROM approval_delegations 
                WHERE delegator_id = ? 
                  AND start_date <= ? 
                  AND end_date >= ? 
                  AND status = 'active'
                ORDER BY id DESC 
                LIMIT 1
            ");
            $stmt->execute([$currentActorId, $today, $today]);
            $delegateeId = $stmt->fetchColumn();

            if (!$delegateeId) {
                // No active delegation found for this node
                break;
            }

            $delegateeId = (int)$delegateeId;

            // Circular delegation check
            if (in_array($delegateeId, $visited, true)) {
                error_log("AppraisalRoutingEngine: WARNING - Circular delegation loop detected for employee $actorId. Aborting delegation routing.");
                // Break out and return the current actor to prevent loop
                return $currentActorId;
            }

            error_log("AppraisalRoutingEngine: Found delegation from $currentActorId to $delegateeId");
            $visited[] = $delegateeId;
            $currentActorId = $delegateeId;
            $depth++;
        }

        return $currentActorId;
    }
}
