<?php

namespace App\Services;

use App\Helpers\NotificationHelper;

/**
 * WorkflowNotificationRouter
 * 
 * Maps workflow lifecycle events (e.g. WORKFLOW_STEP_REACHED, WORKFLOW_COMPLETED, WORKFLOW_ESCALATED)
 * to recipient notifications and handles translation of employee IDs to system user IDs.
 */
class WorkflowNotificationRouter
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Route a workflow event to dispatch notifications to the appropriate users.
     */
    public function routeEvent(int $appraisalId, string $eventType, array $payload): void
    {
        error_log("WorkflowNotificationRouter: Routing event '$eventType' for appraisal $appraisalId");

        // Fetch employee name and metadata
        $stmtEmp = $this->db->prepare("
            SELECT e.first_name, e.last_name, ea.employee_id
            FROM employee_appraisals ea
            JOIN employees e ON ea.employee_id = e.id
            WHERE ea.id = ?
        ");
        $stmtEmp->execute([$appraisalId]);
        $emp = $stmtEmp->fetch(\PDO::FETCH_ASSOC);

        if (!$emp) {
            error_log("WorkflowNotificationRouter: Could not find appraisal metadata for ID $appraisalId");
            return;
        }

        $employeeName = $emp['first_name'] . ' ' . $emp['last_name'];
        $employeeId = (int)$emp['employee_id'];

        switch ($eventType) {
            case 'WORKFLOW_STEP_REACHED':
                $approverId = (int)($payload['approver_id'] ?? 0);
                $stepOrder = (int)($payload['step_order'] ?? 0);
                $role = $payload['role_required'] ?? '';

                if ($approverId > 0) {
                    $userId = $this->getUserIdFromEmployeeId($approverId);
                    if ($userId) {
                        NotificationHelper::send(
                            $userId,
                            'appraisal_pending_approval',
                            'Performance Appraisal Pending Approval',
                            "You have a pending appraisal approval request (Step $stepOrder: $role) for employee $employeeName.",
                            ['link' => "/appraisals/$appraisalId"],
                            true // Send email notification too!
                        );
                        error_log("WorkflowNotificationRouter: Sent pending approval notification to user $userId (Employee $approverId)");
                    } else {
                        error_log("WorkflowNotificationRouter: WARNING - No active user account found for employee approver $approverId");
                    }
                }
                break;

            case 'WORKFLOW_COMPLETED':
                $empUserId = $this->getUserIdFromEmployeeId($employeeId);
                if ($empUserId) {
                    NotificationHelper::send(
                        $empUserId,
                        'appraisal_completed',
                        'Performance Appraisal Completed',
                        "Your performance appraisal workflow has been completed by all managers and is now in HR Calibration.",
                        ['link' => "/appraisals/$appraisalId"],
                        true
                    );
                    error_log("WorkflowNotificationRouter: Sent completion notification to employee user $empUserId (Employee $employeeId)");
                }
                break;

            case 'WORKFLOW_ESCALATED':
                $escalatedApproverId = (int)($payload['escalated_approver_id'] ?? 0);
                $originalApproverId = (int)($payload['original_approver_id'] ?? 0);

                if ($escalatedApproverId > 0) {
                    $userId = $this->getUserIdFromEmployeeId($escalatedApproverId);
                    if ($userId) {
                        NotificationHelper::send(
                            $userId,
                            'appraisal_escalated',
                            'Escalated Performance Appraisal Request',
                            "An appraisal approval request for employee $employeeName has breached the SLA period and has been escalated to you.",
                            ['link' => "/appraisals/$appraisalId"],
                            true
                        );
                        error_log("WorkflowNotificationRouter: Sent escalation notification to user $userId (Employee $escalatedApproverId)");
                    }
                }
                break;
        }
    }

    /**
     * Resolves the system user ID for a given employee ID.
     */
    private function getUserIdFromEmployeeId(int $employeeId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE employee_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$employeeId]);
        $val = $stmt->fetchColumn();
        return $val ? (int)$val : null;
    }
}
