<?php

namespace App\Services;

use PDO;

/**
 * AuditService
 * 
 * Centralized logging for system actions and data mutations.
 */
class AuditService
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Logs a system action or data change.
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): void {
        try {
            // Default to session user if not provided
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = (int)$_SESSION['user_id'];
            }

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, :user_agent)
            ");

            $stmt->execute([
                'user_id' => $userId,
                'action' => strtoupper($action),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System'
            ]);
        } catch (\Exception $e) {
            // Fail silently or log to a file to prevent audit failures from blocking business logic
            error_log("Audit Logging Failed: " . $e->getMessage());
        }
    }

    /**
     * Retrieves audit history for a specific entity.
     */
    public function getEntityHistory(string $entityType, int $entityId): array
    {
        $stmt = $this->db->prepare("
            SELECT al.*, u.username 
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.entity_type = :type AND al.entity_id = :id
            ORDER BY al.created_at_utc DESC
        ");
        $stmt->execute(['type' => $entityType, 'id' => $entityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves global audit logs with filtering options.
     */
    public function listLogs(array $filters = []): array
    {
        $query = "SELECT al.*, u.username 
                  FROM audit_logs al
                  LEFT JOIN users u ON al.user_id = u.id
                  WHERE 1=1";
        $params = [];

        if (!empty($filters['entity_type'])) {
            $query .= " AND al.entity_type = :entity_type";
            $params['entity_type'] = $filters['entity_type'];
        }
        if (!empty($filters['action'])) {
            $query .= " AND al.action = :action";
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $query .= " AND al.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }

        $query .= " ORDER BY al.created_at_utc DESC LIMIT 500";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
