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
        try {
            $db = \Database::getInstance()->getConnection();

            $date = $requestData['date'] ?? date('Y-m-d');
            $companyId = $requestData['company_id'] ?? null;
            $this->verifyDataScope($companyId);
            $status = $requestData['status'] ?? null;

            $query = "
                SELECT al.*, 
                       e.first_name, e.last_name, e.employee_code,
                       c.name as company_name, c.attendance_mode
                FROM attendance_logs al
                JOIN employees e ON al.employee_id = e.id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                LEFT JOIN companies c ON ec.company_id = c.id
                WHERE al.attendance_date = :date
            ";

            $params = ['date' => $date];

            if ($companyId) {
                $query .= " AND c.id = :company_id";
                $params['company_id'] = $companyId;
            }

            if ($status) {
                $query .= " AND al.status = :status";
                $params['status'] = $status;
            }

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($logs);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
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
        $status = $requestData['status'] ?? 'present';
        $checkIn = !empty($requestData['check_in']) ? $requestData['check_in'] : null;
        $checkOut = !empty($requestData['check_out']) ? $requestData['check_out'] : null;

        $remarks = $requestData['remarks'] ?? null;

        if (!$employeeId || !$date) {
            return $this->jsonResponse(null, 400, "Employee ID and Date are required.");
        }

        // Noah Audit Fix: Scope validation for manual entry
        $this->verifyDataScope(null, null, $employeeId);

        // Validate and Format Time
        if ($checkIn && strlen($checkIn) <= 8) $checkIn = $date . ' ' . $checkIn;
        if ($checkOut && strlen($checkOut) <= 8) $checkOut = $date . ' ' . $checkOut;

        if ($checkIn && $checkOut) {
            if (strtotime($checkOut) <= strtotime($checkIn)) {
                return $this->jsonResponse(null, 400, "Check-out time must be after check-in time.");
            }
        }


        try {
            $db = \Database::getInstance()->getConnection();

            // Fetch company policy for the employee
            $stmt = $db->prepare("
                SELECT c.attendance_mode, ap.shift_start, ap.grace_period_mins 
                FROM companies c
                JOIN employee_companies ec ON c.id = ec.company_id AND ec.employee_id = :eid AND ec.is_primary = 1 AND ec.is_active = 1
                LEFT JOIN attendance_policies ap ON c.id = ap.company_id
                LIMIT 1
            ");
            $stmt->execute(['eid' => $employeeId]);
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Logic: Process status based on mode if not explicitly set
            if ($config && $config['attendance_mode'] === 'time_based' && !empty($checkIn) && $status === 'present') {
                $checkInTime = new \DateTime($checkIn);
                $shiftStart = new \DateTime($date . ' ' . ($config['shift_start'] ?? '08:00:00'));
                $graceMins = (int)($config['grace_period_mins'] ?? 15);

                $diff = $shiftStart->diff($checkInTime);
                $minutesLate = ($diff->invert === 0) ? ($diff->h * 60 + $diff->i) : 0;

                if ($checkInTime > $shiftStart && $minutesLate > $graceMins) {
                    $status = 'late';
                }
            }

            // Fetch existing record for audit logging
            $stmt = $db->prepare("SELECT * FROM attendance_logs WHERE employee_id = :eid AND attendance_date = :date");
            $stmt->execute(['eid' => $employeeId, 'date' => $date]);
            $oldValues = $stmt->fetch(\PDO::FETCH_ASSOC);

            $newValues = [
                'status' => $status,
                'check_in_utc' => $checkIn,
                'check_out_utc' => $checkOut,
                'remarks' => $remarks,
                'approval_status' => 'draft',
                'source' => 'manual'
            ];

            if ($oldValues) {
                $stmt = $db->prepare("
                    UPDATE attendance_logs 
                    SET status = :status, 
                        check_in_utc = :check_in_utc, 
                        check_out_utc = :check_out_utc, 
                        remarks = :remarks,
                        approval_status = 'draft',
                        source = 'manual'
                    WHERE id = :id
                ");
                $stmt->execute([
                    'status' => $status,
                    'check_in_utc' => $checkIn,
                    'check_out_utc' => $checkOut,
                    'remarks' => $remarks,
                    'id' => $oldValues['id']
                ]);
                $logId = $oldValues['id'];
                
                // Audit Log
                $this->logChange($logId, $userId, $oldValues, $newValues, "Manual Edit");
                $message = "Manual attendance updated for approval.";
            } else {
                $stmt = $db->prepare("
                    INSERT INTO attendance_logs (employee_id, attendance_date, status, check_in_utc, check_out_utc, remarks, approval_status, source)
                    VALUES (:eid, :date, :status, :check_in_utc, :check_out_utc, :remarks, 'draft', 'manual')
                ");
                $stmt->execute([
                    'eid' => $employeeId,
                    'date' => $date,
                    'status' => $status,
                    'check_in_utc' => $checkIn,
                    'check_out_utc' => $checkOut,
                    'remarks' => $remarks
                ]);
                $logId = $db->lastInsertId();
                
                // Audit Log (Creation)
                $this->logChange($logId, $userId, null, $newValues, "Manual Creation");
                $message = "Manual attendance recorded as draft.";
            }

            return $this->jsonResponse(['message' => $message]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
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
        $status = $requestData['status'] ?? 'present';
        $checkIn = !empty($requestData['check_in']) ? $requestData['check_in'] : null;
        $checkOut = !empty($requestData['check_out']) ? $requestData['check_out'] : null;

        $remarks = $requestData['remarks'] ?? null;

        if (empty($employeeIds) || !$date) {
            return $this->jsonResponse(null, 400, "Employee IDs and Date are required.");
        }

        // Format times if provided
        if ($checkIn && strlen($checkIn) <= 8) $checkIn = $date . ' ' . $checkIn;
        if ($checkOut && strlen($checkOut) <= 8) $checkOut = $date . ' ' . $checkOut;

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            foreach ($employeeIds as $employeeId) {
                // Fetch existing record
                $stmt = $db->prepare("SELECT * FROM attendance_logs WHERE employee_id = :eid AND attendance_date = :date");
                $stmt->execute(['eid' => $employeeId, 'date' => $date]);
                $oldValues = $stmt->fetch(\PDO::FETCH_ASSOC);

                $newValues = [
                    'status' => $status,
                    'check_in_utc' => $checkIn,
                    'check_out_utc' => $checkOut,
                    'remarks' => $remarks,
                    'approval_status' => 'draft',
                    'source' => 'manual'
                ];

                if ($oldValues) {
                    $stmt = $db->prepare("
                        UPDATE attendance_logs 
                        SET status = :status, 
                            check_in_utc = :check_in_utc, 
                            check_out_utc = :check_out_utc, 
                            remarks = :remarks,
                            approval_status = 'draft',
                            source = 'manual'
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'status' => $status,
                        'check_in_utc' => $checkIn,
                        'check_out_utc' => $checkOut,
                        'remarks' => $remarks,
                        'id' => $oldValues['id']
                    ]);
                    $logId = $oldValues['id'];
                    $this->logChange($logId, $userId, $oldValues, $newValues, "Bulk Edit");
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO attendance_logs (employee_id, attendance_date, status, check_in_utc, check_out_utc, remarks, approval_status, source)
                        VALUES (:eid, :date, :status, :check_in_utc, :check_out_utc, :remarks, 'draft', 'manual')
                    ");
                    $stmt->execute([
                        'eid' => $employeeId,
                        'date' => $date,
                        'status' => $status,
                        'check_in_utc' => $checkIn,
                        'check_out_utc' => $checkOut,
                        'remarks' => $remarks
                    ]);
                    $logId = $db->lastInsertId();
                    $this->logChange($logId, $userId, null, $newValues, "Bulk Creation");
                }
            }

            $db->commit();
            return $this->jsonResponse(['message' => count($employeeIds) . " attendance records logged as draft."]);
        } catch (\Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
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
            $db = \Database::getInstance()->getConnection();
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) return $this->jsonResponse(null, 401, "User session required.");
            
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("UPDATE attendance_logs SET approval_status = 'submitted', submitted_by_id = ? WHERE id IN ($placeholders)");
            $params = array_merge([$userId], $ids);
            $stmt->execute($params);

            return $this->jsonResponse(['message' => count($ids) . " logs submitted for approval."]);
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
            $stmt = $db->prepare("
                UPDATE attendance_logs 
                SET approval_status = ?, approved_by_id = ?, remarks = ? 
                WHERE id IN ($placeholders)
            ");
            $params = array_merge([$action, $userId, $remarks], $ids);
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
                // Fetch weekends
                $stmt = $db->prepare("SELECT day_of_week FROM office_weekly_schedules WHERE company_id = ? AND status = 'weekend'");
                $stmt->execute([$companyId]);
                $weekends = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                if (empty($weekends)) $weekends = ['Saturday', 'Sunday'];

                // Fetch holidays in range
                $stmt = $db->prepare("SELECT holiday_date FROM holidays WHERE company_id = ? AND holiday_date BETWEEN ? AND ?");
                $stmt->execute([$companyId, $startDate, $endDate]);
                $holidays = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }

            foreach ($period as $dt) {
                $dateStr = $dt->format("Y-m-d");
                $dayName = $dt->format("l");

                if ($isWorkingDayOnly) {
                    if (in_array($dayName, $weekends)) continue;
                    if (in_array($dateStr, $holidays)) continue;
                }

                // Check if log already exists
                $stmt = $db->prepare("SELECT id FROM attendance_logs WHERE employee_id = :eid AND attendance_date = :date");
                $stmt->execute(['eid' => $employeeId, 'date' => $dateStr]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $stmt = $db->prepare("UPDATE attendance_logs SET status = :status, approval_status = 'approved', source = 'leave_module' WHERE id = :id");
                    $stmt->execute(['status' => $status, 'id' => $existing['id']]);
                } else {
                    $stmt = $db->prepare("INSERT INTO attendance_logs (employee_id, attendance_date, status, approval_status, source) VALUES (:eid, :date, :status, 'approved', 'leave_module')");
                    $stmt->execute(['eid' => $employeeId, 'date' => $dateStr, 'status' => $status]);
                }
            }
            return true;
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

            // Fetch actual attendance logs
            $stmt = $db->prepare("
                SELECT attendance_date, status, approval_status 
                FROM attendance_logs 
                WHERE employee_id = :eid 
                AND MONTH(attendance_date) = :month 
                AND YEAR(attendance_date) = :year
            ");
            $stmt->execute(['eid' => $employeeId, 'month' => $month, 'year' => $year]);
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Fetch Holidays & Weekly Schedules
            $holidays = [];
            $weekends = [];
            if ($emp) {
                // Public Holidays (Country-wide)
                $stmt = $db->prepare("
                    SELECT holiday_date, name 
                    FROM public_holidays 
                    WHERE country_id = :cid AND MONTH(holiday_date) = :month AND YEAR(holiday_date) = :year
                ");
                $stmt->execute(['cid' => $emp['country_id'], 'month' => $month, 'year' => $year]);
                $pubHolidays = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($pubHolidays as $ph) {
                    $holidays[$ph['holiday_date']] = ['status' => 'public_holiday', 'name' => $ph['name']];
                }

                // Company-specific Holidays
                $stmt = $db->prepare("
                    SELECT holiday_date, name 
                    FROM holidays 
                    WHERE company_id = :comp_id AND MONTH(holiday_date) = :month AND YEAR(holiday_date) = :year
                ");
                $stmt->execute(['comp_id' => $emp['company_id'], 'month' => $month, 'year' => $year]);
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

            // Logic: Enrich daily logs for full month visibility
            $daysInMonth = (int)date('t', strtotime("$year-$month-01"));
            $enrichedLogs = [];
            $logsByDate = array_column($logs, null, 'attendance_date');

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $d);
                $dayName = date('l', strtotime($dateStr));

                if (isset($logsByDate[$dateStr])) {
                    $enrichedLogs[] = $logsByDate[$dateStr];
                } elseif (isset($holidays[$dateStr])) {
                    $enrichedLogs[] = [
                        'attendance_date' => $dateStr,
                        'status' => $holidays[$dateStr]['status'],
                        'name' => $holidays[$dateStr]['name'],
                        'approval_status' => 'approved'
                    ];
                } elseif (in_array($dayName, $weekends)) {
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
            $stmt = $db->query("SELECT id, name, iso_code FROM countries ORDER BY name ASC");
            $countries = $stmt->fetchAll(\PDO::FETCH_ASSOC);
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
            
            // RBAC: Verify user has access to this company context
            $this->verifyDataScope($companyId);
            
            // 1. Fetch Leave Types with their direct colors
            $stmt = $db->prepare("
                SELECT lt.code as id, lt.name, lt.gender_restriction, 'leave' as type, lt.color_code
                FROM leave_types lt
                ORDER BY lt.name ASC
            ");
            $stmt->execute();
            $leaveTypes = $stmt->fetchAll(\PDO::FETCH_ASSOC);


            // 2. Define System Defaults
            $systemDefaults = [
                'present' => ['id' => 'present', 'name' => 'Present', 'type' => 'system', 'color_code' => '#10b981'],
                'absent' => ['id' => 'absent', 'name' => 'Absent', 'type' => 'system', 'color_code' => '#ef4444'],
                'work_from_home' => ['id' => 'work_from_home', 'name' => 'Work From Home', 'type' => 'system', 'color_code' => '#d97706'],
                'on_site' => ['id' => 'on_site', 'name' => 'On Site', 'type' => 'system', 'color_code' => '#059669'],
                'training' => ['id' => 'training', 'name' => 'Training', 'type' => 'system', 'color_code' => '#8b5cf6'],
                'weekend' => ['id' => 'weekend', 'name' => 'Weekend', 'type' => 'system', 'color_code' => '#9ca3af'],
                'public_holiday' => ['id' => 'public_holiday', 'name' => 'Public Holiday', 'type' => 'system', 'color_code' => '#3b82f6'],
                'holiday' => ['id' => 'holiday', 'name' => 'Holiday', 'type' => 'system', 'color_code' => '#3b82f6']
            ];

            $officeStatuses = [];
            if ($companyId) {
                $stmt = $db->prepare("SELECT id, status_key, status_label as name, 'attendance' as type, color_code, is_default 
                                     FROM office_attendance_status_definitions 
                                     WHERE company_id = ? 
                                     ORDER BY sort_order ASC, name ASC");
                $stmt->execute([$companyId]);
                $customRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($customRows as $row) {
                    $key = $row['status_key'];
                    $officeStatuses[$key] = [
                        'id' => $key,
                        'name' => $row['name'],
                        'type' => 'attendance',
                        'color_code' => $row['color_code'],
                        'is_default' => (bool)$row['is_default'],
                        'status_id' => $row['id']
                    ];
                }
            }

            // Merge: Office statuses override system defaults
            $finalStatuses = array_values(array_merge($systemDefaults, $officeStatuses));

            // Create a unified lookup for the calendar/frontend
            $all = array_merge($finalStatuses, $leaveTypes);

            return $this->jsonResponse([
                'attendance' => $finalStatuses,
                'leave' => $leaveTypes,
                'all' => $all
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
                INSERT INTO office_attendance_status_definitions (company_id, status_key, status_label, color_code, is_default, sort_order)
                VALUES (:cid, :key, :label, :color, :is_def, :sort)
                ON DUPLICATE KEY UPDATE 
                    status_label = :label_up, 
                    color_code = :color_up, 
                    is_default = :is_def_up, 
                    sort_order = :sort_up
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
            if (!$id) return $this->jsonResponse(null, 400, "ID is required.");

            // RBAC: Verify ownership before deletion
            $stmt = $db->prepare("SELECT company_id FROM office_attendance_status_definitions WHERE id = ?");
            $stmt->execute([$id]);
            $companyId = $stmt->fetchColumn();
            if ($companyId) {
                $this->verifyDataScope($companyId);
            }
            $stmt = $db->prepare("DELETE FROM office_attendance_status_definitions WHERE id = ?");
            $stmt->execute([$id]);

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

            $dateObj = new \DateTime($date);
            $dayOfWeek = $dateObj->format('l');

            $query = "
                SELECT 
                    e.id as employee_id, e.first_name, e.last_name, e.employee_code, e.gender,
                    d.title as role_name,
                    c.id as company_id,
                    al.id as log_id,
                    -- Priority: Logged Status > Approved Leave > Public Holiday > Company Holiday > Weekend
                    COALESCE(
                        al.status, 
                        lr.leave_code,
                        IF(ph.id IS NOT NULL, 'public_holiday', NULL),
                        IF(ch.id IS NOT NULL, 'holiday', NULL),
                        IF(ws.status IN ('Off', 'Weekend', 'Holiday'), 'weekend', 
                           IF(ws.id IS NULL AND :day_name_weekend IN ('Saturday', 'Sunday'), 'weekend', 'present')
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
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                LEFT JOIN companies c ON ec.company_id = c.id
                -- Join for approved leave
                LEFT JOIN (
                    SELECT lr_inner.employee_id, lt_inner.code as leave_code
                    FROM leave_requests lr_inner
                    JOIN leave_types lt_inner ON lr_inner.leave_type_id = lt_inner.id
                    WHERE lr_inner.status = 'approved'
                      AND :date_lr BETWEEN lr_inner.start_date AND lr_inner.end_date
                ) lr ON e.id = lr.employee_id
                -- Join for public holidays (country)
                LEFT JOIN public_holidays ph ON c.country_id = ph.country_id AND ph.holiday_date = :date_ph
                -- Join for company-specific holidays
                LEFT JOIN holidays ch ON c.id = ch.company_id AND ch.holiday_date = :date_ch
                -- Join for weekend definition
                LEFT JOIN office_weekly_schedules ws ON c.id = ws.company_id AND ws.day_of_week = :day_name
                LEFT JOIN attendance_logs al ON e.id = al.employee_id AND al.attendance_date = :date_al
                WHERE e.status = 'active'
            ";

            $params = [
                'date_al' => $date,
                'date_lr' => $date,
                'date_ph' => $date,
                'date_ch' => $date,
                'day_name' => $dayOfWeek,
                'day_name_weekend' => $dayOfWeek
            ];

            if ($countryId && $countryId !== 'global' && $countryId !== 'null') {
                $query .= " AND c.country_id = :country_id";
                $params['country_id'] = $countryId;
            }

            $query .= " ORDER BY e.first_name ASC, e.last_name ASC";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $grid = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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

        try {
            $db = \Database::getInstance()->getConnection();
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

                    $stmt = $db->prepare("
                        UPDATE attendance_logs 
                        SET status = :status, 
                            remarks = :remarks,
                            approval_status = 'approved',
                            source = 'manual',
                            is_manually_modified = 1,
                            actor_type = 'user'
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'status' => $status,
                        'remarks' => $remarks ?? '',
                        'id' => $oldValues['id']
                    ]);
                    $logId = $oldValues['id'];
                    $this->logChange($logId, $userId, $oldValues, $newValues, "Grid Update");
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO attendance_logs (employee_id, attendance_date, status, remarks, approval_status, source, is_manually_modified, actor_type)
                        VALUES (:eid, :date, :status, :remarks, 'approved', 'manual', 1, 'user')
                    ");
                    $stmt->execute([
                        'eid' => $employeeId,
                        'date' => $date,
                        'status' => $status,
                        'remarks' => $remarks ?? ''
                    ]);
                    $logId = $db->lastInsertId();
                    $this->logChange($logId, $userId, null, $newValues, "Grid Creation");
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
     * This follows the same priority as getGridLogs but actually saves the records.
     */
    public function autoPersistDefaults($requestData = [])
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $date = $requestData['date'] ?? date('Y-m-d');
            
            $dateObj = new \DateTime($date);
            $dayOfWeek = $dateObj->format('l');

            // 1. Fetch all active employees who DON'T have a log for the date
            $query = "
                SELECT 
                    e.id as employee_id,
                    c.id as company_id,
                    lr.leave_code,
                    ph.id as ph_id,
                    ch.id as ch_id,
                    ws.status as weekend_status
                FROM employees e
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                LEFT JOIN companies c ON ec.company_id = c.id
                -- Join for approved leave
                LEFT JOIN (
                    SELECT lr_inner.employee_id, lt_inner.code as leave_code
                    FROM leave_requests lr_inner
                    JOIN leave_types lt_inner ON lr_inner.leave_type_id = lt_inner.id
                    WHERE lr_inner.status = 'approved'
                      AND :date_lr BETWEEN lr_inner.start_date AND lr_inner.end_date
                ) lr ON e.id = lr.employee_id
                -- Join for public holidays (country)
                LEFT JOIN public_holidays ph ON c.country_id = ph.country_id AND ph.holiday_date = :date_ph
                -- Join for company-specific holidays
                LEFT JOIN holidays ch ON c.id = ch.company_id AND ch.holiday_date = :date_ch
                -- Join for weekend definition
                LEFT JOIN office_weekly_schedules ws ON c.id = ws.company_id AND ws.day_of_week = :day_name
                WHERE e.status = 'active'
                  AND NOT EXISTS (
                      SELECT 1 FROM attendance_logs al 
                      WHERE al.employee_id = e.id AND al.attendance_date = :date_al
                  )
            ";

            $stmt = $db->prepare($query);
            $stmt->execute([
                'date_lr' => $date,
                'date_ph' => $date,
                'date_ch' => $date,
                'day_name' => $dayOfWeek,
                'date_al' => $date
            ]);
            $toProcess = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $persistedCount = 0;
            $db->beginTransaction();

            foreach ($toProcess as $row) {
                // Determine Status based on priority
                $status = null;
                if ($row['leave_code']) {
                    $status = $row['leave_code'];
                } elseif ($row['ph_id']) {
                    $status = 'public_holiday';
                } elseif ($row['ch_id']) {
                    $status = 'holiday';
                } elseif (in_array($row['weekend_status'], ['Off', 'Weekend', 'Holiday'])) {
                    $status = 'weekend';
                }

                // If a default status is identified, persist it
                if ($status) {
                    $stmtIns = $db->prepare("
                        INSERT INTO attendance_logs (employee_id, attendance_date, status, approval_status, source, is_default_applied, actor_type)
                        VALUES (:eid, :date, :status, 'approved', 'system_auto', 1, 'system')
                    ");
                    $stmtIns->execute([
                        'eid' => $row['employee_id'],
                        'date' => $date,
                        'status' => $status
                    ]);
                    $persistedCount++;
                }
            }

            $db->commit();
            return $this->jsonResponse(['message' => "Auto-persistence complete: $persistedCount records created for $date."]);
        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            return $this->jsonResponse(null, 500, "Auto-persistence failed: " . $e->getMessage());
        }
    }
}

