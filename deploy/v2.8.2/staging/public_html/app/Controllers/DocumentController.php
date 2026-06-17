<?php

namespace App\Controllers;

use App\Core\Controller;

class DocumentController extends Controller
{
    public function getEmployeeDocuments($employeeId)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM employee_documents WHERE employee_id = :employee_id ORDER BY created_at_utc DESC");
            $stmt->execute(['employee_id' => $employeeId]);
            $documents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($documents);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    public function uploadDocument($employeeId)
    {
        if (!$employeeId) {
            return $this->jsonResponse(null, 400, "Employee ID is required.");
        }

        if (!isset($_FILES['document'])) {
            return $this->jsonResponse(null, 400, "No document file provided.");
        }

        $documentType = $_POST['document_type'] ?? 'other';
        $documentName = $_POST['document_name'] ?? $_FILES['document']['name'];
        $expiryDate = $_POST['expiry_date'] ?? null;
        if (empty($expiryDate)) $expiryDate = null;

        // Use UploadHelper for consistent validation and handling
        $uploadResult = \App\Helpers\UploadHelper::upload($_FILES['document'], 'documents');

        if (!$uploadResult['success']) {
            return $this->jsonResponse(null, 400, $uploadResult['message']);
        }

        $dbFilePath = $uploadResult['file_path'];

        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $uploadedById = $_SESSION['user_id'] ?? null;

            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO employee_documents (employee_id, document_name, file_path, document_type, expiry_date, uploaded_by_id)
                VALUES (:employee_id, :document_name, :file_path, :document_type, :expiry_date, :uploaded_by_id)
            ");

            $stmt->execute([
                'employee_id' => $employeeId,
                'document_name' => $documentName,
                'file_path' => $dbFilePath,
                'document_type' => $documentType,
                'expiry_date' => $expiryDate,
                'uploaded_by_id' => $uploadedById
            ]);

            return $this->jsonResponse([
                'message' => 'Document uploaded successfully.', 
                'file_path' => $dbFilePath,
                'document_id' => $db->lastInsertId()
            ]);
        } catch (\Exception $e) {
            // Clean up the newly uploaded file if DB update fails
            \App\Helpers\UploadHelper::delete($dbFilePath);
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    public function deleteDocument($id)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT file_path FROM employee_documents WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $doc = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($doc) {
                // Determine absolute path
                $absPath = str_replace('/HRMS V2/', BASE_PATH . '/', $doc['file_path']);
                if (file_exists($absPath)) {
                    unlink($absPath);
                }

                $delStmt = $db->prepare("DELETE FROM employee_documents WHERE id = :id");
                $delStmt->execute(['id' => $id]);

                return $this->jsonResponse(['message' => 'Document deleted successfully.']);
            }
            return $this->jsonResponse(null, 404, "Document not found.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }
    public function updateDocument($id)
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $documentName = $input['document_name'] ?? null;
            $expiryDate = $input['expiry_date'] ?? null;
            
            if (empty($documentName)) {
                return $this->jsonResponse(null, 400, "Document name is required.");
            }

            if (empty($expiryDate)) $expiryDate = null;

            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                UPDATE employee_documents 
                SET document_name = :document_name, expiry_date = :expiry_date 
                WHERE id = :id
            ");

            $stmt->execute([
                'document_name' => $documentName,
                'expiry_date' => $expiryDate,
                'id' => $id
            ]);

            return $this->jsonResponse(['message' => 'Document updated successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }
}
