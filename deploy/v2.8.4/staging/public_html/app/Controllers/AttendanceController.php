<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * AttendanceController
 * 
 * Handles daily attendance tracking and manual log entry.
 */
class AttendanceController extends Controller
{
    /**
     * Fetch attendance logs filtered by date and company
     */
    public function getLogs($requestData)
    {
        $service = new \App\Services\AttendanceService();
        $logs = $service->fetchLogs($requestData, $_SESSION, $this->isGlobalAdmin());
        return $this->jsonResponse($logs);
    }

    /**
     * Logic-only version of getLogs for internal composition
     */
    public function fetchLogs($requestData)
    {
        $service = new \App\Services\AttendanceService();
        return $service->fetchLogs($requestData, $_SESSION, $this->isGlobalAdmin());
    }

    /**
     * Save a manual attendance entry (RBAC Protected)
     */
    public function saveManualEntry($requestData)
    {
        $userId = $_SESSION['user_id'] ?? null;
        $userRole = $_SESSION['user_role'] ?? 'employee';

        if ($userRole === 'employee') {
            return $this->jsonResponse(null, 403, "Employees are not permitted to log manual attendance.");
        }

        $employeeId = $requestData['employee_id'] ?? null;
        $date = $requestData['attendance_date'] ?? null;

        if (!$employeeId || !$date) {
            return $this->jsonResponse(null, 400, "Employee ID and Date are required.");
        }

        // Prevent future dates
        if (strtotime($date) > strtotime(date('Y-m-d'))) {
            return $this->jsonResponse(null, 400, "Attendance cannot be logged for a future date.");
        }

        $this->verifyDataScope(null, null, $employeeId);

        try {
            $service = new \App\Services\AttendanceService();
            $result = $service->saveManualEntry($requestData, (int)$userId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Save bulk attendance entries
     */
    public function saveBulkEntry($requestData)
    {
        $userId = $_SESSION['user_id'] ?? null;
        $userRole = $_SESSION['user_role'] ?? 'employee';

        if ($userRole === 'employee') {
            return $this->jsonResponse(null, 403, "Employees are not permitted to log manual attendance.");
        }

        $employeeIds = $requestData['employee_ids'] ?? [];
        $date = $requestData['attendance_date'] ?? null;

        if (empty($employeeIds) || !$date) {
            return $this->jsonResponse(null, 400, "Employee IDs and Date are required.");
        }

        if (!$this->isGlobalAdmin()) {
            $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];
            if (empty($myCompanyIds)) {
                return $this->jsonResponse(null, 403, "Security Violation: Access Denied.");
            }
        }

        // Prevent future dates
        if (strtotime($date) > strtotime(date('Y-m-d'))) {
            return $this->jsonResponse(null, 400, "Attendance cannot be logged for a future date.");
        }

        try {
            $service = new \App\Services\AttendanceService();
            $result = $service->saveBulkEntry($requestData, (int)$userId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Submit logs for approval
     */
    public function submitLogs($requestData)
    {
        $ids = $requestData['ids'] ?? [];
        if (empty($ids)) return $this->jsonResponse(null, 400, "No log IDs provided.");

        try {
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) return $this->jsonResponse(null, 401, "User session required.");
            
            $service = new \App\Services\AttendanceService();
            $scopeData = [
                'is_global_admin' => $this->isGlobalAdmin(),
                'associated_company_ids' => $_SESSION['associated_company_ids'] ?? []
            ];
            
            $count = $service->submitLogs($ids, (int)$userId, $scopeData);
            return $this->jsonResponse(['message' => "$count logs submitted for approval."]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Review logs (Approve/Reject)
     */
    public function reviewLogs($requestData)
    {
        $ids = $requestData['ids'] ?? [];
        $action = $requestData['action'] ?? null; // 'approved' or 'rejected'
        $remarks = $requestData['remarks'] ?? null;

        if (empty($ids) || !in_array($action, ['approved', 'rejected'])) {
            return $this->jsonResponse(null, 400, "Invalid request parameters.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) return $this->jsonResponse(null, 401, "User session required.");
            
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Security Audit Fix: Scope check for review
            $scopeFilter = "";
            $scopeParams = [$action, $userId, $remarks];
            if (!$this->isGlobalAdmin()) {
                $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                if (!empty($myCompanyIds)) {
                    $cList = implode(',', array_map('intval', $myCompanyIds));
                    $scopeFilter = " AND company_id IN ($cList)";
                } else {
                    $scopeFilter = " AND 1=0"; // Hard deny
                }
            }

            $stmt = $db->prepare("
                UPDATE attendance_logs 
                SET approval_status = ?, approved_by_id = ?, remarks = ? 
                WHERE id IN ($placeholders) $scopeFilter
            ");
            $params = array_merge($scopeParams, $ids);
            $stmt->execute($params);

            return $this->jsonResponse(['message' => count($ids) . " logs have been " . $action . "."]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Get audit history for a specific log
     */
    public function getAuditHistory($requestData)
    {
        $logId = $requestData['id'] ?? null;
        if (!$logId) return $this->jsonResponse(null, 400, "Log ID required.");

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT al.*, u.username as changed_by_name 
                FROM attendance_audit_logs al
                JOIN users u ON al.changed_by_id = u.id
                WHERE al.attendance_log_id = ?
                ORDER BY al.created_at_utc DESC
            ");
            $stmt->execute([$logId]);
            $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($history);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Helper to log changes
     */
    private function logChange($logId, $userId, $old, $new, $reason)
    {
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO attendance_audit_logs (attendance_log_id, changed_by_id, old_values, new_values, change_reason)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $logId,
            $userId,
            $old ? json_encode($old) : null,
            json_encode($new),
            $reason
        ]);
    }

    /**
     * Bulk set attendance status for a date range (used by Leave approval)
     * Now intelligently skips weekends/holidays if leave policy dictates it.
     */
    public function setLeaveAttendanceRange($employeeId, $startDate, $endDate, $status = 'on_leave', $isWorkingDayOnly = false, $companyId = null)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            $end->modify('+1 day');
            $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);

            $weekends = [];
            $holidays = [];

            if ($isWorkingDayOnly && $companyId) {
                // 1. Fetch weekends (Robust check for Off, Weekend, Holiday)
                $stmt = $db->prepare("SELECT day_of_week FROM office_weekly_schedules WHERE company_id = ? AND (status = 'Weekend' OR status = 'Off' OR status = 'Holiday')");
                $stmt->execute([$companyId]);
                $weekends = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                if (empty($weekends)) $weekends = ['Saturday', 'Sunday'];

                // 2. Fetch company-specific holidays in range
                $stmt = $db->prepare("SELECT holiday_date FROM holidays WHERE company_id = ? AND holiday_date BETWEEN ? AND ?");
                $stmt->execute([$companyId, $startDate, $endDate]);
                $holidays = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                // 3. Fetch public holidays (country-specific) in range
                $stmtC = $db->prepare("SELECT country_id FROM companies WHERE id = ?");
                $stmtC->execute([$companyId]);
                $cntryId = $stmtC->fetchColumn();

                if ($cntryId) {
                    $stmtPH = $db->prepare("SELECT holiday_date FROM public_holidays WHERE country_id = ? AND holiday_date BETWEEN ? AND ?");
                    $stmtPH->execute([$cntryId, $startDate, $endDate]);
                    $pubHolidays = $stmtPH->fetchAll(\PDO::FETCH_COLUMN);
                    $holidays = array_unique(array_merge($holidays, $pubHolidays));
                }
            }

            foreach ($period as $dt) {
                $dateStr = $dt->format("Y-m-d");
                $dayName = $dt->format("l");

                // If Working Day Only mode, we SKIP creating 'on_leave' logs for non-working days.
                // This allows the attendance system to fall back to 'weekend' or 'public_holiday'.
                if ($isWorkingDayOnly) {
                    if (in_array($dayName, $weekends)) continue;
                    if (in_array($dateStr, $holidays)) continue;
                }

                // Check if log already exists
                $stmt = $db->prepare("SELECT id FROM attendance_logs WHERE employee_id = :eid AND attendance_date = :date");
                $stmt->execute(['eid' => $employeeId, 'date' => $dateStr]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $stmt = $db->prepare("UPDATE attendance_logs SET status = :status, company_id = :cid, approval_status = 'approved', source = 'leave_module' WHERE id = :id");
                    $stmt->execute(['status' => $status, 'cid' => $companyId, 'id' => $existing['id']]);
                } else {
                    $stmt = $db->prepare("INSERT INTO attendance_logs (employee_id, company_id, attendance_date, status, approval_status, source) VALUES (:eid, :cid, :date, :status, 'approved', 'leave_module')");
                    $stmt->execute(['eid' => $employeeId, 'cid' => $companyId, 'date' => $dateStr, 'status' => $status]);
                }
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Remove attendance status for a date range (used when Leave is cancelled)
     */
    public function removeLeaveAttendanceRange($employeeId, $startDate, $endDate)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                DELETE FROM attendance_logs 
                WHERE employee_id = :eid 
                AND attendance_date BETWEEN :start AND :end 
                AND source = 'leave_module'
            ");
            return $stmt->execute([
                'eid' => $employeeId,
                'start' => $startDate,
                'end' => $endDate
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get attendance summary for a specific employee and month
     */
    public function getEmployeeSummary($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $employeeId = $requestData['employee_id'] ?? null;
            $month = $requestData['month'] ?? date('m');
            $year = $requestData['year'] ?? date('Y');

            if (!$employeeId) {
                return $this->jsonResponse(null, 400, "Employee ID is required.");
            }

            $this->verifyDataScope(null, null, $employeeId);


            // Fetch Employee Details (Country ID and Primary Company ID)
            $stmt = $db->prepare("
                SELECT e.id, comp.country_id, ec.company_id
                FROM employees e
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                JOIN companies comp ON ec.company_id = comp.id
                WHERE e.id = :eid
            ");
            $stmt->execute(['eid' => $employeeId]);
            $emp = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Fetch actual attendance logs using range query for index optimization
            $startDate = "$year-$month-01";
            $endDate = date("Y-m-t", strtotime($startDate));
            
            $stmt = $db->prepare("
                SELECT attendance_date, status, approval_status 
                FROM attendance_logs 
                WHERE employee_id = :eid 
                AND company_id = :cid
                AND attendance_date BETWEEN :start AND :end
            ");
            $stmt->execute(['eid' => $employeeId, 'cid' => $emp['company_id'], 'start' => $startDate, 'end' => $endDate]);
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Fetch Holidays & Weekly Schedules
            $holidays = [];
            $weekends = [];
            if ($emp) {
                // Public Holidays (Strictly based on primary country)
                $stmt = $db->prepare("
                    SELECT holiday_date, name 
                    FROM public_holidays 
                    WHERE country_id = :cntry_id AND holiday_date BETWEEN :start AND :end
                ");
                $stmt->execute(['cntry_id' => $emp['country_id'], 'start' => $startDate, 'end' => $endDate]);
                $pubHolidays = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($pubHolidays as $ph) {
                    $holidays[$ph['holiday_date']] = ['status' => 'public_holiday', 'name' => $ph['name']];
                }

                // Company-specific Holidays
                $stmt = $db->prepare("
                    SELECT holiday_date, name 
                    FROM holidays 
                    WHERE company_id = :comp_id AND holiday_date BETWEEN :start AND :end
                ");
                $stmt->execute(['comp_id' => $emp['company_id'], 'start' => $startDate, 'end' => $endDate]);
                $compHolidays = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($compHolidays as $ch) {
                    $holidays[$ch['holiday_date']] = ['status' => 'holiday', 'name' => $ch['name']];
                }

                // Weekly Schedule (Weekends)
                $stmt = $db->prepare("
                    SELECT day_of_week, status 
                    FROM office_weekly_schedules 
                    WHERE company_id = :comp_id AND (status = 'Off' OR status = 'Weekend' OR status = 'Holiday')
                ");
                $stmt->execute(['comp_id' => $emp['company_id']]);
                $schedule = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($schedule as $s) {
                    $weekends[] = $s['day_of_week'];
                }
            }
            if (empty($weekends)) {
                $weekends = ['Saturday', 'Sunday']; // Standard Default
            }

            // Fetch Leave Policies to determine which statuses are leave and if they should count on weekends
            $leavePolicies = [];
            if ($emp) {
                // Determine if any leave types for this company are counted as calendar days
                $stmt = $db->prepare("
                    SELECT lt.code, clp.is_calendar_days, lt.name as leave_type_name
                    FROM leave_types lt
                    LEFT JOIN company_leave_policies clp ON lt.id = clp.leave_type_id AND clp.company_id = :comp_id
                ");
                $stmt->execute(['comp_id' => $emp['company_id']]);
                $policies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($policies as $p) {
                    $isCal = (bool)($p['is_calendar_days'] ?? false);
                    if ($p['code']) $leavePolicies[$p['code']] = $isCal;
                    if ($p['leave_type_name']) $leavePolicies[$p['leave_type_name']] = $isCal;
                }
            }

            // Fetch Approved Leave Requests for this month
            $stmt = $db->prepare("
                SELECT lr.start_date, lr.end_date, lt.code, clp.is_calendar_days
                FROM leave_requests lr
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN company_leave_policies clp ON lt.id = clp.leave_type_id AND clp.company_id = :cid
                WHERE lr.employee_id = :eid AND lr.status = 'approved'
                AND lr.start_date <= :end AND lr.end_date >= :start
            ");
            $stmt->execute(['eid' => $employeeId, 'cid' => $emp['company_id'], 'start' => $startDate, 'end' => $endDate]);
            $leaves = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $leavesByDate = [];
            foreach ($leaves as $l) {
                $s = new \DateTime(max($l['start_date'], $startDate));
                $e = (new \DateTime(min($l['end_date'], $endDate)))->modify('+1 day');
                foreach (new \DatePeriod($s, new \DateInterval('P1D'), $e) as $dt) {
                    $leavesByDate[$dt->format('Y-m-d')] = [
                        'code' => $l['code'],
                        'is_calendar' => (bool)($l['is_calendar_days'] ?? false)
                    ];
                }
            }

            // Logic: Enrich daily logs for full month visibility
            $daysInMonth = (int)date('t', strtotime("$year-$month-01"));
            $enrichedLogs = [];
            $logsByDate = array_column($logs, null, 'attendance_date');

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $d);
                $dayName = date('l', strtotime($dateStr));
                $isWeekend = in_array($dayName, $weekends);
                $isHoliday = isset($holidays[$dateStr]);
                $isNonWorking = $isWeekend || $isHoliday;

                if (isset($logsByDate[$dateStr])) {
                    $log = $logsByDate[$dateStr];
                    $st = $log['status'];
                    $log['is_future'] = ($dateStr > date('Y-m-d'));
                    
                    // Priority Enforcement: If it's a leave status on a non-working day, check policy
                    if (isset($leavePolicies[$st])) {
                        $isCalendarDays = $leavePolicies[$st];
                        if (!$isCalendarDays && ($isWeekend || $isHoliday)) {
                            // Demote to weekend/holiday status for display/stats if policy is Working Days only
                            $log['status'] = $isHoliday ? $holidays[$dateStr]['status'] : 'weekend';
                            if ($isHoliday) $log['name'] = $holidays[$dateStr]['name'];
                        } else {
                            $log['is_leave'] = true;
                        }
                    } else if ($isWeekend || $isHoliday) {
                        // Demote other statuses to weekend/holiday for display consistency
                        $log['status'] = $isHoliday ? $holidays[$dateStr]['status'] : 'weekend';
                        if ($isHoliday) $log['name'] = $holidays[$dateStr]['name'];
                    }
                } else if (isset($leavesByDate[$dateStr])) {
                    $lInfo = $leavesByDate[$dateStr];
                    if (!$isNonWorking || $lInfo['is_calendar']) {
                        $log = [
                            'attendance_date' => $dateStr,
                            'status' => $lInfo['code'],
                            'approval_status' => 'approved',
                            'is_leave' => true,
                            'is_future' => ($dateStr > date('Y-m-d'))
                        ];
                    } else {
                        // Falls through to holiday/weekend check
                        $log = null;
                    }
                } else {
                    $log = null;
                }

                if ($log) {
                    // Timezone conversion for specific log
                    $tz = \App\Helpers\DateHelper::getEmployeeTimezone($employeeId);
                    if (isset($log['check_in_utc'])) {
                        $log['check_in_local'] = \App\Helpers\DateHelper::toLocal($log['check_in_utc'] ?? null, $tz, 'H:i:s');
                        $log['check_out_local'] = \App\Helpers\DateHelper::toLocal($log['check_out_utc'] ?? null, $tz, 'H:i:s');
                        $log['check_in_display'] = \App\Helpers\DateHelper::toLocal($log['check_in_utc'] ?? null, $tz, 'h:i A');
                        $log['check_out_display'] = \App\Helpers\DateHelper::toLocal($log['check_out_utc'] ?? null, $tz, 'h:i A');
                    }
                    $enrichedLogs[] = $log;
                } elseif ($isHoliday) {
                    $enrichedLogs[] = [
                        'attendance_date' => $dateStr,
                        'status' => $holidays[$dateStr]['status'],
                        'name' => $holidays[$dateStr]['name'],
                        'approval_status' => 'approved'
                    ];
                } elseif ($isWeekend) {
                    $enrichedLogs[] = [
                        'attendance_date' => $dateStr,
                        'status' => 'weekend',
                        'approval_status' => 'approved'
                    ];
                }
            }

            // Recalculate stats for the summary cards
            $statsMap = [];
            foreach ($enrichedLogs as $log) {
                $st = $log['status'];
                $statsMap[$st] = ($statsMap[$st] ?? 0) + 1;
            }
            $stats = [];
            foreach ($statsMap as $st => $count) {
                $stats[] = ['status' => $st, 'count' => $count];
            }

            return $this->jsonResponse([
                'stats' => $stats,
                'logs' => $enrichedLogs
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Get all countries for the attendance tabs

     */
    public function getCountries()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT id, name, iso_code, default_timezone FROM countries ORDER BY name ASC");
            $countries = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($countries as &$country) {
                $tz = $country['default_timezone'] ?: 'UTC';
                $country['current_time_local'] = \App\Helpers\DateHelper::toLocal(date('Y-m-d H:i:s'), $tz, 'Y-m-d H:i:s');
                $country['current_date_local'] = \App\Helpers\DateHelper::toLocal(date('Y-m-d H:i:s'), $tz, 'Y-m-d');
                $country['current_time_display'] = \App\Helpers\DateHelper::toLocal(date('Y-m-d H:i:s'), $tz, 'h:i A');
            }

            return $this->jsonResponse($countries);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Get office attendance configurations
     */
    public function getOfficeConfigs($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $date = $requestData['date'] ?? null;
            $countryId = $requestData['country_id'] ?? null;
            $companyId = $requestData['company_id'] ?? null;

            $query = "SELECT oac.*, c.name as company_name FROM office_attendance_configs oac
                      JOIN companies c ON oac.company_id = c.id
                      WHERE 1=1";
            $params = [];

            if ($date) {
                $query .= " AND oac.config_date = :date";
                $params['date'] = $date;
            }

            if ($countryId && $countryId !== 'global') {
                $query .= " AND c.country_id = :country_id";
                $params['country_id'] = $countryId;
            }

            if ($companyId) {
                $query .= " AND oac.company_id = :company_id";
                $params['company_id'] = $companyId;
            }

            // Default to latest if no date specified
            if (!$date) {
                $query .= " ORDER BY oac.config_date DESC LIMIT 50";
            }

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $configs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($configs);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Save/Update an office attendance configuration for a day
     */
    public function saveOfficeConfig($requestData)
    {
        $userId = $_SESSION['user_id'] ?? null;
        $userRole = $_SESSION['user_role'] ?? 'employee';

        if ($userRole === 'employee') {
            return $this->jsonResponse(null, 403, "Insufficient permissions.");
        }

        $companyId = $requestData['company_id'] ?? null;
        $countryId = $requestData['country_id'] ?? null;
        $date = $requestData['config_date'] ?? null;
        $status = $requestData['status'] ?? null;
        $remarks = $requestData['remarks'] ?? null;

        if ((!$companyId && !$countryId) || !$date || !$status) {
            return $this->jsonResponse(null, 400, "Office/Country, Date, and Status are required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();

            if (!$companyId && $countryId) {
                // Resolve first company for the country
                $stmt = $db->prepare("SELECT id FROM companies WHERE country_id = ? LIMIT 1");
                $stmt->execute([$countryId]);
                $companyId = $stmt->fetchColumn();
                
                if (!$companyId) {
                    return $this->jsonResponse(null, 404, "No office found for this country.");
                }
            }
            
            $stmt = $db->prepare("INSERT INTO office_attendance_configs (company_id, config_date, status, remarks) 
                                  VALUES (:cid, :date, :status, :remarks)
                                  ON DUPLICATE KEY UPDATE status = :status2, remarks = :remarks2");
            $stmt->execute([
                'cid' => $companyId,
                'date' => $date,
                'status' => $status,
                'remarks' => $remarks,
                'status2' => $status,
                'remarks2' => $remarks
            ]);

            return $this->jsonResponse(['message' => "Office configuration saved successfully."]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Get combined list of valid attendance statuses (Office-specific + Leave Types)
     */
    public function getAttendanceStatuses($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $companyId = $requestData['company_id'] ?? null;
            $countryId = $requestData['country_id'] ?? null;
            
            // RBAC: Verify user has access to this data scope
            $this->verifyDataScope($companyId, $countryId === 'global' ? null : $countryId);
            
            // 1. Define System Defaults
            $systemDefaults = [
                'present' => ['id' => 'present', 'name' => 'Present', 'type' => 'system', 'color_code' => '#10b981'],
                'absent' => ['id' => 'absent', 'name' => 'Absent', 'type' => 'system', 'color_code' => '#ef4444'],
                'work_from_home' => ['id' => 'work_from_home', 'name' => 'Work From Home', 'type' => 'system', 'color_code' => '#84cc16'],
                'on_site' => ['id' => 'on_site', 'name' => 'On Site', 'type' => 'system', 'color_code' => '#059669'],
                'training' => ['id' => 'training', 'name' => 'Training', 'type' => 'system', 'color_code' => '#8b5cf6'],
                'weekend' => ['id' => 'weekend', 'name' => 'Weekend', 'type' => 'system', 'color_code' => '#9ca3af'],
                'public_holiday' => ['id' => 'public_holiday', 'name' => 'Public Holiday', 'type' => 'system', 'color_code' => '#3b82f6'],
                'holiday' => ['id' => 'holiday', 'name' => 'Holiday', 'type' => 'system', 'color_code' => '#2563eb']
            ];

            // Determine which companies we are fetching for
            $companyIds = [];
            if ($companyId) {
                $companyIds[] = $companyId;
            } elseif ($countryId && $countryId !== 'global') {
                $stmt = $db->prepare("SELECT id FROM companies WHERE country_id = ?");
                $stmt->execute([$countryId]);
                $companyIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            } else {
                $stmt = $db->query("SELECT id FROM companies");
                $companyIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }

            $companiesConfig = [];
            foreach ($companyIds as $cid) {
                $companiesConfig[$cid] = ['attendance' => [], 'leave' => []];
            }

            // 2. Fetch Leave Types
            $queryLt = "SELECT lt.company_id, lt.code as id, lt.name, lt.gender_restriction, 'leave' as type, lt.color_code FROM leave_types lt";
            // 2 & 3. Consolidate Leave Types and Custom Attendance Statuses into ONE trip
            $queryConsolidated = "
                SELECT 'leave' as source, lt.company_id, lt.code as id, lt.name, lt.gender_restriction, lt.color_code, NULL as is_default, NULL as is_deleted, NULL as sort_order 
                FROM leave_types lt
            ";
            
            $params = [];
            if ($companyId) {
                $queryConsolidated .= " WHERE lt.company_id = ? OR lt.company_id IS NULL";
                $params[] = $companyId;
            } elseif ($countryId && $countryId !== 'global') {
                $queryConsolidated .= " JOIN companies c ON lt.company_id = c.id WHERE c.country_id = ? OR lt.company_id IS NULL";
                $params[] = $countryId;
            }

            $queryConsolidated .= " UNION ALL 
                SELECT 'attendance' as source, oasd.company_id, oasd.status_key as id, oasd.status_label as name, NULL as gender_restriction, oasd.color_code, oasd.is_default, oasd.is_deleted, oasd.sort_order
                FROM office_attendance_status_definitions oasd
            ";

            if ($companyId) {
                $queryConsolidated .= " WHERE oasd.company_id = ?";
                $params[] = $companyId;
            } elseif ($countryId && $countryId !== 'global') {
                $queryConsolidated .= " JOIN companies c ON oasd.company_id = c.id WHERE c.country_id = ?";
                $params[] = $countryId;
            }

            $stmt = $db->prepare($queryConsolidated);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $globalLeaveTypes = [];
            $companyOverrides = [];
            
            foreach ($rows as $row) {
                $cid = $row['company_id'];
                if ($row['source'] === 'leave') {
                    $lt = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'gender_restriction' => $row['gender_restriction'],
                        'type' => 'leave',
                        'color_code' => $row['color_code']
                    ];
                    if ($cid) {
                        if (isset($companiesConfig[$cid])) $companiesConfig[$cid]['leave'][] = $lt;
                    } else {
                        $globalLeaveTypes[] = $lt;
                    }
                } else {
                    if (!isset($companyOverrides[$cid])) $companyOverrides[$cid] = ['deleted' => [], 'custom' => []];
                    $key = $row['id'];
                    if (!empty($row['is_deleted'])) {
                        $companyOverrides[$cid]['deleted'][] = $key;
                    } else {
                        $companyOverrides[$cid]['custom'][$key] = [
                            'id' => $key,
                            'name' => $row['name'],
                            'type' => 'attendance',
                            'color_code' => $row['color_code'],
                            'is_default' => (bool)$row['is_default']
                        ];
                    }
                }
            }

            foreach ($companiesConfig as $cid => &$config) {
                // Merge Leave Types
                $config['leave'] = array_merge($globalLeaveTypes, $config['leave']);
                
                // Merge Attendance Statuses
                $overrides = $companyOverrides[$cid] ?? ['deleted' => [], 'custom' => []];
                $finalAtt = [];
                foreach ($systemDefaults as $key => $sys) {
                    if (in_array($key, $overrides['deleted'])) continue;
                    if (isset($overrides['custom'][$key])) {
                        $finalAtt[] = $overrides['custom'][$key];
                        unset($overrides['custom'][$key]);
                    } else {
                        $finalAtt[] = $sys;
                    }
                }
                foreach ($overrides['custom'] as $custom) {
                    $finalAtt[] = $custom;
                }
                $config['attendance'] = $finalAtt;
            }
            unset($config);

            // 4. Build Response
            if ($companyId) {
                // Backward compatibility for single company (e.g. Attendance Configuration page)
                $finalStatuses = $companiesConfig[$companyId]['attendance'] ?? array_values($systemDefaults);
                $finalLeave = $companiesConfig[$companyId]['leave'] ?? [];
                $all = array_merge($finalStatuses, $finalLeave);
                return $this->jsonResponse([
                    'attendance' => $finalStatuses,
                    'leave' => $finalLeave,
                    'all' => $all,
                    'system_templates' => array_values($systemDefaults)
                ]);
            }

            // Global / Country View
            $flatAtt = [];
            $flatLeave = [];
            foreach ($companiesConfig as $cid => $config) {
                foreach ($config['attendance'] as $a) $flatAtt[$a['id']] = $a;
                foreach ($config['leave'] as $l) $flatLeave[$l['id']] = $l;
            }

            return $this->jsonResponse([
                'attendance' => array_values($flatAtt),
                'leave' => array_values($flatLeave),
                'all' => array_merge(array_values($flatAtt), array_values($flatLeave)),
                'companies' => $companiesConfig
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error fetching statuses: " . $e->getMessage());
        }
    }

    /**
     * Save/Update an office attendance status definition
     */
    public function saveAttendanceStatusDefinition($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $companyId = $requestData['company_id'] ?? null;
            $statusKey = $requestData['status_key'] ?? null;
            $statusLabel = $requestData['status_label'] ?? null;
            $color = $requestData['color_code'] ?? '#3b82f6';
            $isDefault = ($requestData['is_default'] ?? false) ? 1 : 0;
            $sortOrder = $requestData['sort_order'] ?? 0;

            if (!$companyId || !$statusKey || !$statusLabel) {
                return $this->jsonResponse(null, 400, "Company ID, Status Key, and Label are required.");
            }

            // RBAC: Verify user has permission to manage this company's config
            $this->verifyDataScope($companyId);
            $db->beginTransaction();

            if ($isDefault) {
                // Only one default per company
                $stmt = $db->prepare("UPDATE office_attendance_status_definitions SET is_default = 0 WHERE company_id = ?");
                $stmt->execute([$companyId]);
            }

            $stmt = $db->prepare("
                INSERT INTO office_attendance_status_definitions (company_id, status_key, status_label, color_code, is_default, sort_order, is_deleted)
                VALUES (:cid, :key, :label, :color, :is_def, :sort, 0)
                ON DUPLICATE KEY UPDATE 
                    status_label = :label_up, 
                    color_code = :color_up, 
                    is_default = :is_def_up, 
                    sort_order = :sort_up,
                    is_deleted = 0
            ");
            $stmt->execute([
                'cid' => $companyId,
                'key' => $statusKey,
                'label' => $statusLabel,
                'color' => $color,
                'is_def' => $isDefault,
                'sort' => $sortOrder,
                'label_up' => $statusLabel,
                'color_up' => $color,
                'is_def_up' => $isDefault,
                'sort_up' => $sortOrder
            ]);

            $db->commit();
            return $this->jsonResponse(['message' => "Status definition saved successfully."]);
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Delete an office attendance status definition
     */
    public function deleteAttendanceStatusDefinition($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $id = $requestData['id'] ?? null;
            $companyId = $requestData['company_id'] ?? null;
            if (!$id) return $this->jsonResponse(null, 400, "ID is required.");

            try { $db->query("ALTER TABLE office_attendance_status_definitions ADD COLUMN is_deleted TINYINT(1) DEFAULT 0"); } catch (\Exception $e) {}

            // Check if ID is numeric (custom status)
            if (is_numeric($id)) {
                // RBAC: Verify ownership before deletion
                $stmt = $db->prepare("SELECT company_id FROM office_attendance_status_definitions WHERE id = ?");
                $stmt->execute([$id]);
                $cid = $stmt->fetchColumn();
                if ($cid) {
                    $this->verifyDataScope($cid);
                }
                $stmt = $db->prepare("DELETE FROM office_attendance_status_definitions WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                // System default being deleted
                if (!$companyId) return $this->jsonResponse(null, 400, "Company ID required to delete system defaults.");
                $this->verifyDataScope($companyId);
                
                $stmt = $db->prepare("
                    INSERT INTO office_attendance_status_definitions (company_id, status_key, status_label, color_code, is_deleted)
                    VALUES (:cid, :key, :label, '#000000', 1)
                    ON DUPLICATE KEY UPDATE is_deleted = 1
                ");
                $stmt->execute([
                    'cid' => $companyId,
                    'key' => $id,
                    'label' => ucfirst(str_replace('_', ' ', $id))
                ]);
            }

            return $this->jsonResponse(['message' => "Status definition removed successfully."]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get weekly schedules for a company
     */
    public function getWeeklySchedules($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $companyId = $requestData['company_id'] ?? null;

            if (!$companyId) {
                return $this->jsonResponse(null, 400, "Company ID required.");
            }

            $stmt = $db->prepare("SELECT * FROM office_weekly_schedules WHERE company_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
            $stmt->execute([$companyId]);
            return $this->jsonResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Save/Update a weekly schedule entry
     */
    public function saveWeeklySchedule($requestData)
    {
        $userId = $_SESSION['user_id'] ?? null;
        $userRole = $_SESSION['user_role'] ?? 'employee';

        if ($userRole === 'employee') {
            return $this->jsonResponse(null, 403, "Insufficient permissions.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $companyId = $requestData['company_id'];
            $dayOfWeek = $requestData['day_of_week'];
            $status = $requestData['status'];
            $remarks = $requestData['remarks'] ?? null;

            $stmt = $db->prepare("
                INSERT INTO office_weekly_schedules (company_id, day_of_week, status, remarks)
                VALUES (:cid, :day, :status, :remarks)
                ON DUPLICATE KEY UPDATE status = :status_up, remarks = :remarks_up
            ");
            $stmt->execute([
                'cid' => $companyId,
                'day' => $dayOfWeek,
                'status' => $status,
                'remarks' => $remarks,
                'status_up' => $status,
                'remarks_up' => $remarks
            ]);

            return $this->jsonResponse(['message' => "Schedule for $dayOfWeek updated."]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get grid data for all employees and their status for a date
     */
    public function getGridLogs($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $date = $requestData['date'] ?? date('Y-m-d');
            $countryId = $requestData['country_id'] ?? null;
            $this->verifyDataScope(null, ($countryId === 'global' ? null : $countryId));
            
            $isSuperAdmin = $this->isSuperAdmin();
            $isGlobalAdmin = $this->isGlobalAdmin();



            $dateObj = new \DateTime($date);
            $dayOfWeek = $dateObj->format('l');

            $query = "
                SELECT 
                    e.id as employee_id, e.first_name, e.last_name, e.employee_code, e.gender,
                    d.title as role_name,
                    c.id as company_id,
                    al.id as log_id,
                    -- Priority Logic: 
                    -- 1. Actual Manual Log (al.status)
                    -- 2. If it's a non-working day (Holiday/PH/Weekend), we only show Leave if is_calendar_days is true.
                    -- 3. Otherwise show Leave if applicable.
                    -- 4. Fall back to Holiday/PH/Weekend/Present.
                    COALESCE(
                        al.status, 
                        CASE 
                            WHEN (ph.id IS NOT NULL OR ch.id IS NOT NULL OR ws.status IN ('Off', 'Weekend', 'Holiday') OR (ws.id IS NULL AND :day_name_weekend_1 IN ('Saturday', 'Sunday')))
                            THEN IF(clp.is_calendar_days = 1, lr.leave_code, NULL)
                            ELSE lr.leave_code
                        END,
                        IF(ph.id IS NOT NULL, 'public_holiday', NULL),
                        IF(ch.id IS NOT NULL, 'holiday', NULL),
                        IF(ws.status IN ('Off', 'Weekend', 'Holiday'), 'weekend', 
                           IF(ws.id IS NULL AND :day_name_weekend_2 IN ('Saturday', 'Sunday'), 'weekend', 'present')
                        )
                    ) as status,
                    al.approval_status,
                    al.remarks,
                    al.check_in_utc, al.check_out_utc,
                    IF(al.id IS NOT NULL, al.is_default_applied, 0) as is_default_applied,
                    IF(al.id IS NOT NULL, al.is_manually_modified, 0) as is_manually_modified,
                    IF(al.id IS NOT NULL, 1, 0) as is_saved
                FROM employees e
                LEFT JOIN designations d ON e.designation_id = d.id
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                JOIN companies c ON ec.company_id = c.id
                -- Join for approved leave
                LEFT JOIN (
                    SELECT lr_inner.employee_id, lt_inner.code as leave_code, lt_inner.id as leave_type_id
                    FROM leave_requests lr_inner
                    JOIN leave_types lt_inner ON lr_inner.leave_type_id = lt_inner.id
                    WHERE lr_inner.status = 'approved'
                      AND :date_lr BETWEEN lr_inner.start_date AND lr_inner.end_date
                ) lr ON e.id = lr.employee_id
                -- Join for leave policy
                LEFT JOIN company_leave_policies clp ON lr.leave_type_id = clp.leave_type_id AND c.id = clp.company_id
                -- Join for public holidays (country)
                LEFT JOIN public_holidays ph ON ph.holiday_date = :date_ph AND ph.country_id = c.country_id
                -- Join for company-specific holidays
                LEFT JOIN holidays ch ON ch.company_id = c.id AND ch.holiday_date = :date_ch
                -- Join for weekend definition
                LEFT JOIN office_weekly_schedules ws ON c.id = ws.company_id AND ws.day_of_week = :day_name
                LEFT JOIN attendance_logs al ON e.id = al.employee_id AND al.attendance_date = :date_al
                JOIN users u ON e.id = u.employee_id
                WHERE e.status = 'active'
            ";

            if (!$isSuperAdmin) {
                $query .= " AND NOT EXISTS (
                    SELECT 1 FROM user_roles ur2 
                    WHERE ur2.user_id = u.id AND ur2.role_id = 1
                )";
            }


            $params = [
                'date_al' => $date,
                'date_lr' => $date,
                'date_ph' => $date,
                'date_ch' => $date,
                'day_name' => $dayOfWeek,
                'day_name_weekend_1' => $dayOfWeek,
                'day_name_weekend_2' => $dayOfWeek
            ];

            if (!$isGlobalAdmin) {
                $isMultiOffice = $this->hasAnyRole(['HRManager', 'HRAssistant', 'CountryManager', 'COUNTRY MANAGER']);

                $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;

                if ($isMultiOffice && !empty($associatedCompanyIds)) {
                    $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                    $query .= " AND ec.company_id IN ($companyIdList)";
                } else if ($this->hasAnyRole(['CountryManager', 'COUNTRY MANAGER']) && $sessionCountryId) {
                    $query .= " AND c.country_id = :session_country_id";
                    $params['session_country_id'] = $sessionCountryId;
                } else {
                    $sessionCompanyId = $_SESSION['scope_company_id'] ?? null;
                    if ($sessionCompanyId) {
                        $query .= " AND ec.company_id = :session_company_id";
                        $params['session_company_id'] = $sessionCompanyId;
                    } else if (!$isMultiOffice) {
                        $query .= " AND 1=0";
                    }
                }
            }

            if ($countryId && $countryId !== 'global' && $countryId !== 'null') {
                $query .= " AND c.country_id = :country_id";
                $params['country_id'] = $countryId;
            }


            $query .= " ORDER BY e.first_name ASC, e.last_name ASC";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $grid = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($grid as &$row) {
                $tz = \App\Helpers\DateHelper::getEmployeeTimezone($row['employee_id']);
                $row['check_in_local'] = \App\Helpers\DateHelper::toLocal($row['check_in_utc'] ?? null, $tz, 'H:i:s');
                $row['check_out_local'] = \App\Helpers\DateHelper::toLocal($row['check_out_utc'] ?? null, $tz, 'H:i:s');
                $row['check_in_display'] = \App\Helpers\DateHelper::toLocal($row['check_in_utc'] ?? null, $tz, 'h:i A');
                $row['check_out_display'] = \App\Helpers\DateHelper::toLocal($row['check_out_utc'] ?? null, $tz, 'h:i A');
            }

            return $this->jsonResponse($grid);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Batch save grid entries
     */
    public function saveGridEntries($requestData)
    {
        $userId = $_SESSION['user_id'] ?? null;
        $userRole = $_SESSION['user_role'] ?? 'employee';

        if ($userRole === 'employee') {
            return $this->jsonResponse(null, 403, "Employees are not permitted to log manual attendance.");
        }

        $entries = $requestData['entries'] ?? [];
        $date = $requestData['attendance_date'] ?? null;

        if (empty($entries) || !$date) {
            return $this->jsonResponse(null, 400, "Entries and Date are required.");
        }

        // Prevent future dates
        if (strtotime($date) > strtotime(date('Y-m-d'))) {
            return $this->jsonResponse(null, 400, "Attendance cannot be logged for a future date.");
        }

        try {
            $db = \Database::getInstance()->getConnection();

            // Pre-fetch primary companies for employees in this batch
            $employeeIds = array_unique(array_column($entries, 'employee_id'));
            $empCompMap = [];
            if (!empty($employeeIds)) {
                $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
                $compStmt = $db->prepare("SELECT employee_id, company_id FROM employee_companies WHERE employee_id IN ($placeholders) AND is_primary = 1 AND is_active = 1");
                $compStmt->execute($employeeIds);
                $empCompMap = $compStmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            }

            $db->beginTransaction();

            $updatedCount = 0;
            foreach ($entries as $entry) {
                $employeeId = $entry['employee_id'];
                $status = $entry['status'] ?? 'present';
                $remarks = $entry['remarks'] ?? null;

                // Fetch existing record
                $stmt = $db->prepare("SELECT * FROM attendance_logs WHERE employee_id = :eid AND attendance_date = :date");
                $stmt->execute(['eid' => $employeeId, 'date' => $date]);
                $oldValues = $stmt->fetch(\PDO::FETCH_ASSOC);

                $newValues = [
                    'status' => $status,
                    'remarks' => $remarks,
                    'approval_status' => 'approved',
                    'source' => 'manual',
                    'is_manually_modified' => 1,
                    'actor_type' => 'user'
                ];

                if ($oldValues) {
                    // Only update if status or remarks changed
                    if ($oldValues['status'] === $status && ($oldValues['remarks'] ?? '') === ($remarks ?? '')) {
                        continue;
                    }

                    $cid = $empCompMap[$employeeId] ?? null;
                    if (!$cid) continue; // Skip employees without primary company

                    $stmt = $db->prepare("
                        UPDATE attendance_logs 
                        SET status = :status, 
                            company_id = :cid,
                            remarks = :remarks,
                            approval_status = 'approved',
                            source = 'manual',
                            is_manually_modified = 1,
                            actor_type = 'user'
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'status' => $status,
                        'cid' => $cid,
                        'remarks' => $remarks ?? '',
                        'id' => $oldValues['id']
                    ]);
                    $logId = $oldValues['id'];
                    $this->logChange($logId, $userId, $oldValues, $newValues, "Grid Update");
                } else {
                    $cid = $empCompMap[$employeeId] ?? null;
                    if (!$cid) continue; // Safety skip

                    $stmt = $db->prepare("
                        INSERT INTO attendance_logs (employee_id, company_id, attendance_date, status, remarks, approval_status, source, is_manually_modified, actor_type)
                        VALUES (:eid, :cid, :date, :status, :remarks, 'approved', 'manual', 1, 'user')
                    ");
                    $stmt->execute([
                        'eid' => $employeeId,
                        'cid' => $cid,
                        'date' => $date,
                        'status' => $status,
                        'remarks' => $remarks ?? ''
                    ]);
                    $logId = $db->lastInsertId();
                    if ($logId) {
                        $this->logChange($logId, $userId, null, $newValues, "Grid Creation");
                    }
                }
                $updatedCount++;
            }

            $db->commit();
            return $this->jsonResponse(['message' => "$updatedCount attendance records updated successfully."]);
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Automatically persist default attendance values for the current day.
     */
    public function autoPersistDefaults($requestData = [])
    {
        try {
            $service = new \App\Services\AttendanceService();
            $date = $requestData['date'] ?? date('Y-m-d');
            $count = $service->autoPersistDefaults($date);
            return $this->jsonResponse(['message' => "Auto-persistence complete: $count records created for $date."]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Auto-persistence failed: " . $e->getMessage());
        }
    }

    /**
     * Get data for Monthly Attendance Report Grid
     */
    public function getMonthlyReport($requestData, $internalCall = false)
    {
        try {
            $companyId = $requestData['company_id'] ?? null;
            if ($companyId === 'global' || $companyId === 'null') $companyId = null;
            $this->verifyDataScope($companyId);

            $service = new \App\Services\AttendanceService();
            $data = $service->getMonthlyReport($requestData);

            if ($internalCall) return $data;
            return $this->jsonResponse($data);
        } catch (\Exception $e) {
            if ($internalCall) throw $e;
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Export Monthly Attendance Report to CSV
     */
    public function exportMonthlyReport($requestData)
    {
        try {
            // Re-use logic to get the grid via internal call
            $data = $this->getMonthlyReport($_GET, true);
            
            if (!$data || !isset($data['grid'])) {
                header("Content-Type: application/json");
                echo json_encode(['status' => 'error', 'message' => 'Failed to generate report data']);
                exit();
            }

            $gridData = $data['grid'];
            $dates = $data['dates'];
            $month = str_pad($_GET['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
            $year = $_GET['year'] ?? date('Y');
            $companyId = $_GET['company_id'] ?? 'global';

            // Fetch company name for the title
            $companyName = "All Offices";
            if ($companyId !== 'global' && $companyId !== 'null') {
                $db = \Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT name FROM companies WHERE id = ?");
                $stmt->execute([$companyId]);
                $companyName = $stmt->fetchColumn() ?: "Unknown Office";
            }

            // Construct CSV
            $output = fopen('php://temp', 'r+');

            // Add Title Rows
            fputcsv($output, ["Attendance Report"], ",", "\"", "\\");
            fputcsv($output, ["Office:", $companyName], ",", "\"", "\\");
            
            if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
                fputcsv($output, ["Period:", $_GET['start_date'] . " to " . $_GET['end_date']], ",", "\"", "\\");
            } else {
                $monthName = date('F', strtotime("$year-$month-01"));
                fputcsv($output, ["Period:", "$monthName $year"], ",", "\"", "\\");
            }
            fputcsv($output, [""], ",", "\"", "\\"); // Spacer row

            // Headers
            $headers = ['S/N', 'Employee Name', 'Employee Code'];
            foreach ($dates as $dm) {
                $headers[] = $dm['day'] . " (" . $dm['day_name'] . ")";
            }
            
            // Add summary headers
            $summaryKeys = array_keys($data['config']);
            foreach ($summaryKeys as $sk) {
                $headers[] = "Total " . $data['config'][$sk]['label'];
            }
            
            fputcsv($output, $headers, ",", "\"", "\\");

            // Rows
            $sn = 1;
            foreach ($gridData as $row) {
                $csvRow = [
                    $sn++,
                    $row['name'],
                    $row['code']
                ];
                
                foreach ($dates as $dm) {
                    $statusRaw = $row['days'][$dm['date']];
                    $csvRow[] = ($statusRaw === 'weekend') ? '-' : ($data['config'][$statusRaw]['key'] ?? ($statusRaw ? strtoupper(substr($statusRaw, 0, 3)) : ''));
                }
                
                // Add totals for this employee
                foreach ($summaryKeys as $sk) {
                    $csvRow[] = $row['totals'][$sk] ?? 0;
                }
                
                fputcsv($output, $csvRow, ",", "\"", "\\");
            }

            // Grand Total Row
            $totalRow = ['', 'GRAND TOTAL', ''];
            foreach ($dates as $dm) {
                $totalRow[] = '';
            }
            foreach ($summaryKeys as $sk) {
                $totalRow[] = $data['summary'][$sk] ?? 0;
            }
            fputcsv($output, $totalRow, ",", "\"", "\\");

            // Add dedicated summary section at bottom
            fputcsv($output, [""], ",", "\"", "\\");
            fputcsv($output, ["REPORT ANALYSIS & SUMMARY"], ",", "\"", "\\");
            foreach ($data['config'] as $key => $cfg) {
                fputcsv($output, [$cfg['label'], $data['summary'][$key] ?? 0], ",", "\"", "\\");
            }

            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);

            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Expose-Headers: Content-Disposition, Content-Type');
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="Attendance_Report_' . $year . '_' . $month . '.csv"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            if (ob_get_length()) ob_clean();
            echo $csv;
            exit();

        } catch (\Exception $e) {
            header("Content-Type: application/json");
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit();
        }
    }
}
