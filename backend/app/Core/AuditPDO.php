<?php

namespace App\Core;

use PDO;
use Exception;
use App\Helpers\DataSanitizer;

/**
 * AuditPDO
 * Wrapper to intercept queries and maintain the Audit Trail.
 */
class AuditPDO extends PDO
{
    private static $auditLogs = [];
    private $inTransaction = false;
    public $isInternalQuery = false;

    // Temporary storage for "before" states during a single request
    private $pendingAudits = [];

    public function __construct($dsn, $username = null, $password = null, $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        // Bind the custom statement class so we can intercept execute()
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, ['App\Core\AuditPDOStatement', [$this]]);
    }

    public function exec(string $statement): int|false
    {
        $this->handlePreExecute($statement, null);
        $result = parent::exec($statement);
        $this->handlePostExecute($statement, null, null); // For exec, we can't easily get 'after' state rows
        return $result;
    }

    /**
     * Intercept query execution to fetch "before" state.
     */
    public function handlePreExecute($queryString, $params)
    {
        if ($this->isInternalQuery) return;

        // Don't audit the audit logs
        if (stripos($queryString, 'audit_logs') !== false) {
            // Immutability Check
            if (preg_match('/^\s*(UPDATE|DELETE)\s/i', $queryString)) {
                throw new Exception("Security Violation: audit_logs table is immutable. UPDATE and DELETE are strictly forbidden.");
            }
            return;
        }

        $action = null;
        if (preg_match('/^\s*INSERT\s+INTO\s+([`\w]+)/i', $queryString, $matches)) {
            $action = 'CREATE';
            $table = str_replace('`', '', $matches[1]);
        } elseif (preg_match('/^\s*UPDATE\s+([`\w]+)/i', $queryString, $matches)) {
            $action = 'UPDATE';
            $table = str_replace('`', '', $matches[1]);
        } elseif (preg_match('/^\s*DELETE\s+FROM\s+([`\w]+)/i', $queryString, $matches)) {
            $action = 'DELETE';
            $table = str_replace('`', '', $matches[1]);
        }

        if ($action) {
            $auditEntry = [
                'action' => $action,
                'table' => $table,
                'query' => $queryString,
                'params' => $params,
                'before' => null,
                'after' => null,
                'target_id' => null
            ];
            
            // For simple queries we can try to extract ID. In a real system, parsing WHERE is complex.
            // For this basic implementation, we just store the payload (query + params).
            // Advanced implementations would parse WHERE or require explicit target_ids.
            // Since we can't perfectly parse raw SQL safely, we will log the query params as the payload.
            
            $this->pendingAudits[] = $auditEntry;
        }
    }

    /**
     * Intercept query execution to fetch "after" state.
     */
    public function handlePostExecute($queryString, $params, $stmt)
    {
        if (empty($this->pendingAudits)) return;

        // Pop the latest pending audit
        $auditEntry = array_pop($this->pendingAudits);

        // Sanitize parameters for payload
        $payloadData = [
            'query' => $auditEntry['query'],
            'params' => $auditEntry['params'] ? DataSanitizer::sanitize((array)$auditEntry['params']) : null
        ];

        self::$auditLogs[] = [
            'action' => $auditEntry['action'],
            'module' => $this->getModuleFromUri(),
            'entity_type' => $auditEntry['table'],
            'entity_id' => null, // Would require complex SQL parsing or inserted_id
            'payload' => json_encode($payloadData),
            'user_id' => $_SESSION['user_id'] ?? null,
            'role_id' => $_SESSION['role_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System'
        ];
    }

    /**
     * Explicitly push an event to the audit queue (e.g. for failed logins, or non-db events)
     */
    public static function logExplicitAction($action, $module, $entityType, $entityId, $payloadArray)
    {
        $payloadData = $payloadArray ? DataSanitizer::sanitize($payloadArray) : null;
        self::$auditLogs[] = [
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => json_encode($payloadData),
            'user_id' => $_SESSION['user_id'] ?? null,
            'role_id' => $_SESSION['role_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/System'
        ];
    }

    private function getModuleFromUri()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
        if (preg_match('/^\/api\/([a-zA-Z0-9_-]+)/', $uri, $matches)) {
            return strtoupper($matches[1]);
        }
        return 'SYSTEM';
    }

    public static function flushLogs(AuditPDO $pdo)
    {
        if (empty(self::$auditLogs)) {
            error_log("AuditPDO DEBUG: flushLogs called but self::\$auditLogs is empty.");
            return;
        }

        error_log("AuditPDO DEBUG: flushLogs starting with " . count(self::$auditLogs) . " entries.");

        $pdo->isInternalQuery = true;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, role_id, action, module, entity_type, entity_id, payload, ip_address, user_agent)
                VALUES (:user_id, :role_id, :action, :module, :entity_type, :entity_id, :payload, :ip_address, :user_agent)
            ");

            foreach (self::$auditLogs as $log) {
                $stmt->execute([
                    'user_id' => $log['user_id'],
                    'role_id' => $log['role_id'],
                    'action' => $log['action'],
                    'module' => $log['module'],
                    'entity_type' => $log['entity_type'],
                    'entity_id' => $log['entity_id'],
                    'payload' => $log['payload'],
                    'ip_address' => $log['ip_address'],
                    'user_agent' => $log['user_agent']
                ]);
            }
            error_log("AuditPDO DEBUG: Successfully flushed logs.");
        } catch (Exception $e) {
            error_log("AuditPDO DEBUG: EXCEPTION in flushLogs: " . $e->getMessage());
            error_log("Failed to flush audit logs: " . $e->getMessage());
        }

        $pdo->isInternalQuery = false;
        self::$auditLogs = [];
    }
}
