<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * TravelService
 * 
 * Handles business logic for travel requests, routing rules, conflict checks,
 * itinerary versioning, and attendance integration.
 */
class TravelService
{
    private $db;
    private $auditService;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->auditService = new AuditService();
    }

    /**
     * Fetch active travel purpose categories
     */
    public function getCategories(): array
    {
        $stmt = $this->db->query("SELECT * FROM travel_categories WHERE is_active = 1 ORDER BY category_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch active travel routing rules
     */
    public function getRoutingRules(): array
    {
        $stmt = $this->db->query("SELECT * FROM travel_routing_rules WHERE is_active = 1 ORDER BY scope_name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all roles (filtering out SuperAdmin unless user is SuperAdmin)
     */
    public function getRoles(bool $isSuperAdmin = false): array
    {
        if ($isSuperAdmin) {
            $stmt = $this->db->query("SELECT id, name FROM roles ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->db->prepare("SELECT id, name FROM roles WHERE id != ? ORDER BY name ASC");
            $stmt->execute([\App\Helpers\RoleConstants::SUPER_ADMIN]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function createCategory(string $categoryName): void
    {
        $stmt = $this->db->prepare("INSERT INTO travel_categories (category_name, is_active) VALUES (?, 1)");
        $stmt->execute([$categoryName]);
    }

    public function createRoutingRule(string $scopeName, string $approverRoles, int $requiresPassport = 0, int $requiresVisa = 0, int $requiresFlight = 0, string $description = ''): void
    {
        $stmt = $this->db->prepare("INSERT INTO travel_routing_rules (scope_name, approver_roles, requires_passport, requires_visa, requires_flight, description, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([$scopeName, $approverRoles, $requiresPassport, $requiresVisa, $requiresFlight, $description]);
    }

    public function updateCategory(int $id, string $categoryName): void
    {
        $stmt = $this->db->prepare("UPDATE travel_categories SET category_name = ? WHERE id = ?");
        $stmt->execute([$categoryName, $id]);
    }

    public function deleteCategory(int $id): void
    {
        // Check if it's used in travel_requests
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM travel_requests WHERE category_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            // Soft delete
            $stmtDel = $this->db->prepare("UPDATE travel_categories SET is_active = 0 WHERE id = ?");
            $stmtDel->execute([$id]);
        } else {
            // Hard delete
            $stmtDel = $this->db->prepare("DELETE FROM travel_categories WHERE id = ?");
            $stmtDel->execute([$id]);
        }
    }

    public function updateRoutingRule(int $id, string $scopeName, string $approverRoles, int $requiresPassport = 0, int $requiresVisa = 0, int $requiresFlight = 0, string $description = ''): void
    {
        $stmt = $this->db->prepare("UPDATE travel_routing_rules SET scope_name = ?, approver_roles = ?, requires_passport = ?, requires_visa = ?, requires_flight = ?, description = ? WHERE id = ?");
        $stmt->execute([$scopeName, $approverRoles, $requiresPassport, $requiresVisa, $requiresFlight, $description, $id]);
    }

    public function deleteRoutingRule(int $id): void
    {
        // Check if it's used in travel_requests
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM travel_requests WHERE routing_rule_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $stmtDel = $this->db->prepare("UPDATE travel_routing_rules SET is_active = 0 WHERE id = ?");
            $stmtDel->execute([$id]);
        } else {
            $stmtDel = $this->db->prepare("DELETE FROM travel_routing_rules WHERE id = ?");
            $stmtDel->execute([$id]);
        }
    }

    /**
     * Distributed Lock - Acquire
     */
    public function acquireLock(int $employeeId, string $startDate, string $endDate): bool
    {
        $lockName = "travel_lock_{$employeeId}_{$startDate}_{$endDate}";
        $stmt = $this->db->prepare("SELECT GET_LOCK(?, 10) as locked");
        $stmt->execute([$lockName]);
        $res = $stmt->fetch();
        return (int)($res['locked'] ?? 0) === 1;
    }

    /**
     * Distributed Lock - Release
     */
    public function releaseLock(int $employeeId, string $startDate, string $endDate): void
    {
        $lockName = "travel_lock_{$employeeId}_{$startDate}_{$endDate}";
        $stmt = $this->db->prepare("SELECT RELEASE_LOCK(?)");
        $stmt->execute([$lockName]);
    }

    /**
     * Query HRMS DB for overlapping events (leave, public holidays, approved travel)
     */
    public function checkConflicts(int $employeeId, string $startDate, string $endDate, ?int $excludeRequestId = null): array
    {
        $conflicts = [];

        // 1. Overlapping approved/pending leaves
        $stmt = $this->db->prepare("
            SELECT lr.id, lr.start_date, lr.end_date, lt.name as leave_name, lr.status 
            FROM leave_requests lr
            JOIN leave_types lt ON lr.leave_type_id = lt.id
            WHERE lr.employee_id = :employee_id
              AND lr.status IN ('approved', 'pending')
              AND lr.start_date <= :end_date
              AND lr.end_date >= :start_date
        ");
        $stmt->execute([
            'employee_id' => $employeeId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($leaves as $leave) {
            $conflicts[] = [
                'type' => 'leave',
                'id' => $leave['id'],
                'status' => $leave['status'],
                'description' => "Overlaps with Leave Request: {$leave['leave_name']} from {$leave['start_date']} to {$leave['end_date']} (Status: {$leave['status']})"
            ];
        }

        // 2. Overlapping public holidays (scoped by employee's primary company country)
        $stmtCountry = $this->db->prepare("
            SELECT c.country_id 
            FROM employee_companies ec
            JOIN companies c ON ec.company_id = c.id
            WHERE ec.employee_id = ? AND ec.is_primary = 1 AND ec.is_active = 1
            LIMIT 1
        ");
        $stmtCountry->execute([$employeeId]);
        $countryId = $stmtCountry->fetchColumn();

        if ($countryId) {
            $stmtHolidays = $this->db->prepare("
                SELECT id, name, holiday_date 
                FROM public_holidays 
                WHERE country_id = :country_id
                  AND holiday_date BETWEEN :start_date AND :end_date
            ");
            $stmtHolidays->execute([
                'country_id' => $countryId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            $holidays = $stmtHolidays->fetchAll(PDO::FETCH_ASSOC);
            foreach ($holidays as $holiday) {
                $conflicts[] = [
                    'type' => 'public_holiday',
                    'id' => $holiday['id'],
                    'status' => 'locked',
                    'description' => "Overlaps with Public Holiday: {$holiday['name']} on {$holiday['holiday_date']}"
                ];
            }
        }

        // 3. Overlapping approved travel requests
        $query = "
            SELECT id, start_date, end_date, destination, status 
            FROM travel_requests 
            WHERE employee_id = :employee_id
              AND status = 'Approved'
              AND start_date <= :end_date
              AND end_date >= :start_date
        ";
        if ($excludeRequestId) {
            $query .= " AND id != :exclude_id";
        }
        $stmtTravel = $this->db->prepare($query);
        $params = [
            'employee_id' => $employeeId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        if ($excludeRequestId) {
            $params['exclude_id'] = $excludeRequestId;
        }
        $stmtTravel->execute($params);
        $trips = $stmtTravel->fetchAll(PDO::FETCH_ASSOC);
        foreach ($trips as $trip) {
            $conflicts[] = [
                'type' => 'travel',
                'id' => $trip['id'],
                'status' => $trip['status'],
                'description' => "Overlaps with Approved Travel Request #{$trip['id']} to {$trip['destination']} from {$trip['start_date']} to {$trip['end_date']}"
            ];
        }

        return $conflicts;
    }

    /**
     * Suggest closest shift to avoid all conflicts
     */
    public function calculateWorkaround(int $employeeId, string $startDate, string $endDate, ?int $excludeRequestId = null): array
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $diff = $start->diff($end);
        $durationDays = $diff->days + 1;

        $currentStart = new \DateTime($startDate);
        $attempts = 0;
        while ($attempts < 30) {
            $candidateStart = $currentStart->format('Y-m-d');
            $candidateEnd = (clone $currentStart)->modify('+' . ($durationDays - 1) . ' days')->format('Y-m-d');
            
            $conflicts = $this->checkConflicts($employeeId, $candidateStart, $candidateEnd, $excludeRequestId);
            if (empty($conflicts)) {
                return [
                    'suggested_start_date' => $candidateStart,
                    'suggested_end_date' => $candidateEnd,
                    'message' => "Programmatic Workaround: Shift the travel to start on $candidateStart and end on $candidateEnd to bypass all conflicts."
                ];
            }
            
            // Advance to the day after the latest conflict end date in this attempt
            $latestConflictEnd = $candidateStart;
            foreach ($conflicts as $c) {
                if ($c['type'] === 'leave') {
                    $stmt = $this->db->prepare("SELECT end_date FROM leave_requests WHERE id = ?");
                    $stmt->execute([$c['id']]);
                    $d = $stmt->fetchColumn();
                    if ($d && $d > $latestConflictEnd) $latestConflictEnd = $d;
                } elseif ($c['type'] === 'travel') {
                    $stmt = $this->db->prepare("SELECT end_date FROM travel_requests WHERE id = ?");
                    $stmt->execute([$c['id']]);
                    $d = $stmt->fetchColumn();
                    if ($d && $d > $latestConflictEnd) $latestConflictEnd = $d;
                }
            }
            
            $currentStart = new \DateTime($latestConflictEnd);
            $currentStart->modify('+1 day');
            $attempts++;
        }
        
        return [
            'suggested_start_date' => null,
            'suggested_end_date' => null,
            'message' => "No available workaround window found within the next 30 days."
        ];
    }

    /**
     * Create Travel Request
     */
    public function createRequest(array $data, int $createdByUserId): int
    {
        $employeeId = (int)$data['employee_id'];
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];
        $status = $data['status'] ?? 'Draft';
        $categoryId = (int)$data['category_id'];
        $routingRuleId = (int)$data['routing_rule_id'];
        $destination = $data['destination'];
        $itineraryData = $data['itinerary'] ?? '{}';

        // 1. Acquire concurrency lock
        if (!$this->acquireLock($employeeId, $startDate, $endDate)) {
            throw new Exception("Unable to acquire lock. The travel request for this period is being modified by another user.");
        }

        $this->db->beginTransaction();
        try {
            // 2. Conflict validations
            $conflicts = $this->checkConflicts($employeeId, $startDate, $endDate);
            if (!empty($conflicts)) {
                if ($status === 'Approved' || $status === 'Complete' || $status === 'Pending Approval') {
                    $workaround = $this->calculateWorkaround($employeeId, $startDate, $endDate);
                    throw new Exception("Conflict detected! Cannot confirm/submit travel due to overlapping dates. " . $conflicts[0]['description'] . ". " . $workaround['message']);
                } else if ($status === 'Provisional') {
                    // Provisional allows overlaps but flags warning (returned as success metadata in controller)
                }
            }

            // 3. Insert Request
            $stmt = $this->db->prepare("
                INSERT INTO travel_requests (employee_id, created_by_id, category_id, routing_rule_id, destination, start_date, end_date, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $employeeId,
                $createdByUserId,
                $categoryId,
                $routingRuleId,
                $destination,
                $startDate,
                $endDate,
                $status
            ]);
            $requestId = (int)$this->db->lastInsertId();

            // 4. Save Version 1 of the Itinerary
            $stmtVersion = $this->db->prepare("
                INSERT INTO travel_itinerary_versions (travel_request_id, version, itinerary_data, created_by_id)
                VALUES (?, 1, ?, ?)
            ");
            $stmtVersion->execute([
                $requestId,
                is_array($itineraryData) ? json_encode($itineraryData) : $itineraryData,
                $createdByUserId
            ]);

            // 5. Update Attendance if Approved
            if ($status === 'Approved' || $status === 'Complete') {
                $this->updateAttendanceForTravel($employeeId, $startDate, $endDate, true);
                $this->enqueueAsyncJob('calendar_sync', [
                    'travel_request_id' => $requestId,
                    'action' => 'insert'
                ]);
                $this->enqueueAsyncJob('finance_trigger', [
                    'travel_request_id' => $requestId,
                    'action' => 'approve'
                ]);
            } elseif ($status === 'Provisional') {
                $this->enqueueAsyncJob('calendar_sync', [
                    'travel_request_id' => $requestId,
                    'action' => 'insert_provisional'
                ]);
            }

            $this->auditService->log('CREATE', 'travel_requests', $requestId, null, $data);
            $this->db->commit();
            $this->releaseLock($employeeId, $startDate, $endDate);
            return $requestId;

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->releaseLock($employeeId, $startDate, $endDate);
            throw $e;
        }
    }

    /**
     * Update/Modify Travel Request
     */
    public function updateRequest(int $requestId, array $data, int $updatedByUserId): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM travel_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $oldRequest = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$oldRequest) {
            throw new Exception("Travel Request not found.");
        }

        $employeeId = (int)($data['employee_id'] ?? $oldRequest['employee_id']);
        $startDate = $data['start_date'] ?? $oldRequest['start_date'];
        $endDate = $data['end_date'] ?? $oldRequest['end_date'];
        $status = $data['status'] ?? $oldRequest['status'];
        $categoryId = (int)($data['category_id'] ?? $oldRequest['category_id']);
        $routingRuleId = (int)($data['routing_rule_id'] ?? $oldRequest['routing_rule_id']);
        $destination = $data['destination'] ?? $oldRequest['destination'];
        $itineraryData = $data['itinerary'] ?? null;

        // 1. Concurrency lock
        if (!$this->acquireLock($employeeId, $startDate, $endDate)) {
            throw new Exception("Unable to acquire lock. The travel request for this period is being modified by another user.");
        }

        $this->db->beginTransaction();
        try {
            // 2. Overlap pre-checks
            $conflicts = $this->checkConflicts($employeeId, $startDate, $endDate, $requestId);
            if (!empty($conflicts)) {
                if ($status === 'Approved' || $status === 'Complete' || $status === 'Pending Approval') {
                    $workaround = $this->calculateWorkaround($employeeId, $startDate, $endDate, $requestId);
                    throw new Exception("Conflict detected! Cannot confirm/submit travel due to overlapping dates. " . $conflicts[0]['description'] . ". " . $workaround['message']);
                }
            }

            // 3. Update main request record
            $stmtUpdate = $this->db->prepare("
                UPDATE travel_requests 
                SET employee_id = ?, category_id = ?, routing_rule_id = ?, destination = ?, start_date = ?, end_date = ?, status = ?
                WHERE id = ?
            ");
            $stmtUpdate->execute([
                $employeeId,
                $categoryId,
                $routingRuleId,
                $destination,
                $startDate,
                $endDate,
                $status,
                $requestId
            ]);

            // 4. Save new Itinerary Version if changed
            if ($itineraryData !== null) {
                // Check current latest version
                $stmtVersionCheck = $this->db->prepare("SELECT MAX(version) FROM travel_itinerary_versions WHERE travel_request_id = ?");
                $stmtVersionCheck->execute([$requestId]);
                $maxVersion = (int)$stmtVersionCheck->fetchColumn();
                $newVersion = $maxVersion + 1;

                $stmtVersion = $this->db->prepare("
                    INSERT INTO travel_itinerary_versions (travel_request_id, version, itinerary_data, created_by_id)
                    VALUES (?, ?, ?, ?)
                ");
                $stmtVersion->execute([
                    $requestId,
                    $newVersion,
                    is_array($itineraryData) ? json_encode($itineraryData) : $itineraryData,
                    $updatedByUserId
                ]);
            }

            // 5. Handle attendance and integrations depending on status transitions
            $oldStatus = $oldRequest['status'];
            
            if ($status === 'Approved' || $status === 'Complete') {
                // If it transitioned to Approved, or it was Approved and dates changed
                if ($oldStatus !== 'Approved' && $oldStatus !== 'Complete' || $oldRequest['start_date'] !== $startDate || $oldRequest['end_date'] !== $endDate) {
                    // Revert old attendance dates just in case they changed
                    if ($oldStatus === 'Approved' || $oldStatus === 'Complete') {
                        $this->updateAttendanceForTravel((int)$oldRequest['employee_id'], $oldRequest['start_date'], $oldRequest['end_date'], false);
                    }
                    // Apply new attendance
                    $this->updateAttendanceForTravel($employeeId, $startDate, $endDate, true);
                    $this->enqueueAsyncJob('calendar_sync', [
                        'travel_request_id' => $requestId,
                        'action' => 'update'
                    ]);
                    $this->enqueueAsyncJob('finance_trigger', [
                        'travel_request_id' => $requestId,
                        'action' => 'approve'
                    ]);
                }
            } elseif ($status === 'Cancelled' || $status === 'Rejected') {
                if ($oldStatus === 'Approved' || $oldStatus === 'Complete') {
                    $this->updateAttendanceForTravel($employeeId, $startDate, $endDate, false);
                    $this->enqueueAsyncJob('calendar_sync', [
                        'travel_request_id' => $requestId,
                        'action' => 'cancel'
                    ]);
                    $this->enqueueAsyncJob('finance_trigger', [
                        'travel_request_id' => $requestId,
                        'action' => 'cancel'
                    ]);
                }
            }

            $this->auditService->log('UPDATE', 'travel_requests', $requestId, $oldRequest, $data);
            $this->db->commit();
            $this->releaseLock($employeeId, $startDate, $endDate);
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->releaseLock($employeeId, $startDate, $endDate);
            throw $e;
        }
    }

    /**
     * Handle Mid-Trip cancellation
     */
    public function midTripCancel(int $requestId, string $cancellationDate, int $updatedByUserId): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM travel_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request || $request['status'] !== 'Approved') {
            throw new Exception("Only active Approved travel requests can be cancelled mid-trip.");
        }

        if ($cancellationDate < $request['start_date'] || $cancellationDate > $request['end_date']) {
            throw new Exception("Cancellation date must fall within the trip boundaries ($request[start_date] to $request[end_date]).");
        }

        $employeeId = (int)$request['employee_id'];
        
        if (!$this->acquireLock($employeeId, $request['start_date'], $request['end_date'])) {
            throw new Exception("Lock acquisition failed.");
        }

        $this->db->beginTransaction();
        try {
            // Revert attendance status for dates from $cancellationDate to original end_date
            $this->updateAttendanceForTravel($employeeId, $cancellationDate, $request['end_date'], false);

            // Update travel request end_date to the day before cancellation (or cancellationDate depending on policy)
            // Let's set the status to 'Cancelled' if cancellation date is the start_date, else keep Approved but shorten dates, or transition status.
            // Requirement: "provision for Mid trip cancellation... complete trip cancellation".
            // We shorten the approved trip to end the day before cancellation date, and create a record/comment or status change.
            $newEndDate = date('Y-m-d', strtotime($cancellationDate . ' -1 day'));
            
            if ($newEndDate < $request['start_date']) {
                // If cancelled before/on day 1, it is a complete cancellation
                $stmtUpdate = $this->db->prepare("UPDATE travel_requests SET status = 'Cancelled' WHERE id = ?");
                $stmtUpdate->execute([$requestId]);
            } else {
                $stmtUpdate = $this->db->prepare("UPDATE travel_requests SET end_date = ?, status = 'Complete' WHERE id = ?");
                $stmtUpdate->execute([$newEndDate, $requestId]);
            }

            $this->enqueueAsyncJob('calendar_sync', [
                'travel_request_id' => $requestId,
                'action' => 'mid_trip_cancel',
                'cancellation_date' => $cancellationDate
            ]);
            $this->enqueueAsyncJob('finance_trigger', [
                'travel_request_id' => $requestId,
                'action' => 'mid_trip_cancel',
                'cancellation_date' => $cancellationDate
            ]);

            $this->auditService->log('MID_TRIP_CANCEL', 'travel_requests', $requestId, $request, ['cancellation_date' => $cancellationDate]);
            $this->db->commit();
            $this->releaseLock($employeeId, $request['start_date'], $request['end_date']);
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->releaseLock($employeeId, $request['start_date'], $request['end_date']);
            throw $e;
        }
    }

    /**
     * Map travel days to attendance status (attendance logs integration)
     */
    private function updateAttendanceForTravel(int $employeeId, string $startDate, string $endDate, bool $isApproved = true): void
    {
        $stmt = $this->db->prepare("
            SELECT company_id FROM employee_companies 
            WHERE employee_id = ? AND is_primary = 1 AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([$employeeId]);
        $companyId = $stmt->fetchColumn();
        if (!$companyId) return;

        $statusKey = $this->getBusinessTravelStatusKey((int)$companyId);

        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day');
        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($start, $interval, $end);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            
            if ($isApproved) {
                $stmtExist = $this->db->prepare("SELECT id FROM attendance_logs WHERE employee_id = ? AND attendance_date = ?");
                $stmtExist->execute([$employeeId, $dateStr]);
                $logId = $stmtExist->fetchColumn();

                if ($logId) {
                    $stmtUpd = $this->db->prepare("
                        UPDATE attendance_logs 
                        SET status = ?, remarks = 'On Approved Business Travel', approval_status = 'approved', source = 'system'
                        WHERE id = ?
                    ");
                    $stmtUpd->execute([$statusKey, $logId]);
                } else {
                    $stmtIns = $this->db->prepare("
                        INSERT INTO attendance_logs (employee_id, company_id, attendance_date, status, remarks, approval_status, source)
                        VALUES (?, ?, ?, ?, 'On Approved Business Travel', 'approved', 'system')
                    ");
                    $stmtIns->execute([$employeeId, $companyId, $dateStr, $statusKey]);
                }
            } else {
                $stmtDel = $this->db->prepare("
                    DELETE FROM attendance_logs 
                    WHERE employee_id = ? 
                      AND attendance_date = ? 
                      AND source = 'system' 
                      AND remarks LIKE '%Business Travel%'
                ");
                $stmtDel->execute([$employeeId, $dateStr]);
            }
        }
    }

    /**
     * Get or create dynamic status key for business travel
     */
    private function getBusinessTravelStatusKey(int $companyId): string
    {
        $stmt = $this->db->prepare("SELECT status_key FROM office_attendance_status_definitions WHERE company_id = ? AND status_key = 'business_travel' AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$companyId]);
        $key = $stmt->fetchColumn();
        if ($key) {
            return $key;
        }
        
        try {
            $stmtInsert = $this->db->prepare("
                INSERT INTO office_attendance_status_definitions (company_id, status_key, status_label, display_code, color_code, is_default, sort_order)
                VALUES (?, 'business_travel', 'Business Travel', 'BT', '#8b5cf6', 0, 10)
            ");
            $stmtInsert->execute([$companyId]);
            return 'business_travel';
        } catch (Exception $e) {
            return 'present'; // Fallback
        }
    }

    /**
     * Enqueue background job (Async Separation)
     */
    public function enqueueAsyncJob(string $jobType, array $payload): void
    {
        $stmt = $this->db->prepare("INSERT INTO travel_async_jobs (job_type, payload) VALUES (?, ?)");
        $stmt->execute([$jobType, json_encode($payload)]);
    }

    /**
     * Fetch Travel Requests with dynamic scoping
     */
    public function fetchRequests(array $filters, array $sessionData, bool $isGlobalAdmin): array
    {
        $params = [];
        $whereClauses = ["1=1"];

        if (!$isGlobalAdmin) {
            $associatedCompanyIds = $sessionData['associated_company_ids'] ?? [];
            $roleId = (int)($sessionData['role_id'] ?? 6);
            $userId = (int)($sessionData['user_id'] ?? 0);
            $employeeId = (int)($sessionData['scope_employee_id'] ?? 0);

            // Travel Coordinators or HR can see requests of their company
            $isTravelCoordinator = ($roleId === 60 || $roleId === 3 || $roleId === 4 || $roleId === 5 || $roleId === 1 || $roleId === 2);
            // Wait, resolved roles handles custom coordinator roles.

            if ($roleId === 6) { // Standard Employee can only see their own
                $whereClauses[] = "(tr.employee_id = :session_emp_id OR tr.created_by_id = :session_user_id)";
                $params['session_emp_id'] = $employeeId;
                $params['session_user_id'] = $userId;
            } else {
                // Limit to company scopes
                if (!empty($associatedCompanyIds)) {
                    $placeholders = [];
                    foreach ($associatedCompanyIds as $idx => $cid) {
                        $key = "company_id_$idx";
                        $placeholders[] = ":$key";
                        $params[$key] = $cid;
                    }
                    $whereClauses[] = "ec.company_id IN (" . implode(',', $placeholders) . ")";
                }
            }
        }

        if (!empty($filters['employee_id'])) {
            $whereClauses[] = "tr.employee_id = :filter_emp_id";
            $params['filter_emp_id'] = (int)$filters['employee_id'];
        }

        if (!empty($filters['status'])) {
            $whereClauses[] = "tr.status = :filter_status";
            $params['filter_status'] = $filters['status'];
        }

        $query = "
            SELECT tr.*, 
                   e.first_name, e.last_name, e.employee_code,
                   tc.category_name, trr.scope_name,
                   iv.itinerary_data as latest_itinerary, iv.version as latest_version
            FROM travel_requests tr
            JOIN employees e ON tr.employee_id = e.id
            JOIN travel_categories tc ON tr.category_id = tc.id
            JOIN travel_routing_rules trr ON tr.routing_rule_id = trr.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            LEFT JOIN (
                SELECT iv1.travel_request_id, iv1.itinerary_data, iv1.version 
                FROM travel_itinerary_versions iv1
                JOIN (
                    SELECT travel_request_id, MAX(version) as max_v 
                    FROM travel_itinerary_versions 
                    GROUP BY travel_request_id
                ) iv2 ON iv1.travel_request_id = iv2.travel_request_id AND iv1.version = iv2.max_v
            ) iv ON tr.id = iv.travel_request_id
            WHERE " . implode(" AND ", $whereClauses) . "
            ORDER BY tr.start_date DESC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Dashboard Stats for Travel Dashboard
     */
    public function getDashboardStats(array $sessionData): array
    {
        $isGlobalAdmin = (int)($sessionData['role_id'] ?? 6) === 1 || (int)($sessionData['role_id'] ?? 6) === 2;
        $associatedCompanyIds = $sessionData['associated_company_ids'] ?? [];
        $employeeId = (int)$sessionData['scope_employee_id'];

        $whereScope = "1=1";
        $params = [];

        if (!$isGlobalAdmin) {
            $roleId = (int)($sessionData['role_id'] ?? 6);
            if ($roleId === 6) { // Employee
                $whereScope = "tr.employee_id = :session_emp_id";
                $params['session_emp_id'] = $employeeId;
            } else if (!empty($associatedCompanyIds)) {
                $placeholders = [];
                foreach ($associatedCompanyIds as $idx => $cid) {
                    $key = "scope_cid_$idx";
                    $placeholders[] = ":$key";
                    $params[$key] = $cid;
                }
                $whereScope = "ec.company_id IN (" . implode(',', $placeholders) . ")";
            }
        }

        // Count Active: status Approved and date in range
        $stmtActive = $this->db->prepare("
            SELECT COUNT(DISTINCT tr.id) 
            FROM travel_requests tr
            LEFT JOIN employees e ON tr.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            WHERE tr.status = 'Approved' AND CURRENT_DATE BETWEEN tr.start_date AND tr.end_date AND $whereScope
        ");
        $stmtActive->execute($params);
        $activeCount = (int)$stmtActive->fetchColumn();

        // Count Upcoming: status Approved and start_date > CURRENT_DATE
        $stmtUpcoming = $this->db->prepare("
            SELECT COUNT(DISTINCT tr.id) 
            FROM travel_requests tr
            LEFT JOIN employees e ON tr.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            WHERE tr.status = 'Approved' AND tr.start_date > CURRENT_DATE AND $whereScope
        ");
        $stmtUpcoming->execute($params);
        $upcomingCount = (int)$stmtUpcoming->fetchColumn();

        // Count Pending: status Pending Approval
        $stmtPending = $this->db->prepare("
            SELECT COUNT(DISTINCT tr.id) 
            FROM travel_requests tr
            LEFT JOIN employees e ON tr.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            WHERE tr.status = 'Pending Approval' AND $whereScope
        ");
        $stmtPending->execute($params);
        $pendingCount = (int)$stmtPending->fetchColumn();

        // Count Provisional: status Provisional
        $stmtProvisional = $this->db->prepare("
            SELECT COUNT(DISTINCT tr.id) 
            FROM travel_requests tr
            LEFT JOIN employees e ON tr.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            WHERE tr.status = 'Provisional' AND $whereScope
        ");
        $stmtProvisional->execute($params);
        $provisionalCount = (int)$stmtProvisional->fetchColumn();

        // List active travelers
        $stmtActiveList = $this->db->prepare("
            SELECT DISTINCT tr.*, e.first_name, e.last_name, tc.category_name, trr.scope_name
            FROM travel_requests tr
            JOIN employees e ON tr.employee_id = e.id
            JOIN travel_categories tc ON tr.category_id = tc.id
            JOIN travel_routing_rules trr ON tr.routing_rule_id = trr.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            WHERE tr.status = 'Approved' AND CURRENT_DATE BETWEEN tr.start_date AND tr.end_date AND $whereScope
            ORDER BY tr.end_date ASC
            LIMIT 10
        ");
        $stmtActiveList->execute($params);
        $activeTravelers = $stmtActiveList->fetchAll(PDO::FETCH_ASSOC);

        // List upcoming travelers
        $stmtUpcomingList = $this->db->prepare("
            SELECT DISTINCT tr.*, e.first_name, e.last_name, tc.category_name, trr.scope_name
            FROM travel_requests tr
            JOIN employees e ON tr.employee_id = e.id
            JOIN travel_categories tc ON tr.category_id = tc.id
            JOIN travel_routing_rules trr ON tr.routing_rule_id = trr.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            WHERE tr.status = 'Approved' AND tr.start_date > CURRENT_DATE AND $whereScope
            ORDER BY tr.start_date ASC
            LIMIT 10
        ");
        $stmtUpcomingList->execute($params);
        $upcomingTravel = $stmtUpcomingList->fetchAll(PDO::FETCH_ASSOC);

        return [
            'stats' => [
                'currently_on_travel' => $activeCount,
                'upcoming_travel' => $upcomingCount,
                'pending_approval' => $pendingCount,
                'provisional_plans' => $provisionalCount
            ],
            'currently_on_travel_list' => $activeTravelers,
            'upcoming_travel_list' => $upcomingTravel
        ];
    }
}
