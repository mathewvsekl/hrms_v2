<?php

namespace App\Services;

use App\Helpers\UploadHelper;
use PDO;

/**
 * DocumentService
 * 
 * Handles employee document storage, metadata, and lifecycle management.
 */
class DocumentService
{
    private $db;
    private $auditService;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->auditService = new AuditService();
    }

    /**
     * Lists documents associated with an employee
     */
    public function getEmployeeDocuments(int $employeeId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM employee_documents WHERE employee_id = :employee_id ORDER BY created_at_utc DESC");
        $stmt->execute(['employee_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Stores a new document record and its associated file
     */
    public function uploadDocument(int $employeeId, array $file, array $metadata, int $uploadedBy): int
    {
        $uploadResult = UploadHelper::upload($file, 'documents');
        if (!$uploadResult['success']) {
            throw new \Exception($uploadResult['message']);
        }

        $filePath = $uploadResult['file_path'];

        try {
            $stmt = $this->db->prepare("
                INSERT INTO employee_documents (employee_id, document_name, file_path, document_type, expiry_date, uploaded_by_id)
                VALUES (:employee_id, :document_name, :file_path, :document_type, :expiry_date, :uploaded_by_id)
            ");

            $stmt->execute([
                'employee_id' => $employeeId,
                'document_name' => $metadata['document_name'] ?? $file['name'],
                'file_path' => $filePath,
                'document_type' => $metadata['document_type'] ?? 'other',
                'expiry_date' => !empty($metadata['expiry_date']) ? $metadata['expiry_date'] : null,
                'uploaded_by_id' => $uploadedBy
            ]);

            $newId = (int)$this->db->lastInsertId();
            $this->auditService->log('UPLOAD', 'employee_documents', $newId, null, ['employee_id' => $employeeId, 'file' => $file['name']]);
            
            return $newId;
        } catch (\Exception $e) {
            UploadHelper::delete($filePath);
            throw $e;
        }
    }

    /**
     * Updates document metadata
     */
    public function updateDocument(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE employee_documents 
            SET document_name = :document_name, expiry_date = :expiry_date 
            WHERE id = :id
        ");
        $success = $stmt->execute([
            'document_name' => $data['document_name'],
            'expiry_date' => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
            'id' => $id
        ]);
        if ($success) {
            $this->auditService->log('UPDATE', 'employee_documents', $id, null, $data);
        }
        return $success;
    }

    /**
     * Deletes a document record and its physical file
     */
    public function deleteDocument(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT file_path, employee_id, document_name FROM employee_documents WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doc) return false;

        // Clean up physical file
        $absPath = str_replace('/HRMS V2/', BASE_PATH . '/', $doc['file_path']);
        if (file_exists($absPath)) {
            unlink($absPath);
        }

        $delStmt = $this->db->prepare("DELETE FROM employee_documents WHERE id = :id");
        $success = $delStmt->execute(['id' => $id]);
        if ($success) {
            $this->auditService->log('DELETE', 'employee_documents', $id, null, ['employee_id' => $doc['employee_id'], 'name' => $doc['document_name']]);
        }
        return $success;
    }
}
