<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * OrganizationController
 * 
 * Manages the core corporate structure: Countries, Offices, Departments, and Designations.
 * Hierarchy: Country → Office → Department → Designation
 * Also handles Global Settings and Exchange Rates for the Configuration page.
 */
class OrganizationController extends Controller
{
    public function __construct()
    {
        // Constructor maintained for future middleware initialization
    }

    /* ──────────────────────────────────────────────
     * LIST ENDPOINTS (GET)
     * ────────────────────────────────────────────── */

    /** GET /api/organization/countries */
    public function listCountries()
    {
        // Performance Audit Fix: Static Data Caching
        $cacheKey = "org_countries_list";
        $cached = \App\Helpers\CacheHelper::get($cacheKey);
        if ($cached) return $this->jsonResponse($cached);

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT * FROM countries ORDER BY name ASC");
            $countries = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            \App\Helpers\CacheHelper::set($cacheKey, $countries, 3600); // Cache for 1 hour
            
            return $this->jsonResponse($countries);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** GET /api/organization/companies */
    public function listCompanies()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $params = [];
            $geographicFilter = "";

            $isGlobalAdmin = $this->isGlobalAdmin();
            if (!$isGlobalAdmin) {
                $isMultiOffice = $this->hasAnyRole(['HRManager', 'HRAssistant', 'CountryManager', 'COUNTRY MANAGER']);

                $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;

                if ($isMultiOffice && !empty($associatedCompanyIds)) {
                    $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                    $geographicFilter = " AND c_base.id IN ($companyIdList)";
                } else if ($this->hasAnyRole(['CountryManager', 'COUNTRY MANAGER']) && $sessionCountryId) {
                    $geographicFilter = " AND c_base.country_id = :session_country_id";
                    $params['session_country_id'] = $sessionCountryId;
                } else {
                    $sessionCompanyId = $_SESSION['scope_company_id'] ?? null;
                    if ($sessionCompanyId) {
                        $geographicFilter = " AND c_base.id = :session_company_id";
                        $params['session_company_id'] = $sessionCompanyId;
                    } else if (!$isMultiOffice) {
                        $geographicFilter = " AND 1=0";
                    }
                }
            }

            $query = "
                SELECT c_base.*, c.name as country_name, c.currency_code
                FROM companies c_base
                LEFT JOIN countries c ON c_base.country_id = c.id
                WHERE 1=1 $geographicFilter
                ORDER BY c_base.name ASC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $companies = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->jsonResponse($companies);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }


    /** GET /api/organization/companies/{id} */
    public function getCompany($id)
    {
        // Security Audit Fix: Scope Verification
        $this->verifyDataScope($id);

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT c_base.*, c.name as country_name, c.currency_code
                FROM companies c_base
                LEFT JOIN countries c ON c_base.country_id = c.id
                WHERE c_base.id = ?
            ");
            $stmt->execute([$id]);
            $company = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$company) {
                return $this->jsonResponse(null, 404, "Company not found.");
            }
            
