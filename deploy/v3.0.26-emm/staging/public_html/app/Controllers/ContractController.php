<?php

namespace App\Controllers;

use App\Core\Controller;

class ContractController extends Controller
{
    public function getEmployeeContracts($employeeId)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM employee_contracts WHERE employee_id = :employee_id ORDER BY start_date DESC");
            $stmt->execute(['employee_id' => $employeeId]);
            $contracts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($contracts);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    public function createContract($requestData)
    {
        $employeeId = $requestData['employee_id'] ?? null;
        $contractType = $requestData['contract_type'] ?? 'permanent';
        $startDate = $requestData['start_date'] ?? null;

        if (!$employeeId || !$startDate) {
            return $this->jsonResponse(null, 400, "Employee ID and Start Date are required.");
        }

        $endDate = $requestData['end_date'] ?? null;
        $probationEndDate = $requestData['probation_end_date'] ?? null;
        $notes = $requestData['notes'] ?? null;

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO employee_contracts (employee_id, contract_type, start_date, end_date, probation_end_date, notes)
                VALUES (:employee_id, :contract_type, :start_date, :end_date, :probation_end_date, :notes)
            ");

            $stmt->execute([
                'employee_id' => $employeeId,
                'contract_type' => $contractType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'probation_end_date' => $probationEndDate,
                'notes' => $notes
            ]);

            return $this->jsonResponse(['message' => 'Contract created successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    public function deleteContract($id)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM employee_contracts WHERE id = :id");
            $stmt->execute(['id' => $id]);

            return $this->jsonResponse(['message' => 'Contract deleted successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }
}
