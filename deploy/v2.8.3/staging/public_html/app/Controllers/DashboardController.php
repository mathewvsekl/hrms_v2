<?php

namespace App\Controllers;

use App\Core\Controller;

class DashboardController extends Controller
{
    public function getSummary()
    {
        $userId = $_SESSION['user_id'] ?? 0;
        $params = $_GET;
        $companyId = $params['company_id'] ?? $_SESSION['scope_company_id'] ?? 0;
        
        // Performance Audit Fix: Dashboard Data Caching
        $cacheKey = "dashboard_summary_u{$userId}_c{$companyId}";
        $cachedData = (isset($_GET['force']) && $_GET['force'] == 1) ? null : \App\Helpers\CacheHelper::get($cacheKey);
        
        if ($cachedData) {
            return $this->jsonResponse($cachedData, 200, "Dashboard summary retrieved from cache.");
        }

        try {
            $data = [
                'notifications' => [],
                'organization_settings' => [],
                'attendance_statuses' => [],
                'attendance_countries' => [],
                'employee_stats' => [],
                'payroll_summary' => [],
                'appraisal_stats' => [],
                'today_attendance' => []
            ];
            $attController = new AttendanceController();
            $notifController = new NotificationController();
            $orgController = new OrganizationController();
            $empController = new EmployeeController();
            $payrollController = new PayrollController();
            $appraisalController = new AppraisalController();

            // Set all to internal mode to prevent exit()
            $attController->setInternal();
            $notifController->setInternal();
            $orgController->setInternal();
            $empController->setInternal();
            $payrollController->setInternal();
            $appraisalController->setInternal();

            $userRole = strtoupper($_SESSION['user_role'] ?? 'EMPLOYEE');
            $isMultiOfficeRole = in_array($userRole, ['SUPERADMIN', 'SUPER_ADMIN', 'ADMIN', 'HRMANAGER', 'HR_MANAGER', 'HRASSISTANT', 'HR_ASSISTANT', 'COUNTRYMANAGER', 'COUNTRY_MANAGER', 'COUNTRY MANAGER']);

            // Only force the primary company scope if the user is NOT in a multi-office role
            // or if they explicitly requested a specific company.
            if (empty($params['company_id']) && !empty($_SESSION['scope_company_id']) && !$isMultiOfficeRole) {
                $params['company_id'] = $_SESSION['scope_company_id'];
            }

            // Aggregated Data Collection with isolated fault tolerance
            $data['today_attendance'] = $this->safeCall(fn() => $attController->getLogs(array_merge($params, ['date' => date('Y-m-d')]))->getData(), []);
            $data['notifications'] = $this->safeCall(fn() => $notifController->index($params)->getData(), []);
            $data['organization_settings'] = $this->safeCall(fn() => $orgController->listSettings()->getData(), []);
            $data['attendance_statuses'] = $this->safeCall(fn() => $attController->getAttendanceStatuses($params)->getData(), []);
            $data['attendance_countries'] = $this->safeCall(fn() => $attController->getCountries()->getData(), []);
            $data['employee_stats'] = $this->safeCall(fn() => $empController->getDashboardStats()->getData(), ['total_count' => 0, 'status_stats' => [], 'country_stats' => []]);
            $data['payroll_summary'] = $this->safeCall(fn() => $payrollController->getSummary()->getData(), []);
            $data['appraisal_stats'] = $this->safeCall(fn() => $appraisalController->getStats()->getData(), []);

            // Cache for 1 minute to balance freshness and performance
            \App\Helpers\CacheHelper::set($cacheKey, $data, 60);

            return $this->jsonResponse($data);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Dashboard data error: " . $e->getMessage());
        }
    }

    private function safeCall($callback, $fallback = null)
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            error_log("Dashboard Aggregation Fault: " . $e->getMessage());
            return $fallback;
        }
    }
}
