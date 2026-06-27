<?php

namespace App\Controllers;

use App\Core\BaseApiController;
use App\Services\TravelService;
use App\Middleware\RoleMiddleware;
use Exception;

/**
 * TravelController
 * 
 * Orchestrates Travel Request REST APIs and coordinates with TravelService.
 */
class TravelController extends BaseApiController
{
    private $travelService;

    public function __construct()
    {
        $this->travelService = new TravelService();
    }

    /**
     * GET /api/travel/categories
     */
    public function getCategories()
    {
        RoleMiddleware::requirePermission('Travel', 'view');
        try {
            $categories = $this->travelService->getCategories();
            file_put_contents(__DIR__ . '/../../tmp/categories_debug.log', "getCategories fetched: " . json_encode($categories) . "\n", FILE_APPEND);
            return $this->apiSuccess($categories, "Travel categories fetched.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/travel/roles
     */
    public function getRoles()
    {
        RoleMiddleware::requirePermission('Travel', 'view');
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $isSuperAdmin = ((int)($_SESSION['role_id'] ?? 6) === \App\Helpers\RoleConstants::SUPER_ADMIN);
            $roles = $this->travelService->getRoles($isSuperAdmin);
            return $this->apiSuccess($roles, "System roles fetched.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/travel/routing-rules
     */
    public function getRoutingRules()
    {
        RoleMiddleware::requirePermission('Travel', 'view');
        try {
            $rules = $this->travelService->getRoutingRules();
            return $this->apiSuccess($rules, "Routing rules fetched.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/travel/categories
     */
    public function createCategory()
    {
        RoleMiddleware::requirePermission('Travel', 'edit');
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['category_name'])) {
                return $this->apiError("Category name is required.", 400);
            }
            $this->travelService->createCategory($data['category_name']);
            return $this->apiSuccess(null, "Category created successfully.");
        } catch (Exception $e) {
            error_log("createCategory ERROR: " . $e->getMessage());
            return $this->apiError($e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), 500);
        }
    }

    /**
     * POST /api/travel/routing-rules
     */
    public function createRoutingRule()
    {
        RoleMiddleware::requirePermission('Travel', 'edit');
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['scope_name']) || empty($data['approver_roles'])) {
                return $this->apiError("Scope name and approvers are required.", 400);
            }
            $requiresPassport = isset($data['requires_passport']) ? (int)$data['requires_passport'] : 0;
            $requiresVisa = isset($data['requires_visa']) ? (int)$data['requires_visa'] : 0;
            $requiresFlight = isset($data['requires_flight']) ? (int)$data['requires_flight'] : 0;
            $description = isset($data['description']) ? $data['description'] : '';
            
            $this->travelService->createRoutingRule(
                $data['scope_name'], 
                $data['approver_roles'],
                $requiresPassport,
                $requiresVisa,
                $requiresFlight,
                $description
            );
            return $this->apiSuccess(null, "Routing rule created successfully.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/travel/categories/{id}
     */
    public function updateCategory($id)
    {
        RoleMiddleware::requirePermission('Travel', 'edit');
        try {
            $data = $this->getJsonPayload();
            if (empty($data['category_name'])) {
                return $this->apiError("Category name is required.", 400);
            }
            $this->travelService->updateCategory((int)$id, $data['category_name']);
            return $this->apiSuccess(null, "Category updated successfully.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/travel/categories/{id}
     */
    public function deleteCategory($id)
    {
        RoleMiddleware::requirePermission('Travel', 'edit');
        try {
            $this->travelService->deleteCategory((int)$id);
            return $this->apiSuccess(null, "Category deleted successfully.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/travel/routing-rules/{id}
     */
    public function updateRoutingRule($id)
    {
        RoleMiddleware::requirePermission('Travel', 'edit');
        try {
            $data = $this->getJsonPayload();
            if (empty($data['scope_name']) || empty($data['approver_roles'])) {
                return $this->apiError("Scope name and approvers are required.", 400);
            }
            $requiresPassport = isset($data['requires_passport']) ? (int)$data['requires_passport'] : 0;
            $requiresVisa = isset($data['requires_visa']) ? (int)$data['requires_visa'] : 0;
            $requiresFlight = isset($data['requires_flight']) ? (int)$data['requires_flight'] : 0;
            $description = isset($data['description']) ? $data['description'] : '';

            $this->travelService->updateRoutingRule(
                (int)$id, 
                $data['scope_name'], 
                $data['approver_roles'],
                $requiresPassport,
                $requiresVisa,
                $requiresFlight,
                $description
            );
            return $this->apiSuccess(null, "Routing rule updated successfully.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/travel/routing-rules/{id}
     */
    public function deleteRoutingRule($id)
    {
        RoleMiddleware::requirePermission('Travel', 'edit');
        try {
            $this->travelService->deleteRoutingRule((int)$id);
            return $this->apiSuccess(null, "Routing rule deleted successfully.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/travel/requests
     */
    public function index()
    {
        RoleMiddleware::requirePermission('Travel', 'view');
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $isGlobalAdmin = in_array((int)($_SESSION['role_id'] ?? 6), \App\Helpers\RoleConstants::GLOBAL_ADMINS);
            $requests = $this->travelService->fetchRequests($_GET, $_SESSION, $isGlobalAdmin);
            return $this->apiSuccess($requests, "Travel requests fetched.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/travel/dashboard
     */
    public function dashboard()
    {
        RoleMiddleware::requirePermission('Travel', 'view');
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $stats = $this->travelService->getDashboardStats($_SESSION);
            return $this->apiSuccess($stats, "Dashboard data fetched.");
        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/travel/requests
     */
    public function store()
    {
        RoleMiddleware::requirePermission('Travel', 'create');
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $data = $this->getJsonPayload();
            
            // Validate basic input
            if (empty($data['employee_id']) || empty($data['start_date']) || empty($data['end_date']) || empty($data['destination']) || empty($data['category_id']) || empty($data['routing_rule_id'])) {
                return $this->apiError("Missing required fields.", 400);
            }

            // Scope Check: Employees can only create for themselves
            $roleId = (int)($_SESSION['role_id'] ?? 6);
            $selfEmployeeId = (int)($_SESSION['scope_employee_id'] ?? 0);
            $targetEmployeeId = (int)$data['employee_id'];

            if ($roleId === 6 && $selfEmployeeId !== $targetEmployeeId) {
                return $this->apiError("Forbidden: Employees can only submit travel requests for themselves.", 403);
            }

            // If attempting to create directly in Approved status, check if they have permission to approve
            $status = $data['status'] ?? 'Draft';
            if (in_array($status, ['Approved', 'Complete'])) {
                if (!RoleMiddleware::hasPermission('Travel', 'approve')) {
                    return $this->apiError("Forbidden: You do not have permissions to approve travel requests. Saving as Draft/Pending instead.", 403);
                }
            }

            $requestId = $this->travelService->createRequest($data, (int)$_SESSION['user_id']);
            
            // Return warnings if Provisional overlaps exist
            $warnings = [];
            if ($status === 'Provisional') {
                $conflicts = $this->travelService->checkConflicts($targetEmployeeId, $data['start_date'], $data['end_date'], $requestId);
                foreach ($conflicts as $c) {
                    $warnings[] = $c['description'];
                }
            }

            return $this->apiSuccess([
                'travel_request_id' => $requestId,
                'warnings' => $warnings
            ], "Travel request created successfully.", 210);

        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/travel/requests/{id}
     */
    public function update($requestId)
    {
        RoleMiddleware::requirePermission('Travel', 'edit');
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $data = $this->getJsonPayload();
            $requestId = (int)$requestId;

            // Scope check: Employees can only edit their own
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT employee_id, status FROM travel_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();

            if (!$request) {
                return $this->apiError("Travel request not found.", 404);
            }

            $roleId = (int)($_SESSION['role_id'] ?? 6);
            $selfEmployeeId = (int)($_SESSION['scope_employee_id'] ?? 0);
            $ownerEmployeeId = (int)$request['employee_id'];

            if ($roleId === 6 && $selfEmployeeId !== $ownerEmployeeId) {
                return $this->apiError("Forbidden: You can only edit your own travel requests.", 403);
            }

            // Employees cannot approve or transition to complete
            $newStatus = $data['status'] ?? $request['status'];
            if (in_array($newStatus, ['Approved', 'Complete']) && $request['status'] !== $newStatus) {
                if (!RoleMiddleware::hasPermission('Travel', 'approve')) {
                    return $this->apiError("Forbidden: You do not have permissions to approve travel requests.", 403);
                }
            }

            $this->travelService->updateRequest($requestId, $data, (int)$_SESSION['user_id']);

            $warnings = [];
            if ($newStatus === 'Provisional') {
                $targetEmployeeId = (int)($data['employee_id'] ?? $request['employee_id']);
                $startDate = $data['start_date'] ?? null;
                $endDate = $data['end_date'] ?? null;
                if ($startDate && $endDate) {
                    $conflicts = $this->travelService->checkConflicts($targetEmployeeId, $startDate, $endDate, $requestId);
                    foreach ($conflicts as $c) {
                        $warnings[] = $c['description'];
                    }
                }
            }

            return $this->apiSuccess([
                'warnings' => $warnings
            ], "Travel request updated successfully.");

        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/travel/requests/{id}/mid-trip-cancel
     */
    public function midTripCancel($requestId)
    {
        RoleMiddleware::requirePermission('Travel', 'edit');
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $data = $this->getJsonPayload();
            $requestId = (int)$requestId;
            $cancellationDate = $data['cancellation_date'] ?? date('Y-m-d');

            // Fetch owner for scope check
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT employee_id FROM travel_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $ownerEmployeeId = (int)$stmt->fetchColumn();

            if (!$ownerEmployeeId) {
                return $this->apiError("Travel request not found.", 404);
            }

            $roleId = (int)($_SESSION['role_id'] ?? 6);
            $selfEmployeeId = (int)($_SESSION['scope_employee_id'] ?? 0);

            if ($roleId === 6 && $selfEmployeeId !== $ownerEmployeeId) {
                return $this->apiError("Forbidden: You can only cancel your own travel requests.", 403);
            }

            $this->travelService->midTripCancel($requestId, $cancellationDate, (int)$_SESSION['user_id']);
            return $this->apiSuccess(null, "Travel request cancelled mid-trip successfully.");

        } catch (Exception $e) {
            return $this->apiError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/travel/check-conflicts
     */
    public function checkConflicts()
    {
        try {
            $data = $this->getJsonPayload();
            if (empty($data['employee_id']) || empty($data['start_date']) || empty($data['end_date'])) {
                return $this->apiError("Missing required fields.", 400);
            }

            $employeeId = (int)$data['employee_id'];
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];
            $excludeRequestId = !empty($data['exclude_request_id']) ? (int)$data['exclude_request_id'] : null;

            $conflicts = $this->travelService->checkConflicts($employeeId, $startDate, $endDate, $excludeRequestId);
            $workaround = [];
            if (!empty($conflicts)) {
                $workaround = $this->travelService->calculateWorkaround($employeeId, $startDate, $endDate, $excludeRequestId);
            }

            return $this->apiSuccess([
                'conflicts' => $conflicts,
                'workaround' => $workaround
            ], "Conflict pre-check completed.");

        } catch (Exception $e) {
            error_log("checkConflicts API ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return $this->apiError($e->getMessage(), 500);
        }
    }
}
