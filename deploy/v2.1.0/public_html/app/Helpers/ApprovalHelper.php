<?php

namespace App\Helpers;

/**
 * ApprovalHelper
 * 
 * Centralized utility for logging approval history across all modules.
 */
class ApprovalHelper
{
    /**
     * Log an approval action
     * 
     * @param string $module The module name (leave, appraisal, attendance)
     * @param int $referenceId The primary key of the record being acted upon
     * @param int $actorId The users.id of the person performing the action
     * @param string $action The action taken (submitted, approved, rejected, returned, cancelled, finalized)
     * @param string|null $comment Optional comments for the action
     * @return bool
     */
    public static function log($module, $referenceId, $actorId, $action, $comment = null)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO approval_history (module, reference_id, actor_id, action, comment)
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$module, $referenceId, $actorId, $action, $comment]);
        } catch (\Exception $e) {
            // Log error to system log or ignore for audit log failure to prevent breaking the flow
            error_log("ApprovalHelper Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get history for a specific record
     * 
     * @param string $module
     * @param int $referenceId
     * @return array
     */
    public static function getHistory($module, $referenceId)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT ah.*, u.username as actor_name, e.first_name, e.last_name
                FROM approval_history ah
                JOIN users u ON ah.actor_id = u.id
                LEFT JOIN employees e ON u.employee_id = e.id
                WHERE ah.module = ? AND ah.reference_id = ?
                ORDER BY ah.created_at_utc DESC
            ");
            $stmt->execute([$module, $referenceId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("ApprovalHelper Error: " . $e->getMessage());
            return [];
        }
    }
}
