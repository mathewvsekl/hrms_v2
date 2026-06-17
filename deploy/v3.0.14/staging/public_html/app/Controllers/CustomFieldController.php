<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * CustomFieldController
 * 
 * Handles CRUD operations for custom fields assigned to specific offices.
 */
class CustomFieldController extends Controller
{
    /**
     * Get all custom fields for a company
     * GET /api/organization/companies/{id}/custom_fields
     */
    public function index($companyId)
    {
        $this->verifyDataScope($companyId);
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM company_custom_fields WHERE company_id = ? ORDER BY display_order ASC");
            $stmt->execute([$companyId]);
            $fields = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Parse json correctly
            foreach ($fields as &$field) {
                if ($field['field_options']) {
                    $field['field_options'] = json_decode($field['field_options'], true);
                }
            }

            return $this->jsonResponse($fields);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /**
     * Create a new custom field for a company
     * POST /api/organization/companies/{id}/custom_fields
     */
    public function store($companyId)
    {
        $this->verifyDataScope($companyId);
        $data = $this->getJsonPayload();

        // Validation
        if (empty($data['field_key']) || empty($data['field_name']) || empty($data['field_type'])) {
            return $this->jsonResponse(null, 400, "Field key, name, and type are required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO company_custom_fields (company_id, field_key, field_name, field_type, field_options, is_required, display_order) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $companyId,
                $data['field_key'],
                $data['field_name'],
                $data['field_type'],
                isset($data['field_options']) ? json_encode($data['field_options']) : null,
                (int) ($data['is_required'] ?? 0),
                (int) ($data['display_order'] ?? 0)
            ]);

            return $this->jsonResponse(['id' => $db->lastInsertId()], 201, "Custom field created successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /**
     * Update an existing custom field
     * PUT /api/organization/companies/{id}/custom_fields/{fieldId}
     */
    public function update($companyId, $fieldId)
    {
        $this->verifyDataScope($companyId);
        $data = $this->getJsonPayload();

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                UPDATE company_custom_fields 
                SET field_name = ?, field_type = ?, field_options = ?, is_required = ?, display_order = ? 
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([
                $data['field_name'],
                $data['field_type'],
                isset($data['field_options']) ? json_encode($data['field_options']) : null,
                (int) ($data['is_required'] ?? 0),
                (int) ($data['display_order'] ?? 0),
                $fieldId,
                $companyId
            ]);

            return $this->jsonResponse(null, 200, "Custom field updated successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /**
     * Delete a custom field
     * DELETE /api/organization/companies/{id}/custom_fields/{fieldId}
     */
    public function destroy($companyId, $fieldId)
    {
        $this->verifyDataScope($companyId);
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM company_custom_fields WHERE id = ? AND company_id = ?");
            $stmt->execute([$fieldId, $companyId]);

            return $this->jsonResponse(null, 200, "Custom field deleted successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }
}
