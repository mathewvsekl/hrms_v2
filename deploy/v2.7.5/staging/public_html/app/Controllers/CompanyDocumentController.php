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

            $myCompanyId = $_SESSION['scope_company_id'] ?? null;
            $myCountryId = $_SESSION['scope_country_id'] ?? null;

            $db = \Database::getInstance()->getConnection();
            
            // Scoping logic: 
            // 1. Global documents (company_id IS NULL AND country_id IS NULL)
            // 2. Documents for my specific company
            // 3. Documents for my specific country
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
                'company_id' => $myCompanyId,
                'country_id' => $myCountryId
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
