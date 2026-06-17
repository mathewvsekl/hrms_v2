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
        try {
            $success = $this->docService->deleteDocument((int)$id);
            if (!$success) return $this->jsonResponse(null, 404, "Not found.");
            return $this->jsonResponse(['message' => 'Deleted.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }
}
