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

            $userRoleStr = str_replace([' ', '_'], '', strtoupper($_SESSION['user_role'] ?? 'EMPLOYEE'));
            $isMultiOfficeRole = false;
            $multiOfficeTargets = ['SUPERADMIN', 'ADMIN', 'HRMANAGER', 'HRASSISTANT', 'COUNTRYMANAGER'];
            foreach ($multiOfficeTargets as $target) {
                if ($userRoleStr === $target || strpos($userRoleStr, 'OFFICE' . $target) !== false || strpos($userRoleStr, 'GLOBAL' . $target) !== false) {
                    $isMultiOfficeRole = true;
                    break;
                }
            }

            // Only force the primary company scope if the user is NOT in a multi-office role
            // or if they explicitly requested a specific company.
            if (empty($params['company_id']) && !empty($_SESSION['scope_company_id']) && !$isMultiOfficeRole) {
                $params['company_id'] = $_SESSION['scope_company_id'];
            }

            // Aggregated Data Collection with isolated fault tolerance
            $data['today_attendance'] = $this->safeCall(fn() => $attController->getLogs(array_merge($params, ['date' => date('Y-m-d')]))->getData(), [], 'attendance_logs');
            $data['notifications'] = $this->safeCall(fn() => $notifController->index($params)->getData(), [], 'notifications');
            $data['organization_settings'] = $this->safeCall(fn() => $orgController->listSettings()->getData(), [], 'organization_settings');
            $data['attendance_statuses'] = $this->safeCall(fn() => $attController->getAttendanceStatuses($params)->getData(), [], 'attendance_statuses');
            $data['attendance_countries'] = $this->safeCall(fn() => $attController->getCountries()->getData(), [], 'attendance_countries');
            $data['employee_stats'] = $this->safeCall(fn() => $empController->getDashboardStats()->getData(), ['total_count' => 0, 'status_stats' => [], 'country_stats' => []], 'employee_stats');
            $data['payroll_summary'] = $this->safeCall(fn() => $payrollController->getSummary()->getData(), [], 'payroll_summary');
            $data['appraisal_stats'] = $this->safeCall(fn() => $appraisalController->getStats()->getData(), [], 'appraisal_stats');

            // Action Required: Fetch pending approvals
            $data['pending_approvals'] = $this->getPendingApprovals();

            // Cache for 1 minute to balance freshness and performance
            \App\Helpers\CacheHelper::set($cacheKey, $data, 60);

            return $this->jsonResponse($data);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Dashboard data error: " . $e->getMessage());
        }
    }

    private function getPendingApprovals()
    {
        $pending = [];
        $db = \Database::getInstance()->getConnection();
        
        try {
            $leaveService = new \App\Services\LeaveService();
            $appraisalService = new \App\Services\AppraisalService();
            $userRole = strtoupper($_SESSION['user_role'] ?? 'EMPLOYEE');
            $isGlobalAdmin = in_array($userRole, ['SUPERADMIN', 'SUPER_ADMIN']);

            // 1. Pending Leave Requests (To be approved by me/HR)
            try {
                $leaves = $leaveService->fetchRequests(['status' => 'pending,cancel_requested,draft'], $_SESSION, $isGlobalAdmin);
                foreach ($leaves as $l) {
                    $pending[] = [
                        'id' => $l['id'],
                        'type' => 'leave',
                        'title' => $l['status'] === 'draft' ? 'Draft Leave Request' : 'Pending Leave Approval',
                        'subtitle' => $l['first_name'] . ' ' . $l['last_name'] . ' (' . $l['leave_type_name'] . ')',
                        'date' => $l['start_date'],
                        'end_date' => $l['end_date'],
                        'status' => $l['status'],
                        'leave_type_id' => $l['leave_type_id'],
                        'link' => ($l['employee_id'] == ($_SESSION['scope_employee_id'] ?? 0)) ? '/leave?tab=my_leaves&id=' . $l['id'] : '/leave?tab=requests&id=' . $l['id']
                    ];
                }
            } catch (\Exception $e) {
                error_log("Dashboard ERROR (Leaves): " . $e->getMessage());
            }

            // 2. Pending Appraisals
            $appraisals = $appraisalService->listAppraisals($_SESSION, $userRole);
            $targetStatuses = [];
            if ($isGlobalAdmin) $targetStatuses = ['manager_review', 'hr_review'];
            else if (str_contains($userRole, 'HR')) $targetStatuses = ['hr_review'];
            else $targetStatuses = ['manager_review'];

            foreach ($appraisals as $a) {
                if (in_array($a['status'], $targetStatuses)) {
                    $pending[] = [
                        'id' => $a['id'],
                        'type' => 'appraisal',
                        'title' => 'Appraisal Review',
                        'subtitle' => $a['first_name'] . ' ' . $a['last_name'] . ' (' . $a['cycle_name'] . ')',
                        'date' => $a['created_at_utc'] ?? null,
                        'status' => $a['status'],
                        'link' => '/appraisals/' . $a['id']
                    ];
                }
            }

            // 3. Pending Payroll Approvals
            try {
                $payrollQuery = "
                    SELECT pr.month, pr.year, pr.company_id, c.name as company_name, MAX(pr.created_at) as max_created_at, COUNT(pr.id) as record_count
                    FROM payroll_records pr
                    JOIN companies c ON pr.company_id = c.id
                    WHERE pr.status = 'Pending Approval'
                ";
                
                $payrollParams = [];
                $payrollWhere = [];
                if (!$isGlobalAdmin) {
                    $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                    if (!empty($associatedCompanyIds)) {
                        foreach ($associatedCompanyIds as $idx => $cid) {
                            $key = "pay_cid_$idx";
                            $payrollParams[$key] = $cid;
                            $payrollWhere[] = "pr.company_id = :$key";
                        }
                        $payrollWhere = ["(" . implode(' OR ', $payrollWhere) . ")"];
                    } else {
                        $payrollWhere[] = "1=0";
                    }
                }
                
                if ($payrollWhere) {
                    $payrollQuery .= " AND " . implode(' AND ', $payrollWhere);
                }

                $payrollQuery .= " GROUP BY pr.month, pr.year, pr.company_id, c.name ORDER BY pr.year DESC, pr.month DESC";
                $stmtPayroll = $db->prepare($payrollQuery);
                $stmtPayroll->execute($payrollParams);
                
                while ($pr = $stmtPayroll->fetch(\PDO::FETCH_ASSOC)) {
                    $monthName = date("F", mktime(0, 0, 0, $pr['month'], 10));
                    $pending[] = [
                        'id' => 'run_' . $pr['company_id'] . '_' . $pr['month'] . '_' . $pr['year'],
                        'type' => 'payroll',
                        'title' => 'Pending Payroll Approval',
                        'subtitle' => $pr['company_name'] . ' - ' . $monthName . ' ' . $pr['year'] . ' (' . $pr['record_count'] . ' Employees)',
                        'date' => $pr['max_created_at'],
                        'status' => 'Pending Approval',
                        'link' => '/payroll?company_id=' . $pr['company_id'] . '&month=' . $pr['month'] . '&year=' . $pr['year']
                    ];
                }
            } catch (\Exception $e) {
                error_log("Dashboard ERROR (Payroll): " . $e->getMessage());
            }

            // 4. Pending Attendance Logs
            // Attendance approval is not fully implemented, hiding from Action Required
            /*
            $attQuery = "
                SELECT DISTINCT al.*, e.first_name, e.last_name 
                FROM attendance_logs al
                JOIN employees e ON al.employee_id = e.id
                JOIN users u ON e.id = u.employee_id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                LEFT JOIN companies c ON ec.company_id = c.id
                WHERE al.approval_status = 'submitted'
            ";
            
            $attParams = [];
            $attWhere = [];
            if (!$isGlobalAdmin) {
                $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;
                
                if (!empty($associatedCompanyIds)) {
                    foreach ($associatedCompanyIds as $idx => $cid) {
                        $key = "att_cid_$idx";
                        $attParams[$key] = $cid;
                        $attWhere[] = "ec.company_id = :$key";
                    }
                    $attWhere = ["(" . implode(' OR ', $attWhere) . ")"];
                } else {
                    $attWhere[] = "1=0";
                }
            }
            
            if ($attWhere) {
                $attQuery .= " AND " . implode(' AND ', $attWhere);
            }

            $attQuery .= " ORDER BY al.attendance_date DESC LIMIT 10";
            $stmtAtt = $db->prepare($attQuery);
            $stmtAtt->execute($attParams);
            while ($att = $stmtAtt->fetch(\PDO::FETCH_ASSOC)) {
                $pending[] = [
                    'id' => $att['id'],
                    'type' => 'attendance',
                    'title' => 'Attendance Approval',
                    'subtitle' => $att['first_name'] . ' ' . $att['last_name'] . ' (Manual Log)',
                    'date' => $att['attendance_date'],
                    'status' => 'submitted',
                    'link' => '/attendance'
                ];
            }
            */

        } catch (\Throwable $e) {
            error_log("Dashboard Pending Approvals Error: " . $e->getMessage());
        }

        return $pending;
    }

    private function safeCall($callback, $fallback = null, $module = 'unknown')
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            $logFile = (defined('STORAGE_PATH') ? STORAGE_PATH : (defined('ROOT_PATH') ? ROOT_PATH : BASE_PATH) . '/storage') . '/dashboard_errors.log';
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Module: $module - " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            return $fallback;
        }
    }
}
