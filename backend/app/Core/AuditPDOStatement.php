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
        
        if (preg_match('/^\s*(UPDATE|INSERT|DELETE)/i', $queryString)) {
            error_log("AuditPDOStatement DEBUG: execute called for: " . preg_replace('/\s+/', ' ', $queryString));
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
