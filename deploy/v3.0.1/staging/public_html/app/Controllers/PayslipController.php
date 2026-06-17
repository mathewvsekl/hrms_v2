<?php
namespace App\Controllers;

use App\Core\Controller;

class PayslipController extends Controller
{
    public function getEmployeePayslips($employeeId = null)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            if ($employeeId) {
                $stmt = $db->prepare("
                    SELECT p.*, e.first_name, e.last_name, e.employee_code 
                    FROM payslips p 
                    JOIN employees e ON p.employee_id = e.id 
                    WHERE p.employee_id = ? 
                    ORDER BY p.year DESC, p.month DESC
                ");
                $stmt->execute([$employeeId]);
            } else {
                $stmt = $db->prepare("
                    SELECT p.*, e.first_name, e.last_name, e.employee_code 
                    FROM payslips p 
                    JOIN employees e ON p.employee_id = e.id 
                    ORDER BY p.created_at DESC
                ");
                $stmt->execute();
            }
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
        $file = $_FILES['document'];

        // File upload logic
        $uploadDir = STORAGE_PATH . '/documents/payslips/';
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
                $stmt = $db->prepare("SELECT id FROM payslips WHERE employee_id = ? AND month = ? AND year = ?");
                $stmt->execute([$employeeId, $month, $year]);
                if ($stmt->fetch()) {
                    // Do not allow duplicate upload
                    return $this->jsonResponse(null, 400, "A payslip for this month and year already exists for this employee.");
                } else {
                    $stmt = $db->prepare("INSERT INTO payslips (employee_id, month, year, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$employeeId, $month, $year, $filePath, $_SESSION['user_id'] ?? null]);
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
        $filePath = STORAGE_PATH . '/documents/payslips/' . $fileName;

        if (!file_exists($filePath)) {
            return $this->jsonResponse(null, 404, "File not found.");
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
