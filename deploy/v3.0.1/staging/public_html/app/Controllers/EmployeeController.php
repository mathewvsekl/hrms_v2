<?php

namespace App\Controllers;

use App\Helpers\CustomFieldValidator;
use App\Helpers\ApprovalHelper;
use App\Core\Controller;

/**
 * EmployeeController
 * 
 * Handles employee profiles and custom dynamic data.
 */
class EmployeeController extends Controller
{
    private $employeeService;

    public function __construct()
    {
        $this->employeeService = new \App\Services\EmployeeService();
    }

    /**
     * Fetch a specific employee by ID
     */
    public function getEmployee($id)
    {
        $this->verifyDataScope(null, null, $id); 
        try {
            $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;
            $isGlobalAdmin = $this->isGlobalAdmin();

            // Fetch with isAdmin=true internally to retrieve raw data for masking decision
            $employee = $this->employeeService->getEmployeeDetail(
                (int)$id, 
                $myEmployeeId ? (int)$myEmployeeId : null, 
                true 
            );

            if (empty($employee)) {
                return $this->jsonResponse(null, 404, 'Employee not found');
            }

            // PII Masking Logic for Regional Admins (Country Managers / HR Managers)
            $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];
            $primaryCompanyId = null;
            if (isset($employee['companies'])) {
                foreach ($employee['companies'] as $c) {
                    if ($c['is_primary'] == 1) {
                        $primaryCompanyId = $c['id'];
                        break;
                    }
                }
            }

            $canSeeFullDetails = $isGlobalAdmin || in_array($primaryCompanyId, $myCompanyIds) || (int)$employee['id'] === (int)$myEmployeeId;

            if (!$canSeeFullDetails) {
                // Mask sensitive fields for cross-company viewing
                $sensitiveFields = ['bank_account_no', 'bank_name', 'phone', 'personal_phone', 'personal_email', 'date_of_birth', 'nationality'];
                foreach ($sensitiveFields as $field) {
                    $employee[$field] = '***';
                }
                $employee['custom_data'] = [];
            } else {
                // If they can see full details, ensure custom_data is decoded
                if (isset($employee['custom_data']) && is_string($employee['custom_data'])) {
                    $employee['custom_data'] = json_decode($employee['custom_data'], true) ?: [];
                }
            }

            return $this->jsonResponse($employee);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Service error: " . $e->getMessage());
        }
    }

    /**
     * Fetch onboarding approval history for an employee
     */
    public function getOnboardingHistory($id)
    {
        $this->verifyDataScope(null, null, null); 
        return $this->jsonResponse(ApprovalHelper::getHistory('onboarding', $id));
    }

    /**
     * Update an existing employee profile
     */
    public function updateEmployee($requestData)
    {
        $employeeId = $requestData['id'] ?? null;
        if (!$employeeId) {
            return $this->jsonResponse(null, 400, "Employee ID is required for update.");
        }

        // Security Audit Fix: Admin-Only field protection
        $isAdmin = $this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN', 'HRMANAGER', 'HR_MANAGER', 'COUNTRYMANAGER', 'COUNTRY_MANAGER']);
        if (!$isAdmin) {
            $adminOnlyFields = ['status', 'designation_id', 'department_id', 'reporting_manager_id', 'employee_code', 'hire_date', 'role_id', 'company_ids'];
            foreach ($adminOnlyFields as $f) {
                if (isset($requestData[$f])) {
                    return $this->jsonResponse(null, 403, "Security Violation: You do not have permission to update field: $f");
                }
            }
        }

        $primaryCompanyId = $requestData['company_ids'][0] ?? null;
        $this->verifyDataScope($primaryCompanyId, null, $employeeId);

        try {
            $this->employeeService->updateEmployee((int)$employeeId, $requestData, $isAdmin);
            return $this->jsonResponse(['message' => 'Employee profile updated successfully.']);
        } catch (\Exception $e) {
            $tmpPath = defined('TMP_PATH') ? TMP_PATH : (defined('ROOT_PATH') ? ROOT_PATH : BASE_PATH) . '/tmp';
            file_put_contents($tmpPath . '/update_error.log', "PAYLOAD: " . json_encode($requestData) . "\nEXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->jsonResponse(null, 400, $e->getMessage());
        }
    }

    /**
     * Store a new employee (Onboarding)
     */
    public function save($requestData)
    {
        $companyIds = $requestData['company_ids'] ?? [];
        $primaryCompanyId = $companyIds[0] ?? null;
        $this->verifyDataScope($primaryCompanyId, null, null);

        try {
            $newId = $this->employeeService->createEmployee($requestData);
            return $this->jsonResponse(['message' => 'Employee created successfully.', 'id' => $newId]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 400, $e->getMessage());
        }
    }


    /**
     * Get employee counts and milestones for the dashboard (Performance Optimized)
     */
    public function getDashboardStats()
    {
        try {
            $db = \Database::getInstance()->getConnection();
            $data = [
                'total_count' => 0,
                'active_count' => 0,
                'status_stats' => [],
                'country_stats' => [],
                'milestones' => []
            ];

            $params = [];
            $companyFilter = "";
            $isGlobalAdmin = $this->isGlobalAdmin();
            if (!$isGlobalAdmin) {
                $isMultiOffice = $this->hasAnyRole(['HRManager', 'HRAssistant', 'CountryManager', 'COUNTRY MANAGER', 'COUNTRY_MANAGER', 'COUNTRYMANAGER']);

                $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;

                if ($isMultiOffice && !empty($associatedCompanyIds)) {
                    $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                    $companyFilter = " AND ec.company_id IN ($companyIdList) AND ec.is_primary = 1";

                } else {
                    $sessionCompanyId = $_SESSION['scope_company_id'] ?? null;
                    if ($sessionCompanyId) {
                        $companyFilter = " AND ec.company_id = :session_company_id AND ec.is_primary = 1";
                        $params['session_company_id'] = $sessionCompanyId;
                    } else if (!$isMultiOffice) {
                        $companyFilter = " AND 1=0";
                    }
                }
                
                // Hierarchical Isolation: Non-SuperAdmins cannot see SuperAdmin metrics
                $companyFilter .= " AND NOT EXISTS (
                    SELECT 1 FROM user_roles ur_dash 
                    JOIN roles r_dash ON ur_dash.role_id = r_dash.id 
                    JOIN users u_dash ON ur_dash.user_id = u_dash.id
                    WHERE u_dash.employee_id = e.id AND (UPPER(r_dash.name) = 'SUPERADMIN' OR UPPER(r_dash.name) = 'SUPER_ADMIN')
                )";
            }


            // 1a. Fetch Status Stats
            $queryStatus = "
                SELECT e.status as label, COUNT(*) as count
                FROM employees e
                " . ($isGlobalAdmin ? "" : "WHERE e.id IN (SELECT ec.employee_id FROM employee_companies ec WHERE ec.is_active = 1 $companyFilter)") . "
                GROUP BY e.status
            ";
            $stmt = $db->prepare($queryStatus);
            $stmt->execute($params);
            $statusRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($statusRows as $row) {
                $statusLabel = $row['label'];
                $count = (int)$row['count'];
                
                // Talent Acquisition Sync: Map pending approvals to onboarding for pipeline visibility
                if ($statusLabel === 'pending_approval') {
                    $statusLabel = 'onboarding';
                }

                // Aggregate counts if they map to the same label (e.g. onboarding + pending_approval)
                $existing = false;
                foreach ($data['status_stats'] as &$s) {
                    if ($s['status'] === $statusLabel) {
                        $s['count'] += $count;
                        $existing = true;
                        break;
                    }
                }
                if (!$existing) {
                    $data['status_stats'][] = ['status' => $statusLabel, 'count' => $count];
                }

                $data['total_count'] += $count;
                if ($row['label'] === 'active') {
                    $data['active_count'] = $count;
                }
            }

            // 1b. Fetch Regional Stats (Primary assignments only to avoid double-counting)
            $queryCountry = "
                SELECT 
                    COALESCE(cn.name, 'Unassigned / Global') as label, 
                    COALESCE(cn.iso_code, 'GL') as extra, 
                    COALESCE(cn.id, 0) as id, 
                    COUNT(DISTINCT e.id) as count,
                    COUNT(DISTINCT CASE WHEN e.status = 'active' THEN e.id END) as active_count
                FROM employees e
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                LEFT JOIN companies comp ON ec.company_id = comp.id
                LEFT JOIN countries cn ON comp.country_id = cn.id
                " . ($isGlobalAdmin ? "" : "WHERE e.id IN (SELECT ec_inner.employee_id FROM employee_companies ec_inner WHERE ec_inner.is_active = 1 " . str_replace('ec.', 'ec_inner.', $companyFilter) . ")") . "
                GROUP BY cn.name, cn.iso_code, cn.id
                ORDER BY count DESC
            ";
            $stmt = $db->prepare($queryCountry);
            $stmt->execute($params);
            $countryRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Format for frontend
            $data['country_stats'] = array_map(function($row) {
                return [
                    'country_name' => $row['label'],
                    'count' => (int)$row['count'],
                    'active_count' => (int)$row['active_count'],
                    'country_iso' => $row['extra'],
                    'country_id' => $row['id']
                ];
            }, $countryRows);

            // 2. Milestones (Birthdays and Anniversaries in the next 30 days) - 1 trip
            $queryMilestones = "
                SELECT e.id, e.first_name, e.last_name, 
                       CONCAT(e.first_name, ' ', e.last_name) as name,
                       e.date_of_birth as dob, e.hire_date as joining_date, 
                       e.email as personal_email, e.profile_image_path as photo,
                       TIMESTAMPDIFF(YEAR, e.hire_date, CURDATE()) + 1 as anniversary_years,
                       CASE 
                         WHEN (STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                               OR (STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                         THEN 'birthday'
                         WHEN (STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(hire_date, '%m-%d')), '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                               OR (STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', DATE_FORMAT(hire_date, '%m-%d')), '%Y-%m-%d') BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                         THEN 'anniversary'
                       END as type
                FROM employees e
                WHERE e.status = 'active'
                " . ($isGlobalAdmin ? "" : "AND e.id IN (SELECT ec.employee_id FROM employee_companies ec WHERE ec.is_active = 1 $companyFilter)") . "
                HAVING type IS NOT NULL
                ORDER BY 
                    CASE 
                        WHEN type='birthday' THEN 
                            CASE WHEN STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d') < CURDATE()
                                 THEN STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d')
                                 ELSE STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d')
                            END
                        ELSE
                            CASE WHEN STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(hire_date, '%m-%d')), '%Y-%m-%d') < CURDATE()
                                 THEN STR_TO_DATE(CONCAT(YEAR(CURDATE()) + 1, '-', DATE_FORMAT(hire_date, '%m-%d')), '%Y-%m-%d')
                                 ELSE STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', DATE_FORMAT(hire_date, '%m-%d')), '%Y-%m-%d')
                            END
                    END ASC
                LIMIT 10
            ";
            $stmt = $db->prepare($queryMilestones);
            $stmt->execute($params);
            $data['milestones'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($data);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }
    /**
     * Fetch all employees (minimal data)
     */
    public function listEmployees()
    {
        try {
            $context = $_GET['context'] ?? null;
            $db = \Database::getInstance()->getConnection();

            $whereClause = "WHERE 1=1";
            $params = [];
            
            if ($context !== 'onboarding' && $context !== 'dashboard') {
                $whereClause .= " AND e.status NOT IN ('onboarding', 'pending_approval')";
            }

            // Country Filter
            $countryId = $_GET['country_id'] ?? null;
            if ($countryId && $countryId !== 'global') {
                $whereClause .= " AND cn.id = :country_id";
                $params['country_id'] = $countryId;
            }

            // Super Admin Restriction: Invisible to everyone except other Super Admins
            if (!$this->isSuperAdmin()) {
                $whereClause .= " AND NOT EXISTS (
                    SELECT 1 FROM user_roles ur2 
                    WHERE ur2.user_id = u.id AND ur2.role_id = 1
                )";
            }    

            if ($this->isGlobalAdmin()) {
                // Global Admin: No mandatory company isolation
            } else if ($this->hasAnyRole(['HRManager', 'HRAssistant', 'CountryManager', 'COUNTRY MANAGER', 'COUNTRYMANAGER'])) {
                $viewMode = $_SERVER['HTTP_X_VIEW_MODE'] ?? 'admin';

                // Exception: Users in 'employee' directory view can view global public profiles.
                if ($viewMode === 'employee') {
                    // Do not apply company filter, allowing global visibility (masking applied in loop).
                } else {
                    $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                    if (!empty($associatedCompanyIds)) {
                        $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                        $whereClause .= " AND ec.company_id IN ($companyIdList)";
                    } else {
                        $whereClause .= " AND 1=0";
                    }
                }
            }

            $orderBy = "ORDER BY e.first_name ASC";
            if ($context === 'onboarding') {
                $orderBy = "ORDER BY FIELD(e.status, 'onboarding', 'pending_approval', 'active'), e.first_name ASC";
            }

            $stmt = $db->prepare("
                SELECT e.id, 
                       MAX(e.first_name) as first_name, 
                       MAX(e.last_name) as last_name, 
                       MAX(e.employee_code) as employee_code, 
                       MAX(e.email) as email, 
                       MAX(e.status) as status, 
                       MAX(e.hire_date) as hire_date, 
                       MAX(e.employment_type) as employment_type, 
                       MAX(e.custom_data) as custom_data, 
                       MAX(e.phone) as phone, 
                       MAX(e.date_of_birth) as date_of_birth, 
                       MAX(e.nationality) as nationality,
                       MAX(d.name) as department_name,
                       MAX(dg.title) as designation,
                       MAX(dg.level) as designation_level,
                       MAX(u.id) as user_id,
                       MAX(ur.role_id) as role_id,
                       MAX(cn.name) as primary_country,
                       MAX(cn.id) as primary_country_id,
                       MAX(cn.iso_code) as country_iso,
                       MAX(e.profile_image_path) as profile_image_path,
                       MAX(c.id) as primary_company_id
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN designations dg ON e.designation_id = dg.id
                LEFT JOIN users u ON e.id = u.employee_id
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                LEFT JOIN companies c ON ec.company_id = c.id
                LEFT JOIN countries cn ON c.country_id = cn.id
                $whereClause
                GROUP BY e.id
                $orderBy
            ");
            $stmt->execute($params);
            $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $isGlobalAdmin = $this->isGlobalAdmin();
            $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;
            $myCompanyIds = $_SESSION['associated_company_ids'] ?? [];

            foreach ($employees as &$emp) {
                if (isset($emp['custom_data']) && is_string($emp['custom_data'])) {
                    $emp['custom_data'] = json_decode($emp['custom_data'], true) ?: [];
                }

                // Data Masking Logic:
                // 1. Global Admins see everything.
                // 2. Regional Admins see everything within their associated companies.
                // 3. Everyone else (or viewing cross-company) see masked data.
                $canSeeFullDetails = $isGlobalAdmin || in_array($emp['primary_company_id'], $myCompanyIds) || (int)$emp['id'] === (int)$myEmployeeId;

                if (!$canSeeFullDetails) {
                    $emp['phone'] = '***';
                    $emp['personal_phone'] = '***';
                    $emp['personal_email'] = '***';
                    $emp['date_of_birth'] = '***';
                    $emp['nationality'] = '***';
                    $emp['custom_data'] = [];
                }
            }

            return $this->jsonResponse($employees);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Update Employee Salary Structure
     */
    public function updateSalary($requestData)
    {
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN', 'HRMANAGER', 'HR_MANAGER'])) {
            return $this->jsonResponse(null, 403, "Forbidden");
        }

        $employeeId = $requestData['employee_id'] ?? null;
        $this->verifyDataScope(null, null, $employeeId);

        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO salary_structures (employee_id, base_salary, currency_code, effective_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$employeeId, $requestData['base_salary'], $requestData['currency_code'], $requestData['effective_date']]);
            return $this->jsonResponse(['message' => 'Salary structure updated successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Upload and update professional profile photo
     */
    public function uploadProfilePhoto($employeeId)
    {
        $this->verifyDataScope(null, null, $employeeId);
        if (!isset($_FILES['photo'])) {
            return $this->jsonResponse(null, 400, "No photo file provided.");
        }

        $uploadResult = \App\Helpers\UploadHelper::upload($_FILES['photo'], 'avatars');
        if (!$uploadResult['success']) {
            return $this->jsonResponse(null, 400, $uploadResult['message']);
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->prepare("UPDATE employees SET profile_image_path = ? WHERE id = ?")->execute([$uploadResult['file_path'], $employeeId]);
            return $this->jsonResponse(['message' => 'Profile photo updated successfully.', 'file_path' => $uploadResult['file_path']]);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Delete professional profile photo
     */
    public function deleteProfilePhoto($employeeId)
    {
        $this->verifyDataScope(null, null, $employeeId);
        try {
            $db = \Database::getInstance()->getConnection();
            $db->prepare("UPDATE employees SET profile_image_path = NULL WHERE id = ?")->execute([$employeeId]);
            return $this->jsonResponse(['message' => 'Profile photo deleted successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Send Welcome Email manually
     */
    public function sendWelcomeEmail($id)
    {
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN', 'HRMANAGER', 'HR_MANAGER'])) {
            return $this->jsonResponse(null, 403, "Forbidden: Only admins can send welcome emails.");
        }

        try {
            $this->employeeService->sendWelcomeEmail((int)$id);
            return $this->jsonResponse(['message' => 'Welcome email sent successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }
}

