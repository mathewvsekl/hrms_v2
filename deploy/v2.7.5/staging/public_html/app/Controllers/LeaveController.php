<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ApprovalHelper;
use App\Helpers\DateHelper;

/**
 * LeaveController
 * 
 * Handles leave requests, balances, and policies.
 */
class LeaveController extends Controller
{
    /**
     * Fetch leave requests
     */
    public function getRequests($requestData)
    {
        try {
            $db = \Database::getInstance()->getConnection();

            $status = $requestData['status'] ?? null;
            $companyId = $requestData['company_id'] ?? null;
            $employeeId = $requestData['employee_id'] ?? null;

            $params = [];
            $geographicFilter = "";
            $isGlobalAdmin = $this->isGlobalAdmin();
            if (!$isGlobalAdmin) {
                $isMultiOffice = $this->hasAnyRole(['HRManager', 'HRAssistant', 'CountryManager', 'COUNTRY MANAGER']);

                $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;

                if ($isMultiOffice && !empty($associatedCompanyIds)) {
                    $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                    $geographicFilter = " AND ec.company_id IN ($companyIdList)";
                } else if ($this->hasAnyRole(['CountryManager', 'COUNTRY MANAGER']) && $sessionCountryId) {
                    $geographicFilter = " AND EXISTS (SELECT 1 FROM companies c2 WHERE ec.company_id = c2.id AND c2.country_id = :session_country_id)";
                    $params['session_country_id'] = $sessionCountryId;
                } else {
                    $sessionCompanyId = $_SESSION['scope_company_id'] ?? null;
                    if ($sessionCompanyId) {
                        $geographicFilter = " AND ec.company_id = :session_company_id";
                        $params['session_company_id'] = $sessionCompanyId;
                    } else if (!$isMultiOffice) {
                        $geographicFilter = " AND 1=0";
                    }
                }

                $geographicFilter .= " AND NOT EXISTS (
                    SELECT 1 FROM user_roles ur2 
                    WHERE ur2.user_id = u.id AND ur2.role_id = 1
                )";
            }

            $query = "
                SELECT lr.*, 
                       e.first_name, e.last_name,
                       lt.name as leave_type_name, lt.color_code as leave_type_color,
                       appr_u.username as approved_by_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                JOIN users u ON e.id = u.employee_id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users appr_u ON lr.approved_by_id = appr_u.id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                WHERE 1=1 $geographicFilter
            ";


            if ($status) {
                $statusArray = explode(',', $status);
                $placeholders = [];
                foreach ($statusArray as $idx => $s) {
                    $key = "status_$idx";
                    $placeholders[] = ":$key";
                    $params[$key] = trim($s);
                }
                $query .= " AND lr.status IN (" . implode(',', $placeholders) . ")";
            }

            if ($companyId) {
                // If they provided a companyId, verify scope
                $this->verifyDataScope($companyId);
                $query .= " AND ec.company_id = :company_id";
                $params['company_id'] = $companyId;
            }


            if ($employeeId) {
                $query .= " AND lr.employee_id = :employee_id";
                $params['employee_id'] = $employeeId;
            }

            $query .= " ORDER BY lr.start_date DESC";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $requests = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->jsonResponse($requests);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Submit a new leave request (Support for arrays and Multi-Type segments)
     */
    public function submitRequest($requestData)
    {
        $employeeId = $requestData['employee_id'] ?? null;
        $segments = $requestData['segments'] ?? []; // Should be array of {leave_type_id, start_date, end_date}

        // Backward compatibility for single-segment requests
        if (empty($segments) && isset($requestData['leave_type_id'])) {
            $segments = [[
                'leave_type_id' => $requestData['leave_type_id'],
                'start_date' => $requestData['start_date'],
                'end_date' => $requestData['end_date']
            ]];
        }

        if (!$employeeId || empty($segments)) {
            return $this->jsonResponse(null, 400, "Missing required fields or segments.");
        }

        $remarks = $requestData['remarks'] ?? null;
        $attachmentPath = null;

        // Handle File Upload if present
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = BASE_PATH . '/public/uploads/leave/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = uniqid('leave_') . '_' . basename($_FILES['attachment']['name']);
            $filePath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $filePath)) {
                // Use the standardized UI serving path
                // Use a standardized root-relative path
                $attachmentPath = '/public/uploads/leave/' . $fileName;
            }
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Get Employee Details
            $stmt = $db->prepare("
                SELECT ec.company_id, e.gender 
                FROM employees e
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                WHERE e.id = :id
            ");
            $stmt->execute(['id' => $employeeId]);
            $employee = $stmt->fetch(\PDO::FETCH_ASSOC);
            $companyId = $employee['company_id'] ?? null;
            $employeeGender = $employee['gender'] ?? 'other';

            if (!$companyId) {
                $db->rollBack();
                return $this->jsonResponse(null, 404, "Employee primary company not found.");
            }

            $requestGroupId = bin2hex(random_bytes(8));
            $results = [];

            foreach ($segments as $seg) {
                $ltid = $seg['leave_type_id'];
                $start = DateHelper::toSql($seg['start_date']);
                $end = DateHelper::toSql($seg['end_date']);
                $year = date('Y', strtotime($start));

                // 2. Check Overlap (Strict logic per v1.8.7)
                $stmtOver = $db->prepare("
                    SELECT id FROM leave_requests 
                    WHERE employee_id = ? 
                    AND status NOT IN ('rejected', 'cancelled')
                    AND (
                        (start_date <= ? AND end_date >= ?) OR
                        (start_date <= ? AND end_date >= ?) OR
                        (start_date >= ? AND end_date <= ?)
                    )
                ");
                $stmtOver->execute([$employeeId, $start, $start, $end, $end, $start, $end]);
                if ($stmtOver->fetch()) {
                    $db->rollBack();
                    return $this->jsonResponse(null, 400, "Overlapping leave request found for dates $start to $end.");
                }

                // 3. Gender Restriction
                $stmtLt = $db->prepare("SELECT name, gender_restriction FROM leave_types WHERE id = ?");
                $stmtLt->execute([$ltid]);
                $leaveType = $stmtLt->fetch(\PDO::FETCH_ASSOC);

                if ($leaveType && $leaveType['gender_restriction'] !== 'none') {
                    if ($leaveType['gender_restriction'] !== $employeeGender) {
                        $db->rollBack();
                        $genderLabel = $leaveType['gender_restriction'] === 'female' ? 'Female' : 'Male';
                        return $this->jsonResponse(null, 400, "Leave type {$leaveType['name']} is only for {$genderLabel} employees.");
                    }
                }

                // 4. Calculate Days
                $totalDays = $this->calculateLeaveDays($employeeId, $companyId, $ltid, $start, $end);

                // 5. Check Balance
                $stmtBal = $db->prepare("
                    SELECT (allocated_days - used_days) as remaining 
                    FROM leave_balances 
                    WHERE employee_id = :eid AND leave_type_id = :ltid AND year = :year
                ");
                $stmtBal->execute(['eid' => $employeeId, 'ltid' => $ltid, 'year' => $year]);
                $remaining = $stmtBal->fetchColumn();

                if ($remaining === false || $remaining < $totalDays) {
                    $db->rollBack();
                    return $this->jsonResponse(null, 400, "Insufficient balance for {$leaveType['name']}. Available: " . ($remaining ?: 0));
                }

                // 6. Insert
                $stmtIns = $db->prepare("
                    INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, status, request_group_id, remarks, attachment_path)
                    VALUES (:eid, :ltid, :start, :end, :days, 'pending', :gid, :remarks, :attachment)
                ");
                $stmtIns->execute([
                    'eid' => $employeeId,
                    'ltid' => $ltid,
                    'start' => $start,
                    'end' => $end,
                    'days' => $totalDays,
                    'gid' => $requestGroupId,
                    'remarks' => $remarks,
                    'attachment' => $attachmentPath
                ]);
                
                $requestId = $db->lastInsertId();
                ApprovalHelper::log('leave', (int)$requestId, 'submitted', $remarks);

                $results[] = [
                    'type' => $leaveType['name'],
                    'days' => $totalDays,
                    'id' => $requestId
                ];
            }

            $db->commit();

            // Notify Manager
            $totalRequestDays = array_sum(array_column($results, 'days'));
            \App\Helpers\NotificationHelper::notifyManager(
                $employeeId,
                'leave_request',
                'New multi-segment leave request',
                "Employee has submitted leave segments totaling $totalRequestDays days.",
                ['link' => '/leave', 'group_id' => $requestGroupId],
                true // emailNotify
            );

            return $this->jsonResponse(['message' => 'Leave request(s) submitted successfully.', 'results' => $results]);

        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Helper: Calculate actual leave days skipping holidays and weekends
     */
    private function calculateLeaveDays($employeeId, $companyId, $leaveTypeId, $startDate, $endDate)
    {
        $db = \Database::getInstance()->getConnection();

        // 1. Get Company Policy
        $stmt = $db->prepare("SELECT is_calendar_days FROM company_leave_policies WHERE company_id = :cid AND leave_type_id = :ltid");
        $stmt->execute(['cid' => $companyId, 'ltid' => $leaveTypeId]);
        $policy = $stmt->fetch(\PDO::FETCH_ASSOC);

        $isCalendarDays = $policy ? (bool) $policy['is_calendar_days'] : false;
        $weekends = $isCalendarDays ? [] : $this->getCompanyWeekends($companyId);

        // 2. Get Holidays (only needed for Working Days mode)
        $holidays = [];
        if (!$isCalendarDays) {
            // Company-specific holidays
            $stmt = $db->prepare("SELECT holiday_date FROM holidays WHERE company_id = :cid AND holiday_date BETWEEN :start AND :end");
            $stmt->execute(['cid' => $companyId, 'start' => $startDate, 'end' => $endDate]);
            $holidays = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // Fetch country_id for the company to get correct public holidays
            $stmtC = $db->prepare("SELECT country_id FROM companies WHERE id = ?");
            $stmtC->execute([$companyId]);
            $cntryId = $stmtC->fetchColumn();

            // Fetch public holidays based on primary company's country
            $stmtPH = $db->prepare("SELECT holiday_date FROM public_holidays WHERE country_id = ? AND holiday_date BETWEEN ? AND ?");
            $stmtPH->execute([$cntryId, $startDate, $endDate]);
            $pubHolidays = $stmtPH->fetchAll(\PDO::FETCH_COLUMN);
            $holidays = array_unique(array_merge($holidays, $pubHolidays));
        }

        // 3. Iterate Days
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day');
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);

        $total = 0;
        foreach ($period as $dt) {
            $dateStr = $dt->format("Y-m-d");
            $dayName = $dt->format("l");

            if ($isCalendarDays) {
                // In Calendar Days mode, every day in range counts.
                $total++;
                continue;
            }

            // Working Days mode: Skip weekends and holidays
            // Use same logic as recalculateBalances
            if (in_array($dayName, $weekends)) continue;
            if (in_array($dateStr, $holidays)) continue;

            $total++;
        }

        return $total;
    }

    /**
     * Fetch public holidays for a company
     */
    public function getHolidays($companyId)
    {
        try {
            $this->verifyDataScope($companyId);
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM holidays WHERE company_id = :company_id ORDER BY holiday_date ASC");
            $stmt->execute(['company_id' => $companyId]);
            $holidays = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($holidays);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Add a new public holiday
     */
    public function addHoliday($requestData)
    {
        $companyId = $requestData['company_id'] ?? null;
        $date = $requestData['holiday_date'] ?? null;
        $name = $requestData['name'] ?? null;
        $isRecurring = $requestData['is_recurring'] ?? false;

        if (!$companyId || !$date || !$name) {
            return $this->jsonResponse(null, 400, "Company ID, Date, and Name are required.");
        }

        $this->verifyDataScope($companyId);

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO holidays (company_id, holiday_date, name, is_recurring)
                VALUES (:company_id, :holiday_date, :name, :is_recurring)
            ");
            $stmt->execute([
                'company_id' => $companyId,
                'holiday_date' => $date,
                'name' => $name,
                'is_recurring' => $isRecurring ? 1 : 0
            ]);

            // Sync with Attendance Logs
            $stmt = $db->prepare("
                UPDATE attendance_logs al
                JOIN employees e ON al.employee_id = e.id
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_active = 1 AND ec.is_primary = 1
                SET al.status = 'public_holiday', al.source = 'holiday_sync'
                WHERE ec.company_id = :cid
                AND al.attendance_date = :date
                AND al.status != 'on_leave'
            ");
            $stmt->execute(['cid' => $companyId, 'date' => $date]);

            // For employees who don't have a log yet for that day, we should ideally insert one.
            // But usually the Daily Batch creates them. If we want immediate visibility:
            $stmt = $db->prepare("
                INSERT IGNORE INTO attendance_logs (employee_id, company_id, attendance_date, status, approval_status, source)
                SELECT e.id, ec.company_id, :date, 'public_holiday', 'approved', 'holiday_sync'
                FROM employees e
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_active = 1 AND ec.is_primary = 1
                WHERE ec.company_id = :cid2 AND e.status = 'active'
                AND NOT EXISTS (SELECT 1 FROM attendance_logs al2 WHERE al2.employee_id = e.id AND al2.attendance_date = :date2)
            ");
            $stmt->execute(['date' => $date, 'cid2' => $companyId, 'date2' => $date]);

            return $this->jsonResponse(['message' => 'Holiday added and attendance synced.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Delete a public holiday
     */
    public function deleteHoliday($id)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Scope Check
            $stmt = $db->prepare("SELECT company_id FROM holidays WHERE id = ?");
            $stmt->execute([$id]);
            $cid = $stmt->fetchColumn();
            if ($cid) $this->verifyDataScope($cid);

            $stmt = $db->prepare("DELETE FROM holidays WHERE id = :id");
            $stmt->execute(['id' => $id]);

            return $this->jsonResponse(['message' => 'Holiday deleted successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Approve a leave request
     */
    public function approveRequest($requestData)
    {
        $id = $requestData['id'] ?? null;
        $approverId = $_SESSION['user_id'] ?? null;

        if (!$id) {
            return $this->jsonResponse(null, 400, "Leave Request ID is required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Fetch Request Details
            $stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $id]);
            $request = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$request || $request['status'] !== 'pending') {
                $db->rollBack();
                return $this->jsonResponse(null, 400, "Request not found or already processed.");
            }

            // 2. Update Status
            $stmt = $db->prepare("
                UPDATE leave_requests 
                SET status = 'approved', approved_by_id = :approver_id, updated_at_utc = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id, 'approver_id' => $approverId]);

            // 3. Deduct Balance
            $stmt = $db->prepare("
                UPDATE leave_balances 
                SET used_days = used_days + :days 
                WHERE employee_id = :eid AND leave_type_id = :ltid AND year = :year
            ");
            $stmt->execute([
                'days' => $request['total_days'],
                'eid' => $request['employee_id'],
                'ltid' => $request['leave_type_id'],
                'year' => date('Y', strtotime($request['start_date']))
            ]);

            // 4. Sync with Attendance
            // First, fetch the leave type code to use as the attendance status
            $stmt = $db->prepare("SELECT code FROM leave_types WHERE id = :id");
            $stmt->execute(['id' => $request['leave_type_id']]);
            $leaveTypeCode = $stmt->fetchColumn() ?: 'on_leave';

            // Fetch policy to determine sync behavior (Working Days vs Calendar Days)
            $stmt = $db->prepare("SELECT ec.company_id, clp.is_calendar_days 
                                 FROM employee_companies ec 
                                 LEFT JOIN company_leave_policies clp ON ec.company_id = clp.company_id AND clp.leave_type_id = :ltid
                                 WHERE ec.employee_id = :eid AND ec.is_primary = 1 AND ec.is_active = 1");
            $stmt->execute(['eid' => $request['employee_id'], 'ltid' => $request['leave_type_id']]);
            $policyInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $companyId = $policyInfo['company_id'] ?? null;
            $isWorkingDayOnly = $policyInfo ? !((bool)$policyInfo['is_calendar_days']) : true;

            // Noah Audit Fix: Only sync attendance if the leave has already started or starts today.
            // Future leaves will be picked up by the daily attendance generator.
            $today = date('Y-m-d');
            if ($request['start_date'] <= $today) {
                $attendance = new \App\Controllers\AttendanceController();
                $attendance->setLeaveAttendanceRange(
                    $request['employee_id'], 
                    $request['start_date'], 
                    ($request['end_date'] < $today ? $request['end_date'] : $today),
                    $leaveTypeCode,
                    $isWorkingDayOnly,
                    $companyId
                );
            }
            $db->commit();

            // Noah Logic Auditor: Centralized History Log
            ApprovalHelper::log('leave', (int)$id, 'approved', 'Request approved and synced with attendance.');
            // We need the user_id associated with the employee
            $stmtUser = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmtUser->execute([$request['employee_id']]);
            $recipientUserId = $stmtUser->fetchColumn();

            if ($recipientUserId) {
                \App\Helpers\NotificationHelper::send(
                    $recipientUserId,
                    'leave_approved',
                    'Leave Request Approved',
                    "Your leave request from {$request['start_date']} to {$request['end_date']} has been approved.",
                    ['link' => '/leave', 'id' => $id],
                    true // emailNotify
                );
            }

            return $this->jsonResponse(['message' => 'Leave request approved and synced with attendance.']);
        } catch (\Exception $e) {
            if (isset($db))
                $db->rollBack();
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Reject a leave request
     */
    public function rejectRequest($requestData)
    {
        $id = $requestData['id'] ?? null;
        $comment = $requestData['comment'] ?? '';
        $approverId = $_SESSION['user_id'] ?? null;

        if (!$id) {
            return $this->jsonResponse(null, 400, "Leave Request ID is required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                UPDATE leave_requests 
                SET status = 'rejected', manager_comment = :comment, approved_by_id = :approver_id, updated_at_utc = CURRENT_TIMESTAMP
                WHERE id = :id AND status = 'pending'
            ");
            $stmt->execute(['id' => $id, 'comment' => $comment, 'approver_id' => $approverId]);

            // Noah Logic Auditor: Centralized History Log
            ApprovalHelper::log('leave', (int)$id, 'rejected', $comment);

            // Notify Employee
            $stmtReq = $db->prepare("SELECT employee_id, start_date, end_date FROM leave_requests WHERE id = ?");
            $stmtReq->execute([$id]);
            $request = $stmtReq->fetch(\PDO::FETCH_ASSOC);

            $stmtUser = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmtUser->execute([$request['employee_id']]);
            $recipientUserId = $stmtUser->fetchColumn();

            if ($recipientUserId) {
                \App\Helpers\NotificationHelper::send(
                    $recipientUserId,
                    'leave_rejected',
                    'Leave Request Rejected',
                    "Your leave request from {$request['start_date']} to {$request['end_date']} has been rejected. Reason: $comment",
                    ['link' => '/leave', 'id' => $id],
                    true // emailNotify
                );
            }

            return $this->jsonResponse(['message' => 'Leave request rejected.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Get employee leave balances
     */
    public function getBalances($employeeId)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $year = date('Y');

            $this->verifyDataScope(null, null, $employeeId);


            // Find employee gender to filter restricted leave types
            $stmtEmp = $db->prepare("SELECT gender FROM employees WHERE id = ?");
            $stmtEmp->execute([$employeeId]);
            $gender = $stmtEmp->fetchColumn() ?: 'other';

            $stmt = $db->prepare("
                SELECT lb.*, lt.name as leave_type_name, lt.gender_restriction, lt.code as leave_type_code,
                       lt.color_code
                FROM leave_balances lb
                JOIN leave_types lt ON lb.leave_type_id = lt.id
                WHERE lb.employee_id = :eid 
                AND lb.year = :year
                AND (lt.gender_restriction = 'none' OR lt.gender_restriction = :gender)
            ");
            $stmt->execute(['eid' => $employeeId, 'year' => $year, 'gender' => $gender]);
            $balances = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($balances);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }


    /**
     * Preview leave days based on start and end date (AJAX)
     */
    public function previewLeaveDays($requestData)
    {
        $employeeId = $requestData['employee_id'] ?? null;
        $leaveTypeId = $requestData['leave_type_id'] ?? null;
        $startDate = $requestData['start_date'] ?? null;
        $endDate = $requestData['end_date'] ?? null;

        if (!$employeeId || !$leaveTypeId || !$startDate || !$endDate) {
            return $this->jsonResponse(null, 400, "Missing required fields.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT ec.company_id 
                FROM employee_companies ec 
                WHERE ec.employee_id = :id AND ec.is_primary = 1 AND ec.is_active = 1
            ");
            $stmt->execute(['id' => $employeeId]);
            $companyId = $stmt->fetchColumn();

            if (!$companyId)
                return $this->jsonResponse(null, 404, "Employee company not found.");

            $total = $this->calculateLeaveDays($employeeId, $companyId, $leaveTypeId, $startDate, $endDate);
            return $this->jsonResponse(['total_days' => $total]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get all leave types
     */
    public function getLeaveTypes($requestData = [])
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $employeeId = $requestData['employee_id'] ?? null;
            $companyId = $requestData['company_id'] ?? null;
            
            $query = "SELECT * FROM leave_types WHERE 1=1";
            $params = [];

            // If employee_id is provided, find their primary company
            if ($employeeId && is_numeric($employeeId)) {
                $stmtEmp = $db->prepare("
                    SELECT e.gender, ec.company_id 
                    FROM employees e
                    LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                    WHERE e.id = ?
                ");
                $stmtEmp->execute([$employeeId]);
                $empData = $stmtEmp->fetch(\PDO::FETCH_ASSOC);
                
                if ($empData) {
                    $gender = $empData['gender'] ?: 'none';
                    $query .= " AND (gender_restriction = 'none' OR gender_restriction = ?)";
                    $params[] = $gender;
                    
                    if (!$companyId) {
                        $companyId = $empData['company_id'];
                    }
                }
            }

            if ($companyId) {
                // Strict Isolation: Only show types for this company
                $query .= " AND company_id = ?";
                $params[] = $companyId;
            } else if (!$employeeId) {
                // Safe Default: If no context, only show global/unassigned types
                $query .= " AND company_id IS NULL";
            }

            $query .= " ORDER BY name ASC";
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $types = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->jsonResponse($types);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Add a new leave type
     */
    public function addLeaveType($data)
    {
        try {
            if (isset($data['company_id'])) {
                $this->verifyDataScope($data['company_id']);
            }
            $db = \Database::getInstance()->getConnection();
            // Auto-generate code if missing
            $code = $data['code'] ?? '';
            $companyId = $data['company_id'] ?? null;
            
            if (empty($code)) {
                $words = explode(' ', $data['name']);
                $cleanWords = array_values(array_filter(array_map('trim', $words)));
                $word1 = preg_replace('/[^A-Za-z]/', '', $cleanWords[0] ?? '');
                $word2 = preg_replace('/[^A-Za-z]/', '', $cleanWords[1] ?? '');
                
                $candidates = [];
                if (!empty($word2)) {
                    $len1 = strlen($word1);
                    $len2 = strlen($word2);
                    for ($sum = 0; $sum < $len1 + $len2; $sum++) {
                        for ($i = 0; $i <= $sum; $i++) {
                            $j = $sum - $i;
                            if ($i < $len1 && $j < $len2) {
                                $candidate = strtoupper($word1[$i] . $word2[$j]);
                                if (!in_array($candidate, $candidates)) {
                                    $candidates[] = $candidate;
                                }
                            }
                        }
                    }
                } else {
                    $len1 = strlen($word1);
                    if ($len1 >= 2) {
                        for ($i = 1; $i < $len1; $i++) {
                            $candidate = strtoupper($word1[0] . $word1[$i]);
                            if (!in_array($candidate, $candidates)) {
                                $candidates[] = $candidate;
                            }
                        }
                    } elseif ($len1 == 1) {
                        $candidates[] = strtoupper($word1[0] . $word1[0]);
                    }
                }
                
                // Find the first unique candidate
                foreach ($candidates as $candidate) {
                    if ($companyId === null) {
                        $checkStmt = $db->prepare("SELECT COUNT(*) FROM leave_types WHERE code = ? AND company_id IS NULL");
                        $checkStmt->execute([$candidate]);
                    } else {
                        $checkStmt = $db->prepare("SELECT COUNT(*) FROM leave_types WHERE code = ? AND company_id = ?");
                        $checkStmt->execute([$candidate, $companyId]);
                    }
                    
                    if ($checkStmt->fetchColumn() == 0) {
                        $code = $candidate;
                        break;
                    }
                }
                
                // Extreme fallback if all 2-letter combinations are taken
                if (empty($code)) {
                    $baseCode = !empty($candidates) ? $candidates[0] : 'XX';
                    $counter = 1;
                    while (true) {
                        $fallbackCode = $baseCode . $counter;
                        if ($companyId === null) {
                            $checkStmt = $db->prepare("SELECT COUNT(*) FROM leave_types WHERE code = ? AND company_id IS NULL");
                            $checkStmt->execute([$fallbackCode]);
                        } else {
                            $checkStmt = $db->prepare("SELECT COUNT(*) FROM leave_types WHERE code = ? AND company_id = ?");
                            $checkStmt->execute([$fallbackCode, $companyId]);
                        }
                        if ($checkStmt->fetchColumn() == 0) {
                            $code = $fallbackCode;
                            break;
                        }
                        $counter++;
                    }
                }
            }

            $stmt = $db->prepare("INSERT INTO leave_types (name, code, company_id, is_paid, gender_restriction, color_code) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'], 
                $code, 
                $data['company_id'] ?? null,
                $data['is_paid'] ? 1 : 0,
                $data['gender_restriction'] ?? 'none',
                $data['color_code'] ?? '#6b7280'
            ]);
            return $this->jsonResponse(['id' => $db->lastInsertId()], 201, "Leave type added.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get leave policies for a company
     */
    public function getPolicies($companyId)
    {
        try {
            $this->verifyDataScope($companyId);
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM company_leave_policies WHERE company_id = ?");
            $stmt->execute([$companyId]);
            return $this->jsonResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Save/Update a leave policy
     */
    public function savePolicy($data)
    {
        try {
            $this->verifyDataScope($data['company_id']);
            $db = \Database::getInstance()->getConnection();
            // Check if policy exists to use UPDATE or INSERT
            $stmt = $db->prepare("SELECT id FROM company_leave_policies WHERE company_id = ? AND leave_type_id = ?");
            $stmt->execute([$data['company_id'], $data['leave_type_id']]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                $stmt = $db->prepare("
                    UPDATE company_leave_policies 
                    SET default_days_per_year = ?, carry_forward_allowed = ?, is_calendar_days = ?, weekend_definition = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $data['default_days_per_year'],
                    $data['carry_forward_allowed'] ? 1 : 0,
                    $data['is_calendar_days'] ? 1 : 0,
                    $data['weekend_definition'] ?? '["Saturday", "Sunday"]',
                    $existingId
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO company_leave_policies (company_id, leave_type_id, default_days_per_year, carry_forward_allowed, is_calendar_days, weekend_definition)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['company_id'],
                    $data['leave_type_id'],
                    $data['default_days_per_year'],
                    $data['carry_forward_allowed'] ? 1 : 0,
                    $data['is_calendar_days'] ? 1 : 0,
                    $data['weekend_definition'] ?? '["Saturday", "Sunday"]'
                ]);
            }

            // Noah Audit Fix: Sync leave counts for all employees in this company for the current year
            $this->recalculateBalances($data['company_id']);

            return $this->jsonResponse(null, 200, "Policy saved and balances synced successfully.");

        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Update an existing leave type
     */
    public function updateLeaveType($data)
    {
        if (!isset($data['id'])) return $this->jsonResponse(null, 400, "Leave Type ID is required.");

        try {
            $db = \Database::getInstance()->getConnection();
            
            // Scope Check
            $stmt = $db->prepare("SELECT company_id FROM leave_types WHERE id = ?");
            $stmt->execute([$data['id']]);
            $cid = $stmt->fetchColumn();
            if ($cid) $this->verifyDataScope($cid);
            $stmt = $db->prepare("UPDATE leave_types SET name = ?, code = ?, is_paid = ?, gender_restriction = ?, color_code = ? WHERE id = ?");
            $stmt->execute([
                $data['name'], 
                $data['code'], 
                $data['is_paid'] ? 1 : 0,
                $data['gender_restriction'] ?? 'none',
                $data['color_code'] ?? '#6b7280',
                $data['id']
            ]);
            return $this->jsonResponse(null, 200, "Leave type updated.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Delete a leave type
     */
    public function deleteLeaveType($id)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            
            // Scope Check
            $stmt = $db->prepare("SELECT company_id FROM leave_types WHERE id = ?");
            $stmt->execute([$id]);
            $cid = $stmt->fetchColumn();
            if ($cid) $this->verifyDataScope($cid);

            $stmt = $db->prepare("DELETE FROM leave_types WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(null, 200, "Leave type deleted.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Force recalculation of balances for a company or specific employee
     */
    public function recalculate($requestData = [])
    {
        $companyId = $requestData['company_id'] ?? null;
        $employeeId = $requestData['employee_id'] ?? null;

        if (!$companyId && !$employeeId) {
            return $this->jsonResponse(null, 400, "Company ID or Employee ID is required.");
        }

        if ($companyId) {
            $this->verifyDataScope($companyId);
        }

        try {
            if ($employeeId) {
                $db = \Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT company_id FROM employee_companies WHERE employee_id = ? AND is_primary = 1");
                $stmt->execute([$employeeId]);
                $companyId = $stmt->fetchColumn();
            }

            $this->recalculateBalances($companyId, $employeeId);
            return $this->jsonResponse(null, 200, "Balances recalculated successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Logic to sync allocated days from policies and used days from attendance logs
     */
    private function recalculateBalances($companyId, $employeeId = null)
    {
        $db = \Database::getInstance()->getConnection();
        $year = date('Y');

        // 1. Fetch all policies for the company
        $stmt = $db->prepare("
            SELECT clp.leave_type_id, clp.default_days_per_year, clp.is_calendar_days, lt.name as leave_type_name 
            FROM company_leave_policies clp
            JOIN leave_types lt ON clp.leave_type_id = lt.id
            WHERE clp.company_id = ?
        ");
        $stmt->execute([$companyId]);
        $policies = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 1.1 Fetch global company weekends
        $globalWeekends = $this->getCompanyWeekends($companyId);

        // 2. Fetch company-specific holidays (current year)
        $stmtH = $db->prepare("SELECT holiday_date FROM holidays WHERE company_id = ? AND YEAR(holiday_date) = ?");
        $stmtH->execute([$companyId, $year]);
        $companyHolidays = $stmtH->fetchAll(\PDO::FETCH_COLUMN);
        
        // Fetch country ID for the company to use in public holiday check
        $stmtCntry = $db->prepare("SELECT country_id FROM companies WHERE id = ?");
        $stmtCntry->execute([$companyId]);
        $cntryId = $stmtCntry->fetchColumn();

        // 3. Fetch target employees
        $empQuery = "SELECT employee_id FROM employee_companies WHERE company_id = ? AND is_primary = 1 AND is_active = 1";
        $params = [$companyId];
        if ($employeeId) {
            $empQuery .= " AND employee_id = ?";
            $params[] = $employeeId;
        }
        $stmt = $db->prepare($empQuery);
        $stmt->execute($params);
        $employees = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($employees as $eid) {
            foreach ($policies as $policy) {
                $ltid = $policy['leave_type_id'];
                $allocated = $policy['default_days_per_year'];
                $isCalendarDays = (bool)$policy['is_calendar_days'];
                $weekends = $isCalendarDays ? [] : $globalWeekends;
                
                // Fetch public holidays if applicable for this employee
                $holidays = $companyHolidays;

                if (!$isCalendarDays) {
                    $stmtPH = $db->prepare("SELECT holiday_date FROM public_holidays WHERE country_id = ? AND YEAR(holiday_date) = ?");
                    $stmtPH->execute([$cntryId, $year]);
                    $pubHolidays = $stmtPH->fetchAll(\PDO::FETCH_COLUMN);
                    $holidays = array_unique(array_merge($holidays, $pubHolidays));
                }

                // 4. Get all possible status matches (Code, Name, or ID)
                $stmtLT = $db->prepare("SELECT id, code, name FROM leave_types WHERE id = ?");
                $stmtLT->execute([$ltid]);
                $lt = $stmtLT->fetch(\PDO::FETCH_ASSOC);
                
                $validStatuses = array_filter([$lt['id'], $lt['code'], $lt['name']]);

                // Fetch ALL leave days from attendance_logs for this year
                $placeholders = implode(',', array_fill(0, count($validStatuses), '?'));
                $stmtLogs = $db->prepare("
                    SELECT attendance_date 
                    FROM attendance_logs 
                    WHERE employee_id = ? 
                    AND status IN ($placeholders)
                    AND YEAR(attendance_date) = ?
                ");
                $stmtLogs->execute(array_merge([$eid], $validStatuses, [$year]));
                $logDates = $stmtLogs->fetchAll(\PDO::FETCH_COLUMN);

                // Calculate valid used days based on policy
                $used = 0;
                $uniqueDates = array_unique($logDates); // Avoid double counting if multiple logs exist
                foreach ($uniqueDates as $dateStr) {
                    if ($isCalendarDays) {
                        $used++;
                        continue;
                    }

                    $dayName = date('l', strtotime($dateStr));
                    if (in_array($dayName, $weekends)) continue;
                    if (in_array($dateStr, $holidays)) continue;

                    $used++;
                }

                // Update or Insert balance
                $stmtSync = $db->prepare("
                    INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days)
                    VALUES (:eid, :ltid, :year, :allocated, :used)
                    ON DUPLICATE KEY UPDATE 
                        allocated_days = :allocated2,
                        used_days = :used2
                ");
                $stmtSync->execute([
                    'eid' => $eid,
                    'ltid' => $ltid,
                    'year' => $year,
                    'allocated' => $allocated,
                    'used' => $used,
                    'allocated2' => $allocated,
                    'used2' => $used
                ]);
            }
        }
    }

    /**
     * Employee requests leave cancellation
     */
    public function requestCancellation($requestData)
    {
        $id = $requestData['id'] ?? null;
        if (!$id) {
            $receivedKeys = !empty($requestData) ? implode(', ', array_keys($requestData)) : 'none';
            // Debugging context: see if stream is empty but $_POST is not
            $debugHeaders = function_exists('getallheaders') ? getallheaders() : [];
            $contentType = $debugHeaders['Content-Type'] ?? $debugHeaders['content-type'] ?? 'unknown';
            return $this->jsonResponse(null, 400, "Request ID required. Received keys: " . $receivedKeys . ". Content-Type: " . $contentType . ". Raw POST count: " . count($_POST));
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $comment = $requestData['reason'] ?? 'No reason provided';
            
            // Check current status
            $stmtStatus = $db->prepare("SELECT status FROM leave_requests WHERE id = ?");
            $stmtStatus->execute([$id]);
            $currentStatus = $stmtStatus->fetchColumn();

            if ($currentStatus === 'pending') {
                // If pending, allow direct cancellation/withdrawal
                $stmt = $db->prepare("UPDATE leave_requests SET status = 'cancelled', cancellation_reason = ? WHERE id = ?");
                $stmt->execute([$comment, $id]);
                $message = 'Leave request withdrawn.';
            } else {
                // If approved, request cancellation from manager
                $stmt = $db->prepare("UPDATE leave_requests SET status = 'cancel_requested', cancellation_reason = ? WHERE id = ? AND status = 'approved'");
                $stmt->execute([$comment, $id]);
                $message = 'Cancellation requested.';
            }

            // Noah Logic Auditor: Centralized History Log
            ApprovalHelper::log('leave', (int)$id, $currentStatus === 'pending' ? 'cancelled' : 'cancel_requested', $comment);

            // Notify Manager if it was approved
            if ($currentStatus === 'approved') {
                $stmtReq = $db->prepare("SELECT employee_id FROM leave_requests WHERE id = ?");
                $stmtReq->execute([$id]);
                $eid = $stmtReq->fetchColumn();

                if ($eid) {
                    \App\Helpers\NotificationHelper::notifyManager($eid, 'leave_cancel_request', 'Leave cancellation requested', "An employee has requested to cancel their approved leave.");
                }
            }

            return $this->jsonResponse(['message' => $message]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Admin/Manager cancels an approved leave
     */
    public function adminCancel($requestData)
    {
        $id = $requestData['id'] ?? null;
        $comment = $requestData['comment'] ?? 'Cancelled by Admin';

        if (!$id) return $this->jsonResponse(null, 400, "Request ID required.");

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Fetch Details
            $stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $request = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$request || !in_array($request['status'], ['approved', 'cancel_requested'])) {
                $db->rollBack();
                return $this->jsonResponse(null, 400, "Request cannot be cancelled in current state.");
            }

            // 2. Update Status
            $stmt = $db->prepare("UPDATE leave_requests SET status = 'cancelled', manager_comment = ? WHERE id = ?");
            $stmt->execute([$comment, $id]);

            // 3. Restore Balance
            $stmtBal = $db->prepare("
                UPDATE leave_balances 
                SET used_days = used_days - :days 
                WHERE employee_id = :eid AND leave_type_id = :ltid AND year = :year
            ");
            $stmtBal->execute([
                'days' => $request['total_days'],
                'eid' => $request['employee_id'],
                'ltid' => $request['leave_type_id'],
                'year' => date('Y', strtotime($request['start_date']))
            ]);

            // 4. Cleanup Attendance
            $attendance = new \App\Controllers\AttendanceController();
            $db->commit();

            // Noah Logic Auditor: Centralized History Log
            ApprovalHelper::log('leave', (int)$id, 'cancelled', $comment);

            return $this->jsonResponse(['message' => 'Leave cancelled and attendance purged.']);
        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Helper to fetch weekends from the company weekly schedule
     */
    private function getCompanyWeekends($companyId)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT day_of_week 
                FROM office_weekly_schedules 
                WHERE company_id = ? AND (status = 'Weekend' OR status = 'Off')
            ");
            $stmt->execute([$companyId]);
            $weekends = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($weekends)) {
                return ['Saturday', 'Sunday'];
            }
            return $weekends;
        } catch (\Exception $e) {
            return ['Saturday', 'Sunday'];
        }
    }
}

