<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\OrganizationService;

/**
 * OrganizationController
 * 
 * Manages the core corporate structure: Countries, Offices, Departments, and Designations.
 */
class OrganizationController extends Controller
{
    private $orgService;

    public function __construct()
    {
        $this->orgService = new OrganizationService();
    }

    /** GET /api/organization/countries */
    public function listCountries()
    {
        try {
            return $this->jsonResponse($this->orgService->listCountries());
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service Error: " . $e->getMessage());
        }
    }

    /** GET /api/organization/companies */
    public function listCompanies()
    {
        try {
            $isGlobalAdmin = $this->isGlobalAdmin();
            $isMultiOffice = $this->hasAnyRole(['HRManager', 'HRAssistant', 'CountryManager', 'COUNTRY MANAGER']);
            
            $companies = $this->orgService->listCompanies(
                $isGlobalAdmin,
                $_SESSION['associated_company_ids'] ?? [],
                $_SESSION['scope_country_id'] ?? null,
                $_SESSION['scope_company_id'] ?? null,
                $isMultiOffice
            );
            return $this->jsonResponse($companies);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service Error: " . $e->getMessage());
        }
    }

    /** GET /api/organization/companies/{id} */
    public function getCompany($id)
    {
        $this->verifyDataScope($id);
        try {
            $company = $this->orgService->getCompany((int)$id);
            if (!$company) return $this->jsonResponse(null, 404, "Company not found.");
            return $this->jsonResponse($company);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service Error: " . $e->getMessage());
        }
    }

    /** GET /api/organization/departments */
    public function listDepartments()
    {
        try {
            return $this->jsonResponse($this->orgService->listDepartments());
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service Error: " . $e->getMessage());
        }
    }

    /** GET /api/organization/designations */
    public function listDesignations()
    {
        try {
            return $this->jsonResponse($this->orgService->listDesignations());
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service Error: " . $e->getMessage());
        }
    }

    /** POST /api/organization/countries */
    public function createCountry()
    {
        if (!$this->isGlobalAdmin()) return $this->jsonResponse(null, 403, "Admin Only");
        try {
            $id = $this->orgService->createCountry($this->getJsonPayload());
            return $this->jsonResponse(['id' => $id], 201, "Country registered.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /** POST /api/organization/companies */
    public function createCompany()
    {
        $data = $this->getJsonPayload();
        $this->verifyDataScope(null, $data['country_id'] ?? null);
        try {
            $id = $this->orgService->createCompany($data);
            return $this->jsonResponse(['id' => $id], 201, "Company registered.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /** PUT /api/organization/companies/{id} */
    public function updateCompany($id)
    {
        $this->verifyDataScope($id);
        try {
            $this->orgService->updateCompany((int)$id, $this->getJsonPayload());
            return $this->jsonResponse(['id' => $id], 200, "Company updated.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /** POST /api/organization/departments */
    public function createDepartment()
    {
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'HRMANAGER'])) return $this->jsonResponse(null, 403, "Admin Only");
        $data = $this->getJsonPayload();
        try {
            $id = $this->orgService->createDepartment($data['name']);
            return $this->jsonResponse(['id' => $id], 201, "Department registered.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /** DELETE /api/organization/departments/{id} */
    public function deleteDepartment($id)
    {
        if (!$this->isGlobalAdmin()) return $this->jsonResponse(null, 403, "Admin Only");
        try {
            $this->orgService->deleteDepartment((int)$id);
            return $this->jsonResponse(null, 200, "Department deleted.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /** GET /api/organization/settings */
    public function listSettings()
    {
        try {
            $settings = $this->orgService->getGlobalSettings();
            $sensitiveKeys = ['PASS', 'KEY', 'SECRET', 'TOKEN'];
            foreach ($settings as &$setting) {
                foreach ($sensitiveKeys as $sKey) {
                    if (str_contains(strtoupper($setting['setting_key']), $sKey)) {
                        $setting['setting_value'] = '********';
                        break;
                    }
                }
            }
            return $this->jsonResponse($settings);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /** POST /api/organization/settings */
    public function updateSetting()
    {
        if (!$this->isGlobalAdmin()) return $this->jsonResponse(null, 403, "Admin Only");
        $data = $this->getJsonPayload();
        try {
            $this->orgService->saveGlobalSetting($data['setting_key'], $data['setting_value'], $data['category'] ?? 'general');
            return $this->jsonResponse(['setting_key' => $data['setting_key']], 200, "Setting updated.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /** GET /api/organization/exchange-rates */
    public function listExchangeRates()
    {
        try {
            return $this->jsonResponse($this->orgService->listExchangeRates());
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /** POST /api/organization/exchange-rates */
    public function createExchangeRate()
    {
        if (!$this->isGlobalAdmin()) return $this->jsonResponse(null, 403, "Admin Only");
        try {
            $this->orgService->saveExchangeRate($this->getJsonPayload());
            return $this->jsonResponse(null, 201, "Exchange rate added.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }
}
