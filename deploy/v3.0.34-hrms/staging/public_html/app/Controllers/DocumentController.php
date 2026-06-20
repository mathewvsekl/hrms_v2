<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\DocumentService;

/**
 * DocumentController
 * 
 * Secure management of employee files and regulatory documentation.
 */
class DocumentController extends Controller
{
    private $docService;

    public function __construct()
    {
        $this->docService = new DocumentService();
    }

    /** GET /api/documents/employee/{employeeId} */
    public function getEmployeeDocuments($employeeId)
    {
        $this->verifyDataScope(null, null, $employeeId);
        
        $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;
        if ($employeeId != $myEmployeeId && !\App\Middleware\RoleMiddleware::hasPermission('Documents', 'view')) {
            return $this->jsonResponse(null, 403, "Insufficient permissions to view employee documents.");
        }
        try {
            $docs = $this->docService->getEmployeeDocuments((int)$employeeId);
            return $this->jsonResponse($docs);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service error: " . $e->getMessage());
        }
    }

    /** POST /api/documents/employee/{employeeId} */
    public function uploadDocument($employeeId)
    {
        $this->verifyDataScope(null, null, $employeeId);
        
        $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;
        if ($employeeId != $myEmployeeId && !\App\Middleware\RoleMiddleware::hasPermission('Documents', 'edit')) {
            return $this->jsonResponse(null, 403, "Insufficient permissions to upload employee documents.");
        }
        
        if (!isset($_FILES['document'])) {
            return $this->jsonResponse(null, 400, "No file provided.");
        }

        try {
            $uploadedById = $_SESSION['user_id'] ?? null;
            $docId = $this->docService->uploadDocument(
                (int)$employeeId,
                $_FILES['document'],
                $_POST,
                (int)$uploadedById
            );

            return $this->jsonResponse(['message' => 'Document uploaded.', 'id' => $docId]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 400, $e->getMessage());
        }
    }

    /** PUT /api/documents/{id} */
    public function updateDocument($id)
    {
        if (!\App\Middleware\RoleMiddleware::hasPermission('Documents', 'edit')) {
            // Note: If employees can edit their own, we'd need to fetch the doc's employee_id first.
            // But usually employees don't edit existing docs.
            return $this->jsonResponse(null, 403, "Insufficient permissions to edit documents.");
        }
        $input = $this->getJsonPayload();
        if (empty($input['document_name'])) {
            return $this->jsonResponse(null, 400, "Name required.");
        }

        try {
            $this->docService->updateDocument((int)$id, $input);
            return $this->jsonResponse(['message' => 'Updated.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /** DELETE /api/documents/{id} */
    public function deleteDocument($id)
    {
        if (!\App\Middleware\RoleMiddleware::hasPermission('Documents', 'delete')) {
            return $this->jsonResponse(null, 403, "Insufficient permissions to delete documents.");
        }
        try {
            $success = $this->docService->deleteDocument((int)$id);
            if (!$success) return $this->jsonResponse(null, 404, "Not found.");
            return $this->jsonResponse(['message' => 'Deleted.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }
}
