<?php
/**
 * CompanyDocumentController.php
 * 
 * Handles reference documents (Policies, Laws, Manuals) for the company.
 * Scoping ensures employees only see relevant documents for their office/country.
 */

namespace App\Controllers;

use App\Core\Controller;

class CompanyDocumentController extends Controller
{
    /**
     * Get documents visible to the current employee based on their scope (Office/Country)
     */
    public function getDocuments()
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $employeeId = $_GET['employee_id'] ?? null;
            $companyId = $_SESSION['scope_company_id'] ?? null;
            $countryId = $_SESSION['scope_country_id'] ?? null;

            $db = \Database::getInstance()->getConnection();

            // If an employee_id is provided (e.g. from Admin viewing a profile), 
            // we use that employee's specific scope instead of the current user's session scope.
            if ($employeeId && is_numeric($employeeId)) {
                // Reset scope first to ensure we don't leak the requester's scope
                $companyId = null;
                $countryId = null;

                $stmt = $db->prepare("
                    SELECT ec.company_id, c.country_id 
                    FROM employee_companies ec
                    JOIN companies c ON ec.company_id = c.id
                    WHERE ec.employee_id = :id AND ec.is_primary = 1 AND ec.is_active = 1
                    LIMIT 1
                ");
                $stmt->execute(['id' => $employeeId]);
                $scope = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($scope) {
                    $companyId = $scope['company_id'];
                    $countryId = $scope['country_id'];
                }
            }
            
            // Scoping logic: 
            // 1. Global documents (company_id IS NULL AND country_id IS NULL)
            // 2. Documents for specific company
            // 3. Documents for specific country
            $sql = "
                SELECT d.*, c.name as company_name, cn.name as country_name 
                FROM company_documents d
                LEFT JOIN companies c ON d.company_id = c.id
                LEFT JOIN countries cn ON d.country_id = cn.id
                WHERE (d.company_id IS NULL AND d.country_id IS NULL)
                   OR (d.company_id = :company_id)
                   OR (d.country_id = :country_id)
                ORDER BY d.created_at_utc DESC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'company_id' => $companyId,
                'country_id' => $countryId
            ]);
            $documents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($documents);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Admin method to view all documents for management
     */
    public function getAllDocuments()
    {
        $this->verifyDataScope();
        
        // Ensure user has at least some admin-level role
        if (!$this->isGlobalAdmin() && !$this->hasAnyRole(['HRMANAGER', 'HR_MANAGER', 'HRASSISTANT', 'HR_ASSISTANT', 'COUNTRYMANAGER', 'COUNTRY_MANAGER'])) {
            return $this->jsonResponse(null, 403, "Insufficient permissions to view the full document registry.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("
                SELECT d.*, c.name as company_name, cn.name as country_name 
                FROM company_documents d
                LEFT JOIN companies c ON d.company_id = c.id
                LEFT JOIN countries cn ON d.country_id = cn.id
                ORDER BY d.created_at_utc DESC
            ");
            $documents = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->jsonResponse($documents);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Upload a new reference document
     */
    public function uploadDocument()
    {
        $this->verifyDataScope();

        // Permission check
        if (!$this->isGlobalAdmin() && !$this->hasAnyRole(['HRMANAGER', 'HR_MANAGER', 'HRASSISTANT', 'HR_ASSISTANT'])) {
             return $this->jsonResponse(null, 403, "Insufficient permissions to upload reference documents.");
        }

        if (!isset($_FILES['document'])) {
            return $this->jsonResponse(null, 400, "No document file provided.");
        }

        $documentName = $_POST['document_name'] ?? $_FILES['document']['name'];
        $category = $_POST['category'] ?? 'Policy';
        $companyId = !empty($_POST['company_id']) ? $_POST['company_id'] : null;
        $countryId = !empty($_POST['country_id']) ? $_POST['country_id'] : null;

        // Use UploadHelper with 'reference_docs' context
        $uploadResult = \App\Helpers\UploadHelper::upload($_FILES['document'], 'reference_docs');

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
                INSERT INTO company_documents (document_name, category, file_path, company_id, country_id, uploaded_by_id)
                VALUES (:document_name, :category, :file_path, :company_id, :country_id, :uploaded_by_id)
            ");

            $stmt->execute([
                'document_name' => $documentName,
                'category' => $category,
                'file_path' => $dbFilePath,
                'company_id' => $companyId,
                'country_id' => $countryId,
                'uploaded_by_id' => $uploadedById
            ]);

            return $this->jsonResponse([
                'message' => 'Reference document uploaded successfully.', 
                'document_id' => $db->lastInsertId(),
                'file_path' => $dbFilePath
            ]);
        } catch (\Exception $e) {
            \App\Helpers\UploadHelper::delete($dbFilePath);
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Update an existing reference document
     */
    public function updateDocument($id)
    {
        $this->verifyDataScope();

        if (!$this->isGlobalAdmin() && !$this->hasAnyRole(['HRMANAGER', 'HR_MANAGER'])) {
             return $this->jsonResponse(null, 403, "Insufficient permissions to edit reference documents.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            
            // Check if document exists
            $stmt = $db->prepare("SELECT * FROM company_documents WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$existing) {
                return $this->jsonResponse(null, 404, "Document not found.");
            }

            // Support both JSON (PUT) and multipart/form-data (POST with _method=PUT or similar if needed, 
            // but for simplicity we'll handle standard PUT with JSON and POST for file updates if needed).
            // Actually, standard PHP doesn't parse multipart/form-data on PUT. 
            // We'll support metadata update via PUT (JSON) and if a new file is provided, we might need a POST.
            // But let's see how the frontend handles it. 
            // Most often, metadata update is enough, or they re-upload.
            
            $json = json_decode(file_get_contents('php://input'), true);
            $documentName = $json['document_name'] ?? $existing['document_name'];
            $category = $json['category'] ?? $existing['category'];
            $companyId = isset($json['company_id']) ? (!empty($json['company_id']) ? $json['company_id'] : null) : $existing['company_id'];
            $countryId = isset($json['country_id']) ? (!empty($json['country_id']) ? $json['country_id'] : null) : $existing['country_id'];

            $updateStmt = $db->prepare("
                UPDATE company_documents 
                SET document_name = :document_name, 
                    category = :category, 
                    company_id = :company_id, 
                    country_id = :country_id
                WHERE id = :id
            ");

            $updateStmt->execute([
                'document_name' => $documentName,
                'category' => $category,
                'company_id' => $companyId,
                'country_id' => $countryId,
                'id' => $id
            ]);

            return $this->jsonResponse(['message' => 'Document updated successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Delete a reference document
     */
    public function deleteDocument($id)
    {
        $this->verifyDataScope();

        if (!$this->isGlobalAdmin() && !$this->hasAnyRole(['HRMANAGER', 'HR_MANAGER'])) {
             return $this->jsonResponse(null, 403, "Insufficient permissions to delete reference documents.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT file_path FROM company_documents WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $doc = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($doc) {
                \App\Helpers\UploadHelper::delete($doc['file_path']);

                $delStmt = $db->prepare("DELETE FROM company_documents WHERE id = :id");
                $delStmt->execute(['id' => $id]);

                return $this->jsonResponse(['message' => 'Document deleted successfully.']);
            }
            return $this->jsonResponse(null, 404, "Document not found.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }
}
