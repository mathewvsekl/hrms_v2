<?php

namespace App\Controllers;

/**
 * CompanyController
 * 
 * Manages company-specific logic, including fetching the master configuration 
 * templates constructed from the seeders.
 */
class CompanyController
{
    /**
     * Get a list of all companies
     */
    public function index()
    {
        // TODO: Map out SELECT * FROM companies JOIN countries on country_id
        return [
            'status' => 'success',
            'data' => [
                // List of companies
            ]
        ];
    }

    /**
     * Get a specific company and its required custom fields templates
     * (e.g., used by frontend to render onboarding forms correctly per region)
     */
    public function getTemplate($companyId)
    {
        // Mocking Data that would typically come from:
        // 1. $company = \DB::query("SELECT * FROM companies WHERE id = ?", [$companyId]);
        // 2. $customFields = \DB::query("SELECT * FROM company_custom_fields WHERE company_id = ? ORDER BY display_order ASC", [$companyId]);

        // Mocking DB response for demonstration purposes based on seeded data
        $companies = [
            1 => ['id' => 1, 'name' => 'Dubai Head Office', 'timezone' => 'Asia/Dubai'],
            2 => ['id' => 2, 'name' => 'Nairobi Corporate Center', 'timezone' => 'Africa/Nairobi'],
            6 => ['id' => 6, 'name' => 'Mumbai Development Center', 'timezone' => 'Asia/Kolkata']
        ];

        $allCustomFields = [
            1 => [
                ['field_key' => 'emirates_id', 'field_name' => 'Emirates ID', 'field_type' => 'text', 'is_required' => true],
                ['field_key' => 'wps_routing_code', 'field_name' => 'WPS Routing Code', 'field_type' => 'text', 'is_required' => true]
            ],
            2 => [
                ['field_key' => 'kra_pin', 'field_name' => 'KRA PIN', 'field_type' => 'text', 'is_required' => true],
                ['field_key' => 'national_id', 'field_name' => 'National ID', 'field_type' => 'text', 'is_required' => true],
                ['field_key' => 'sha_number', 'field_name' => 'SHA Number', 'field_type' => 'text', 'is_required' => true],
                ['field_key' => 'nssf_number', 'field_name' => 'NSSF Number', 'field_type' => 'text', 'is_required' => true]
            ],
            6 => [
                ['field_key' => 'pan_number', 'field_name' => 'PAN', 'field_type' => 'text', 'is_required' => true],
                ['field_key' => 'aadhaar_number', 'field_name' => 'Aadhaar Card', 'field_type' => 'text', 'is_required' => true],
                ['field_key' => 'uan_number', 'field_name' => 'UAN (EPF)', 'field_type' => 'text', 'is_required' => true]
            ]
        ];

        if (!array_key_exists($companyId, $companies)) {
            return [
                'status' => 'error',
                'message' => 'Company not found or templates not configured'
            ];
        }

        return [
            'status' => 'success',
            'data' => [
                'company' => $companies[$companyId],
                'custom_fields' => $allCustomFields[$companyId] ?? []
            ]
        ];
    }

    public function updateLogo($companyId)
    {
        try {
            if (!isset($_FILES['logo'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No logo file provided in the request (missing $_FILES["logo"])']);
                return;
            }
            if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                $errCode = $_FILES['logo']['error'];
                echo json_encode(['status' => 'error', 'message' => 'Upload failed with error code: ' . $errCode]);
                return;
            }

            $uploadDir = __DIR__ . '/../../public/uploads/logos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileInfo = pathinfo($_FILES['logo']['name']);
            $extension = strtolower($fileInfo['extension'] ?? '');
            $allowedExts = ['jpg', 'jpeg', 'png', 'svg', 'gif', 'webp'];
            if (!in_array($extension, $allowedExts)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Invalid file type']);
                return;
            }

            $fileName = 'company_' . $companyId . '_' . time() . '.' . $extension;
            $targetFile = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
                $logoUrl = '/public/uploads/logos/' . $fileName;

                // Create standalone connection since CompanyController doesn't extend Controller yet
                require_once __DIR__ . '/../../config/database.php';
                $db = \Database::getInstance()->getConnection();
                
                $stmt = $db->prepare('UPDATE companies SET logo_url = ? WHERE id = ?');
                $stmt->execute([$logoUrl, $companyId]);
                
                http_response_code(200);
                echo json_encode(['status' => 'success', 'data' => ['logo_url' => $logoUrl], 'message' => 'Logo updated successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Failed to save logo file']);
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
}