            return $this->jsonResponse($company);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** GET /api/organization/departments */
    public function listDepartments()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT * FROM departments ORDER BY name ASC");
            $departments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->jsonResponse($departments);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** GET /api/organization/designations */
    public function listDesignations()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("
                SELECT dg.*, d.name as department_name
                FROM designations dg
                LEFT JOIN departments d ON dg.department_id = d.id
                ORDER BY dg.title ASC
            ");
            $designations = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->jsonResponse($designations);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /* ──────────────────────────────────────────────
     * UPDATE ENDPOINTS (PUT)
     * ────────────────────────────────────────────── */

    /** PUT /api/organization/countries/{id} */
    public function updateCountry($id)
    {
        // Security Audit Fix: Global Admin Protection
        if (!$this->isGlobalAdmin()) {
            return $this->jsonResponse(null, 403, "Security Violation: Only Global Admins can modify Country configurations.");
        }

        $data = $this->getJsonPayload();
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE countries SET name = ?, iso_code = ?, currency_code = ?, default_timezone = ? WHERE id = ?");
            $stmt->execute([$data['name'], $data['iso_code'], $data['currency_code'], $data['default_timezone'], $id]);
            return $this->jsonResponse(['id' => $id], 200, "Country updated.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** PUT /api/organization/companies/{id} */
    public function updateCompany($id)
    {
        $this->verifyDataScope($id);
        $data = $this->getJsonPayload();
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                UPDATE companies 
                SET country_id = ?, name = ?, timezone = ?, address = ?, contact_phone = ?, contact_email = ?, attendance_mode = ?, is_time_tracking_enabled = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['country_id'], 
                $data['name'], 
                $data['timezone'], 
                $data['address'] ?? null, 
                $data['contact_phone'] ?? null, 
                $data['contact_email'] ?? null, 
                $data['attendance_mode'] ?? 'time_based', 
                isset($data['is_time_tracking_enabled']) ? (int) $data['is_time_tracking_enabled'] : 0,
                $id
            ]);
            return $this->jsonResponse(['id' => $id], 200, "Company updated.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** PUT /api/organization/departments/{id} */
    public function updateDepartment($id)
    {
        // Security Audit Fix: Administrative RBAC
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'HRMANAGER'])) {
            return $this->jsonResponse(null, 403, "Security Violation: Insufficient permissions to modify Departments.");
        }

        $data = $this->getJsonPayload();
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE departments SET name = ? WHERE id = ?");
            $stmt->execute([$data['name'], $id]);
            return $this->jsonResponse(['id' => $id], 200, "Department updated.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** PUT /api/organization/designations/{id} */
    public function updateDesignation($id)
    {
        $data = $this->getJsonPayload();
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE designations SET department_id = ?, title = ?, level = ? WHERE id = ?");
            $stmt->execute([$data['department_id'], $data['title'], $data['level'] ?? 0, $id]);
            return $this->jsonResponse(['id' => $id], 200, "Designation updated.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /* ──────────────────────────────────────────────
     * DELETE ENDPOINTS (DELETE)
     * ────────────────────────────────────────────── */

    /** DELETE /api/organization/countries/{id} */
    public function deleteCountry($id)
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM countries WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(null, 200, "Country deleted.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Cannot delete: " . $e->getMessage());
        }
    }

    /** DELETE /api/organization/companies/{id} */
    public function deleteCompany($id)
    {
        $this->verifyDataScope($id);
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(null, 200, "Company deleted.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Cannot delete: " . $e->getMessage());
        }
    }

    /** DELETE /api/organization/departments/{id} */
    public function deleteDepartment($id)
    {
        $this->verifyDataScope();
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(null, 200, "Department deleted.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Cannot delete: " . $e->getMessage());
        }
    }

