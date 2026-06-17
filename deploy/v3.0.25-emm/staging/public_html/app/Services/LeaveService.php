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
                   appr_u.username as approved_by_name,
                   cn.name as country_name
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.id
            JOIN users u ON e.id = u.employee_id
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            LEFT JOIN users appr_u ON lr.approved_by_id = appr_u.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            LEFT JOIN companies c ON ec.company_id = c.id
            LEFT JOIN countries cn ON c.country_id = cn.id
            WHERE " . implode(' AND ', $whereClauses) . "
            ORDER BY lr.created_at_utc DESC
        ";

        error_log("LeaveService DEBUG: user=" . ($sessionData['user_id'] ?? 'unknown') . " role=" . ($sessionData['user_role'] ?? 'none') . " query=" . preg_replace('/\s+/', ' ', $query));
        error_log("LeaveService DEBUG: params=" . json_encode($params));

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        error_log("LeaveService DEBUG: found=" . count($results));
        
        foreach ($results as &$r) {
            $r['origin'] = (stripos($r['remarks'] ?? '', 'System-Generated') !== false) ? 'system' : 'employee';
        }
        unset($r);
        
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
            $draftId = $data['draft_id'] ?? null;
            $results = [];

            for ($idx = 0; $idx < count($segments); $idx++) {
                $seg = $segments[$idx];
                $ltid = (int)$seg['leave_type_id'];
                $start = DateHelper::toSql($seg['start_date']);
                $end = DateHelper::toSql($seg['end_date']);
                $year = date('Y', strtotime($start));
                $currentRemarks = $remarks;

                // 1. Check Overlap
                $stmtOver = $db->prepare("
                    SELECT id FROM leave_requests 
                    WHERE employee_id = ? 
                    AND status NOT IN ('rejected', 'cancelled', 'draft')
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
                $stmtLt = $db->prepare("SELECT name, gender_restriction, is_paid FROM leave_types WHERE id = ?");
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

                $isPaid = $leaveType ? (bool)$leaveType['is_paid'] : true;

                if ($isPaid && $totalDays > $remaining) {
                    // Rule 1: Force Unpaid Leave or Split Request
                    $stmtUl = $db->prepare("SELECT id FROM leave_types WHERE code = 'UL' AND (company_id = ? OR company_id IS NULL) LIMIT 1");
                    $stmtUl->execute([$companyId]);
                    $unpaidLeaveId = $stmtUl->fetchColumn();

                    if (!$unpaidLeaveId) {
                        throw new \Exception("Insufficient balance for {$leaveType['name']}. Available: $remaining days. (No Unpaid Leave type found to fallback)");
                    }

                    if ($remaining <= 0) {
                        // Completely exhausted
                        $ltid = $unpaidLeaveId;
                    } else {
                        // Partially exhausted - Split request
                        // Calculate exact cutoff date where paid balance runs out
                        $weekends = $this->getCompanyWeekends($companyId);
                        
                        $stmtH = $db->prepare("SELECT holiday_date, is_recurring FROM holidays WHERE company_id = ? AND (holiday_date BETWEEN ? AND ? OR is_recurring = 1)");
                        $stmtH->execute([$companyId, $start, $end]);
                        $holidaysData = $stmtH->fetchAll(\PDO::FETCH_ASSOC);
                        $holidays = [];
                        $startYear = (int)date('Y', strtotime($start));
                        $endYear = (int)date('Y', strtotime($end));
                        foreach ($holidaysData as $h) {
                            if ($h['is_recurring']) {
                                for ($y = $startYear; $y <= $endYear; $y++) {
                                    $d = $y . '-' . date('m-d', strtotime($h['holiday_date']));
                                    if ($d >= $start && $d <= $end) {
                                        $holidays[] = $d;
                                    }
                                }
                            } else {
                                $holidays[] = $h['holiday_date'];
                            }
                        }

                        $stmtC = $db->prepare("SELECT country_id FROM companies WHERE id = ?");
                        $stmtC->execute([$companyId]);
                        $cntryId = $stmtC->fetchColumn();

                        $stmtPH = $db->prepare("SELECT holiday_date FROM public_holidays WHERE country_id = ? AND holiday_date BETWEEN ? AND ?");
                        $stmtPH->execute([$cntryId, $start, $end]);
                        $pubHolidays = $stmtPH->fetchAll(\PDO::FETCH_COLUMN);
                        $holidays = array_unique(array_merge($holidays, $pubHolidays));

                        $startDt = new \DateTime($start);
                        $endDt = new \DateTime($end);
                        $endDt->modify('+1 day');
                        $period = new \DatePeriod($startDt, new \DateInterval('P1D'), $endDt);

                        $paidDaysCount = 0;
                        $cutoffDate = $start;
                        
                        $stmtPol = $db->prepare("SELECT is_calendar_days FROM company_leave_policies WHERE company_id = ? AND leave_type_id = ?");
                        $stmtPol->execute([$companyId, $ltid]);
                        $pol = $stmtPol->fetch(\PDO::FETCH_ASSOC);
                        $isCal = $pol ? (bool)$pol['is_calendar_days'] : false;

                        foreach ($period as $dt) {
                            $dateStr = $dt->format("Y-m-d");
                            $isWorkDay = true;
                            if (!$isCal) {
                                if (in_array($dt->format("l"), $weekends)) $isWorkDay = false;
                                if (in_array($dateStr, $holidays)) $isWorkDay = false;
                            }
                            if ($isWorkDay) {
                                $paidDaysCount++;
                                if ($paidDaysCount == $remaining) {
                                    $cutoffDate = $dateStr;
                                    break;
                                }
                            }
                        }

                        $nextDayDt = new \DateTime($cutoffDate);
                        $nextDayDt->modify('+1 day');
                        $nextStartDate = $nextDayDt->format('Y-m-d');

                        $segments[] = [
                            'leave_type_id' => $unpaidLeaveId,
                            'start_date' => $nextStartDate,
                            'end_date' => $seg['end_date']
                        ];
                        
                        $end = $cutoffDate;
                        $totalDays = $remaining;
                        $currentRemarks = ($currentRemarks ? $currentRemarks . ' ' : '') . '[Split due to insufficient balance]';
                    }
                }

                // 5. Insert or Update
                if ($draftId && $idx === 0) {
                    $isSuperAdmin = $data['is_super_admin'] ?? false;
                    
                    $stmtOld = $db->prepare("SELECT status, total_days, leave_type_id, start_date FROM leave_requests WHERE id = ? AND employee_id = ?");
                    $stmtOld->execute([$draftId, $employeeId]);
                    $oldReq = $stmtOld->fetch(\PDO::FETCH_ASSOC);
                    
                    if (!$oldReq) {
                        throw new \Exception("Original request not found.");
                    }
                    if (!$isSuperAdmin && $oldReq['status'] !== 'draft') {
                        throw new \Exception("Cannot modify a non-draft request.");
                    }
                    
                    $isDraftSubmit = !empty($data['is_draft']);
                    $newStatus = $isDraftSubmit ? 'draft' : (($isSuperAdmin && $oldReq['status'] === 'approved') ? 'approved' : 'pending');

                    $stmtUpd = $db->prepare("
                        UPDATE leave_requests 
                        SET leave_type_id = :ltid, start_date = :start, end_date = :end, total_days = :days, status = :status, remarks = :remarks, attachment_path = COALESCE(:attachment, attachment_path)
                        WHERE id = :draft_id AND employee_id = :eid
                    ");
                    $stmtUpd->execute([
                        'ltid' => $ltid,
                        'start' => $start,
                        'end' => $end,
                        'days' => $totalDays,
                        'status' => $newStatus,
                        'remarks' => $currentRemarks,
                        'attachment' => $attachmentPath,
                        'draft_id' => $draftId,
                        'eid' => $employeeId
                    ]);
                    
                    if ($oldReq['status'] === 'approved' && $newStatus === 'approved') {
                        $this->recalculateBalances($companyId, $employeeId);
                    }
                    
                    $requestId = $draftId;
                } else {
                    $isDraftSubmit = !empty($data['is_draft']);
                    $status = $isDraftSubmit ? 'draft' : 'pending';
                    $stmtIns = $db->prepare("
                        INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, status, request_group_id, remarks, attachment_path)
                        VALUES (:eid, :ltid, :start, :end, :days, :status, :gid, :remarks, :attachment)
                    ");
                    $stmtIns->execute([
                        'eid' => $employeeId,
                        'ltid' => $ltid,
                        'start' => $start,
                        'end' => $end,
                        'days' => $totalDays,
                        'status' => $status,
                        'gid' => $requestGroupId,
                        'remarks' => $currentRemarks,
                        'attachment' => $attachmentPath
                    ]);
                    $requestId = $db->lastInsertId();
                }
                
                $currentStatus = $isDraftSubmit ?? false ? 'draft' : ($newStatus ?? $status ?? 'pending');
                if ($currentStatus !== 'draft') {
                    ApprovalHelper::log('leave', (int)$requestId, 'submitted', $remarks);
                }

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
        
        $stmt = $db->prepare("SELECT holiday_date, is_recurring FROM holidays WHERE company_id = :cid AND ((holiday_date BETWEEN :start AND :end) OR is_recurring = 1)");
        $stmt->execute(['cid' => $companyId, 'start' => $startDate, 'end' => $endDate]);
        $holidaysResult = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $holidays = [];
        $startYear = (int)date('Y', strtotime($startDate));
        $endYear = (int)date('Y', strtotime($endDate));
        foreach ($holidaysResult as $h) {
            if ($h['is_recurring']) {
                for ($y = $startYear; $y <= $endYear; $y++) {
                    $d = $y . '-' . date('m-d', strtotime($h['holiday_date']));
                    if ($d >= $startDate && $d <= $endDate) {
                        $holidays[] = $d;
                    }
                }
            } else {
                $holidays[] = $h['holiday_date'];
            }
        }

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
    public function approveRequest(int $requestId, int $approverId, string $comment = ''): void
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
                SET status = 'approved', manager_comment = :comment, approved_by_id = :approver_id, updated_at_utc = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute(['id' => $requestId, 'comment' => $comment, 'approver_id' => $approverId]);

            $stmtComp = $db->prepare("SELECT company_id FROM employee_companies WHERE employee_id = ? AND is_primary = 1");
            $stmtComp->execute([$request['employee_id']]);
            $companyId = $stmtComp->fetchColumn();

            if ($companyId) {
                $this->recalculateBalances($companyId, $request['employee_id']);
            }

            $db->commit();

            ApprovalHelper::log('leave', $requestId, 'approved', $comment ?: 'Request approved.');

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

    /**
     * Generate draft leaves from attendance logs with leave statuses
     */
    public function generateSystemDraftLeaves(?int $companyId = null, ?int $employeeId = null): int
    {
        $db = \Database::getInstance()->getConnection();
        
        $stmtLt = $db->query("SELECT id, code, company_id FROM leave_types");
        $companies = $db->query("SELECT id FROM companies")->fetchAll(\PDO::FETCH_COLUMN);
        $companyLeaveTypes = [];
        $allCodes = [];
        while ($row = $stmtLt->fetch(\PDO::FETCH_ASSOC)) {
            $allCodes[$row['code']] = true;
            if ($row['company_id'] === null) {
                foreach ($companies as $cid) {
                    $companyLeaveTypes[$cid][$row['code']] = $row['id'];
                }
            } else {
                $companyLeaveTypes[$row['company_id']][$row['code']] = $row['id'];
            }
        }
        
        if (empty($companyLeaveTypes)) return 0;

        $codesList = array_keys($allCodes);
        $inClause = implode(',', array_fill(0, count($codesList), '?'));
        $params = $codesList;

        $query = "
            SELECT al.id as log_id, al.employee_id, al.attendance_date, al.status, ec.company_id
            FROM attendance_logs al
            JOIN employee_companies ec ON al.employee_id = ec.employee_id AND al.company_id = ec.company_id AND ec.is_primary = 1
            WHERE al.status IN ($inClause)
              AND NOT EXISTS (
                  SELECT 1 FROM leave_requests lr 
                  WHERE lr.employee_id = al.employee_id 
                    AND lr.status NOT IN ('rejected', 'cancelled')
                    AND al.attendance_date BETWEEN lr.start_date AND lr.end_date
              )
        ";
        
        if ($companyId) {
            $query .= " AND al.company_id = ?";
            $params[] = $companyId;
        }
        if ($employeeId) {
            $query .= " AND al.employee_id = ?";
            $params[] = $employeeId;
        }
        
        $query .= " ORDER BY al.employee_id, al.status, al.attendance_date ASC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $draftsCreated = 0;
        
        // Group consecutive dates
        $groups = [];
        foreach ($logs as $log) {
            $eid = $log['employee_id'];
            $cid = $log['company_id'];
            $status = $log['status'];
            $ltid = $companyLeaveTypes[$cid][$status] ?? null;
            if (!$ltid) continue;
            
            $key = "{$eid}_{$status}";
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $log;
        }

        $db->beginTransaction();
        try {
            foreach ($groups as $key => $groupLogs) {
                $currentGroup = null;
                foreach ($groupLogs as $log) {
                    $date = $log['attendance_date'];
                    if (!$currentGroup) {
                        $currentGroup = [
                            'employee_id' => $log['employee_id'],
                            'leave_type_id' => $companyLeaveTypes[$log['company_id']][$log['status']],
                            'company_id' => $log['company_id'],
                            'start_date' => $date,
                            'end_date' => $date,
                            'log_ids' => [$log['log_id']]
                        ];
                    } else {
                        // check if consecutive working day
                        $lastDate = new \DateTime($currentGroup['end_date']);
                        $nextExpected = $lastDate->modify('+1 day')->format('Y-m-d');
                        if ($date === $nextExpected) {
                            $currentGroup['end_date'] = $date;
                            $currentGroup['log_ids'][] = $log['log_id'];
                        } else {
                            // Check if days between end_date and $date are all weekends/holidays
                            $isConsecutive = true;
                            $tempDate = new \DateTime($currentGroup['end_date']);
                            $targetDate = new \DateTime($date);
                            $tempDate->modify('+1 day');
                            while ($tempDate < $targetDate) {
                                $dtStr = $tempDate->format('Y-m-d');
                                if (!$this->isWeekendOrHoliday($currentGroup['company_id'], $dtStr)) {
                                    $isConsecutive = false;
                                    break;
                                }
                                $tempDate->modify('+1 day');
                            }
                            if ($isConsecutive) {
                                $currentGroup['end_date'] = $date;
                                $currentGroup['log_ids'][] = $log['log_id'];
                            } else {
                                // Check if employee has returned to work
                                $stmtReturn = $db->prepare("
                                    SELECT 1 FROM attendance_logs 
                                    WHERE employee_id = ? AND attendance_date > ? 
                                    AND status IN ('present', 'work_from_home')
                                    LIMIT 1
                                ");
                                $stmtReturn->execute([$currentGroup['employee_id'], $currentGroup['end_date']]);
                                if ($stmtReturn->fetch()) {
                                    $this->saveDraftLeaveGroup($currentGroup);
                                    $draftsCreated++;
                                }
                                
                                // Start new group
                                $currentGroup = [
                                    'employee_id' => $log['employee_id'],
                                    'leave_type_id' => $companyLeaveTypes[$log['company_id']][$log['status']],
                                    'company_id' => $log['company_id'],
                                    'start_date' => $date,
                                    'end_date' => $date,
                                    'log_ids' => [$log['log_id']]
                                ];
                            }
                        }
                    }
                }
                if ($currentGroup) {
                    // Check if employee has returned to work
                    $stmtReturn = $db->prepare("
                        SELECT 1 FROM attendance_logs 
                        WHERE employee_id = ? AND attendance_date > ? 
                        AND status IN ('present', 'work_from_home')
                        LIMIT 1
                    ");
                    $stmtReturn->execute([$currentGroup['employee_id'], $currentGroup['end_date']]);
                    if ($stmtReturn->fetch()) {
                        $this->saveDraftLeaveGroup($currentGroup);
                        $draftsCreated++;
                    }
                }
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
        
        return $draftsCreated;
    }

    private function isWeekendOrHoliday(int $companyId, string $date): bool
    {
        $db = \Database::getInstance()->getConnection();
        
        $dateObj = new \DateTime($date);
        $dayOfWeek = $dateObj->format('l');

        $stmt = $db->prepare("
            SELECT ws.status as weekend_status, ph.id as ph_id, ch.id as ch_id
            FROM companies c
            LEFT JOIN office_weekly_schedules ws ON c.id = ws.company_id AND ws.day_of_week = ?
            LEFT JOIN public_holidays ph ON ph.country_id = c.country_id AND ph.holiday_date = ?
            LEFT JOIN holidays ch ON c.id = ch.company_id AND (ch.holiday_date = ? OR (ch.is_recurring = 1 AND MONTH(ch.holiday_date) = MONTH(?) AND DAY(ch.holiday_date) = DAY(?)))
            WHERE c.id = ?
        ");
        $stmt->execute([$dayOfWeek, $date, $date, $date, $date, $companyId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            // Fallback if company doesn't exist? Just check default weekend
            return in_array($dayOfWeek, ['Saturday', 'Sunday']);
        }

        $wStatus = strtolower((string)$row['weekend_status']);
        if ($wStatus === 'off' || $wStatus === 'weekend') return true;
        
        if (empty($wStatus) && in_array($dayOfWeek, ['Saturday', 'Sunday'])) {
            return true;
        }

        if (!empty($row['ph_id'])) return true;
        if (!empty($row['ch_id'])) return true;

        return false;
    }

    private function saveDraftLeaveGroup(array $group): void
    {
        $db = \Database::getInstance()->getConnection();
        
        $totalDays = $this->calculateLeaveDays($group['employee_id'], $group['company_id'], $group['leave_type_id'], $group['start_date'], $group['end_date']);
        
        $requestGroupId = bin2hex(random_bytes(8));
        
        $stmtIns = $db->prepare("
            INSERT INTO leave_requests (employee_id, leave_type_id, start_date, end_date, total_days, status, request_group_id, remarks)
            VALUES (:eid, :ltid, :start, :end, :days, 'draft', :gid, 'System-Generated Leave Request')
        ");
        $stmtIns->execute([
            'eid' => $group['employee_id'],
            'ltid' => $group['leave_type_id'],
            'start' => $group['start_date'],
            'end' => $group['end_date'],
            'days' => $totalDays,
            'gid' => $requestGroupId
        ]);
        
        $requestId = $db->lastInsertId();
        
        // Notify Manager
        $stmtMgr = $db->prepare("
            SELECT reporting_manager_id FROM employees WHERE id = ?
        ");
        $stmtMgr->execute([$group['employee_id']]);
        $managerId = $stmtMgr->fetchColumn();
        
        if ($managerId) {
            $stmtUser = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmtUser->execute([$managerId]);
            $managerUserId = $stmtUser->fetchColumn();
            
            if ($managerUserId) {
                \App\Helpers\NotificationHelper::send(
                    $managerUserId,
                    'leave_draft',
                    'System-Generated Leave Request',
                    "A draft leave request has been generated for an employee.",
                    ['link' => '/leave', 'id' => $requestId],
                    true
                );
            }
        }
    }
    public function recalculateBalances($companyId, $employeeId = null)
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
}
