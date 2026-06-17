<?php

namespace App\Controllers;

use App\Core\Controller;

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

            $query = "
                SELECT lr.*, 
                       e.first_name, e.last_name,
                       lt.name as leave_type_name,
                       appr_u.username as approved_by_name
                FROM leave_requests lr
                JOIN employees e ON lr.employee_id = e.id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users appr_u ON lr.approved_by_id = appr_u.id
                WHERE 1=1
            ";

            $params = [];

            if ($status) {
                $query .= " AND lr.status = :status";
                $params['status'] = $status;
            }

            if ($companyId) {
                $query .= " AND lr.employee_id IN (SELECT employee_id FROM employee_companies WHERE company_id = :company_id)";
                $params['company_id'] = $companyId;
            }

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $requests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($requests);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Submit a new leave request with balance and policy validation
     */
    public function submitRequest($requestData)
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

            // 1. Get Employee Details (Context, Year, Gender)
            $stmt = $db->prepare("
                SELECT ec.company_id, e.gender 
                FROM employees e
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
                WHERE e.id = :id
            ");
            $stmt->execute(['id' => $employeeId]);
            $employee = $stmt->fetch(\PDO::FETCH_ASSOC);
            $companyId = $employee['company_id'] ?? null;
            $employeeGender = $employee['gender'] ?? 'other';
            $year = date('Y', strtotime($startDate));

            if (!$companyId)
                return $this->jsonResponse(null, 404, "Employee primary company not found.");

            // 1b. Check Gender Restriction for Leave Type
            $stmt = $db->prepare("SELECT name, gender_restriction FROM leave_types WHERE id = :id");
            $stmt->execute(['id' => $leaveTypeId]);
            $leaveType = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($leaveType && $leaveType['gender_restriction'] !== 'none') {
                if ($leaveType['gender_restriction'] !== $employeeGender) {
                    $genderLabel = $leaveType['gender_restriction'] === 'female' ? 'Female' : 'Male';
                    return $this->jsonResponse(null, 400, "This leave type ({$leaveType['name']}) is only available for {$genderLabel} employees.");
                }
            }

            // 2. Calculate Total Days (Respecting Policy & Holidays)
            $totalDays = $this->calculateLeaveDays($companyId, $leaveTypeId, $startDate, $endDate);

            // 3. Check Balance
            $stmt = $db->prepare("
                SELECT (allocated_days - used_days) as remaining 
                FROM leave_balances 
                WHERE employee_id = :eid AND leave_type_id = :ltid AND year = :year
            ");
            $stmt->execute(['eid' => $employeeId, 'ltid' => $leaveTypeId, 'year' => $year]);
            $remaining = $stmt->fetchColumn();

            if ($remaining === false || $remaining < $totalDays) {
                return $this->jsonResponse(null, 400, "Insufficient leave balance. Required: $totalDays, Available: " . ($remaining ?: 0));
            }

            // 4. Submit Request
            $stmt = $db->prepare("
                INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, status)
                VALUES (:eid, :ltid, :start, :end, :days, 'pending')
            ");
            $stmt->execute([
                'eid' => $employeeId,
                'ltid' => $leaveTypeId,
                'start' => $startDate,
                'end' => $endDate,
                'days' => $totalDays
            ]);

            return $this->jsonResponse(['message' => 'Leave request submitted.', 'total_days_calculated' => $totalDays]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Helper: Calculate actual leave days skipping holidays and weekends
     */
    private function calculateLeaveDays($companyId, $leaveTypeId, $startDate, $endDate)
    {
        $db = \Database::getInstance()->getConnection();

        // 1. Get Company Policy (Weekend definition)
        $stmt = $db->prepare("SELECT weekend_definition, is_calendar_days FROM company_leave_policies WHERE company_id = :cid AND leave_type_id = :ltid");
        $stmt->execute(['cid' => $companyId, 'ltid' => $leaveTypeId]);
        $policy = $stmt->fetch(\PDO::FETCH_ASSOC);

        $weekends = $policy ? json_decode($policy['weekend_definition'], true) : ['Saturday', 'Sunday'];
        $isCalendarDays = $policy ? (bool) $policy['is_calendar_days'] : false;

        // 2. Get Public Holidays
        $stmt = $db->prepare("SELECT holiday_date FROM holidays WHERE company_id = :cid AND holiday_date BETWEEN :start AND :end");
        $stmt->execute(['cid' => $companyId, 'start' => $startDate, 'end' => $endDate]);
        $holidays = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // 3. Iterate Days
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day');
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);

        $total = 0;
        foreach ($period as $dt) {
            $dateStr = $dt->format("Y-m-d");
            $dayName = $dt->format("l");

            // Skip if it's a weekend (and not calendar days policy)
            if (!$isCalendarDays && in_array($dayName, $weekends))
                continue;

            // Skip if it's a holiday
            if (in_array($dateStr, $holidays))
                continue;

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

            return $this->jsonResponse(['message' => 'Holiday added successfully.']);
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
            $attendance = new \App\Controllers\AttendanceController();
            $attendance->setLeaveAttendanceRange($request['employee_id'], $request['start_date'], $request['end_date']);

            $db->commit();
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
            $stmt = $db->prepare("
                SELECT lb.*, lt.name as leave_type_name 
                FROM leave_balances lb
                JOIN leave_types lt ON lb.leave_type_id = lt.id
                WHERE lb.employee_id = :eid AND lb.year = :year
            ");
            $stmt->execute(['eid' => $employeeId, 'year' => $year]);
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
                WHERE ec.employee_id = :id AND ec.is_primary = 1
            ");
            $stmt->execute(['id' => $employeeId]);
            $companyId = $stmt->fetchColumn();

            if (!$companyId)
                return $this->jsonResponse(null, 404, "Employee company not found.");

            $total = $this->calculateLeaveDays($companyId, $leaveTypeId, $startDate, $endDate);
            return $this->jsonResponse(['total_days' => $total]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get all leave types
     */
    public function getLeaveTypes()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT * FROM leave_types ORDER BY name ASC");
            return $this->jsonResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
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
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO leave_types (name, code, is_paid) VALUES (?, ?, ?)");
            $stmt->execute([$data['name'], $data['code'], $data['is_paid'] ? 1 : 0]);
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
            return $this->jsonResponse(null, 200, "Policy saved successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }
}
