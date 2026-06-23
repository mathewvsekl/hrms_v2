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

    private function requireAccess($action = 'view')
    {
        if (!$this->isInternal) {
            $user = \App\Middleware\AuthMiddleware::getUser();
            if (!$user || !\App\Middleware\RoleMiddleware::hasPermission('Payroll', $action)) {
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
        if (!$this->requireAccess('create')) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $month = isset($data['month']) ? (int)$data['month'] : (int)date('m');
            $year = isset($data['year']) ? (int)$data['year'] : (int)date('Y');
            $companyId = isset($data['company_id']) ? (int)$data['company_id'] : 1;
            $excludedEmployeeIds = isset($data['excluded_employee_ids']) && is_array($data['excluded_employee_ids']) ? $data['excluded_employee_ids'] : [];
            $reportingCurrency = isset($data['reporting_currency']) ? $data['reporting_currency'] : null;
            $exchangeRate = isset($data['exchange_rate']) ? (float)$data['exchange_rate'] : null;

            $result = $this->service->generatePayroll($month, $year, $companyId, $excludedEmployeeIds, $reportingCurrency, $exchangeRate);

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
     * Preview Uganda payroll for a given month and year
     */
    public function preview()
    {
        if (!$this->requireAccess('view')) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            $month = isset($data['month']) ? (int)$data['month'] : (int)date('m');
            $year = isset($data['year']) ? (int)$data['year'] : (int)date('Y');
            $companyId = isset($data['company_id']) ? (int)$data['company_id'] : 1;

            $reportingCurrency = isset($data['reporting_currency']) ? $data['reporting_currency'] : null;
            $exchangeRate = isset($data['exchange_rate']) ? (float)$data['exchange_rate'] : null;

            $result = $this->service->previewPayroll($month, $year, $companyId, $reportingCurrency, $exchangeRate);

            if ($result['success']) {
                return $this->jsonResponse($result['data'], 200, 'Preview calculated');
            } else {
                @file_put_contents(ROOT_PATH . '/tmp/400_debug.log', date('Y-m-d H:i:s') . " - 400 Error: " . $result['message'] . PHP_EOL, FILE_APPEND);
                return $this->jsonResponse(null, 400, $result['message']);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get aggregated payroll runs grouped by month, year, and company
     */
    public function getRuns()
    {
        if (!$this->requireAccess()) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $stmt = $this->db->prepare("
                SELECT pr.month, pr.year, pr.company_id, c.name as company_name, 
                       COUNT(DISTINCT pr.employee_id) as total_employees, 
                       SUM(pr.net_pay) as total_net_pay,
                       SUM(pr.basic_pay + pr.commissions + pr.other_earnings) as total_gross_pay,
                       MAX(pr.status) as status,
                       MAX(pr.reporting_currency) as reporting_currency,
                       MAX(pr.exchange_rate) as exchange_rate
                FROM payroll_records pr
                JOIN companies c ON pr.company_id = c.id
                GROUP BY pr.month, pr.year, pr.company_id, c.name
                ORDER BY pr.year DESC, pr.month DESC, c.name ASC
            ");
            $stmt->execute();
            $runs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($runs, 200, 'Runs retrieved');
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
                LEFT JOIN designations d ON e.designation_id = d.id
                WHERE pr.month = ? AND pr.year = ? AND pr.company_id = ?
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
        if (!$this->requireAccess('edit')) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }
        
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
        $advanceDeductions = isset($data['advance_deductions']) ? (float)$data['advance_deductions'] : null;

        try {
            $result = $this->service->updateRecord($id, $basicPay, $commissions, $otherEarnings, $earnings, $deductions, $advanceDeductions);

            if ($result['success']) {
                return $this->jsonResponse($result, 200, $result['message']);
            } else {
                return $this->jsonResponse(null, 400, $result['message']);
            }
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    public function deleteRecord($id)
    {
        if (!$this->requireAccess('delete')) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }
        
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

    public function submitApproval()
    {
        if (!$this->requireAccess('edit')) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $recordIds = isset($data['record_ids']) ? $data['record_ids'] : [];

        if (empty($recordIds) || !is_array($recordIds)) {
            return $this->jsonResponse(null, 400, 'No records selected for approval');
        }

        $result = $this->service->submitForApproval($recordIds);
        
        if ($result['success']) {
            return $this->jsonResponse($result, 200, $result['message']);
        } else {
            return $this->jsonResponse(null, 400, $result['message']);
        }
    }
    public function approveRecords()
    {
        if (!$this->requireAccess('approve')) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $recordIds = isset($data['record_ids']) ? $data['record_ids'] : [];

        if (empty($recordIds) || !is_array($recordIds)) {
            return $this->jsonResponse(null, 400, 'No records selected for approval');
        }

        $result = $this->service->approveRecords($recordIds);
        
        if ($result['success']) {
            return $this->jsonResponse($result, 200, $result['message']);
        } else {
            return $this->jsonResponse(null, 400, $result['message']);
        }
    }

    public function rejectRecords()
    {
        if (!$this->requireAccess('approve')) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $recordIds = isset($data['record_ids']) ? $data['record_ids'] : [];

        if (empty($recordIds) || !is_array($recordIds)) {
            return $this->jsonResponse(null, 400, 'No records selected for rejection');
        }

        $result = $this->service->rejectRecords($recordIds);
        
        if ($result['success']) {
            return $this->jsonResponse($result, 200, $result['message']);
        } else {
            return $this->jsonResponse(null, 400, $result['message']);
        }
    }

    public function processPayment()
    {
        if (!$this->requireAccess('approve')) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $recordIds = isset($data['record_ids']) ? $data['record_ids'] : [];

        if (empty($recordIds) || !is_array($recordIds)) {
            return $this->jsonResponse(null, 400, 'No records selected for payment processing');
        }

        $result = $this->service->processPayment($recordIds, $_SESSION['user_id'] ?? null);
        
        if ($result['success']) {
            return $this->jsonResponse($result, 200, $result['message']);
        } else {
            return $this->jsonResponse(null, 400, $result['message']);
        }
    }
}
