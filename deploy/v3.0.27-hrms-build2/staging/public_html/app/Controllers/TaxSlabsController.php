<?php
namespace App\Controllers;

use App\Core\Controller;

class TaxSlabsController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN', 'HRMANAGER', 'HR_MANAGER', 'HR MANAGER'])) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
    }

    public function getSlabs()
    {
        try {
            $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            $componentId = isset($_GET['component_id']) ? (int)$_GET['component_id'] : null;

            $sql = "SELECT ts.*, pc.name as component_name FROM tax_slabs ts LEFT JOIN payroll_components pc ON ts.component_id = pc.id WHERE 1=1";
            $params = [];

            if ($companyId) {
                $sql .= " AND (ts.company_id = ? OR ts.company_id IS NULL)";
                $params[] = $companyId;
            }
            if ($componentId) {
                $sql .= " AND ts.component_id = ?";
                $params[] = $componentId;
            }

            $sql .= " ORDER BY ts.component_id ASC, ts.min_amount ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $slabs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($slabs, 200, 'Slabs retrieved');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    public function addSlab($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO tax_slabs (component_id, min_amount, max_amount, tax_type, percentage, fixed_amount, company_id, personal_relief)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                !empty($data['component_id']) ? $data['component_id'] : null,
                $data['min_amount'],
                isset($data['max_amount']) && $data['max_amount'] !== '' ? $data['max_amount'] : null,
                $data['tax_type'] ?? 'PERCENTAGE',
                $data['percentage'] ?? 0,
                $data['fixed_amount'] ?? 0,
                !empty($data['company_id']) ? $data['company_id'] : null,
                $data['personal_relief'] ?? 0
            ]);

            return $this->jsonResponse(['id' => $this->db->lastInsertId()], 201, 'Slab created successfully');
        } catch (\Throwable $e) {
            file_put_contents('slab_error.log', 'Failed to save slab: ' . $e->getMessage() . ' | Payload: ' . json_encode($data) . PHP_EOL, FILE_APPEND);
            return $this->jsonResponse(null, 500, 'Failed to save slab: ' . $e->getMessage());
        }
    }

    public function updateSlab($id, $data)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE tax_slabs 
                SET component_id = ?, min_amount = ?, max_amount = ?, tax_type = ?, percentage = ?, fixed_amount = ?, company_id = ?, personal_relief = ?
                WHERE id = ?
            ");
            $stmt->execute([
                !empty($data['component_id']) ? $data['component_id'] : null,
                $data['min_amount'],
                isset($data['max_amount']) && $data['max_amount'] !== '' ? $data['max_amount'] : null,
                $data['tax_type'] ?? 'PERCENTAGE',
                $data['percentage'] ?? 0,
                $data['fixed_amount'] ?? 0,
                !empty($data['company_id']) ? $data['company_id'] : null,
                $data['personal_relief'] ?? 0,
                $id
            ]);

            return $this->jsonResponse(null, 200, 'Slab updated');
        } catch (\Throwable $e) {
            file_put_contents('slab_error.log', 'Failed to save slab: ' . $e->getMessage() . ' | Payload: ' . json_encode($data) . PHP_EOL, FILE_APPEND);
            return $this->jsonResponse(null, 500, 'Failed to update slab: ' . $e->getMessage());
        }
    }

    public function deleteSlab($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM tax_slabs WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(null, 200, 'Slab deleted');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Failed to delete slab: ' . $e->getMessage());
        }
    }

    public function bulkSave($data)
    {
        try {
            $companyId = !empty($data['company_id']) ? $data['company_id'] : null;
            $componentId = !empty($data['component_id']) ? $data['component_id'] : null;
            $effectiveDate = !empty($data['effective_date']) ? $data['effective_date'] : null;

            $this->db->beginTransaction();

            // Delete existing slabs for this component and company
            if ($companyId && $componentId) {
                $delStmt = $this->db->prepare("DELETE FROM tax_slabs WHERE company_id = ? AND component_id = ?");
                $delStmt->execute([$companyId, $componentId]);
            } elseif ($componentId) {
                $delStmt = $this->db->prepare("DELETE FROM tax_slabs WHERE company_id IS NULL AND component_id = ?");
                $delStmt->execute([$componentId]);
            } else {
                $this->db->rollBack();
                return $this->jsonResponse(null, 400, 'Component ID is required');
            }

            // Insert new slabs
            $stmt = $this->db->prepare("
                INSERT INTO tax_slabs (component_id, effective_date, min_amount, max_amount, tax_type, percentage, fixed_amount, company_id, personal_relief)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($data['brackets'] as $bracket) {
                $stmt->execute([
                    $componentId,
                    $effectiveDate,
                    $bracket['min_amount'],
                    isset($bracket['max_amount']) && $bracket['max_amount'] !== '' ? $bracket['max_amount'] : null,
                    $bracket['tax_type'] ?? 'PERCENTAGE',
                    $bracket['percentage'] ?? 0,
                    $bracket['fixed_amount'] ?? 0,
                    $companyId,
                    $data['personal_relief'] ?? 0
                ]);
            }

            $this->db->commit();
            return $this->jsonResponse(null, 201, 'Slabs saved successfully');
        } catch (\Throwable $e) {
            $this->db->rollBack();
            file_put_contents('slab_error.log', 'Failed to bulk save slabs: ' . $e->getMessage() . ' | Payload: ' . json_encode($data) . PHP_EOL, FILE_APPEND);
            return $this->jsonResponse(null, 500, 'Failed to save slabs: ' . $e->getMessage());
        }
    }
}