    /** DELETE /api/organization/designations/{id} */
    public function deleteDesignation($id)
    {
        $this->verifyDataScope();
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM designations WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(null, 200, "Designation deleted.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Cannot delete: " . $e->getMessage());
        }
    }

    /* ──────────────────────────────────────────────
     * GLOBAL SETTINGS
     * ────────────────────────────────────────────── */

    /** GET /api/organization/settings */
    public function listSettings()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT * FROM global_settings ORDER BY setting_key ASC");
            $settings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Noah Logic Auditor: Mask sensitive values (regardless of RBAC in UI)
            $sensitiveKeys = ['MAIL_PASS', 'SMTP_PASSWORD', 'API_KEY', 'SECRET', 'TOKEN', 'DATABASE_PASSWORD', 'DB_PASS'];
            
            foreach ($settings as &$setting) {
                $isSensitive = false;
                foreach ($sensitiveKeys as $sKey) {
                    if (str_contains(strtoupper($setting['setting_key']), $sKey)) {
                        $isSensitive = true;
                        break;
                    }
                }

                if ($isSensitive) {
                    $setting['setting_value'] = '********'; // Masked even for admins in bulk list
                }
            }

            return $this->jsonResponse($settings);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** POST /api/organization/settings */
    public function updateSetting()
    {
        // Security Audit Fix: Global Settings Protection
        if (!$this->isGlobalAdmin()) {
            return $this->jsonResponse(null, 403, "Security Violation: Only Global Admins can modify system settings.");
        }

        $data = $this->getJsonPayload();
        $key = $data['setting_key'] ?? null;
        $value = $data['setting_value'] ?? null;

        if (!$key) {
            return $this->jsonResponse(null, 400, "Missing setting_key.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO global_settings (setting_key, setting_value, category)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value, $data['category'] ?? 'general']);
            return $this->jsonResponse(['setting_key' => $key], 200, "Setting updated.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /* ──────────────────────────────────────────────
     * EXCHANGE RATES
     * ────────────────────────────────────────────── */

    /** GET /api/organization/exchange-rates */
    public function listExchangeRates()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT * FROM exchange_rates WHERE is_active = 1 ORDER BY effective_date DESC");
            $rates = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->jsonResponse($rates);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** POST /api/organization/exchange-rates */
    public function createExchangeRate()
    {
        $data = $this->getJsonPayload();
        $this->verifyDataScope();

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO exchange_rates (from_currency, to_currency, rate, effective_date)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['from_currency'],
                $data['to_currency'],
                $data['rate'],
                $data['effective_date'] ?? date('Y-m-d')
            ]);
            return $this->jsonResponse(['id' => $db->lastInsertId()], 201, "Exchange rate added.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** PUT /api/organization/exchange-rates/{id} */
    public function updateExchangeRate($id)
    {
        $data = $this->getJsonPayload();
        $this->verifyDataScope();

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                UPDATE exchange_rates 
                SET from_currency = ?, to_currency = ?, rate = ?, effective_date = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['from_currency'],
                $data['to_currency'],
                $data['rate'],
                $data['effective_date'],
                isset($data['is_active']) ? (int)$data['is_active'] : 1,
                $id
            ]);
            return $this->jsonResponse(['id' => $id], 200, "Exchange rate updated.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** DELETE /api/organization/exchange-rates/{id} */
    public function deleteExchangeRate($id)
    {
        $this->verifyDataScope();
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM exchange_rates WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(null, 200, "Exchange rate deleted.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /* ──────────────────────────────────────────────
     * CREATE ENDPOINTS (POST)
     * ────────────────────────────────────────────── */

    /** POST /api/organization/countries */
    public function createCountry()
    {
        $data = $this->getJsonPayload();
        $this->verifyDataScope();

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO countries (name, iso_code, currency_code, default_timezone) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['iso_code'],
                $data['currency_code'],
                $data['default_timezone']
            ]);
            return $this->jsonResponse(['id' => $db->lastInsertId()], 201, "Country registered successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** POST /api/organization/companies */
    public function createCompany()
    {
        $data = $this->getJsonPayload();
        $countryId = $data['country_id'] ?? null;
        $this->verifyDataScope(null, $countryId, null);

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO companies (country_id, name, address, contact_phone, contact_email, timezone, is_time_tracking_enabled, attendance_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $countryId,
                $data['name'],
                $data['address'] ?? null,
                $data['contact_phone'] ?? null,
                $data['contact_email'] ?? null,
                $data['timezone'],
                isset($data['is_time_tracking_enabled']) ? (int) $data['is_time_tracking_enabled'] : 0,
                $data['attendance_mode'] ?? 'time_based'
            ]);
            return $this->jsonResponse(['id' => $db->lastInsertId()], 201, "Company registered successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** POST /api/organization/departments */
    public function createDepartment()
    {
        // Security Audit Fix: Administrative RBAC
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'HRMANAGER'])) {
            return $this->jsonResponse(null, 403, "Security Violation: Insufficient permissions to create Departments.");
        }

        $data = $this->getJsonPayload();
        if (!$data['name']) {
            return $this->jsonResponse(null, 400, "Department Name is required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->execute([$data['name']]);
            return $this->jsonResponse(['id' => $db->lastInsertId()], 201, "Department registered successfully.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }

    /** POST /api/organization/designations */
    public function createDesignation()
    {
        // Security Audit Fix: Administrative RBAC
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'HRMANAGER'])) {
            return $this->jsonResponse(null, 403, "Security Violation: Insufficient permissions to create Designations.");
        }

        $data = $this->getJsonPayload();
        $departmentId = $data['department_id'] ?? null;

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO designations (department_id, title, level) VALUES (?, ?, ?)");
            $stmt->execute([
                $departmentId,
                $data['title'],
                $data['level'] ?? 0
            ]);
            return $this->jsonResponse(['id' => $db->lastInsertId()], 201, "Designation securely created.");
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database Error: " . $e->getMessage());
        }
    }
}
