<?php
namespace App\Controllers;

use App\Core\Controller;

class PayslipController extends Controller
{
    public function getEmployeePayslips($employeeId = null)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $isGlobalAdmin = $this->isGlobalAdmin();
            $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];
            $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;
            
            $whereClause = "";
            $params = [];
            
            if ($employeeId) {
                $whereClause .= " WHERE p.employee_id = ?";
                $params[] = $employeeId;
            }

            // If not global admin, filter by allowed companies unless they are viewing their own payslip
            if (!$isGlobalAdmin && ($employeeId === null || $employeeId != $myEmployeeId)) {
                if (empty($myCompanyIds)) {
                    return $this->jsonResponse([]);
                }
                $placeholders = implode(',', array_fill(0, count($myCompanyIds), '?'));
                // Filter by payslip company, or fallback to employee's primary company if NULL
                $rbacClause = "(p.company_id IN ($placeholders) OR (p.company_id IS NULL AND EXISTS (SELECT 1 FROM employee_company ec WHERE ec.employee_id = p.employee_id AND ec.is_primary = 1 AND ec.company_id IN ($placeholders))))";
                
                if ($whereClause === "") {
                    $whereClause = " WHERE " . $rbacClause;
                } else {
                    $whereClause .= " AND " . $rbacClause;
                }
                
                $params = array_merge($params, $myCompanyIds, $myCompanyIds);
            }

            $stmt = $db->prepare("
                SELECT p.*, e.first_name, e.last_name, e.employee_code, c.name as company_name 
                FROM payslips p 
                JOIN employees e ON p.employee_id = e.id 
                LEFT JOIN companies c ON p.company_id = c.id
                $whereClause
                ORDER BY p.year DESC, p.month DESC
            ");
            $stmt->execute($params);
            $payslips = $stmt->fetchAll();
            return $this->jsonResponse($payslips);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    public function uploadPayslip()
    {
        if (!isset($_FILES['document']) || !isset($_POST['employee_id']) || !isset($_POST['month']) || !isset($_POST['year'])) {
            return $this->jsonResponse(null, 400, "Missing required fields.");
        }

        $employeeId = $_POST['employee_id'];
        $month = $_POST['month'];
        $year = $_POST['year'];
        $companyId = $_POST['company_id'] ?? null;
        $file = $_FILES['document'];

        // File upload logic
        $uploadDir = (defined('PUBLIC_DIR_PATH') ? PUBLIC_DIR_PATH : ROOT_PATH . '/public') . '/uploads/payslips/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = 'payslip_' . $employeeId . '_' . $year . '_' . $month . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $filePath = '/api/payslips/download?file=' . $fileName;

            try {
                $db = \Database::getInstance()->getConnection();
                
                // Check if exists
                if ($companyId) {
                    $stmt = $db->prepare("SELECT id, file_path FROM payslips WHERE employee_id = ? AND month = ? AND year = ? AND company_id = ?");
                    $stmt->execute([$employeeId, $month, $year, $companyId]);
                } else {
                    $stmt = $db->prepare("SELECT id, file_path FROM payslips WHERE employee_id = ? AND month = ? AND year = ?");
                    $stmt->execute([$employeeId, $month, $year]);
                }
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    // Update existing record and delete old file if it's different
                    $oldFile = basename($existing['file_path']);
                    if ($oldFile !== $fileName) {
                        $oldPath = $uploadDir . $oldFile;
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                    $stmt = $db->prepare("UPDATE payslips SET file_path = ?, uploaded_by = ? WHERE id = ?");
                    $stmt->execute([$filePath, $_SESSION['user_id'] ?? null, $existing['id']]);
                } else {
                    if ($companyId) {
                        $stmt = $db->prepare("INSERT INTO payslips (employee_id, company_id, month, year, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$employeeId, $companyId, $month, $year, $filePath, $_SESSION['user_id'] ?? null]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO payslips (employee_id, month, year, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$employeeId, $month, $year, $filePath, $_SESSION['user_id'] ?? null]);
                    }
                }

                return $this->jsonResponse(['message' => 'Payslip uploaded successfully.']);
            } catch (\Exception $e) {
                return $this->jsonResponse(null, 500, "DB Error: " . $e->getMessage());
            }
        }

        return $this->jsonResponse(null, 500, "Failed to upload file.");
    }
    
    public function deletePayslip($id)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM payslips WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(['message' => 'Deleted.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }
    public function downloadPayslip()
    {
        if (!isset($_GET['file'])) {
            return $this->jsonResponse(null, 400, "Missing file parameter.");
        }
        $fileName = basename($_GET['file']);
        $uploadDir = (defined('PUBLIC_DIR_PATH') ? PUBLIC_DIR_PATH : ROOT_PATH . '/public') . '/uploads/payslips/';
        $filePath = $uploadDir . $fileName;

        // Fallback for payslips uploaded during the path change
        if (!file_exists($filePath)) {
            $legacyPaths = [
                STORAGE_PATH . '/documents/payslips/' . $fileName,
                ROOT_PATH . '/private/storage/documents/payslips/' . $fileName,
                ROOT_PATH . '/storage/documents/payslips/' . $fileName,
                dirname(ROOT_PATH) . '/private/storage/documents/payslips/' . $fileName,
            ];
            
            $found = false;
            foreach ($legacyPaths as $legacyPath) {
                if (file_exists($legacyPath)) {
                    $filePath = $legacyPath;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return $this->jsonResponse(null, 404, "File not found.");
            }
        }

        // Output the file
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($filePath) ?: 'application/octet-stream';
        } else {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            switch ($ext) {
                case 'pdf': $mime = 'application/pdf'; break;
                case 'jpg':
                case 'jpeg': $mime = 'image/jpeg'; break;
                case 'png': $mime = 'image/png'; break;
                case 'gif': $mime = 'image/gif'; break;
            }
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        
        // Disable execution for safety
        if (ob_get_level() > 0) ob_end_clean();
        
        readfile($filePath);
        exit();
    }
}
