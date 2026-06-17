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
        if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            return $this->jsonResponse(null, 400, "No file uploaded or upload error.");
        }

        if (!$employeeId) {
            return $this->jsonResponse(null, 400, "Employee ID is required.");
        }

        $documentType = $_POST['document_type'] ?? 'other';
        $documentName = $_POST['document_name'] ?? $_FILES['document']['name'];

        $uploadDir = BASE_PATH . '/public/uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid('doc_') . '_' . basename($_FILES['document']['name']);
        $filePath = $uploadDir . $fileName;
        $dbFilePath = '/HRMS V2/public/uploads/documents/' . $fileName; // For UI serving

        if (move_uploaded_file($_FILES['document']['tmp_name'], $filePath)) {
            try {
                $db = \Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    INSERT INTO employee_documents (employee_id, document_name, file_path, document_type)
                    VALUES (:employee_id, :document_name, :file_path, :document_type)
                ");

                $stmt->execute([
                    'employee_id' => $employeeId,
                    'document_name' => $documentName,
                    'file_path' => $dbFilePath,
                    'document_type' => $documentType
                ]);

                return $this->jsonResponse(['message' => 'Document uploaded successfully.', 'file_path' => $dbFilePath]);
            } catch (\Exception $e) {
                unlink($filePath); // Clean up if DB fails
                return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
            }
        }

        return $this->jsonResponse(null, 500, "Failed to move uploaded file.");
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
}
