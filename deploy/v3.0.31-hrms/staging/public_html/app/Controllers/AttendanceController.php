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

        if ($userRole === 'Employee') {
            return $this->jsonResponse(null, 403, "Employees are not permitted to log manual attendance.");
        }

        $employeeId = $requestData['employee_id'] ?? null;
        $date = $requestData['attendance_date'] ?? null;

        if (!$employeeId || !$date) {
            return $this->jsonResponse(null, 400, "Employee ID and Date are required.");
        }

        $isSuperAdmin = $userRole === 'SuperAdmin';

        // Prevent future dates
        if (!$isSuperAdmin && strtotime($date) > strtotime(date('Y-m-d'))) {
            return $this->jsonResponse(null, 400, "Attendance cannot be logged for a future date.");
        }

        $this->verifyDataScope(null, null, $employeeId);

        try {
            $service = new \App\Services\AttendanceService();
            $result = $service->saveManualEntry($requestData, (int)$userId, $isSuperAdmin);
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

        if ($userRole === 'Employee') {
            return $this->jsonResponse(null, 403, "Employees are not permitted to log manual attendance.");
        }

        $employeeIds = $requestData['employee_ids'] ?? [];
        $date = $requestData['attendance_date'] ?? null;

        if (empty($employeeIds) || !$date) {
            return $this->jsonResponse(null, 400, "Employee IDs and Date are required.");
        }

        if (!$this->isGlobalAdmin()) {
            // Removed country-wide bypass
            $sessionCountryId = $_SESSION['scope_country_id'] ?? null;
            $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];



            if (empty($myCompanyIds)) {
                return $this->jsonResponse(null, 403, "Security Violation: Access Denied.");
            }
        }

        $isSuperAdmin = $userRole === 'SuperAdmin';

        // Prevent future dates
        if (!$isSuperAdmin && strtotime($date) > strtotime(date('Y-m-d'))) {
            return $this->jsonResponse(null, 400, "Attendance cannot be logged for a future date.");
        }

        try {
            $service = new \App\Services\AttendanceService();
            $result = $service->saveBulkEntry($requestData, (int)$userId, $isSuperAdmin);
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
                'associated_company_ids' => $_SESSION['associated_company_ids'] ?? [],
                'scope_country_id' => $_SESSION['scope_country_id'] ?? null,
                'user_role' => $_SESSION['user_role'] ?? 'employee'
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
        if (!\App\Middleware\RoleMiddleware::hasPermission('Attendance', 'approve')) {
            return $this->jsonResponse(null, 403, "Insufficient permissions to review logs.");
        }

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
            $scopeParams = [];
            if (!$this->isGlobalAdmin()) {
                $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;
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
            $params = array_merge([$action, $userId, $remarks], $ids, $scopeParams);
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
                SELECT al.*, COALESCE(u.username, 'System') as changed_by_name 
                FROM attendance_audit_logs al
                LEFT JOIN users u ON al.changed_by_id = u.id
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
                $stmt = $db->prepare("SELECT * FROM attendance_logs WHERE employee_id = :eid AND attendance_date = :date");
                $stmt->execute(['eid' => $employeeId, 'date' => $dateStr]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $stmt = $db->prepare("UPDATE attendance_logs SET status = :status, company_id = :cid, approval_status = 'approved', source = 'leave_module' WHERE id = :id");
                    $stmt->execute(['status' => $status, 'cid' => $companyId, 'id' => $existing['id']]);

                    $newValues = array_merge($existing, [
                        'status' => $status,
                        'company_id' => $companyId,
                        'approval_status' => 'approved',
                        'source' => 'leave_module'
                    ]);
                    $this->logChange($existing['id'], $_SESSION['user_id'] ?? 0, $existing, $newValues, "Leave Approval Sync");
                } else {
                    $stmt = $db->prepare("INSERT INTO attendance_logs (employee_id, company_id, attendance_date, status, approval_status, source) VALUES (:eid, :cid, :date, :status, 'approved', 'leave_module')");
                    $stmt->execute(['eid' => $employeeId, 'cid' => $companyId, 'date' => $dateStr, 'status' => $status]);
                    $newId = $db->lastInsertId();

                    $newValues = [
                        'employee_id' => $employeeId,
                        'company_id' => $companyId,
                        'attendance_date' => $dateStr,
                        'status' => $status,
                        'approval_status' => 'approved',
                        'source' => 'leave_module'
                    ];
                    $this->logChange($newId, $_SESSION['user_id'] ?? 0, null, $newValues, "Leave Approval Sync");
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
                    SELECT holiday_date, name, is_recurring 
                    FROM holidays 
                    WHERE company_id = :comp_id AND (holiday_date BETWEEN :start AND :end OR is_recurring = 1)
                ");
                $stmt->execute(['comp_id' => $emp['company_id'], 'start' => $startDate, 'end' => $endDate]);
                $compHolidays = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $recurringHolidays = [];
                foreach ($compHolidays as $ch) {
                    if ($ch['is_recurring']) {
                        $md = date('m-d', strtotime($ch['holiday_date']));
                        $recurringHolidays[$md] = ['status' => 'public_holiday', 'name' => $ch['name']];
                    } else {
                        $holidays[$ch['holiday_date']] = ['status' => 'public_holiday', 'name' => $ch['name']];
                    }
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

                // Default Status
                $stmt = $db->prepare("
                    SELECT status_key 
                    FROM office_attendance_status_definitions 
                    WHERE company_id = :comp_id AND is_default = 1 AND (is_deleted = 0 OR is_deleted IS NULL) LIMIT 1
                ");
                $stmt->execute(['comp_id' => $emp['company_id']]);
                $defaultStatus = $stmt->fetchColumn();
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

            $start = new \DateTime("$year-$month-01");
            $end = new \DateTime($start->format('Y-m-t'));
            for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
                $dateStr = $date->format('Y-m-d');
                $mdStr = $date->format('m-d');
                $dayOfWeek = $date->format('l');

                $holidayInfo = $holidays[$dateStr] ?? $recurringHolidays[$mdStr] ?? null;
                $isHoliday = $holidayInfo !== null;
                $isWeekend = in_array($dayOfWeek, $weekends);
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
                            $log['status'] = $isHoliday ? $holidayInfo['status'] : 'weekend';
                            if ($isHoliday) $log['name'] = $holidayInfo['name'];
                        } else {
                            $log['is_leave'] = true;
                        }
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
                        'status' => $holidayInfo['status'],
                        'name' => $holidayInfo['name'],
                        'approval_status' => 'approved'
                    ];
                } elseif ($isWeekend) {
                    $enrichedLogs[] = [
                        'attendance_date' => $dateStr,
                        'status' => 'weekend',
                        'approval_status' => 'approved'
                    ];
                } elseif ($defaultStatus && $dateStr <= date('Y-m-d')) {
                    $enrichedLogs[] = [
                        'attendance_date' => $dateStr,
                        'status' => $defaultStatus,
                        'approval_status' => 'approved',
                        'is_default' => true
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
    public function getCountries()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            
            $query = "SELECT DISTINCT cnt.id, cnt.name, cnt.iso_code, cnt.default_timezone 
                      FROM countries cnt";
            $params = [];

            if (!$this->isGlobalAdmin()) {
                $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                if (!empty($myCompanyIds)) {
                    $placeholders = implode(',', array_fill(0, count($myCompanyIds), '?'));
                    $query = "SELECT DISTINCT cnt.id, cnt.name, cnt.iso_code, cnt.default_timezone 
                              FROM countries cnt
                              JOIN companies comp ON cnt.id = comp.country_id 
                              WHERE comp.id IN ($placeholders)";
                    $params = $myCompanyIds;
                } else {
                    return $this->jsonResponse([]);
                }
            }

            $query .= " ORDER BY cnt.name ASC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $countries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Compute current local date and time for each country using default_timezone
            foreach ($countries as &$c) {
                $timezoneStr = !empty($c['default_timezone']) ? $c['default_timezone'] : 'UTC';
                try {
                    $tz = new \DateTimeZone($timezoneStr);
                    $dt = new \DateTime('now', $tz);
                    $c['current_date_local'] = $dt->format('Y-m-d');
                    $c['current_time_local'] = $dt->format('H:i:s');
                } catch (\Exception $ex) {
                    $c['current_date_local'] = date('Y-m-d');
                    $c['current_time_local'] = date('H:i:s');
                }
            }
            unset($c);

            return $this->jsonResponse($countries);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Get office attendance configurations
     * Hardened: Enforces company-level scoping.
     */
    public function getOfficeConfigs($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $date = $requestData['date'] ?? null;
            $countryId = $requestData['country_id'] ?? null;
            $companyId = $requestData['company_id'] ?? null;

            if ($companyId === 'global' || $companyId === 'null') $companyId = null;
            if ($countryId === 'global' || $countryId === 'null') $countryId = null;

            $this->verifyDataScope($companyId, $countryId);

            $query = "SELECT oac.*, c.name as company_name FROM office_attendance_configs oac
                      JOIN companies c ON oac.company_id = c.id
                      WHERE 1=1";
            $params = [];

            if ($date) {
                $query .= " AND oac.config_date = :date";
                $params['date'] = $date;
            }

            if ($countryId) {
                $query .= " AND c.country_id = :country_id";
                $params['country_id'] = $countryId;
            }

            if ($companyId) {
                $query .= " AND oac.company_id = :company_id";
                $params['company_id'] = $companyId;
            }

            if (!$this->isGlobalAdmin()) {
                $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                if (!empty($myCompanyIds)) {
                    $cList = implode(',', array_map('intval', $myCompanyIds));
                    $query .= " AND oac.company_id IN ($cList)";
                } else {
                    return $this->jsonResponse([]);
                }
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

        if ($companyId === 'global' || $companyId === 'null') $companyId = null;
        if ($countryId === 'global' || $countryId === 'null') $countryId = null;

        if ((!$companyId && !$countryId) || !$date || !$status) {
            return $this->jsonResponse(null, 400, "Office/Country, Date, and Status are required.");
        }

        $this->verifyDataScope($companyId, $countryId);

        try {
            $db = \Database::getInstance()->getConnection();

            if (!$companyId && $countryId) {
                $stmt = $db->prepare("SELECT id FROM companies WHERE country_id = ? LIMIT 1");
                $stmt->execute([$countryId]);
                $companyId = $stmt->fetchColumn();
                if (!$companyId) return $this->jsonResponse(null, 404, "No office found for this country.");
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
     * Hardened: Restricted to the manager's authorized company scope.
     */
    public function getAttendanceStatuses($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $companyId = $requestData['company_id'] ?? null;
            $countryId = $requestData['country_id'] ?? null;

            if ($companyId === 'global' || $companyId === 'null') $companyId = null;
            if ($countryId === 'global' || $countryId === 'null') $countryId = null;

            // Security: Enforce scope before proceeding
            $this->verifyDataScope($companyId, $countryId);
            
            // Define System Defaults (Structural only)
            $systemDefaults = [
                'weekend' => ['id' => 'weekend', 'name' => 'Weekend', 'type' => 'system', 'color_code' => '#9ca3af'],
                'public_holiday' => ['id' => 'public_holiday', 'name' => 'Public Holiday', 'type' => 'system', 'color_code' => '#3b82f6']
            ];

            // Filter companies based on scope
            $companyIds = [];
            if ($companyId) {
                $companyIds[] = $companyId;
            } elseif ($countryId) {
                $stmt = $db->prepare("SELECT id FROM companies WHERE country_id = ?");
                $stmt->execute([$countryId]);
                $companyIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            } else {
                if ($this->isGlobalAdmin()) {
                    $stmt = $db->query("SELECT id FROM companies");
                    $companyIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                } else {
                    $companyIds = $_SESSION['associated_company_ids'] ?? [];
                }
            }

            if (empty($companyIds)) {
                return $this->jsonResponse(['attendance' => [], 'leave' => [], 'all' => []]);
            }

            $companiesConfig = [];
            foreach ($companyIds as $cid) {
                $companiesConfig[$cid] = ['attendance' => [], 'leave' => []];
            }

            // Fetch Leave Types
            $placeholders = implode(',', array_fill(0, count($companyIds), '?'));
            $stmtLt = $db->prepare("SELECT lt.company_id, lt.code as id, lt.name, lt.gender_restriction, 'leave' as type, lt.color_code 
                                    FROM leave_types lt 
                                    WHERE lt.company_id IN ($placeholders) OR lt.company_id IS NULL");
            $stmtLt->execute($companyIds);
            $leaveTypes = $stmtLt->fetchAll(\PDO::FETCH_ASSOC);

            $globalLeaveTypes = [];
            foreach ($leaveTypes as $lt) {
                $cid = $lt['company_id'];
                unset($lt['company_id']);
                if ($cid) {
                    if (isset($companiesConfig[$cid])) $companiesConfig[$cid]['leave'][] = $lt;
                } else {
                    $globalLeaveTypes[] = $lt;
                }
            }
            foreach ($companiesConfig as $cid => &$config) {
                $config['leave'] = array_merge($globalLeaveTypes, $config['leave']);
            }
            unset($config);

            // Fetch Custom Attendance Statuses
            $stmtCustom = $db->prepare("SELECT oasd.company_id, oasd.id, oasd.status_key, oasd.status_label as name, 'attendance' as type, oasd.color_code, oasd.is_default, oasd.is_deleted 
                                        FROM office_attendance_status_definitions oasd 
                                        WHERE oasd.company_id IN ($placeholders)");
            $stmtCustom->execute($companyIds);
            $customRows = $stmtCustom->fetchAll(\PDO::FETCH_ASSOC);

            $companyOverrides = [];
            foreach ($customRows as $row) {
                $cid = $row['company_id'];
                if (!isset($companyOverrides[$cid])) $companyOverrides[$cid] = ['deleted' => [], 'custom' => []];
                $key = $row['status_key'];
                if (!empty($row['is_deleted'])) {
                    $companyOverrides[$cid]['deleted'][] = $key;
                } else {
                    $companyOverrides[$cid]['custom'][$key] = [
                        'id' => $key, 'name' => $row['name'], 'type' => 'attendance',
                        'color_code' => $row['color_code'], 'is_default' => (bool)$row['is_default'], 'status_id' => $row['id']
                    ];
                }
            }

            foreach ($companiesConfig as $cid => &$config) {
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
                foreach ($overrides['custom'] as $custom) $finalAtt[] = $custom;
                $config['attendance'] = $finalAtt;
            }
            unset($config);

            // Build Response
            if (count($companyIds) === 1) {
                $cid = $companyIds[0];
                $finalStatuses = $companiesConfig[$cid]['attendance'];
                $finalLeave = $companiesConfig[$cid]['leave'];
                return $this->jsonResponse([
                    'attendance' => $finalStatuses, 'leave' => $finalLeave,
                    'all' => array_merge($finalStatuses, $finalLeave),
                    'system_templates' => array_values($systemDefaults)
                ]);
            }

            $flatAtt = []; $flatLeave = [];
            foreach ($companiesConfig as $cid => $config) {
                foreach ($config['attendance'] as $a) $flatAtt[$a['id']] = $a;
                foreach ($config['leave'] as $l) $flatLeave[$l['id']] = $l;
            }

            return $this->jsonResponse([
                'attendance' => array_values($flatAtt), 'leave' => array_values($flatLeave),
                'all' => array_merge(array_values($flatAtt), array_values($flatLeave)),
                'companies' => $companiesConfig
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
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
                return $this->jsonResponse(null, 400, "Required fields missing.");
            }

            $this->verifyDataScope($companyId);
            $db->beginTransaction();

            if ($isDefault) {
                $db->prepare("UPDATE office_attendance_status_definitions SET is_default = 0 WHERE company_id = ?")
                   ->execute([$companyId]);
            }

            $stmt = $db->prepare("
                INSERT INTO office_attendance_status_definitions (company_id, status_key, status_label, color_code, is_default, sort_order, is_deleted)
                VALUES (:cid, :key, :label1, :color1, :is_def1, :sort1, 0)
                ON DUPLICATE KEY UPDATE status_label = :label2, color_code = :color2, is_default = :is_def2, sort_order = :sort2, is_deleted = 0
            ");
            $stmt->execute([
                'cid' => $companyId, 
                'key' => $statusKey, 
                'label1' => $statusLabel, 
                'color1' => $color, 
                'is_def1' => $isDefault, 
                'sort1' => $sortOrder,
                'label2' => $statusLabel, 
                'color2' => $color, 
                'is_def2' => $isDefault, 
                'sort2' => $sortOrder
            ]);

            $db->commit();
            return $this->jsonResponse(['message' => "Status saved."]);
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return $this->jsonResponse(null, 500, $e->getMessage());
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
            if (!$id) return $this->jsonResponse(null, 400, "ID required.");

            if (!is_numeric($id) && in_array(strtolower($id), ['public_holiday', 'weekend'])) {
                return $this->jsonResponse(null, 400, "Cannot delete core system statuses.");
            }

            if (is_numeric($id)) {
                $stmt = $db->prepare("SELECT company_id FROM office_attendance_status_definitions WHERE id = ?");
                $stmt->execute([$id]);
                $cid = $stmt->fetchColumn();
                if ($cid) $this->verifyDataScope($cid);
                $db->prepare("DELETE FROM office_attendance_status_definitions WHERE id = ?")->execute([$id]);
            } else {
                if (!$companyId) return $this->jsonResponse(null, 400, "Company ID required.");
                $this->verifyDataScope($companyId);
                $stmt = $db->prepare("INSERT INTO office_attendance_status_definitions (company_id, status_key, status_label, is_deleted) 
                                      VALUES (:cid, :key, :label, 1) ON DUPLICATE KEY UPDATE is_deleted = 1");
                $stmt->execute(['cid' => $companyId, 'key' => $id, 'label' => ucfirst(str_replace('_', ' ', $id))]);
            }
            return $this->jsonResponse(['message' => "Status removed."]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Get weekly schedules for a company
     */
    public function getWeeklySchedules($requestData)
    {
        try {
            $companyId = $requestData['company_id'] ?? null;
            if (!$companyId) return $this->jsonResponse(null, 400, "Company ID required.");
            $this->verifyDataScope($companyId);
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM office_weekly_schedules WHERE company_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
            $stmt->execute([$companyId]);
            return $this->jsonResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Save/Update a weekly schedule entry
     */
    public function saveWeeklySchedule($requestData)
    {
        if (($_SESSION['user_role'] ?? 'employee') === 'employee') return $this->jsonResponse(null, 403, "Access Denied.");
        try {
            $companyId = $requestData['company_id'];
            $this->verifyDataScope($companyId);
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO office_weekly_schedules (company_id, day_of_week, status, remarks) 
                                  VALUES (:cid, :day, :status1, :remarks1) ON DUPLICATE KEY UPDATE status = :status2, remarks = :remarks2");
            $stmt->execute([
                'cid' => $companyId, 
                'day' => $requestData['day_of_week'], 
                'status1' => $requestData['status'], 
                'remarks1' => $requestData['remarks'] ?? null,
                'status2' => $requestData['status'], 
                'remarks2' => $requestData['remarks'] ?? null
            ]);
            return $this->jsonResponse(['message' => "Schedule updated."]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Get grid data for all employees and their status for a date
     * Hardened: Restricts visibility to manager's primary/associated company scope.
     */
    public function getGridLogs($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $countryId = $requestData['country_id'] ?? null;
            if ($countryId === 'global' || $countryId === 'null') $countryId = null;

            // Calculate date dynamically using country's default timezone if date is omitted
            $date = $requestData['date'] ?? null;
            if (!$date) {
                if ($countryId) {
                    $stmtTz = $db->prepare("SELECT default_timezone FROM countries WHERE id = ?");
                    $stmtTz->execute([$countryId]);
                    $cTz = $stmtTz->fetchColumn();
                    if ($cTz) {
                        try {
                            $tzObj = new \DateTimeZone($cTz);
                            $dtLocal = new \DateTime('now', $tzObj);
                            $date = $dtLocal->format('Y-m-d');
                        } catch (\Exception $e) {
                            $date = date('Y-m-d');
                        }
                    } else {
                        $date = date('Y-m-d');
                    }
                } else {
                    $date = date('Y-m-d');
                }
            }

            $this->verifyDataScope(null, $countryId);

            $dayOfWeek = (new \DateTime($date))->format('l');

            $query = "
                SELECT 
                    e.id as employee_id, e.first_name, e.last_name, e.employee_code, e.gender,
                    d.title as role_name, c.id as company_id, al.id as log_id,
                    COALESCE(
                        al.status, 
                        IF(lr.leave_code IS NOT NULL AND (
                            lr.is_calendar_days = 1 OR (
                                ph.id IS NULL AND ch.id IS NULL AND (
                                    (ws.status IS NOT NULL AND ws.status NOT IN ('Off', 'Weekend', 'Holiday')) 
                                    OR (ws.status IS NULL AND :day1 NOT IN ('Saturday', 'Sunday'))
                                )
                            )
                        ), lr.leave_code, NULL),
                        IF(ph.id IS NOT NULL OR ch.id IS NOT NULL, 'public_holiday', NULL),
                        IF(ws.status IN ('Off', 'Weekend', 'Holiday') OR (ws.status IS NULL AND :day2 IN ('Saturday', 'Sunday')), 'weekend', NULL),
                        oasd.status_key
                    ) as status,
                    al.approval_status, al.remarks, al.check_in_utc, al.check_out_utc,
                    IF(al.id IS NOT NULL, al.is_default_applied, 0) as is_default_applied,
                    IF(al.id IS NOT NULL, al.is_manually_modified, 0) as is_manually_modified, IF(al.id IS NOT NULL, 1, 0) as is_saved
                FROM employees e
                LEFT JOIN designations d ON e.designation_id = d.id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                LEFT JOIN companies c ON ec.company_id = c.id
                LEFT JOIN (
                    SELECT lr_in.employee_id, lt_in.code as leave_code, clp.is_calendar_days 
                    FROM leave_requests lr_in 
                    JOIN leave_types lt_in ON lr_in.leave_type_id = lt_in.id 
                    LEFT JOIN employee_companies ec_lr ON lr_in.employee_id = ec_lr.employee_id AND ec_lr.is_primary = 1
                    LEFT JOIN company_leave_policies clp ON lt_in.id = clp.leave_type_id AND clp.company_id = ec_lr.company_id
                    WHERE lr_in.status = 'approved' AND :date1 BETWEEN lr_in.start_date AND lr_in.end_date
                ) lr ON e.id = lr.employee_id
                LEFT JOIN public_holidays ph ON c.country_id = ph.country_id AND ph.holiday_date = :date2
                LEFT JOIN holidays ch ON c.id = ch.company_id AND (ch.holiday_date = :date3a OR (ch.is_recurring = 1 AND MONTH(ch.holiday_date) = MONTH(:date3b) AND DAY(ch.holiday_date) = DAY(:date3c)))
                LEFT JOIN office_weekly_schedules ws ON c.id = ws.company_id AND ws.day_of_week = :day3
                LEFT JOIN office_attendance_status_definitions oasd ON c.id = oasd.company_id AND oasd.is_default = 1 AND (oasd.is_deleted = 0 OR oasd.is_deleted IS NULL)
                LEFT JOIN attendance_logs al ON e.id = al.employee_id AND al.attendance_date = :date4
                WHERE e.status = 'active'
            ";

            $params = ['day1' => $dayOfWeek, 'day2' => $dayOfWeek, 'day3' => $dayOfWeek, 'date1' => $date, 'date2' => $date, 'date3a' => $date, 'date3b' => $date, 'date3c' => $date, 'date4' => $date];

            if (!$this->isGlobalAdmin()) {
                $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                if (!empty($myCompanyIds)) {
                    $cList = implode(',', array_map('intval', $myCompanyIds));
                    $query .= " AND ec.company_id IN ($cList)";
                } else {
                    return $this->jsonResponse([]);
                }
            }

            if ($countryId) {
                $query .= " AND c.country_id = :country_id";
                $params['country_id'] = $countryId;
            }

            $query .= " ORDER BY e.first_name ASC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Timezone enrichment for displaying check-in/out times locally
            foreach ($logs as &$log) {
                $tz = \App\Helpers\DateHelper::getEmployeeTimezone((int)$log['employee_id']);
                $log['check_in_local'] = \App\Helpers\DateHelper::toLocal($log['check_in_utc'], $tz, 'H:i:s');
                $log['check_out_local'] = \App\Helpers\DateHelper::toLocal($log['check_out_utc'], $tz, 'H:i:s');
                $log['check_in_display'] = \App\Helpers\DateHelper::toLocal($log['check_in_utc'], $tz, 'h:i A');
                $log['check_out_display'] = \App\Helpers\DateHelper::toLocal($log['check_out_utc'], $tz, 'h:i A');
            }
            unset($log);

            return $this->jsonResponse($logs);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Batch save grid entries
     */
    public function saveGridEntries($requestData)
    {
        if (($_SESSION['user_role'] ?? 'employee') === 'employee') return $this->jsonResponse(null, 403, "Access Denied.");
        $entries = $requestData['entries'] ?? [];
        $date = $requestData['attendance_date'] ?? null;
        if (empty($entries) || !$date) return $this->jsonResponse(null, 400, "Missing data.");

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();
            $updatedCount = 0;
            $virtualUsed = [];
            $unpaidConversions = 0;
            $confirmUnpaid = !empty($requestData['confirm_unpaid']);
            
            foreach ($entries as $entry) {
                $employeeId = $entry['employee_id'];
                $this->verifyDataScope(null, null, $employeeId);
                
                $stmt = $db->prepare("SELECT company_id FROM employee_companies WHERE employee_id = ? AND is_primary = 1 AND is_active = 1");
                $stmt->execute([$employeeId]);
                $cid = $stmt->fetchColumn();
                if (!$cid) continue;

                $status = $entry['status'];
                $remarks = $entry['remarks'] ?? '';

                // Get employee timezone
                $timezone = \App\Helpers\DateHelper::getEmployeeTimezone((int)$employeeId);

                // Check if there is an existing log
                $stmt = $db->prepare("SELECT * FROM attendance_logs WHERE employee_id = ? AND attendance_date = ?");
                $stmt->execute([$employeeId, $date]);
                $existingLog = $stmt->fetch(\PDO::FETCH_ASSOC);

                $existingId = $existingLog ? $existingLog['id'] : null;
                $checkInUtc = $existingLog ? $existingLog['check_in_utc'] : null;
                $checkOutUtc = $existingLog ? $existingLog['check_out_utc'] : null;

                // Rule 2: Dynamic Balance Calculation for Manual Leave Entry
                $stmtLt = $db->prepare("SELECT id, code, is_paid FROM leave_types WHERE (code = ? OR name = ? OR id = ?) AND (company_id = ? OR company_id IS NULL) LIMIT 1");
                $stmtLt->execute([$status, $status, $status, $cid]);
                $lt = $stmtLt->fetch(\PDO::FETCH_ASSOC);

                if ($lt && $lt['is_paid']) {
                    // Check if already covered by an approved leave request
                    $stmtReq = $db->prepare("SELECT id FROM leave_requests WHERE employee_id = ? AND status = 'approved' AND ? BETWEEN start_date AND end_date");
                    $stmtReq->execute([$employeeId, $date]);
                    $hasLeave = $stmtReq->fetchColumn();

                    if (!$hasLeave) {
                        $ltid = $lt['id'];
                        $year = date('Y', strtotime($date));
                        $cacheKey = "{$employeeId}_{$ltid}_{$year}";
                        
                        if (!isset($virtualUsed[$cacheKey])) {
                            $stmtBal = $db->prepare("
                                SELECT (lb.allocated_days - lb.used_days - IFNULL(p.pending_days, 0)) as remaining 
                                FROM leave_balances lb
                                LEFT JOIN (
                                    SELECT employee_id, leave_type_id, SUM(total_days) as pending_days 
                                    FROM leave_requests 
                                    WHERE status = 'pending' AND YEAR(start_date) = :y1
                                    GROUP BY employee_id, leave_type_id
                                ) p ON lb.employee_id = p.employee_id AND lb.leave_type_id = p.leave_type_id
                                WHERE lb.employee_id = :eid AND lb.leave_type_id = :ltid AND lb.year = :y2
                            ");
                            $stmtBal->execute(['eid' => $employeeId, 'ltid' => $ltid, 'y1' => $year, 'y2' => $year]);
                            $remaining = $stmtBal->fetchColumn();
                            if ($remaining === false) {
                                $stmtDef = $db->prepare("SELECT default_days_per_year FROM company_leave_policies WHERE company_id = ? AND leave_type_id = ?");
                                $stmtDef->execute([$cid, $ltid]);
                                $remaining = $stmtDef->fetchColumn() ?: 0;
                            }
                            $virtualUsed[$cacheKey] = $remaining;
                        }

                        if ($virtualUsed[$cacheKey] > 0) {
                            $virtualUsed[$cacheKey]--;
                        } else {
                            // Exhausted: Force Unpaid Leave
                            if (!$confirmUnpaid) {
                                $unpaidConversions++;
                            }
                            $stmtUl = $db->prepare("SELECT code FROM leave_types WHERE code = 'UL' AND (company_id = ? OR company_id IS NULL) LIMIT 1");
                            $stmtUl->execute([$cid]);
                            $ulCode = $stmtUl->fetchColumn();
                            if ($ulCode) {
                                $status = $ulCode;
                            }
                        }
                    }
                }

                // Determine if we need to auto-populate check-in/out times for working statuses
                // Dynamic Check: Any status defined in office_attendance_status_definitions is a working status
                $stmtCheckAtt = $db->prepare("SELECT 1 FROM office_attendance_status_definitions WHERE status_key = ? AND company_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
                $stmtCheckAtt->execute([$status, $cid]);
                $isWorkingStatus = (bool)$stmtCheckAtt->fetchColumn();
                
                if ($isWorkingStatus) {
                    if (empty($checkInUtc) || empty($checkOutUtc)) {
                        // Fetch shift times from attendance policies or fallback
                        $stmtPolicy = $db->prepare("SELECT shift_start, shift_end FROM attendance_policies WHERE company_id = ? LIMIT 1");
                        $stmtPolicy->execute([$cid]);
                        $policy = $stmtPolicy->fetch(\PDO::FETCH_ASSOC);

                        $shiftStart = $policy['shift_start'] ?? '08:00:00';
                        $shiftEnd = $policy['shift_end'] ?? '17:00:00';

                        // Convert to UTC
                        if (empty($checkInUtc)) {
                            $checkInUtc = \App\Helpers\DateHelper::toUtc($date . ' ' . $shiftStart, $timezone);
                        }
                        if (empty($checkOutUtc)) {
                            $checkOutUtc = \App\Helpers\DateHelper::toUtc($date . ' ' . $shiftEnd, $timezone);
                        }
                    }
                } else {
                    // Set check-in/out to NULL for non-working statuses
                    $checkInUtc = null;
                    $checkOutUtc = null;
                }

                if ($existingId) {
                    $stmt = $db->prepare("
                        UPDATE attendance_logs 
                        SET status = ?, remarks = ?, check_in_utc = ?, check_out_utc = ?, is_manually_modified = 1, actor_type = 'user', approval_status = 'approved'
                        WHERE id = ?
                    ");
                    $stmt->execute([$status, $remarks, $checkInUtc, $checkOutUtc, $existingId]);

                    $newValues = array_merge($existingLog, [
                        'status' => $status,
                        'remarks' => $remarks,
                        'check_in_utc' => $checkInUtc,
                        'check_out_utc' => $checkOutUtc,
                        'is_manually_modified' => 1,
                        'actor_type' => 'user',
                        'approval_status' => 'approved'
                    ]);
                    $this->logChange($existingId, $_SESSION['user_id'] ?? 0, $existingLog, $newValues, "Grid Update");
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO attendance_logs (employee_id, company_id, attendance_date, status, remarks, check_in_utc, check_out_utc, approval_status, source, is_manually_modified, actor_type) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', 'manual', 1, 'user')
                    ");
                    $stmt->execute([$employeeId, $cid, $date, $status, $remarks, $checkInUtc, $checkOutUtc]);
                    $newId = $db->lastInsertId();

                    $newValues = [
                        'employee_id' => $employeeId,
                        'company_id' => $cid,
                        'attendance_date' => $date,
                        'status' => $status,
                        'remarks' => $remarks,
                        'check_in_utc' => $checkInUtc,
                        'check_out_utc' => $checkOutUtc,
                        'approval_status' => 'approved',
                        'source' => 'manual',
                        'is_manually_modified' => 1,
                        'actor_type' => 'user'
                    ];
                    $this->logChange($newId, $_SESSION['user_id'] ?? 0, null, $newValues, "Grid Insertion");
                }
                $updatedCount++;
            }
            if ($unpaidConversions > 0 && !$confirmUnpaid) {
                $db->rollBack();
                return $this->jsonResponse(null, 409, "UnpaidLeaveWarning");
            }

            $db->commit();
            
            // Trigger system draft leaves generation
            $leaveService = new \App\Services\LeaveService();
            $leaveService->generateSystemDraftLeaves();
            
            return $this->jsonResponse(['message' => "$updatedCount records updated."]);
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Automatically persist default attendance values for the current day.
     */
    public function autoPersistDefaults($requestData = [])
    {
        try {
            $date = $requestData['date'] ?? date('Y-m-d');
            $service = new \App\Services\AttendanceService();
            $count = $service->autoPersistDefaults($date);
            return $this->jsonResponse(['message' => "Auto-persistence complete: $count records created."]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Get data for Monthly Attendance Report Grid
     * Hardened: Restricts visibility to manager's primary/associated company scope.
     */
    public function getMonthlyReport($requestData, $internalCall = false)
    {
        try {
            $companyId = $requestData['company_id'] ?? null;
            if ($companyId === 'global' || $companyId === 'null') $companyId = null;
            $this->verifyDataScope($companyId);

            $service = new \App\Services\AttendanceService();
            $sessionData = [
                'is_global_admin' => $this->isGlobalAdmin(),
                'associated_company_ids' => $_SESSION['associated_company_ids'] ?? []
            ];

            $data = $service->getMonthlyReport($requestData, $sessionData);
            
            if ($internalCall) return $data;
            return $this->jsonResponse($data);
        } catch (\Exception $e) {
            if ($internalCall) throw $e;
            return $this->jsonResponse(null, 500, "Monthly report error: " . $e->getMessage());
        }
    }

    /**
     * Export Monthly Attendance Report to CSV
     */
    public function exportMonthlyReport($requestData)
    {
        try {
            $data = $this->getMonthlyReport($_GET, true);
            if (!$data) exit("No data found.");

            $gridData = $data['grid'];
            $dates = $data['dates'];
            $summaryKeys = array_keys($data['config']);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="Attendance_Report.csv"');
            $output = fopen('php://output', 'w');

            $headers = ['S/N', 'Employee Name', 'Employee Code'];
            foreach ($dates as $dm) $headers[] = $dm['day'] . " (" . $dm['day_name'] . ")";
            foreach ($summaryKeys as $sk) $headers[] = "Total " . ($data['config'][$sk]['label'] ?? $sk);
            fputcsv($output, $headers);

            $sn = 1;
            foreach ($gridData as $row) {
                $csvRow = [$sn++, $row['name'], $row['code']];
                foreach ($dates as $dm) {
                    $st = $row['days'][$dm['date']];
                    $csvRow[] = $st ? ($data['config'][$st]['key'] ?? $st) : '';
                }
                foreach ($summaryKeys as $sk) $csvRow[] = $row['totals'][$sk] ?? 0;
                fputcsv($output, $csvRow);
            }
            fclose($output);
            exit();
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage();
            exit();
        }
    }
}
