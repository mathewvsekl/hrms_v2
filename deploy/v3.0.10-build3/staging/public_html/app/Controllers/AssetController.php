<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AssetService;

/**
 * AssetController
 * 
 * Handles Asset Inventory and Employee Allocations via AssetService.
 */
class AssetController extends Controller
{
    private $assetService;

    public function __construct()
    {
        $this->assetService = new AssetService();
    }

    /**
     * List all assets for a company
     */
    public function index()
    {
        $companyId = $_GET['company_id'] ?? null;
        $countryId = $_GET['country_id'] ?? null;
        $this->verifyDataScope($companyId, $countryId);
        
        try {
            $assets = $this->assetService->listAssets(
                $companyId ? (int)$companyId : null,
                $countryId ? (int)$countryId : null
            );
            return $this->jsonResponse($assets);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service error: " . $e->getMessage());
        }
    }

    /**
     * Add a new asset
     */
    public function store()
    {
        $requestData = $this->getJsonPayload();
        $companyId = $requestData['company_id'] ?? null;
        $this->verifyDataScope($companyId);

        if (empty($requestData['name']) || empty($companyId)) {
            return $this->jsonResponse(null, 400, "Asset name and Company ID are required.");
        }

        try {
            $assetId = $this->assetService->createAsset($requestData);
            return $this->jsonResponse(['message' => 'Asset created successfully.', 'id' => $assetId]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service error: " . $e->getMessage());
        }
    }

    /**
     * Allocate an asset to an employee
     */
    public function allocate()
    {
        if (!empty($_POST)) {
            $requestData = $_POST;
        } else {
            $requestData = $this->getJsonPayload();
        }
        $assetId = $requestData['asset_id'] ?? null;
        $employeeId = $requestData['employee_id'] ?? null;
        
        if (!$assetId || !$employeeId) {
            return $this->jsonResponse(null, 400, "Asset ID and Employee ID are required.");
        }

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = \App\Helpers\UploadHelper::upload($_FILES['attachment'], 'asset_allocations');
            if (!$uploadResult['success']) {
                return $this->jsonResponse(null, 400, $uploadResult['message']);
            }
            $requestData['attachment'] = $uploadResult['file_path'];
        }

        try {
            $this->assetService->allocateAsset((int)$assetId, (int)$employeeId, $requestData);
            return $this->jsonResponse(['message' => 'Asset allocated successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 400, $e->getMessage());
        }
    }

    /**
     * Deallocate (Return) an asset
     */
    public function deallocate()
    {
        $requestData = $this->getJsonPayload();
        $allocationId = $requestData['allocation_id'] ?? null;

        if (!$allocationId) {
            return $this->jsonResponse(null, 400, "Allocation ID is required.");
        }

        try {
            $this->assetService->returnAsset((int)$allocationId, $requestData['actual_return_date'] ?? null);
            return $this->jsonResponse(['message' => 'Asset returned successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 400, $e->getMessage());
        }
    }

    /**
     * List assets assigned to a specific employee
     */
    public function employeeAssets($employeeId)
    {
        $this->verifyDataScope(null, null, $employeeId);
        try {
            $assets = $this->assetService->getEmployeeAssets((int)$employeeId);
            return $this->jsonResponse($assets);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service error: " . $e->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $asset = $this->assetService->getAsset((int)$id);
            if (!$asset) return $this->jsonResponse(null, 404, "Asset not found.");
            $asset['history'] = $this->assetService->getAssetHistory((int)$id);
            return $this->jsonResponse($asset);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service error: " . $e->getMessage());
        }
    }

    public function update($id)
    {
        $requestData = $this->getJsonPayload();
        try {
            $this->assetService->updateAsset((int)$id, $requestData);
            return $this->jsonResponse(['message' => 'Asset updated successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service error: " . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $this->assetService->deleteAsset((int)$id);
            return $this->jsonResponse(['message' => 'Asset deleted successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service error: " . $e->getMessage());
        }
    }
}
