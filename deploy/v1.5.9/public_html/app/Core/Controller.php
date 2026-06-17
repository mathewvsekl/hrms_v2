<?php

namespace App\Core;

/**
 * Base Controller
 * Houses generic functions for API outputs and parameter checks
 */
class Controller
{
    /**
     * Terminate the executing request and return a structured JSON response
     * 
     * @param array|string $data The main payload
     * @param int $httpStatus HTTP Response code (e.g., 200, 400, 403)
     * @param string $message Developer or User facing contextual message
     */
    protected function jsonResponse($data, $httpStatus = 200, $message = '')
    {
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code($httpStatus);

        $response = [
            'status' => ($httpStatus >= 200 && $httpStatus < 300) ? 'success' : 'error',
            'code' => $httpStatus,
            'message' => $message,
            'data' => $data
        ];

        echo json_encode($response);
        exit(); // Halt all further script execution for safety
    }

    /**
     * Parse raw incoming JSON payloads strictly to associative arrays
     */
    protected function getJsonPayload()
    {
        $payload = file_get_contents('php://input');
        if (empty($payload)) {
            return [];
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(null, 400, "Invalid JSON payload structure.");
        }

        return $decoded;
    }

    /**
     * Enforce geographic Context Scope boundaries
     * 
     * @param int|null $targetOfficeId The Office ID of the payload or target record
     * @param int|null $targetCountryId The Country ID of the payload or target record
     * @param int|null $targetEmployeeId The Employee ID being mutated
     */
    protected function verifyDataScope($targetOfficeId = null, $targetCountryId = null, $targetEmployeeId = null)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->jsonResponse(null, 401, "No active session context for tracking.");
        }

        try {
            // First, find if the user is a SUPER_ADMIN. If so, they have no boundaries.
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = ?");
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (in_array('SUPER_ADMIN', $roles)) {
                return true;
            }

            // Retrieve the active bounded context from the session
            $myCompanyId = $_SESSION['scope_company_id'] ?? null;
            $myCountryId = $_SESSION['scope_country_id'] ?? null;
            $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;

            // Country Managers are bound strictly to their Country
            if (in_array('COUNTRY_MANAGER', $roles)) {
                if ($targetCountryId !== null && $targetCountryId != $myCountryId) {
                    $this->jsonResponse(null, 403, "Context Scope Violation: Target Country ID out of bounds.");
                }

                // If they provided a Company ID but no Country ID, verify the Company belongs to their Country
                if ($targetOfficeId !== null && $targetCountryId === null) {
                    $cacheStmt = $db->prepare("SELECT country_id FROM companies WHERE id = ?");
                    $cacheStmt->execute([$targetOfficeId]);
                    $record = $cacheStmt->fetchColumn();
                    if ($record && $record != $myCountryId) {
                        $this->jsonResponse(null, 403, "Context Scope Violation: Target Company belongs to an out-of-bounds Country.");
                    }
                }
                return true;
            }

            // HR Managers / Assistants are bound strictly to their Company
            if (in_array('HR_MANAGER', $roles) || in_array('HR_ASSISTANT', $roles)) {
                if ($targetOfficeId !== null && $targetOfficeId != $myCompanyId) {
                    $this->jsonResponse(null, 403, "Context Scope Violation: Target Company ID out of bounds.");
                }
                return true;
            }

            // Normal Employees are bound strictly to themselves
            if (in_array('EMPLOYEE', $roles)) {
                if ($targetEmployeeId !== null && $targetEmployeeId != $myEmployeeId) {
                    $this->jsonResponse(null, 403, "Context Scope Violation: You can only act on your own data.");
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->jsonResponse(null, 500, "Scope Enforcer Fault: " . $e->getMessage());
        }
    }
}
