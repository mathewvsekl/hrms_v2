<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AuditService;

/**
 * AuditController
 * 
 * Secure administrative interface for reviewing system audit trails.
 */
class AuditController extends Controller
{
    private $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    /** GET /api/audit/logs */
    public function listLogs()
    {
        if (!$this->isGlobalAdmin()) {
            return $this->jsonResponse(null, 403, "Access Denied: Global Admin privilege required.");
        }

        try {
            $filters = [
                'entity_type' => $_GET['entity_type'] ?? null,
                'action' => $_GET['action'] ?? null,
                'user_id' => $_GET['user_id'] ?? null
            ];
            
            $logs = $this->auditService->listLogs($filters);
            return $this->jsonResponse($logs);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service Error: " . $e->getMessage());
        }
    }

    /** GET /api/audit/entity/{type}/{id} */
    public function getEntityHistory($type, $id)
    {
        // This can be scoped or restricted to managers
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'HRMANAGER'])) {
            return $this->jsonResponse(null, 403, "Insufficient permissions.");
        }

        try {
            $history = $this->auditService->getEntityHistory($type, (int)$id);
            return $this->jsonResponse($history);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service Error: " . $e->getMessage());
        }
    }
}
