<?php

namespace App\Services;

use App\Helpers\DateHelper;

/**
 * AttendanceService
 * 
 * Handles business logic for attendance tracking, manual entries, 
 * and automated calculations.
 */
class AttendanceService
{
    /**
     * Fetch attendance logs with scoping and filters
     */
    public function fetchLogs(array $filters, array $sessionData, bool $isGlobalAdmin): array
    {
        $db = \Database::getInstance()->getConnection();

        $date = $filters['date'] ?? date('Y-m-d');
        $companyId = $filters['company_id'] ?? null;
        $status = $filters['status'] ?? null;

        $params = ['date' => $date];
        $whereClauses = ["al.attendance_date = :date"];

        if (!$isGlobalAdmin) {
            error_log("AttendanceService DEBUG: Session Keys: " . implode(', ', array_keys($sessionData)));
            $associatedCompanyIds = $sessionData['associated_company_ids'] ?? [];
            $sessionCountryId = $sessionData['scope_country_id'] ?? null;
            $userRole = strtoupper($sessionData['user_role'] ?? 'EMPLOYEE');
            error_log("AttendanceService DEBUG: role=$userRole country=$sessionCountryId assoc_count=" . count($associatedCompanyIds));
            
            $userRoleStr = str_replace([' ', '_'], '', $userRole);
            $isMultiOffice = false;
            $multiOfficeTargets = ['SUPERADMIN', 'ADMIN', 'HRMANAGER', 'HRASSISTANT', 'COUNTRYMANAGER'];
            foreach ($multiOfficeTargets as $target) {
                if ($userRoleStr === $target || strpos($userRoleStr, 'OFFICE' . $target) !== false || strpos($userRoleStr, 'GLOBAL' . $target) !== false) {
                    $isMultiOffice = true;
                    break;
                }
            }

            if ($isMultiOffice && !empty($associatedCompanyIds)) {
                $placeholders = [];
                foreach ($associatedCompanyIds as $idx => $cid) {
                    $key = "assoc_cid_$idx";
                    $placeholders[] = ":$key";
                    $params[$key] = $cid;
                }
                $whereClauses[] = "ec.company_id IN (" . implode(',', $placeholders) . ")";
            } else if ((strpos($userRoleStr, 'COUNTRYMANAGER') !== false) && $sessionCountryId) {
                $whereClauses[] = "c.country_id = :session_country_id";
                $params['session_country_id'] = $sessionCountryId;
            } else {
                $sessionCompanyId = $sessionData['scope_company_id'] ?? null;
                if ($sessionCompanyId) {
                    $whereClauses[] = "ec.company_id = :session_company_id";
                    $params['session_company_id'] = $sessionCompanyId;
                } else {
                    $whereClauses[] = "1=0";
                }
            }

            // Exclude SuperAdmins
            $whereClauses[] = "NOT EXISTS (
                SELECT 1 FROM user_roles ur2 
                WHERE ur2.user_id = u.id AND ur2.role_id = 1
            )";
        }

        if ($companyId) {
            $whereClauses[] = "c.id = :filter_company_id";
            $params['filter_company_id'] = $companyId;
        }

        if ($status) {
            $whereClauses[] = "al.status = :filter_status";
            $params['filter_status'] = $status;
        }

        $query = "
            SELECT DISTINCT al.*, 
                   e.first_name, e.last_name, e.employee_code,
                   c.name as company_name, c.attendance_mode,
                   cn.name as country_name
            FROM attendance_logs al
            JOIN employees e ON al.employee_id = e.id
            JOIN users u ON e.id = u.employee_id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            LEFT JOIN companies c ON ec.company_id = c.id
            LEFT JOIN countries cn ON c.country_id = cn.id
            WHERE " . implode(' AND ', $whereClauses) . "
        ";

