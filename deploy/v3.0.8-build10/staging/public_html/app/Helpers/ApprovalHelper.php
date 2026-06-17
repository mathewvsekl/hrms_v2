<?php

namespace App\Helpers;

/**
 * ApprovalHelper
 * 
 * Centralized logic for tracking approval lifecycles across HRMS modules.
 */
class ApprovalHelper
{
    /**
     * Log an approval action to the history audit trail
     * 
     * @param string $module 'leave', 'appraisal', or 'attendance'
     * @param int $referenceId The ID of the record being acted upon
     * @param string $action 'submitted', 'approved', 'rejected', 'returned', 'cancelled', 'finalized'
     * @param string|null $comment Optional comment provided by the actor
     */
    public static function log(string $module, int $referenceId, string $action, ?string $comment = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $actorId = $_SESSION['user_id'] ?? null;
        if (!$actorId) return false;

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO approval_history (module, reference_id, actor_id, action, comment, created_at_utc)
                VALUES (:module, :ref_id, :actor_id, :action, :comment, CURRENT_TIMESTAMP)
            ");
            
            return $stmt->execute([
                'module' => $module,
                'ref_id' => $referenceId,
                'actor_id' => $actorId,
                'action' => $action,
                'comment' => $comment
            ]);
        } catch (\Exception $e) {
            error_log("ApprovalHelper Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve approval history for a specific resource
     */
    public static function getHistory(string $module, int $referenceId): array
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT ah.*, u.username as actor_name, r.name as role_name
                FROM approval_history ah
                JOIN users u ON ah.actor_id = u.id
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id
                WHERE ah.module = :module AND ah.reference_id = :ref_id
                ORDER BY ah.created_at_utc DESC
            ");
            $stmt->execute(['module' => $module, 'ref_id' => $referenceId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
