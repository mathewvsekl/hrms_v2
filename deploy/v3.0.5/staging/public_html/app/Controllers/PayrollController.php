<?php

namespace App\Controllers;

use App\Core\Controller;

class PayrollController extends Controller
{
    private $db;
    private $service;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->service = new \App\Services\PayrollService();
    }

    private function requireAccess()
    {
        if (!$this->isInternal) {
            $user = \App\Middleware\AuthMiddleware::getUser();
            error_log("Payroll Auth User: " . print_r($user, true));
            if (!$user || !in_array(strtoupper($user['role'] ?? ''), ['SUPER_ADMIN', 'SUPERADMIN', 'ADMIN', 'HR_MANAGER', 'HR_ASSISTANT'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get aggregate payroll summary for the dashboard
     */
    public function getSummary()
    {
        if (!$this->requireAccess()) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $summary = $this->service->getSummary($_SESSION ?? []);
            return $this->jsonResponse($summary, 200, 'Payroll summary retrieved successfully');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Trigger generation of Uganda payroll for a given month and year
     */
    public function generate()
    {
        if (!$this->requireAccess()) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $month = isset($data['month']) ? (int)$data['month'] : (int)date('m');
            $year = isset($data['year']) ? (int)$data['year'] : (int)date('Y');
            $companyId = isset($data['company_id']) ? (int)$data['company_id'] : 1;

            $result = $this->service->generatePayroll($month, $year, $companyId);

            if ($result['success']) {
                return $this->jsonResponse($result, 200, $result['message']);
            } else {
                return $this->jsonResponse($result, 400, $result['message']);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get generated payroll records for a given month and year
     */
    public function getRecords()
    {
        if (!$this->requireAccess()) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 1;

            $stmt = $this->db->prepare("
                SELECT pr.*, e.employee_code as emp_code, e.first_name, e.last_name, d.title as designation_name,
                       pr.month, pr.year, pr.status as run_status
                FROM payroll_records pr
                JOIN employees e ON pr.employee_id = e.id
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
                LEFT JOIN designations d ON e.designation_id = d.id
                WHERE pr.month = ? AND pr.year = ? AND ec.company_id = ?
                ORDER BY e.first_name ASC
            ");
            $stmt->execute([$month, $year, $companyId]);
            $records = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($records, 200, 'Records retrieved');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get full payslip data for rendering
     */
    public function getPayslip($id)
    {
        if (!$this->requireAccess()) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $data = $this->service->getPayslipData((int)$id);
            if (!$data) {
                return $this->jsonResponse(null, 404, 'Payslip not found');
            }
            return $this->jsonResponse($data, 200, 'Payslip data retrieved');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error generating payslip data");
        }
    }

    public function updateRecord(array $params)
    {
        $this->requireAccess('admin');
        
        $id = isset($params['id']) ? (int)$params['id'] : 0;
        if (!$id) {
            return $this->jsonResponse(null, 400, "Invalid record ID");
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $basicPay = isset($data['basic_pay']) ? (float)$data['basic_pay'] : 0.0;
        $commissions = isset($data['commissions']) ? (float)$data['commissions'] : 0.0;
        $otherEarnings = isset($data['other_earnings']) ? (float)$data['other_earnings'] : 0.0;
        
        $earnings = isset($data['earnings']) ? $data['earnings'] : null;
        $deductions = isset($data['deductions']) ? $data['deductions'] : null;

        $result = $this->service->updateRecord($id, $basicPay, $commissions, $otherEarnings, $earnings, $deductions);

        if ($result['success']) {
            return $this->jsonResponse($result, 200, $result['message']);
        } else {
            return $this->jsonResponse(null, 400, $result['message']);
        }
    }

    public function deleteRecord($id)
    {
        $this->requireAccess('admin');
        
        $id = (int)$id;
        if (!$id) {
            return $this->jsonResponse(null, 400, "Invalid record ID");
        }

        $result = $this->service->deleteRecord($id);

        if ($result['success']) {
            return $this->jsonResponse($result, 200, $result['message']);
        } else {
            return $this->jsonResponse(null, 400, $result['message']);
        }
    }
}
