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
}
