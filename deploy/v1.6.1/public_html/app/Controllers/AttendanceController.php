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
            $status = $requestData['status'] ?? null;

            $query = "
                SELECT al.*, 
                       e.first_name, e.last_name, e.employee_code,
                       c.name as company_name, c.attendance_mode
                FROM attendance_logs al
                JOIN employees e ON al.employee_id = e.id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
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
                JOIN employee_companies ec ON c.id = ec.company_id AND ec.employee_id = :eid AND ec.is_primary = 1
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
     */
    public function setLeaveAttendanceRange($employeeId, $startDate, $endDate)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            $end->modify('+1 day');
            $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);

            foreach ($period as $dt) {
                $dateStr = $dt->format("Y-m-d");

                // Check if log already exists
                $stmt = $db->prepare("SELECT id FROM attendance_logs WHERE employee_id = :eid AND attendance_date = :date");
                $stmt->execute(['eid' => $employeeId, 'date' => $dateStr]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $stmt = $db->prepare("UPDATE attendance_logs SET status = 'on_leave', approval_status = 'approved', source = 'leave_module' WHERE id = :id");
                    $stmt->execute(['id' => $existing['id']]);
                } else {
                    $stmt = $db->prepare("INSERT INTO attendance_logs (employee_id, attendance_date, status, approval_status, source) VALUES (:eid, :date, 'on_leave', 'approved', 'leave_module')");
                    $stmt->execute(['eid' => $employeeId, 'date' => $dateStr]);
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

            $stmt = $db->prepare("
                SELECT status, COUNT(*) as count 
                FROM attendance_logs 
                WHERE employee_id = :eid 
                AND MONTH(attendance_date) = :month 
                AND YEAR(attendance_date) = :year
                GROUP BY status
            ");
            $stmt->execute(['eid' => $employeeId, 'month' => $month, 'year' => $year]);
            $stats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Fetch daily logs for the calendar
            $stmt = $db->prepare("
                SELECT attendance_date, status, approval_status 
                FROM attendance_logs 
                WHERE employee_id = :eid 
                AND MONTH(attendance_date) = :month 
                AND YEAR(attendance_date) = :year
            ");
            $stmt->execute(['eid' => $employeeId, 'month' => $month, 'year' => $year]);
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse([
                'stats' => $stats,
                'logs' => $logs
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
     * Get grid data for all employees and their status for a date
     */
    public function getGridLogs($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $date = $requestData['date'] ?? date('Y-m-d');
            $countryId = $requestData['country_id'] ?? null;

            $query = "
                SELECT 
                    e.id as employee_id, e.first_name, e.last_name, e.employee_code,
                    d.title as role_name,
                    al.id as log_id, al.status, al.approval_status, al.remarks,
                    al.check_in_utc, al.check_out_utc
                FROM employees e
                LEFT JOIN designations d ON e.designation_id = d.id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
                LEFT JOIN companies c ON ec.company_id = c.id
                LEFT JOIN attendance_logs al ON e.id = al.employee_id AND al.attendance_date = :date
                WHERE e.status = 'active'
            ";

            $params = ['date' => $date];

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
                    'source' => 'manual'
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
                            source = 'manual'
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
                        INSERT INTO attendance_logs (employee_id, attendance_date, status, remarks, approval_status, source)
                        VALUES (:eid, :date, :status, :remarks, 'approved', 'manual')
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
}

