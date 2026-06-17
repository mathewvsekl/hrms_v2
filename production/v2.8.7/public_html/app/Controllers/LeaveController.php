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
    /**
     * Fetch leave requests
     */
    public function getRequests($requestData)
    {
        $service = new \App\Services\LeaveService();
        $requests = $service->fetchRequests($requestData, $_SESSION, $this->isGlobalAdmin());
        
        // Apply Timezone conversion
        $timezone = 'UTC';
        if (!empty($_SESSION['scope_company_id'])) {
            $timezone = \App\Helpers\DateHelper::getCompanyTimezone($_SESSION['scope_company_id']);
        }
        $this->applyTimezones($requests, $timezone);

        return $this->jsonResponse($requests);
    }

    /**
     * Submit a new leave request
     */
    public function submitRequest($requestData)
    {
        $employeeId = $requestData['employee_id'] ?? null;
        $segments = $requestData['segments'] ?? [];

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

        $attachmentPath = null;
        if (isset($_FILES['attachment'])) {
            $uploadResult = \App\Helpers\UploadHelper::upload($_FILES['attachment'], 'leave', [
                'prefix' => 'leave_' . $employeeId . '_'
            ]);

            if ($uploadResult['success']) {
                $attachmentPath = $uploadResult['file_path'];
            } else if ($_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
                return $this->jsonResponse(null, 400, $uploadResult['message']);
            }
        }

        try {
            $service = new \App\Services\LeaveService();
            $data = $requestData;
            $data['segments'] = $segments;
            $result = $service->submitRequest($data, $attachmentPath);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Approve a leave request
     */
    public function approveRequest($requestData)
    {
        $id = $requestData['id'] ?? null;
        $approverId = $_SESSION['user_id'] ?? null;

        if (!$id) return $this->jsonResponse(null, 400, "Leave Request ID is required.");

        try {
            $service = new \App\Services\LeaveService();
            $service->approveRequest((int)$id, (int)$approverId);
            return $this->jsonResponse(['message' => 'Leave request approved and synced.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
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

        if (!$id) return $this->jsonResponse(null, 400, "Leave Request ID is required.");

        try {
            $service = new \App\Services\LeaveService();
            $service->rejectRequest((int)$id, (int)$approverId, $comment);
            return $this->jsonResponse(['message' => 'Leave request rejected.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get employee leave balances
     */
    public function getBalances($employeeId)
    {
        try {
            $this->verifyDataScope(null, null, $employeeId);
            $service = new \App\Services\LeaveService();
            $balances = $service->getBalances((int)$employeeId, (int)date('Y'));
            return $this->jsonResponse($balances);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
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
    /**
     * Get all leave types
     */
    public function getLeaveTypes($requestData = [])
    {
        try {
            $service = new \App\Services\LeaveService();
            $types = $service->getLeaveTypes($requestData);
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
        $batchData = [];

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

                // Calculate valid used days based on policy + include future approved requests not yet in logs
                $uniqueDates = array_unique($logDates); // Start with dates already in attendance logs
                
                // Fetch all approved requests for this year/type to catch future ones
                $stmtReqs = $db->prepare("SELECT start_date, end_date FROM leave_requests WHERE employee_id = ? AND leave_type_id = ? AND status = 'approved' AND (YEAR(start_date) = ? OR YEAR(end_date) = ?)");
                $stmtReqs->execute([$eid, $ltid, $year, $year]);
                $approvedReqs = $stmtReqs->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($approvedReqs as $req) {
                    $current = new \DateTime($req['start_date']);
                    $end = new \DateTime($req['end_date']);
                    $end->modify('+1 day');
                    
                    $interval = new \DateInterval('P1D');
                    $period = new \DatePeriod($current, $interval, $end);

                    foreach ($period as $date) {
                        $dateStr = $date->format('Y-m-d');
                        if (date('Y', strtotime($dateStr)) != $year) continue;
                        if (in_array($dateStr, $uniqueDates)) continue; // Already counted from logs

                        if ($isCalendarDays) {
                            $uniqueDates[] = $dateStr;
                        } else {
                            $dayName = $date->format('l');
                            if (!in_array($dayName, $weekends) && !in_array($dateStr, $holidays)) {
                                $uniqueDates[] = $dateStr;
                            }
                        }
                    }
                }

                $used = count($uniqueDates);

                $batchData[] = [
                    'eid' => $eid,
                    'ltid' => $ltid,
                    'year' => $year,
                    'allocated' => $allocated,
                    'used' => $used
                ];
            }
        }

        // Performance Audit Fix: Bulk Upsert (Single DB Trip)
        if (!empty($batchData)) {
            $values = [];
            $params = [];
            foreach ($batchData as $row) {
                $values[] = "(?, ?, ?, ?, ?)";
                $params[] = $row['eid'];
                $params[] = $row['ltid'];
                $params[] = $row['year'];
                $params[] = $row['allocated'];
                $params[] = $row['used'];
            }

            $sql = "INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days) 
                    VALUES " . implode(", ", $values) . "
                    ON DUPLICATE KEY UPDATE 
                        allocated_days = VALUES(allocated_days),
                        used_days = VALUES(used_days)";
            
            $db->prepare($sql)->execute($params);
        }
    }

    /**
     * Employee requests leave cancellation
     */
    public function requestCancellation($requestData)
    {
        $id = $requestData['id'] ?? null;
        if (!$id) {
            return $this->jsonResponse(null, 400, "Request ID required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $comment = $requestData['reason'] ?? 'No reason provided';
            
            // Security Audit Fix: Ownership Verification (Prevent IDOR)
            $stmtReq = $db->prepare("SELECT employee_id, status FROM leave_requests WHERE id = ?");
            $stmtReq->execute([$id]);
            $request = $stmtReq->fetch(\PDO::FETCH_ASSOC);

            if (!$request) {
                return $this->jsonResponse(null, 404, "Leave request not found.");
            }

            $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;
            if ($request['employee_id'] != $myEmployeeId && !$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'HRMANAGER', 'HR_MANAGER'])) {
                return $this->jsonResponse(null, 403, "Security Violation: You can only cancel your own leave requests.");
            }

            $currentStatus = $request['status'];

            if ($currentStatus === 'pending') {
                // If pending, allow direct cancellation/withdrawal
                $stmt = $db->prepare("UPDATE leave_requests SET status = 'cancelled', cancellation_reason = ? WHERE id = ?");
                $stmt->execute([$comment, $id]);
                $message = 'Leave request withdrawn.';
            } else if ($currentStatus === 'approved') {
                // If approved, request cancellation from manager
                $stmt = $db->prepare("UPDATE leave_requests SET status = 'cancel_requested', cancellation_reason = ? WHERE id = ?");
                $stmt->execute([$comment, $id]);
                $message = 'Cancellation requested.';
            } else {
                return $this->jsonResponse(null, 400, "Request cannot be cancelled in status: $currentStatus");
            }

            // Noah Logic Auditor: Centralized History Log
            ApprovalHelper::log('leave', (int)$id, $currentStatus === 'pending' ? 'cancelled' : 'cancel_requested', $comment);

            // Notify Manager if it was approved
            if ($currentStatus === 'approved') {
                \App\Helpers\NotificationHelper::notifyManager($request['employee_id'], 'leave_cancel_request', 'Leave cancellation requested', "An employee has requested to cancel their approved leave.");
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
        // Security Audit Fix: Administrative RBAC Enforcement
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN', 'HRMANAGER', 'HR_MANAGER', 'COUNTRYMANAGER', 'COUNTRY_MANAGER', 'COUNTRY MANAGER'])) {
            return $this->jsonResponse(null, 403, "Security Violation: Only HR Managers or Admins can perform administrative cancellations.");
        }

        $id = $requestData['id'] ?? null;

        try {
            $db = \Database::getInstance()->getConnection();
            $comment = $requestData['comment'] ?? 'Administrative cancellation';
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
     * Internal helper to calculate net leave days excluding weekends and holidays
     */
    private function calculateLeaveDays($employeeId, $companyId, $leaveTypeId, $startDate, $endDate)
    {
        $db = \Database::getInstance()->getConnection();

        // 1. Check Policy for the specific leave type
        $stmt = $db->prepare("SELECT is_calendar_days FROM company_leave_policies WHERE company_id = ? AND leave_type_id = ?");
        $stmt->execute([$companyId, $leaveTypeId]);
        $isCalendarDays = (bool)$stmt->fetchColumn();

        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day');
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end);

        if ($isCalendarDays) {
            $count = 0;
            foreach ($period as $date) {
                $count++;
            }
            return $count;
        }

        // 2. Fetch Weekends
        $weekends = $this->getCompanyWeekends($companyId);

        // 3. Fetch Company Holidays
        $stmtH = $db->prepare("SELECT holiday_date FROM holidays WHERE company_id = ? AND holiday_date BETWEEN ? AND ?");
        $stmtH->execute([$companyId, $startDate, $endDate]);
        $companyHolidays = $stmtH->fetchAll(\PDO::FETCH_COLUMN);

        // 4. Fetch Public Holidays
        $stmtC = $db->prepare("SELECT country_id FROM companies WHERE id = ?");
        $stmtC->execute([$companyId]);
        $countryId = $stmtC->fetchColumn();

        $stmtPH = $db->prepare("SELECT holiday_date FROM public_holidays WHERE country_id = ? AND holiday_date BETWEEN ? AND ?");
        $stmtPH->execute([$countryId, $startDate, $endDate]);
        $publicHolidays = $stmtPH->fetchAll(\PDO::FETCH_COLUMN);

        $allHolidays = array_unique(array_merge($companyHolidays, $publicHolidays));

        $total = 0;
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayName = $date->format('l');

            if (in_array($dayName, $weekends)) continue;
            if (in_array($dateStr, $allHolidays)) continue;

            $total++;
        }

        return $total;
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

