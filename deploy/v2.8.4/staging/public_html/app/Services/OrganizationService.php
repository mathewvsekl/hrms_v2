<?php

namespace App\Services;

use App\Helpers\CacheHelper;
use PDO;

/**
 * OrganizationService
 * 
 * Manages the corporate hierarchy, geographic localization, and system-wide configuration.
 */
class OrganizationService
{
    private $db;
    private $auditService;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->auditService = new AuditService();
    }

    /* ──────────────────────────────────────────────
     * GEOGRAPHIC DATA (Countries & Companies)
     * ────────────────────────────────────────────── */

    public function listCountries(): array
    {
        $cacheKey = "org_countries_list";
        $cached = CacheHelper::get($cacheKey);
        if ($cached) return $cached;

        $stmt = $this->db->query("SELECT * FROM countries ORDER BY name ASC");
        $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        CacheHelper::set($cacheKey, $countries, 3600);
        return $countries;
    }

    public function listCompanies(bool $isGlobalAdmin = false, array $associatedIds = [], ?int $sessionCountryId = null, ?int $sessionCompanyId = null, bool $isMultiOffice = false): array
    {
        $params = [];
        $filter = "";

        if (!$isGlobalAdmin) {
            if ($isMultiOffice && !empty($associatedIds)) {
                $idList = implode(",", array_map('intval', $associatedIds));
                $filter = " AND c_base.id IN ($idList)";
            } else if ($sessionCountryId) {
                $filter = " AND c_base.country_id = :country_id";
                $params['country_id'] = $sessionCountryId;
            } else if ($sessionCompanyId) {
                $filter = " AND c_base.id = :company_id";
                $params['company_id'] = $sessionCompanyId;
            } else if (!$isMultiOffice) {
                $filter = " AND 1=0";
            }
        }

        $query = "
            SELECT c_base.*, c.name as country_name, c.currency_code
            FROM companies c_base
            LEFT JOIN countries c ON c_base.country_id = c.id
            WHERE 1=1 $filter
            ORDER BY c_base.name ASC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCompany(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c_base.*, c.name as country_name, c.currency_code
            FROM companies c_base
            LEFT JOIN countries c ON c_base.country_id = c.id
            WHERE c_base.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createCountry(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO countries (name, iso_code, currency_code, default_timezone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['name'], $data['iso_code'], $data['currency_code'], $data['default_timezone']]);
        $newId = (int)$this->db->lastInsertId();
        $this->auditService->log('CREATE', 'countries', $newId, null, $data);
        CacheHelper::forget("org_countries_list");
        return $newId;
    }

    public function createCompany(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO companies (country_id, name, address, contact_phone, contact_email, timezone, is_time_tracking_enabled, attendance_mode) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['country_id'], $data['name'], $data['address'] ?? null, $data['contact_phone'] ?? null, $data['contact_email'] ?? null,
            $data['timezone'], (int)($data['is_time_tracking_enabled'] ?? 0), $data['attendance_mode'] ?? 'time_based'
        ]);
        $newId = (int)$this->db->lastInsertId();
        $this->auditService->log('CREATE', 'companies', $newId, null, $data);
        return $newId;
    }

    public function updateCompany(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE companies 
            SET country_id = ?, name = ?, timezone = ?, address = ?, contact_phone = ?, contact_email = ?, attendance_mode = ?, is_time_tracking_enabled = ?
            WHERE id = ?
        ");
        $success = $stmt->execute([
            $data['country_id'], $data['name'], $data['timezone'], $data['address'] ?? null,
            $data['contact_phone'] ?? null, $data['contact_email'] ?? null,
            $data['attendance_mode'] ?? 'time_based', (int)($data['is_time_tracking_enabled'] ?? 0), $id
        ]);
        if ($success) {
            $this->auditService->log('UPDATE', 'companies', $id, null, $data);
        }
        return $success;
    }

    /* ──────────────────────────────────────────────
     * STRUCTURAL DATA (Depts & Designations)
     * ────────────────────────────────────────────── */

    public function listDepartments(): array
    {
        return $this->db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listDesignations(): array
    {
        return $this->db->query("
            SELECT dg.*, d.name as department_name
            FROM designations dg
            LEFT JOIN departments d ON dg.department_id = d.id
            ORDER BY dg.title ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createDepartment(string $name): int
    {
        $stmt = $this->db->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->execute([$name]);
        $newId = (int)$this->db->lastInsertId();
        $this->auditService->log('CREATE', 'departments', $newId, null, ['name' => $name]);
        return $newId;
    }

    public function deleteDepartment(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM departments WHERE id = ?");
        $success = $stmt->execute([$id]);
        if ($success) {
            $this->auditService->log('DELETE', 'departments', $id);
        }
        return $success;
    }

    public function updateDepartment(int $id, string $name): bool
    {
        $stmt = $this->db->prepare("UPDATE departments SET name = ? WHERE id = ?");
        $success = $stmt->execute([$name, $id]);
        if ($success) {
            $this->auditService->log('UPDATE', 'departments', $id, null, ['name' => $name]);
        }
        return $success;
    }

    public function createDesignation(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO designations (department_id, title, level) VALUES (?, ?, ?)");
        $stmt->execute([$data['department_id'], $data['title'], $data['level'] ?? 0]);
        $newId = (int)$this->db->lastInsertId();
        $this->auditService->log('CREATE', 'designations', $newId, null, $data);
        return $newId;
    }

    public function updateDesignation(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("UPDATE designations SET department_id = ?, title = ?, level = ? WHERE id = ?");
        $success = $stmt->execute([$data['department_id'], $data['title'], $data['level'] ?? 0, $id]);
        if ($success) {
            $this->auditService->log('UPDATE', 'designations', $id, null, $data);
        }
        return $success;
    }

    public function deleteDesignation(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM designations WHERE id = ?");
        $success = $stmt->execute([$id]);
        if ($success) {
            $this->auditService->log('DELETE', 'designations', $id);
        }
        return $success;
    }

    /* ──────────────────────────────────────────────
     * CONFIGURATION & FINANCIALS
     * ────────────────────────────────────────────── */

    public function getGlobalSettings(): array
    {
        return $this->db->query("SELECT * FROM global_settings ORDER BY category, setting_key")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveGlobalSetting(string $key, string $value, string $category = 'general'): bool
    {
        $stmt = $this->db->prepare("INSERT INTO global_settings (setting_key, setting_value, category) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, category = ?");
        $success = $stmt->execute([$key, $value, $category, $value, $category]);
        if ($success) {
            $this->auditService->log('SETTING_CHANGE', 'global_settings', null, null, ['key' => $key, 'value' => $value, 'category' => $category]);
        }
        return $success;
    }

    public function listExchangeRates(): array
    {
        return $this->db->query("SELECT * FROM exchange_rates ORDER BY effective_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveExchangeRate(array $data): bool
    {
        $stmt = $this->db->prepare("INSERT INTO exchange_rates (from_currency, to_currency, rate, effective_date, is_active) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE rate = ?, is_active = ?");
        $success = $stmt->execute([
            $data['from_currency'], $data['to_currency'], $data['rate'], $data['effective_date'], $data['is_active'] ?? 1,
            $data['rate'], $data['is_active'] ?? 1
        ]);
        if ($success) {
            $this->auditService->log('RATE_CHANGE', 'exchange_rates', null, null, $data);
        }
        return $success;
    }

    public function deleteExchangeRate(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM exchange_rates WHERE id = ?");
        $success = $stmt->execute([$id]);
        if ($success) {
            $this->auditService->log('DELETE', 'exchange_rates', $id);
        }
        return $success;
    }
}
