<?php

namespace App\Services;

use App\Helpers\DateHelper;
use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;

/**
 * LeaveService
 * 
 * Handles leave requests, balances, policies, and holiday management.
 */
class LeaveService
{
    /**
     * Fetch leave requests with scoping and filters
     */
    public function fetchRequests(array $filters, array $sessionData, bool $isGlobalAdmin): array
    {
        $db = \Database::getInstance()->getConnection();

        $status = $filters['status'] ?? null;
        $companyId = $filters['company_id'] ?? null;
        $employeeId = $filters['employee_id'] ?? null;

        $params = [];
        $whereClauses = ["1=1"];

        if (!$isGlobalAdmin) {
            $associatedCompanyIds = $sessionData['associated_company_ids'] ?? [];
            $sessionCountryId = $sessionData['scope_country_id'] ?? null;
            $userRole = strtoupper($sessionData['user_role'] ?? 'EMPLOYEE');
            
            $isMultiOffice = in_array($userRole, ['SUPERADMIN', 'SUPER_ADMIN', 'ADMIN', 'HRMANAGER', 'HR_MANAGER', 'HRASSISTANT', 'HR_ASSISTANT', 'COUNTRYMANAGER', 'COUNTRY_MANAGER', 'COUNTRY MANAGER']);

            if ($isMultiOffice && !empty($associatedCompanyIds)) {
                $placeholders = [];
                foreach ($associatedCompanyIds as $idx => $cid) {
                    $key = "assoc_cid_$idx";
                    $placeholders[] = ":$key";
                    $params[$key] = $cid;
                }
                $whereClauses[] = "ec.company_id IN (" . implode(',', $placeholders) . ")";
            } else if (in_array($userRole, ['COUNTRYMANAGER', 'COUNTRY_MANAGER', 'COUNTRY MANAGER']) && $sessionCountryId) {
                $whereClauses[] = "c.country_id = :session_country_id";
                $params['session_country_id'] = $sessionCountryId;
            } else {
                $sessionCompanyId = $sessionData['scope_company_id'] ?? null;
                if ($sessionCompanyId) {
                    $whereClauses[] = "ec.company_id = :session_company_id";
                    $params['session_company_id'] = $sessionCompanyId;
                } else if (!$isMultiOffice) {
                    // Regular employee sees only their own requests
                    $myEmployeeId = $sessionData['scope_employee_id'] ?? null;
                    if ($myEmployeeId) {
                        $whereClauses[] = "lr.employee_id = :my_employee_id";
                        $params['my_employee_id'] = $myEmployeeId;
                    } else {
                        $whereClauses[] = "1=0";
                    }
                }
            }

            // Exclude SuperAdmins from results for non-global admins
            $whereClauses[] = "NOT EXISTS (
                SELECT 1 FROM user_roles ur2 
                JOIN roles r2 ON ur2.role_id = r2.id
                WHERE ur2.user_id = u.id AND (UPPER(r2.name) = 'SUPERADMIN' OR UPPER(r2.name) = 'SUPER_ADMIN' OR r2.id = 1)
            )";
        }

        if ($status) {
            $statusArray = explode(',', $status);
            $placeholders = [];
            foreach ($statusArray as $idx => $s) {
                $key = "status_$idx";
                $placeholders[] = ":$key";
                $params[$key] = trim($s);
            }
            $whereClauses[] = "lr.status IN (" . implode(',', $placeholders) . ")";
        }

        if ($companyId) {
            $whereClauses[] = "ec.company_id = :filter_company_id";
            $params['filter_company_id'] = $companyId;
        }

        if ($employeeId) {
            $whereClauses[] = "lr.employee_id = :filter_employee_id";
            $params['filter_employee_id'] = $employeeId;
        }

        $query = "
            SELECT DISTINCT lr.*, 
                   e.first_name, e.last_name,
                   lt.name as leave_type_name, lt.color_code as leave_type_color,
                   appr_u.username as approved_by_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            JOIN users u ON e.id = u.employee_id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN users appr_u ON lr.approved_by_id = appr_u.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            LEFT JOIN companies c ON ec.company_id = c.id
            WHERE " . implode(' AND ', $whereClauses) . "
            ORDER BY lr.created_at_utc DESC
        ";

