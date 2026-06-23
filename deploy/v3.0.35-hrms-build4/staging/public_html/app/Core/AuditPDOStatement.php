<?php

namespace App\Core;

use PDOStatement;

/**
 * AuditPDOStatement
 * Intercepts execute() to log SELECT/INSERT/UPDATE/DELETE actions.
 */
class AuditPDOStatement extends PDOStatement
{
    private $pdo;

    protected function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function execute($params = null): bool
    {
        $queryString = $this->queryString;
        
        $debugLog = (defined('TMP_PATH') ? TMP_PATH : dirname(__DIR__, 2) . '/storage/tmp') . '/audit_debug.log';
        if (preg_match('/^\s*(UPDATE|INSERT|DELETE)/i', $queryString)) {
            @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - AuditPDOStatement::execute called for: $queryString\n", FILE_APPEND);
        }

        // Let AuditPDO handle the before/after capture logic for this query
        // before executing it.
        $this->pdo->handlePreExecute($queryString, $params);

        $result = parent::execute($params);

        // Handle post-execution logic
        $this->pdo->handlePostExecute($queryString, $params, $this);

        return $result;
    }
}
