<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * AssetController
 * 
 * Handles Asset Inventory and Employee Allocations.
 */
class AssetController extends Controller
{
    /**
     * List all assets for a company
     */
    public function index()
    {
        $companyId = $_GET['company_id'] ?? null;
        $countryId = $_GET['country_id'] ?? null;
        $this->verifyDataScope($companyId, $countryId);
        
        try {
            $db = \Database::getInstance()->getConnection();
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
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $assets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($assets);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Add a new asset
     */
    public function store()
    {
        $requestData = json_decode(file_get_contents('php://input'), true);
        $companyId = $requestData['company_id'] ?? null;
        $this->verifyDataScope($companyId);

        if (empty($requestData['name']) || empty($companyId)) {
            return $this->jsonResponse(null, 400, "Asset name and Company ID are required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO assets (company_id, name, category, serial_number, model_number, purchase_date, purchase_cost, currency_code, status, remarks)
                VALUES (:company_id, :name, :category, :serial_number, :model_number, :purchase_date, :purchase_cost, :currency_code, :status, :remarks)
            ");
            
            $stmt->execute([
                'company_id' => $companyId,
                'name' => $requestData['name'],
                'category' => $requestData['category'] ?? 'other',
                'serial_number' => $requestData['serial_number'] ?? null,
                'model_number' => $requestData['model_number'] ?? null,
                'purchase_date' => $requestData['purchase_date'] ?? null,
                'purchase_cost' => $requestData['purchase_cost'] ?? null,
                'currency_code' => $requestData['currency_code'] ?? 'KES',
                'status' => 'available',
                'remarks' => $requestData['remarks'] ?? null
            ]);

            return $this->jsonResponse(['message' => 'Asset created successfully.', 'id' => $db->lastInsertId()]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Update an asset
     */
    public function update($id)
    {
        $requestData = json_decode(file_get_contents('php://input'), true);
        $this->verifyDataScope(); // Generic check

        try {
            $db = \Database::getInstance()->getConnection();
            $allowedFields = ['name', 'category', 'serial_number', 'model_number', 'purchase_date', 'purchase_cost', 'currency_code', 'status', 'remarks'];
            $updates = [];
            $params = ['id' => $id];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $requestData)) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $requestData[$field];
                }
            }

            if (empty($updates)) {
                return $this->jsonResponse(null, 400, "No fields to update.");
            }

            $query = "UPDATE assets SET " . implode(", ", $updates) . " WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute($params);

            return $this->jsonResponse(['message' => 'Asset updated successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Delete an asset
     */
    public function destroy($id)
    {
        $this->verifyDataScope();
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM assets WHERE id = :id");
            $stmt->execute(['id' => $id]);
            return $this->jsonResponse(['message' => 'Asset deleted successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Allocate an asset to an employee
     */
    public function allocate()
    {
        $requestData = json_decode(file_get_contents('php://input'), true);
        $assetId = $requestData['asset_id'] ?? null;
        $employeeId = $requestData['employee_id'] ?? null;
        
        if (!$assetId || !$employeeId) {
            return $this->jsonResponse(null, 400, "Asset ID and Employee ID are required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Check if asset is available
            $stmt = $db->prepare("SELECT status FROM assets WHERE id = ?");
            $stmt->execute([$assetId]);
            $status = $stmt->fetchColumn();

            if ($status !== 'available') {
                $db->rollBack();
                return $this->jsonResponse(null, 400, "Asset is not available for allocation.");
            }

            // 2. Create allocation record
            $stmt = $db->prepare("
                INSERT INTO asset_allocations (asset_id, employee_id, allocation_date, expected_return_date, remarks, status)
                VALUES (:asset_id, :employee_id, :allocation_date, :expected_return_date, :remarks, 'active')
            ");
            $stmt->execute([
                'asset_id' => $assetId,
                'employee_id' => $employeeId,
                'allocation_date' => $requestData['allocation_date'] ?? date('Y-m-d'),
                'expected_return_date' => $requestData['expected_return_date'] ?? null,
                'remarks' => $requestData['remarks'] ?? null
            ]);

            // 3. Update asset status
            $stmt = $db->prepare("UPDATE assets SET status = 'allocated' WHERE id = ?");
            $stmt->execute([$assetId]);

            $db->commit();
            return $this->jsonResponse(['message' => 'Asset allocated successfully.']);
        } catch (\Exception $e) {
            if (isset($db)) $db->rollBack();
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Deallocate (Return) an asset
     */
    public function deallocate()
    {
        $requestData = json_decode(file_get_contents('php://input'), true);
        $allocationId = $requestData['allocation_id'] ?? null;

        if (!$allocationId) {
            return $this->jsonResponse(null, 400, "Allocation ID is required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Get asset ID from allocation
            $stmt = $db->prepare("SELECT asset_id FROM asset_allocations WHERE id = ?");
            $stmt->execute([$allocationId]);
            $assetId = $stmt->fetchColumn();

            if (!$assetId) {
                $db->rollBack();
                return $this->jsonResponse(null, 404, "Allocation record not found.");
            }

            // 2. Update allocation record
            $stmt = $db->prepare("
                UPDATE asset_allocations 
                SET status = 'returned', actual_return_date = :actual_return_date 
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $allocationId,
                'actual_return_date' => $requestData['actual_return_date'] ?? date('Y-m-d')
            ]);

            // 3. Update asset status back to available
            $stmt = $db->prepare("UPDATE assets SET status = 'available' WHERE id = ?");
            $stmt->execute([$assetId]);

            $db->commit();
            return $this->jsonResponse(['message' => 'Asset returned successfully.']);
        } catch (\Exception $e) {
            if (isset($db)) $db->rollBack();
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * List assets assigned to a specific employee
     */
    public function employeeAssets($employeeId)
    {
        $this->verifyDataScope(null, null, $employeeId);
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT aa.*, a.name as asset_name, a.category, a.serial_number, a.model_number, c.name as company_name
                FROM asset_allocations aa
                JOIN assets a ON aa.asset_id = a.id
                JOIN companies c ON a.company_id = c.id
                WHERE aa.employee_id = :employee_id
                ORDER BY aa.allocation_date DESC
            ");
            $stmt->execute(['employee_id' => $employeeId]);
            $assets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse($assets);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }
}
