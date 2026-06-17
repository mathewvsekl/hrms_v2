<?php

namespace App\Controllers;

class SalaryAdvanceController
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    public function handleRequest($method, $path)
    {
        $pathParts = explode('/', trim($path, '/'));
        // Example: /api/salary-advances, /api/salary-advances/1/status
        
        if ($method === 'GET' && empty($pathParts[1])) {
            return $this->getAdvances();
        }

        if ($method === 'POST' && empty($pathParts[1])) {
            return $this->createAdvance();
        }

        if ($method === 'PUT' && isset($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === 'status') {
            return $this->updateStatus((int)$pathParts[1]);
        }

        if ($method === 'PUT' && isset($pathParts[1]) && !isset($pathParts[2])) {
            return $this->updateAdvance((int)$pathParts[1]);
        }

        if ($method === 'DELETE' && isset($pathParts[1])) {
            return $this->deleteAdvance((int)$pathParts[1]);
        }

        http_response_code(404);
        return ['success' => false, 'message' => 'Endpoint not found'];
    }

    private function getAdvances()
    {
        $companyId = $_GET['company_id'] ?? null;
        $employeeId = $_GET['employee_id'] ?? null;

        $query = "
            SELECT sa.*, e.first_name, e.last_name, e.employee_code 
            FROM salary_advances sa 
            JOIN employees e ON sa.employee_id = e.id 
            JOIN employee_companies ec ON e.id = ec.employee_id
            WHERE ec.is_primary = 1
        ";
        $params = [];

        if ($employeeId) {
            $query .= " AND sa.employee_id = ?";
            $params[] = $employeeId;
        } else if ($companyId) {
            $query .= " AND ec.company_id = ?";
            $params[] = $companyId;
        } else {
            http_response_code(400);
            return ['success' => false, 'message' => 'company_id or employee_id is required'];
        }

        $query .= " ORDER BY sa.created_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $advances = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['success' => true, 'data' => $advances];
    }

    private function createAdvance()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $employeeId = $input['employee_id'] ?? null;
        $amount = $input['amount'] ?? null;
        $currencyCode = $input['currency_code'] ?? 'UGX';
        $dateRequested = $input['date_requested'] ?? date('Y-m-d');
        $reason = $input['reason'] ?? null;
        
        if (!$employeeId || !$amount) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Employee ID and Amount are required'];
        }

        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/advances/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileInfo = pathinfo($_FILES['attachment']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');
            $fileName = 'adv_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fileName)) {
                $attachmentPath = '/public/uploads/advances/' . $fileName;
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO salary_advances (employee_id, amount, currency_code, date_requested, reason, attachment, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$employeeId, $amount, $currencyCode, $dateRequested, $reason, $attachmentPath]);

        return ['success' => true, 'message' => 'Salary advance request submitted successfully'];
    }

    private function updateStatus($id)
    {
        @session_start();
        $input = json_decode(file_get_contents('php://input'), true);
        $status = $input['status'] ?? null;
        $managerComment = $input['manager_comment'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$status || !in_array($status, ['Pending', 'Reviewed', 'Approved', 'Rejected', 'Deducted', 'Cancelled'])) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Valid status is required'];
        }

        if ($managerComment !== null) {
            $stmt = $this->db->prepare("UPDATE salary_advances SET manager_comment = ? WHERE id = ?");
            $stmt->execute([$managerComment, $id]);
        }

        if ($status === 'Reviewed') {
            $stmt = $this->db->prepare("UPDATE salary_advances SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $userId, $id]);
        } else if ($status === 'Approved' || $status === 'Rejected') {
            $stmt = $this->db->prepare("UPDATE salary_advances SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $userId, $id]);
        } else {
            $stmt = $this->db->prepare("UPDATE salary_advances SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }

        return ['success' => true, 'message' => 'Status updated successfully'];
    }

    private function updateAdvance($id)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $amount = $input['amount'] ?? null;
        $reason = $input['reason'] ?? null;
        
        if (!$amount) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Amount is required'];
        }

        $stmtCheck = $this->db->prepare("SELECT status FROM salary_advances WHERE id = ?");
        $stmtCheck->execute([$id]);
        $advance = $stmtCheck->fetch();

        if (!$advance || strtolower($advance['status']) !== 'pending') {
            http_response_code(400);
            return ['success' => false, 'message' => 'Only pending requests can be edited'];
        }

        $stmt = $this->db->prepare("UPDATE salary_advances SET amount = ?, reason = ? WHERE id = ?");
        $stmt->execute([$amount, $reason, $id]);

        return ['success' => true, 'message' => 'Salary advance updated successfully'];
    }

    private function deleteAdvance($id)
    {
        $stmtCheck = $this->db->prepare("SELECT status FROM salary_advances WHERE id = ?");
        $stmtCheck->execute([$id]);
        $advance = $stmtCheck->fetch();

        if (!$advance || strtolower($advance['status']) !== 'pending') {
            http_response_code(400);
            return ['success' => false, 'message' => 'Only pending requests can be deleted'];
        }

        $stmt = $this->db->prepare("DELETE FROM salary_advances WHERE id = ?");
        $stmt->execute([$id]);

        return ['success' => true, 'message' => 'Salary advance deleted successfully'];
    }
}
