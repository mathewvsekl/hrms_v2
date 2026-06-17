<?php

namespace App\Services;

use PDO;

/**
 * AssetService
 * 
 * Manages company assets, inventory, and lifecycle of allocations to employees.
 */
class AssetService
{
    private $db;
    private $auditService;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->auditService = new AuditService();
    }

    /**
     * Lists assets with current allocation status and assigned employee details
     */
    public function listAssets(?int $companyId = null, ?int $countryId = null): array
    {
        $query = "SELECT a.*, e.first_name, e.last_name, aa.id as allocation_id 
                  FROM assets a 
                  JOIN companies c ON a.company_id = c.id
                  LEFT JOIN asset_allocations aa ON a.id = aa.asset_id AND aa.status = 'active'
                  LEFT JOIN employees e ON aa.employee_id = e.id";
        
        $where = [];
        $params = [];
        
        if ($companyId) {
            $where[] = "a.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        if ($countryId) {
            $where[] = "c.country_id = :country_id";
            $params['country_id'] = $countryId;
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Records a new asset in the inventory
     */
    public function createAsset(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO assets (company_id, name, category, serial_number, model_number, purchase_date, purchase_cost, currency_code, status, remarks)
            VALUES (:company_id, :name, :category, :serial_number, :model_number, :purchase_date, :purchase_cost, :currency_code, :status, :remarks)
        ");
        
        $stmt->execute([
            'company_id' => $data['company_id'],
            'name' => $data['name'],
            'category' => $data['category'] ?? 'other',
            'serial_number' => $data['serial_number'] ?? null,
            'model_number' => $data['model_number'] ?? null,
            'purchase_date' => $data['purchase_date'] ?? null,
            'purchase_cost' => $data['purchase_cost'] ?? null,
            'currency_code' => $data['currency_code'] ?? 'KES',
            'status' => 'available',
            'remarks' => $data['remarks'] ?? null
        ]);

        $newId = (int)$this->db->lastInsertId();
        $this->auditService->log('CREATE', 'assets', $newId, null, $data);
        return $newId;
    }

    /**
     * Allocates an available asset to an employee
     */
    public function allocateAsset(int $assetId, int $employeeId, array $details): bool
    {
        try {
            $this->db->beginTransaction();

            // Check availability
            $stmt = $this->db->prepare("SELECT status FROM assets WHERE id = ?");
            $stmt->execute([$assetId]);
            if ($stmt->fetchColumn() !== 'available') {
                throw new \Exception("Asset is not available for allocation.");
            }

            // Create allocation
            $ins = $this->db->prepare("
                INSERT INTO asset_allocations (asset_id, employee_id, allocation_date, expected_return_date, remarks, status)
                VALUES (:asset_id, :employee_id, :date, :expected, :remarks, 'active')
            ");
            $ins->execute([
                'asset_id' => $assetId,
                'employee_id' => $employeeId,
                'date' => $details['allocation_date'] ?? date('Y-m-d'),
                'expected' => $details['expected_return_date'] ?? null,
                'remarks' => $details['remarks'] ?? null
            ]);

            // Update status
            $this->db->prepare("UPDATE assets SET status = 'allocated' WHERE id = ?")->execute([$assetId]);

            $this->auditService->log('ALLOCATE', 'assets', $assetId, null, ['employee_id' => $employeeId, 'details' => $details]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Records the return of an asset to inventory
     */
    public function returnAsset(int $allocationId, ?string $returnDate = null): bool
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT asset_id FROM asset_allocations WHERE id = ?");
            $stmt->execute([$allocationId]);
            $assetId = $stmt->fetchColumn();

            if (!$assetId) throw new \Exception("Allocation record not found.");

            $this->db->prepare("UPDATE asset_allocations SET status = 'returned', actual_return_date = ? WHERE id = ?")
                     ->execute([$returnDate ?: date('Y-m-d'), $allocationId]);

            $this->db->prepare("UPDATE assets SET status = 'available' WHERE id = ?")->execute([$assetId]);

            $this->auditService->log('RETURN', 'assets', (int)$assetId, null, ['allocation_id' => $allocationId, 'return_date' => $returnDate]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Fetches all assets currently or historically assigned to an employee
     */
    public function getEmployeeAssets(int $employeeId): array
    {
        $stmt = $this->db->prepare("
            SELECT aa.*, a.name as asset_name, a.category, a.serial_number, a.model_number, c.name as company_name
            FROM asset_allocations aa
            JOIN assets a ON aa.asset_id = a.id
            JOIN companies c ON a.company_id = c.id
            WHERE aa.employee_id = :employee_id
            ORDER BY aa.allocation_date DESC
        ");
        $stmt->execute(['employee_id' => $employeeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
