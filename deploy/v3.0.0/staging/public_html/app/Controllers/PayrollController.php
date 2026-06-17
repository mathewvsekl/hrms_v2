<?php

namespace App\Controllers;

use App\Core\Controller;

class PayrollController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get aggregate payroll summary for the dashboard
     */
    public function getSummary()
    {
        // Require HR or Admin access (skip if internal call from Dashboard)
        if (!$this->isInternal) {
            $user = \App\Middleware\AuthMiddleware::getUser();
            if (!$user || !in_array(strtoupper($user['role'] ?? ''), ['SUPER_ADMIN', 'ADMIN', 'HR_MANAGER', 'HR_ASSISTANT'])) {
                return $this->jsonResponse(null, 401, 'Unauthorized');
            }
        }

        try {
            $service = new \App\Services\PayrollService();
            $summary = $service->getSummary($_SESSION);
            return $this->jsonResponse($summary, 200, 'Payroll summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }
}