        error_log("AttendanceService DEBUG: user=" . ($sessionData['user_id'] ?? 'unknown') . " role=" . ($sessionData['user_role'] ?? 'none') . " query=" . preg_replace('/\s+/', ' ', $query));
        error_log("AttendanceService DEBUG: params=" . json_encode($params));

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        error_log("AttendanceService DEBUG: found=" . count($logs));

        // Timezone enrichment
        foreach ($logs as &$log) {
            $tz = \App\Helpers\DateHelper::getEmployeeTimezone((int)$log['employee_id']);
            $log['check_in_local'] = \App\Helpers\DateHelper::toLocal($log['check_in_utc'], $tz, 'H:i:s');
            $log['check_out_local'] = \App\Helpers\DateHelper::toLocal($log['check_out_utc'], $tz, 'H:i:s');
            $log['check_in_display'] = \App\Helpers\DateHelper::toLocal($log['check_in_utc'], $tz, 'h:i A');
            $log['check_out_display'] = \App\Helpers\DateHelper::toLocal($log['check_out_utc'], $tz, 'h:i A');
        }

        return $logs;
    }

    /**
     * Process manual entry with policy checks
     */
    public function saveManualEntry(array $data, int $actionByUserId, bool $isSuperAdmin = false): array
    {
        $db = \Database::getInstance()->getConnection();
        
        $employeeId = (int)$data['employee_id'];
        $date = $data['attendance_date'];
        $status = $data['status'] ?? 'present';
        $checkInLocal = $data['check_in'] ?? null;
        $checkOutLocal = $data['check_out'] ?? null;
        $remarks = $data['remarks'] ?? null;

        // Fetch policy
        $stmt = $db->prepare("
            SELECT c.id as company_id, c.attendance_mode, ap.shift_start, ap.grace_period_mins 
            FROM companies c
            JOIN employee_companies ec ON c.id = ec.company_id AND ec.employee_id = :eid AND ec.is_primary = 1 AND ec.is_active = 1
            LEFT JOIN attendance_policies ap ON c.id = ap.company_id
            LIMIT 1
        ");
        $stmt->execute(['eid' => $employeeId]);
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$config) {
            throw new \Exception("Employee has no primary company assigned.");
        }

        $timezone = DateHelper::getEmployeeTimezone($employeeId);
        
        // Convert to UTC
        $checkInUtc = $checkInLocal ? DateHelper::toUtc($date . ' ' . $checkInLocal, $timezone) : null;
        $checkOutUtc = $checkOutLocal ? DateHelper::toUtc($date . ' ' . $checkOutLocal, $timezone) : null;

        // Auto-calculate status (Late detection)
        if ($config['attendance_mode'] === 'time_based' && $checkInUtc && $status === 'present') {
            try {
                $checkInTime = new \DateTime($checkInUtc, new \DateTimeZone('UTC'));
                $checkInTime->setTimezone(new \DateTimeZone($timezone));

                $shiftStart = new \DateTime($date . ' ' . ($config['shift_start'] ?? '08:00:00'), new \DateTimeZone($timezone));
                $graceMins = (int)($config['grace_period_mins'] ?? 15);

                if ($checkInTime > $shiftStart) {
                    $diff = $shiftStart->diff($checkInTime);
                    $minutesLate = ($diff->h * 60) + $diff->i;
                    if ($minutesLate > $graceMins) {
                        $status = 'late';
                    }
                }
            } catch (\Exception $ex) {
                // Fallback in case of parsing exceptions
            }
        }

        // Fetch existing for audit
        $stmt = $db->prepare("SELECT * FROM attendance_logs WHERE employee_id = :eid AND attendance_date = :date");
        $stmt->execute(['eid' => $employeeId, 'date' => $date]);
        $oldValues = $stmt->fetch(\PDO::FETCH_ASSOC);

        $newApprovalStatus = $isSuperAdmin ? 'approved' : 'draft';

        $newValues = [
            'status' => $status,
            'company_id' => $config['company_id'],
            'check_in_utc' => $checkInUtc,
            'check_out_utc' => $checkOutUtc,
            'remarks' => $remarks,
            'approval_status' => $newApprovalStatus,
            'source' => 'manual'
        ];

        if ($oldValues) {
            $stmt = $db->prepare("
                UPDATE attendance_logs 
                SET status = :status, 
                    company_id = :cid,
                    check_in_utc = :check_in_utc, 
                    check_out_utc = :check_out_utc, 
                    remarks = :remarks,
                    approval_status = :new_approval_status,
                    source = 'manual'
                WHERE id = :id
            ");
            $stmt->execute([
                'status' => $status,
                'cid' => $config['company_id'],
                'check_in_utc' => $checkInUtc,
                'check_out_utc' => $checkOutUtc,
                'remarks' => $remarks,
                'new_approval_status' => $newApprovalStatus,
                'id' => $oldValues['id']
            ]);
            $logId = (int)$oldValues['id'];
            $this->logAudit($logId, $actionByUserId, $oldValues, $newValues, "Manual Edit");
            $message = "Attendance updated as draft.";
        } else {
            $stmt = $db->prepare("
                INSERT INTO attendance_logs (employee_id, company_id, attendance_date, status, check_in_utc, check_out_utc, remarks, approval_status, source)
                VALUES (:eid, :cid, :date, :status, :check_in_utc, :check_out_utc, :remarks, :new_approval_status, 'manual')
            ");
            $stmt->execute([
                'eid' => $employeeId,
                'cid' => $config['company_id'],
                'date' => $date,
                'status' => $status,
                'check_in_utc' => $checkInUtc,
                'check_out_utc' => $checkOutUtc,
                'remarks' => $remarks,
                'new_approval_status' => $newApprovalStatus
            ]);
            $logId = (int)$db->lastInsertId();
            $this->logAudit($logId, $actionByUserId, null, $newValues, "Manual Creation");
            $message = "Attendance recorded as draft.";
        }

        $leaveService = new \App\Services\LeaveService();
        $leaveService->generateSystemDraftLeaves($config['company_id'], $employeeId);

        return ['message' => $message, 'id' => $logId];
    }

    /**
     * Bulk process manual entries
     */
    public function saveBulkEntry(array $data, int $actionByUserId, bool $isSuperAdmin = false): array
    {
        $db = \Database::getInstance()->getConnection();
        $employeeIds = $data['employee_ids'] ?? [];
        $date = $data['attendance_date'];
        $status = $data['status'] ?? 'present';
        $checkInLocal = $data['check_in'] ?? null;
        $checkOutLocal = $data['check_out'] ?? null;
        $remarks = $data['remarks'] ?? null;

        if (empty($employeeIds)) return ['message' => 'No employees selected.'];

        // Pre-fetch primary companies
        $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
        $compStmt = $db->prepare("SELECT employee_id, company_id FROM employee_companies WHERE employee_id IN ($placeholders) AND is_primary = 1 AND is_active = 1");
        $compStmt->execute($employeeIds);
        $empCompMap = $compStmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $db->beginTransaction();
        try {
            foreach ($employeeIds as $employeeId) {
                $cid = $empCompMap[$employeeId] ?? null;
                if (!$cid) continue;

                $this->saveManualEntry([
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,
                    'status' => $status,
                    'check_in' => $checkInLocal,
                    'check_out' => $checkOutLocal,
                    'remarks' => $remarks
                ], $actionByUserId, $isSuperAdmin);
            }
            $db->commit();
            
            $leaveService = new \App\Services\LeaveService();
            $leaveService->generateSystemDraftLeaves();
            
            return ['message' => count($employeeIds) . " records logged as draft."];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Submit logs for approval
     */
    public function submitLogs(array $ids, int $userId, array $scopeFilterData): int
    {
        $db = \Database::getInstance()->getConnection();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $scopeFilter = "";
        $params = [$userId];
        
        if (!$scopeFilterData['is_global_admin']) {
            $myCompanyIds = $scopeFilterData['associated_company_ids'] ?? [];
            $sessionCountryId = $scopeFilterData['scope_country_id'] ?? null;
            $userRole = strtoupper($scopeFilterData['user_role'] ?? 'EMPLOYEE');

            if (!empty($myCompanyIds)) {



                $cList = implode(',', array_map('intval', $myCompanyIds));
                $scopeFilter = " AND company_id IN ($cList)";
            } else {
                $scopeFilter = " AND 1=0";
            }
        }

        $stmt = $db->prepare("UPDATE attendance_logs SET approval_status = 'submitted', submitted_by_id = ? WHERE id IN ($placeholders) $scopeFilter");
        $stmt->execute(array_merge($params, $ids));
        
        return $stmt->rowCount();
    }

    /**
     * Automatically persist default attendance values for the current day.
     */
    public function autoPersistDefaults(string $date): int
    {
        $db = \Database::getInstance()->getConnection();
        $dateObj = new \DateTime($date);
        $dayOfWeek = $dateObj->format('l');

        $query = "
            SELECT e.id as employee_id, c.id as company_id, lr.leave_code,
                   ph.id as ph_id, ch.id as ch_id, ws.status as weekend_status,
                   clp.is_calendar_days, oasd.status_key as default_status
            FROM employees e
            JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            JOIN companies c ON ec.company_id = c.id
            LEFT JOIN (
                SELECT lr_inner.employee_id, lt_inner.code as leave_code, lt_inner.id as leave_type_id
                FROM leave_requests lr_inner
                JOIN leave_types lt_inner ON lr_inner.leave_type_id = lt_inner.id
                WHERE lr_inner.status = 'approved' AND :date_lr BETWEEN lr_inner.start_date AND lr_inner.end_date
            ) lr ON e.id = lr.employee_id
            LEFT JOIN company_leave_policies clp ON lr.leave_type_id = clp.leave_type_id AND c.id = clp.company_id
            LEFT JOIN public_holidays ph ON ph.holiday_date = :date_ph AND ph.country_id = c.country_id
            LEFT JOIN holidays ch ON ch.company_id = c.id AND (ch.holiday_date = :date_ch1 OR (ch.is_recurring = 1 AND MONTH(ch.holiday_date) = MONTH(:date_ch2) AND DAY(ch.holiday_date) = DAY(:date_ch3)))
            LEFT JOIN office_weekly_schedules ws ON c.id = ws.company_id AND ws.day_of_week = :day_name
            LEFT JOIN office_attendance_status_definitions oasd ON c.id = oasd.company_id AND oasd.is_default = 1 AND (oasd.is_deleted = 0 OR oasd.is_deleted IS NULL)
            WHERE e.status = 'active'
              AND NOT EXISTS (SELECT 1 FROM attendance_logs al WHERE al.employee_id = e.id AND al.attendance_date = :date_al)
        ";

        $stmt = $db->prepare($query);
        $stmt->execute([
            'date_lr' => $date, 'date_ph' => $date, 'date_ch1' => $date, 'date_ch2' => $date, 'date_ch3' => $date,
            'day_name' => $dayOfWeek, 'date_al' => $date
        ]);
        $toProcess = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $persistedCount = 0;
        $db->beginTransaction();
        foreach ($toProcess as $row) {
            $status = null;
            $isNonWorking = ($row['ph_id'] || $row['ch_id'] || in_array($row['weekend_status'], ['Off', 'Weekend', 'Holiday']));
            
            // Priority Logic:
            // 1. Leave (if calendar days OR if it's a working day)
            // 2. Public/Company Holiday
            // 3. Weekend
            // 4. Default Attendance Status (for working days)
            
            if ($row['leave_code']) {
                if (!$isNonWorking || (int)$row['is_calendar_days'] === 1) {
                    $status = $row['leave_code'];
                }
            }
            
            if (!$status) {
                if ($row['ph_id']) {
                    $status = 'public_holiday';
                } elseif ($row['ch_id']) {
                    $status = 'public_holiday';
                } elseif (in_array($row['weekend_status'], ['Off', 'Weekend', 'Holiday'])) {
                    $status = 'weekend';
                } elseif ($row['default_status']) {
                    $status = $row['default_status'];
                }
            }

            if ($status) {
                $db->prepare("
                    INSERT INTO attendance_logs (employee_id, company_id, attendance_date, status, approval_status, source, is_default_applied, actor_type)
                    VALUES (?, ?, ?, ?, 'approved', 'system_auto', 1, 'system')
                ")->execute([$row['employee_id'], $row['company_id'], $date, $status]);
                $persistedCount++;
            }
        }
        $db->commit();
        
        $leaveService = new \App\Services\LeaveService();
        $leaveService->generateSystemDraftLeaves();
        
        return $persistedCount;
    }

    /**
     * Get data for Monthly Attendance Report Grid
     */
    public function getMonthlyReport(array $params, array $sessionData = []): array
    {
        $db = \Database::getInstance()->getConnection();
        $month = str_pad($params['month'] ?? date('m'), 2, '0', STR_PAD_LEFT);
        $year = $params['year'] ?? date('Y');
        $companyId = $params['company_id'] ?? null;
        if ($companyId === 'global' || $companyId === 'null') $companyId = null;

        $startDate = $params['start_date'] ?? "$year-$month-01";
        if (isset($params['start_date']) && isset($params['end_date'])) {
            $endDate = $params['end_date'];
        } else {
            $totalDays = (int)date('t', strtotime($startDate));
            $endDate = "$year-$month-$totalDays";
        }

        // Generate date metadata
        $datesMeta = [];
        $startObj = new \DateTime($startDate);
        $endObj = (new \DateTime($endDate))->modify('+1 day');
        foreach (new \DatePeriod($startObj, new \DateInterval('P1D'), $endObj) as $dt) {
            $datesMeta[] = [
                'date' => $dt->format('Y-m-d'),
                'day' => (int)$dt->format('j'),
                'day_name' => $dt->format('D'),
                'day_full' => $dt->format('l')
            ];
        }

        // 1. Status Codes
        $systemCodes = [
            'work_from_home' => ['key' => 'WFH', 'label' => 'Work From Home', 'color_hex' => '#84cc16'],
            'weekend' => ['key' => 'WE', 'label' => 'Weekend', 'color_hex' => '#9ca3af'],
            'public_holiday' => ['key' => 'PU', 'label' => 'Public Holiday', 'color_hex' => '#3b82f6'],
            'present' => ['key' => 'PR', 'label' => 'Present', 'color_hex' => '#10b981'],
            'absent' => ['key' => 'AB', 'label' => 'Absent', 'color_hex' => '#ef4444'],
        ];

        $statusQuery = "SELECT id, status_key, status_label, color_code, display_code FROM office_attendance_status_definitions";
        $statusParams = [];
        if ($companyId) {
            $statusQuery .= " WHERE company_id = ?";
            $statusParams[] = $companyId;
        }
        $stmtS = $db->prepare($statusQuery);
        $stmtS->execute($statusParams);
        foreach ($stmtS->fetchAll(\PDO::FETCH_ASSOC) as $s) {
            $key = $s['status_key'] ?: $s['id'];
            $systemCodes[$key] = [
                'key' => $s['display_code'] ?: strtoupper(substr(str_replace(' ', '', $s['status_label']), 0, 2)),
                'label' => $s['status_label'], 'color_hex' => $s['color_code']
            ];
        }

        // 2. Leave Codes
        $stmtL = $db->query("SELECT code, name, color_code FROM leave_types");
        foreach ($stmtL->fetchAll(\PDO::FETCH_ASSOC) as $l) {
            $systemCodes[$l['code']] = [
                'key' => $l['code'],
                'label' => $l['name'],
                'color_hex' => $l['color_code'] ?: '#f59e0b'
            ];
        }

        // 2. Employees (Scoped)
        $isGlobalAdmin = !empty($sessionData['is_global_admin']);
        $empParams = [];
        $whereClauses = ["e.status IN ('active', 'onboarding', 'offboarding')"];

        if ($companyId) {
            $whereClauses[] = "ec.company_id = ?";
            $empParams[] = $companyId;
        } else if (!$isGlobalAdmin && !empty($sessionData['associated_company_ids'])) {
            $cIds = $sessionData['associated_company_ids'];
            $placeholders = implode(',', array_fill(0, count($cIds), '?'));
            $whereClauses[] = "ec.company_id IN ($placeholders)";
            $empParams = array_merge($empParams, $cIds);
        } else if (!$isGlobalAdmin) {
            $whereClauses[] = "1=0";
        }

        $empQuery = "
            SELECT e.id as employee_id, e.employee_code, e.first_name, e.last_name, c.id as company_id, c.country_id
            FROM employees e
            JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
            JOIN companies c ON ec.company_id = c.id
            WHERE " . implode(' AND ', $whereClauses) . "
            ORDER BY e.first_name ASC
        ";
        
        $stmtE = $db->prepare($empQuery);
        $stmtE->execute($empParams);
        $employees = $stmtE->fetchAll(\PDO::FETCH_ASSOC);

        error_log("AttendanceService DEBUG: Results count: " . count($employees));

        // 3. Raw Logs & Leaves
        $stmtLog = $db->prepare("SELECT al.employee_id, al.attendance_date, al.status FROM attendance_logs al JOIN employee_companies ec ON al.employee_id = ec.employee_id AND al.company_id = ec.company_id AND ec.is_primary = 1 WHERE al.attendance_date BETWEEN ? AND ?");
        $stmtLog->execute([$startDate, $endDate]);
        $logsMap = [];
        foreach ($stmtLog->fetchAll(\PDO::FETCH_ASSOC) as $log) $logsMap[$log['employee_id']][$log['attendance_date']] = $log['status'];

        $stmtLeave = $db->prepare("
            SELECT lr.employee_id, lr.start_date, lr.end_date, lt.code, clp.is_calendar_days 
            FROM leave_requests lr 
            JOIN leave_types lt ON lr.leave_type_id = lt.id 
            JOIN employee_companies ec ON lr.employee_id = ec.employee_id AND ec.is_primary = 1
            LEFT JOIN company_leave_policies clp ON lr.leave_type_id = clp.leave_type_id AND clp.company_id = ec.company_id
            WHERE lr.status = 'approved' AND lr.start_date <= ? AND lr.end_date >= ?
        ");
        $stmtLeave->execute([$endDate, $startDate]);
        $leavesMap = [];
        foreach ($stmtLeave->fetchAll(\PDO::FETCH_ASSOC) as $leave) {
            $s = new \DateTime(max($leave['start_date'], $startDate));
            $e = (new \DateTime(min($leave['end_date'], $endDate)))->modify('+1 day');
            foreach (new \DatePeriod($s, new \DateInterval('P1D'), $e) as $dt) {
                $leavesMap[$leave['employee_id']][$dt->format('Y-m-d')] = [
                    'code' => $leave['code'],
                    'is_calendar' => (bool)($leave['is_calendar_days'] ?? false)
                ];
            }
        }

        // 4. Holidays & Weekends
        $phMap = [];
        $stmtPh = $db->prepare("SELECT country_id, holiday_date FROM public_holidays WHERE holiday_date BETWEEN ? AND ?");
        $stmtPh->execute([$startDate, $endDate]);
        foreach ($stmtPh->fetchAll(\PDO::FETCH_ASSOC) as $ph) $phMap[$ph['country_id']][] = $ph['holiday_date'];

        $chMap = [];
        $stmtCh = $db->prepare("SELECT company_id, holiday_date, is_recurring FROM holidays WHERE (holiday_date BETWEEN ? AND ?) OR is_recurring = 1");
        $stmtCh->execute([$startDate, $endDate]);
        foreach ($stmtCh->fetchAll(\PDO::FETCH_ASSOC) as $ch) {
            if ($ch['is_recurring']) {
                $startYear = (int)date('Y', strtotime($startDate));
                $endYear = (int)date('Y', strtotime($endDate));
                for ($y = $startYear; $y <= $endYear; $y++) {
                    $d = $y . '-' . date('m-d', strtotime($ch['holiday_date']));
                    if ($d >= $startDate && $d <= $endDate) {
                        $chMap[$ch['company_id']][] = $d;
                    }
                }
            } else {
                $chMap[$ch['company_id']][] = $ch['holiday_date'];
            }
        }

        $weekendMap = [];
        $stmtWs = $db->query("SELECT company_id, day_of_week FROM office_weekly_schedules WHERE status IN ('Off', 'Weekend', 'Holiday')");
        foreach ($stmtWs->fetchAll(\PDO::FETCH_ASSOC) as $ws) $weekendMap[$ws['company_id']][] = $ws['day_of_week'];

        // 5. Default Attendance Status
        $defaultStatusMap = [];
        $stmtDefault = $db->query("SELECT company_id, status_key FROM office_attendance_status_definitions WHERE is_default = 1 AND (is_deleted = 0 OR is_deleted IS NULL)");
        foreach ($stmtDefault->fetchAll(\PDO::FETCH_ASSOC) as $ds) $defaultStatusMap[$ds['company_id']] = $ds['status_key'];

        // 6. Build Grid
        $resultGrid = []; $globalSummary = [];
        foreach ($employees as $emp) {
            $eid = $emp['employee_id']; $cid = $emp['company_id']; $cntryId = $emp['country_id'];
            $row = ['employee_id' => $eid, 'name' => trim($emp['first_name'] . ' ' . $emp['last_name']), 'code' => $emp['employee_code'], 'days' => [], 'totals' => []];
            $companyWeekends = $weekendMap[$cid] ?? ['Saturday', 'Sunday'];

            foreach ($datesMeta as $dm) {
                $d = $dm['date'];
                $status = null;
                $leaveInfo = $leavesMap[$eid][$d] ?? null;
                $actualLog = $logsMap[$eid][$d] ?? null;
                
                $isHoliday = (isset($phMap[$cntryId]) && in_array($d, $phMap[$cntryId])) || (isset($chMap[$cid]) && in_array($d, $chMap[$cid]));
                $isWeekend = in_array($dm['day_full'], $companyWeekends);
                $isNonWorking = $isHoliday || $isWeekend;

                if ($actualLog) {
                    $status = $actualLog;
                } elseif ($leaveInfo) {
                    if (!$isNonWorking || $leaveInfo['is_calendar']) {
                        $status = $leaveInfo['code'];
                    }
                }

                if (!$status) {
                    if (isset($phMap[$cntryId]) && in_array($d, $phMap[$cntryId])) $status = 'public_holiday';
                    elseif (isset($chMap[$cid]) && in_array($d, $chMap[$cid])) $status = 'public_holiday';
                    elseif ($isWeekend) $status = 'weekend';
                    elseif (isset($defaultStatusMap[$cid]) && $d <= date('Y-m-d')) $status = $defaultStatusMap[$cid];
                }

                $row['days'][$d] = $status;
                if ($status && $status !== 'weekend') {
                    $row['totals'][$status] = ($row['totals'][$status] ?? 0) + 1;
                    $globalSummary[$status] = ($globalSummary[$status] ?? 0) + 1;
                }
            }
            $resultGrid[] = $row;
        }

        return [
            'config' => $systemCodes, 'grid' => $resultGrid, 'summary' => $globalSummary,
            'dates' => $datesMeta, 'total_days' => count($datesMeta)
        ];
    }

    /**
     * Audit log helper
     */
    private function logAudit(int $logId, int $userId, ?array $old, array $new, string $reason): void
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
}
