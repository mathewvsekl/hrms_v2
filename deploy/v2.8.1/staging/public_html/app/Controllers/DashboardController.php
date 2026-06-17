<?php

namespace App\Controllers;

use App\Core\Controller;

class DashboardController extends Controller
{
    public function getSummary()
    {
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

            // Scope resolution for internal calls
            $params = $_GET;
            $userRole = $_SESSION['user_role'] ?? 'EMPLOYEE';
            $isMultiOfficeRole = in_array(strtoupper($userRole), ['HRMANAGER', 'HR_MANAGER', 'HRASSISTANT', 'HR_ASSISTANT', 'COUNTRYMANAGER', 'COUNTRY_MANAGER']);

            // Only force the primary company scope if the user is NOT in a multi-office role
            // or if they explicitly requested a specific company.
            if (empty($params['company_id']) && !empty($_SESSION['scope_company_id']) && !$isMultiOfficeRole) {
                $params['company_id'] = $_SESSION['scope_company_id'];
            }

            // Aggregated Data Collection
            // Ensure today's defaults (holidays/weekends) are persisted before fetching logs
            $attController->autoPersistDefaults(['date' => date('Y-m-d')]);

            $data['today_attendance'] = $attController->getLogs(array_merge($params, ['date' => date('Y-m-d')]))->getData();
            $data['notifications'] = $notifController->index($params)->getData();
            $data['organization_settings'] = $orgController->listSettings()->getData();
            $data['attendance_statuses'] = $attController->getAttendanceStatuses($params)->getData();
            $data['attendance_countries'] = $attController->getCountries()->getData();
            $data['employee_stats'] = $empController->getDashboardStats()->getData();
            $data['payroll_summary'] = $payrollController->getSummary()->getData();
            $data['appraisal_stats'] = $appraisalController->getStats()->getData();

            return $this->jsonResponse($data);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Dashboard data error: " . $e->getMessage());
        }
    }
}