        error_log("LeaveService DEBUG: user=" . ($sessionData['user_id'] ?? 'unknown') . " role=" . ($sessionData['user_role'] ?? 'none') . " query=" . preg_replace('/\s+/', ' ', $query));
        error_log("LeaveService DEBUG: params=" . json_encode($params));

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        error_log("LeaveService DEBUG: found=" . count($results));
        return $results;
    }

    /**
     * Submit a new leave request
     */
    public function submitRequest(array $data, ?string $attachmentPath): array
    {
        $db = \Database::getInstance()->getConnection();
        $employeeId = (int)$data['employee_id'];
        $segments = $data['segments'] ?? [];
        $remarks = $data['remarks'] ?? null;

        $db->beginTransaction();
        try {
            // Get Employee Context
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
                throw new \Exception("Employee primary company not found.");
            }

            $requestGroupId = bin2hex(random_bytes(8));
            $results = [];

            foreach ($segments as $seg) {
                $ltid = (int)$seg['leave_type_id'];
                $start = DateHelper::toSql($seg['start_date']);
                $end = DateHelper::toSql($seg['end_date']);
                $year = date('Y', strtotime($start));

                // 1. Check Overlap
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
                    throw new \Exception("Overlapping leave request found for dates $start to $end.");
                }

                // 2. Gender Restriction
                $stmtLt = $db->prepare("SELECT name, gender_restriction FROM leave_types WHERE id = ?");
                $stmtLt->execute([$ltid]);
                $leaveType = $stmtLt->fetch(\PDO::FETCH_ASSOC);

                if ($leaveType && $leaveType['gender_restriction'] !== 'none') {
                    if ($leaveType['gender_restriction'] !== $employeeGender) {
                        throw new \Exception("Leave type {$leaveType['name']} is only for {$leaveType['gender_restriction']} employees.");
                    }
                }

                // 3. Calculate Days
                $totalDays = $this->calculateLeaveDays($employeeId, $companyId, $ltid, $start, $end);

                // 4. Check Balance
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
                    $stmtDef->execute([$companyId, $ltid]);
                    $remaining = $stmtDef->fetchColumn() ?: 0;
                }

                if ($totalDays > $remaining) {
                    throw new \Exception("Insufficient balance for {$leaveType['name']}. Available: $remaining days.");
                }

                // 5. Insert
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
            NotificationHelper::notifyManager(
                $employeeId,
                'leave_request',
                'New multi-segment leave request',
                "Employee has submitted leave segments totaling $totalRequestDays days.",
                ['link' => '/leave', 'group_id' => $requestGroupId],
                true
            );

            return ['message' => 'Leave request submitted successfully.', 'results' => $results];

        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Calculate leave days skipping holidays and weekends
     */
    public function calculateLeaveDays(int $employeeId, int $companyId, int $leaveTypeId, string $startDate, string $endDate): int
    {
        $db = \Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT is_calendar_days FROM company_leave_policies WHERE company_id = :cid AND leave_type_id = :ltid");
        $stmt->execute(['cid' => $companyId, 'ltid' => $leaveTypeId]);
        $policy = $stmt->fetch(\PDO::FETCH_ASSOC);

        $isCalendarDays = $policy ? (bool) $policy['is_calendar_days'] : false;
        
        if ($isCalendarDays) {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            return (int)$start->diff($end)->format("%a") + 1;
        }

        $weekends = $this->getCompanyWeekends($companyId);
        
        $stmt = $db->prepare("SELECT holiday_date FROM holidays WHERE company_id = :cid AND holiday_date BETWEEN :start AND :end");
        $stmt->execute(['cid' => $companyId, 'start' => $startDate, 'end' => $endDate]);
        $holidays = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $stmtC = $db->prepare("SELECT country_id FROM companies WHERE id = ?");
        $stmtC->execute([$companyId]);
        $cntryId = $stmtC->fetchColumn();

        $stmtPH = $db->prepare("SELECT holiday_date FROM public_holidays WHERE country_id = ? AND holiday_date BETWEEN ? AND ?");
        $stmtPH->execute([$cntryId, $startDate, $endDate]);
        $pubHolidays = $stmtPH->fetchAll(\PDO::FETCH_COLUMN);
        $holidays = array_unique(array_merge($holidays, $pubHolidays));

        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day');
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);

        $total = 0;
        foreach ($period as $dt) {
            if (in_array($dt->format("l"), $weekends)) continue;
            if (in_array($dt->format("Y-m-d"), $holidays)) continue;
            $total++;
        }

        return $total;
    }

    /**
     * Approve a leave request
     */
    public function approveRequest(int $requestId, int $approverId): void
    {
        $db = \Database::getInstance()->getConnection();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM leave_requests WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $requestId]);
            $request = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$request || $request['status'] !== 'pending') {
                throw new \Exception("Request not found or already processed.");
            }

            $stmt = $db->prepare("
                UPDATE leave_requests 
                SET status = 'approved', approved_by_id = :approver_id, updated_at_utc = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $requestId, 'approver_id' => $approverId]);

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

            $db->commit();

            ApprovalHelper::log('leave', $requestId, 'approved', 'Request approved.');

            $stmtUser = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmtUser->execute([$request['employee_id']]);
            $recipientUserId = $stmtUser->fetchColumn();

            if ($recipientUserId) {
                NotificationHelper::send(
                    $recipientUserId,
                    'leave_approved',
                    'Leave Request Approved',
                    "Your leave request from {$request['start_date']} to {$request['end_date']} has been approved.",
                    ['link' => '/leave', 'id' => $requestId],
                    true
                );
            }
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Reject a leave request
     */
    public function rejectRequest(int $requestId, int $approverId, string $comment): void
    {
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE leave_requests 
            SET status = 'rejected', manager_comment = :comment, approved_by_id = :approver_id, updated_at_utc = CURRENT_TIMESTAMP
            WHERE id = :id AND status = 'pending'
        ");
        $stmt->execute(['id' => $requestId, 'comment' => $comment, 'approver_id' => $approverId]);

        ApprovalHelper::log('leave', $requestId, 'rejected', $comment);

        $stmtReq = $db->prepare("SELECT employee_id, start_date, end_date FROM leave_requests WHERE id = ?");
        $stmtReq->execute([$requestId]);
        $request = $stmtReq->fetch(\PDO::FETCH_ASSOC);

        $stmtUser = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmtUser->execute([$request['employee_id']]);
        $recipientUserId = $stmtUser->fetchColumn();

        if ($recipientUserId) {
            NotificationHelper::send(
                $recipientUserId,
                'leave_rejected',
                'Leave Request Rejected',
                "Your leave request from {$request['start_date']} to {$request['end_date']} has been rejected. Reason: $comment",
                ['link' => '/leave', 'id' => $requestId],
                true
            );
        }
    }

    /**
     * Get leave balances for an employee
     */
    public function getBalances(int $employeeId, int $year): array
    {
        $db = \Database::getInstance()->getConnection();
        
        $stmtEmp = $db->prepare("SELECT gender FROM employees WHERE id = ?");
        $stmtEmp->execute([$employeeId]);
        $gender = $stmtEmp->fetchColumn() ?: 'other';

        $stmt = $db->prepare("
            SELECT lb.*, 
                   (SELECT IFNULL(SUM(total_days), 0) FROM leave_requests WHERE employee_id = lb.employee_id AND leave_type_id = lb.leave_type_id AND status = 'pending' AND YEAR(start_date) = lb.year) as pending_days,
                   lt.name as leave_type_name, lt.gender_restriction, lt.code as leave_type_code,
                   lt.color_code
            FROM leave_balances lb
            JOIN leave_types lt ON lb.leave_type_id = lt.id
            WHERE lb.employee_id = :eid 
            AND lb.year = :year
            AND (lt.gender_restriction = 'none' OR lt.gender_restriction = :gender)
        ");
        $stmt->execute(['eid' => $employeeId, 'year' => $year, 'gender' => $gender]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get available leave types
     */
    public function getLeaveTypes(array $requestData): array
    {
        $db = \Database::getInstance()->getConnection();
        $employeeId = $requestData['employee_id'] ?? null;
        $companyId = $requestData['company_id'] ?? null;
        
        $query = "SELECT * FROM leave_types WHERE 1=1";
        $params = [];

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
                if (!$companyId) $companyId = $empData['company_id'];
            }
        }

        if ($companyId) {
            $query .= " AND (company_id = ? OR company_id IS NULL)";
            $params[] = $companyId;
        } else if (!$employeeId) {
            $query .= " AND company_id IS NULL";
        }

        $query .= " ORDER BY name ASC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getCompanyWeekends(int $companyId): array
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

            return !empty($weekends) ? $weekends : ['Saturday', 'Sunday'];
        } catch (\Exception $e) {
            return ['Saturday', 'Sunday'];
        }
    }
}
