<?php

namespace App\Controllers;

use App\Helpers\CustomFieldValidator;
use App\Core\Controller;

/**
 * EmployeeController
 * 
 * Handles employee profiles and custom dynamic data.
 */
class EmployeeController extends Controller
{
    public function __construct()
    {
        // Constructor maintained for future middleware initialization
    }
    /**
     * Fetch a specific employee by ID
     */
    public function getEmployee($id)
    {
        // Allow public read access to basic profile data
        // We skip the strict self-only check but keep general company/country context if applicable
        $this->verifyDataScope(null, null, null); 
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT e.*, 
                       d.name as department_name,
                       dg.title as designation_title,
                       rm.first_name as manager_first_name, rm.last_name as manager_last_name,
                       ur.role_id
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN designations dg ON e.designation_id = dg.id
                LEFT JOIN employees rm ON e.reporting_manager_id = rm.id
                LEFT JOIN users u ON e.id = u.employee_id
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                WHERE e.id = :id
            ");
            $stmt->execute(['id' => $id]);
            $employee = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$employee) {
                return $this->jsonResponse(null, 404, 'Employee not found');
            }

            // Fetch associated companies (active only, plus deactivated for history)
            $compStmt = $db->prepare("
                SELECT c.id, c.name, cn.name as country_name, cn.iso_code, cn.currency_code, ec.is_primary, ec.is_active, ec.deactivated_at_utc 
                FROM companies c
                JOIN employee_companies ec ON ec.company_id = c.id
                JOIN countries cn ON c.country_id = cn.id
                WHERE ec.employee_id = :id
                ORDER BY ec.is_active DESC, ec.is_primary DESC
            ");
            $compStmt->execute(['id' => $id]);
            $employee['companies'] = $compStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Security Masking: For public profile view (non-admins viewing others)
            $myEmployeeId = $_SESSION['scope_employee_id'] ?? null;
            $isAdmin = $this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN', 'HRMANAGER', 'HR_MANAGER']);
            
            if (!$isAdmin && $employee['id'] != $myEmployeeId) {
                // Mask sensitive identification and financial fields
                $employee['tin_number'] = '***';
                $employee['nssf_number'] = '***';
                $employee['bank_account_no'] = '***';
                $employee['bank_name'] = '***';
                
            // Redact custom data entirely for public view unless it's their own profile
                // This ensures company-specific sensitive fields aren't leaked
                $employee['custom_data'] = [];
            } else {
                // Ensure custom_data is an array if it's a JSON string from DB
                if (isset($employee['custom_data']) && is_string($employee['custom_data'])) {
                    $employee['custom_data'] = json_decode($employee['custom_data'], true) ?: [];
                }
            }

            return $this->jsonResponse($employee);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
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

        $companyIds = $requestData['company_ids'] ?? [];

        // Validation for the primary company (first in list)
        $primaryCompanyId = $companyIds[0] ?? null;
        if ($primaryCompanyId) {
            $this->verifyDataScope($primaryCompanyId, null, $employeeId);
        }

        $db = \Database::getInstance()->getConnection();

        $customFieldsPayload = $requestData['custom_fields'] ?? [];
        // Defensive: If custom_fields is received as a JSON string, decode it
        if (is_string($customFieldsPayload)) {
            $customFieldsPayload = json_decode($customFieldsPayload, true) ?: [];
        }

        $stmt = $db->prepare("SELECT custom_data FROM employees WHERE id = :id");
        $stmt->execute(['id' => $employeeId]);
        $existingCustomDataRaw = $stmt->fetchColumn();
        $existingCustomData = is_string($existingCustomDataRaw) ? (json_decode($existingCustomDataRaw, true) ?: []) : [];
        $mergedCustomData = array_merge($existingCustomData, $customFieldsPayload);

        if ($primaryCompanyId) {
            $companyCustomFields = $this->getCompanyCustomFields($primaryCompanyId);
            $validationResult = CustomFieldValidator::validatePayload($mergedCustomData, $companyCustomFields);
            if (!$validationResult['is_valid']) {
                return $this->jsonResponse(['errors' => $validationResult['errors']], 400, 'Custom field validation failed');
            }
        }

        $reportingManagerId = !empty($requestData['reporting_manager_id']) ? $requestData['reporting_manager_id'] : null;
        if ($reportingManagerId !== null) {
            $isCycle = $this->checkManagerHierarchyCycle($employeeId, $reportingManagerId);
            if ($isCycle) {
                return $this->jsonResponse(null, 400, 'Hierarchy validation failed: Circular dependency detected.');
            }
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // Build dynamic update query
            $allowedFields = [
                'first_name',
                'last_name',
                'email',
                'phone',
                'date_of_birth',
                'gender',
                'nationality',
                'tin_number',
                'nssf_number',
                'bank_account_no',
                'bank_name',
                'employment_type',
                'status',
                'department_id',
                'designation_id',
                'reporting_manager_id',
                'profile_image_path',
                'hire_date',
                'employee_code'
            ];

            $updates = [];
            $params = ['id' => $employeeId];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $requestData)) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $requestData[$field] === '' ? null : $requestData[$field];
                }
            }

            // Sync multi-company memberships (soft-deactivation — never delete rows)
            if (isset($requestData['company_ids'])) {
                $primaryId = $requestData['primary_company_id'] ?? ($companyIds[0] ?? null);

                // 1. Fetch all existing links for this employee
                $existStmt = $db->prepare("SELECT company_id, is_active FROM employee_companies WHERE employee_id = ?");
                $existStmt->execute([$employeeId]);
                $existingLinks = $existStmt->fetchAll(\PDO::FETCH_KEY_PAIR); // company_id => is_active

                // 2. Deactivate links NOT in the incoming list (soft-delete)
                foreach ($existingLinks as $existCompId => $isActive) {
                    if (!in_array($existCompId, $companyIds)) {
                        if ($isActive) {
                            $db->prepare("
                                UPDATE employee_companies 
                                SET is_active = 0, is_primary = 0, deactivated_at_utc = CURRENT_TIMESTAMP 
                                WHERE employee_id = ? AND company_id = ?
                            ")->execute([$employeeId, $existCompId]);
                        }
                    }
                }

                // 3. Upsert active links (reactivate or insert)
                foreach ($companyIds as $compId) {
                    $isPrimary = ($compId == $primaryId) ? 1 : 0;
                    if (array_key_exists($compId, $existingLinks)) {
                        // Existing link — reactivate if needed and update primary flag
                        $db->prepare("
                            UPDATE employee_companies 
                            SET is_active = 1, is_primary = ?, deactivated_at_utc = NULL 
                            WHERE employee_id = ? AND company_id = ?
                        ")->execute([$isPrimary, $employeeId, $compId]);
                    } else {
                        // Brand new link
                        $db->prepare("
                            INSERT INTO employee_companies (employee_id, company_id, is_primary, is_active) 
                            VALUES (?, ?, ?, 1)
                        ")->execute([$employeeId, $compId, $isPrimary]);
                    }
                }
            }

            // Merge custom data
            if (isset($requestData['custom_fields'])) {
                $updates[] = "custom_data = :custom_data";
                $params['custom_data'] = json_encode($mergedCustomData);
            }

            if (!empty($updates)) {
                $query = "UPDATE employees SET " . implode(", ", $updates) . " WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->execute($params);
            }

            // Noah Audit Fix: Appraisal Manager Reassignment (Orphaning Prevention)
            if (isset($requestData['status']) && in_array($requestData['status'], ['inactive', 'offboarding'])) {
                $escStmt = $db->prepare("SELECT reporting_manager_id FROM employees WHERE id = :id");
                $escStmt->execute(['id' => $employeeId]);
                $escalationManagerId = $escStmt->fetchColumn();

                if ($escalationManagerId) {
                    $reassignStmt = $db->prepare("
                        UPDATE employee_appraisals 
                        SET manager_id = :new_manager 
                        WHERE manager_id = :old_manager AND status IN ('draft', 'manager_review')
                    ");
                    $reassignStmt->execute([
                        'new_manager' => $escalationManagerId,
                        'old_manager' => $employeeId
                    ]);
                }
            }

            // Noah Audit Fix: Explicit RBAC management in profile update
            if (isset($requestData['role_id'])) {
                $roleId = $requestData['role_id'];

                // Security Enforcement: Only Super Admins can assign the Super Admin role (ID 1)
                $dbRoleStmt = $db->prepare("SELECT name FROM roles WHERE id = ?");
                $dbRoleStmt->execute([$roleId]);
                $targetRoleName = strtoupper($dbRoleStmt->fetchColumn() ?: '');
                
                if (($targetRoleName === 'SUPERADMIN' || $targetRoleName === 'SUPER_ADMIN') && !$this->isSuperAdmin()) {
                    return $this->jsonResponse(null, 403, "Security Violation: Only Super Admins can assign the Super Admin role.");
                }

                // Get user id for this employee
                $usrStmt = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
                $usrStmt->execute([$employeeId]);
                $userId = $usrStmt->fetchColumn();

                if ($userId) {
                    // Force single-role assignment (Noah Audit Fix: prevent duplication)
                    $db->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
                    $roleIns = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $roleIns->execute([$userId, $roleId]);
                }
            }

            $db->commit();
            return $this->jsonResponse(['message' => 'Employee profile updated successfully.']);

        } catch (\Exception $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            return $this->jsonResponse(null, 500, "Database error during update: " . $e->getMessage());
        }
    }
    /**
     * Store a new employee (Onboarding)
     * Uses positional placeholders to prevent any named-parameter collision.
     */
    public function save($requestData)
    {
        $companyIds = $requestData['company_ids'] ?? [];
        $primaryCompanyId = $companyIds[0] ?? null;

        // Context check using primary company
        $this->verifyDataScope($primaryCompanyId, null, null);

        $customFieldsPayload = $requestData['custom_fields'] ?? [];
        // Defensive: If custom_fields is received as a JSON string, decode it
        if (is_string($customFieldsPayload)) {
            $customFieldsPayload = json_decode($customFieldsPayload, true) ?: [];
        }

        $companyCustomFields = $this->getCompanyCustomFields($primaryCompanyId);
        $validationResult = CustomFieldValidator::validatePayload($customFieldsPayload, $companyCustomFields);

        if (!$validationResult['is_valid']) {
            return $this->jsonResponse(['errors' => $validationResult['errors']], 400, 'Custom field validation failed');
        }

        $reportingManagerId = !empty($requestData['reporting_manager_id']) ? $requestData['reporting_manager_id'] : null;

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            $firstName = $requestData['first_name'] ?? '';
            $lastName = $requestData['last_name'] ?? '';
            $email = $requestData['email'] ?? '';
            $status = $requestData['status'] ?? 'onboarding';

            // Positional placeholders to prevent any named-parameter collision
            $empStmt = $db->prepare("
                INSERT INTO employees (
                    employee_code, reporting_manager_id, first_name, last_name, 
                    email, phone, date_of_birth, gender, nationality, 
                    tin_number, nssf_number, bank_account_no, bank_name,
                    employment_type, hire_date, status, custom_data,
                    department_id, designation_id
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $employeeCode = !empty($requestData['employee_code']) 
                ? $requestData['employee_code'] 
                : 'EMP-' . strtoupper(bin2hex(random_bytes(3)));

            $empStmt->execute([
                $employeeCode,
                $reportingManagerId,
                $firstName,
                $lastName,
                $email,
                $requestData['phone'] ?? null,
                $requestData['date_of_birth'] ?? null,
                $requestData['gender'] ?? null,
                $requestData['nationality'] ?? null,
                $requestData['tin_number'] ?? null,
                $requestData['nssf_number'] ?? null,
                $requestData['bank_account_no'] ?? null,
                $requestData['bank_name'] ?? null,
                $requestData['employment_type'] ?? 'full_time',
                $requestData['hire_date'] ?? date('Y-m-d'),
                $status,
                json_encode($customFieldsPayload),
                !empty($requestData['department_id']) ? $requestData['department_id'] : null,
                !empty($requestData['designation_id']) ? $requestData['designation_id'] : null
            ]);

            $newEmployeeId = $db->lastInsertId();

            // Insert Many-to-Many company memberships (first company = primary)
            $insComp = $db->prepare("INSERT INTO employee_companies (employee_id, company_id, is_primary, is_active) VALUES (?, ?, ?, 1)");
            foreach ($companyIds as $index => $compId) {
                $insComp->execute([$newEmployeeId, $compId, ($index === 0) ? 1 : 0]);
            }

            $rawPassword = bin2hex(random_bytes(6));
            $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);

            $usrStmt = $db->prepare("
                INSERT INTO users (employee_id, username, password_hash) 
                VALUES (?, ?, ?)
            ");
            $usrStmt->execute([$newEmployeeId, $email, $hashedPassword]);

            $newUserId = $db->lastInsertId();

            // Noah Audit Fix: Explicit RBAC assignment during creation
            $roleId = $requestData['role_id'] ?? 6; // Default to EMPLOYEE (ID 6) if not specified
            
            // Security Enforcement: Only Super Admins can assign the Super Admin role (ID 1)
            $dbRoleStmt = $db->prepare("SELECT name FROM roles WHERE id = ?");
            $dbRoleStmt->execute([$roleId]);
            $targetRoleName = strtoupper($dbRoleStmt->fetchColumn() ?: '');

            if (($targetRoleName === 'SUPER_ADMIN' || $targetRoleName === 'SUPERADMIN') && !$this->isSuperAdmin()) {
                return $this->jsonResponse(null, 403, "Security Violation: Only Super Admins can assign the Super Admin role.");
            }

            $roleStmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
            $roleStmt->execute([$newUserId, $roleId]);

            // Initialize Leave Balances for the current year based on company policies
            if ($primaryCompanyId) {
                $year = date('Y');
                $policyStmt = $db->prepare("SELECT leave_type_id, default_days_per_year FROM company_leave_policies WHERE company_id = ?");
                $policyStmt->execute([$primaryCompanyId]);
                $policies = $policyStmt->fetchAll(\PDO::FETCH_ASSOC);

                $balIns = $db->prepare("INSERT IGNORE INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days) VALUES (?, ?, ?, ?, 0)");
                foreach ($policies as $p) {
                    $balIns->execute([$newEmployeeId, $p['leave_type_id'], $year, $p['default_days_per_year']]);
                }
            }

            $db->commit();

            return $this->jsonResponse([
                'employee_id' => $newEmployeeId,
                'auto_generated_password' => $rawPassword,
                'message' => 'Employee onboarded securely.'
            ]);

        } catch (\Exception $e) {
            if (isset($db))
                $db->rollBack();
            return $this->jsonResponse(null, 500, "Database fault during onboarding: " . $e->getMessage());
        }
    }

    /**
     * Prevent infinite loops in reporting hierarchies
     * Example: A -> B -> C -> A
     */
    private function checkManagerHierarchyCycle($employeeId, $proposedManagerId)
    {
        if (empty($employeeId) || empty($proposedManagerId)) {
            return false; // New employees or those without a manager cannot have circles initially
        }

        if ($employeeId == $proposedManagerId) {
            return true; // Cannot report to self
        }

        // Climb the tree to verify the proposed manager doesn't eventually report back to target employeeId
        try {
            $db = \Database::getInstance()->getConnection();
            $currentManagerId = $proposedManagerId;
            $safeguardDepth = 0; // Prevent runaway query loops on massive trees
            $MAX_DEPTH = 50;

            while ($currentManagerId !== null && $safeguardDepth < $MAX_DEPTH) {
                if ($currentManagerId == $employeeId) {
                    return true; // Cycle detected
                }

                $stmt = $db->prepare("SELECT reporting_manager_id FROM employees WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $currentManagerId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$row || empty($row['reporting_manager_id'])) {
                    break; // Reached the top of the tree (CEO etc)
                }

                $currentManagerId = $row['reporting_manager_id'];
                $safeguardDepth++;
            }
        } catch (\Exception $e) {
            // Failsafe: If DB fails, block the operation rather than allowing a silent loop
            return true;
        }

        return false;
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
            // Using NOT EXISTS to ensure anyone with role_id 1 is hidden, even if they have other roles
            if (!$this->isSuperAdmin()) {
                $whereClause .= " AND NOT EXISTS (
                    SELECT 1 FROM user_roles ur2 
                    WHERE ur2.user_id = u.id AND ur2.role_id = 1
                )";
            }    
            if ($this->isGlobalAdmin()) {
                // Global Admin: No mandatory company isolation (can see all countries/companies)
            } else if ($this->hasAnyRole(['HRManager', 'HRAssistant', 'CountryManager', 'COUNTRY MANAGER'])) {

                $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;

                if (!empty($associatedCompanyIds)) {
                    $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                    $whereClause .= " AND ec.company_id IN ($companyIdList)";
                } else if ($this->hasAnyRole(['CountryManager', 'COUNTRY MANAGER']) && $sessionCountryId) {
                    $whereClause .= " AND EXISTS (SELECT 1 FROM companies c2 WHERE ec.company_id = c2.id AND c2.country_id = :session_country_id)";
                    $params['session_country_id'] = $sessionCountryId;
                } else {
                    $whereClause .= " AND 1=0";
                }
            } else {
                // Global Directory Requirement: Standard employees can see all colleagues across the organization.
                // We bypass mandatory company isolation to provide a unified directory experience.
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
                       MAX(cn.iso_code) as country_iso,
                       MAX(e.profile_image_path) as profile_image_path
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
            $t_start = microtime(true);
            $stmt->execute($params);
            $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Standardize JSON decoding for custom_data fields
            foreach ($employees as &$emp) {
                if (isset($emp['custom_data']) && is_string($emp['custom_data'])) {
                    $emp['custom_data'] = json_decode($emp['custom_data'], true) ?: [];
                }
            }

            // Wrap in standard JSON response format
            return $this->jsonResponse($employees);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Mock function to fetch field definitions instead of reading DB for now
     */
    private function getCompanyCustomFields($companyId)
    {
        if (!$companyId) return [];
        try {
            $db = \Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM company_custom_fields WHERE company_id = ? ORDER BY display_order ASC");
            $stmt->execute([$companyId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Update Employee Salary Structure (Noah Audit Fix: Non-Destructive Ledger)
     */
    public function updateSalary($requestData)
    {
        $employeeId = $requestData['employee_id'] ?? null;
        $this->verifyDataScope(null, null, $employeeId);

        $baseSalary = $requestData['base_salary'] ?? null;
        $currencyCode = $requestData['currency_code'] ?? null;
        $effectiveDate = $requestData['effective_date'] ?? null;

        if (!$employeeId || !$baseSalary || !$currencyCode || !$effectiveDate) {
            return $this->jsonResponse(null, 400, "Employee ID, Base Salary, Currency, and Effective Date are required.");
        }

        try {
            $db = \Database::getInstance()->getConnection();
            $db->beginTransaction();

            // 1. Close current active salary structure
            $closeStmt = $db->prepare("
                UPDATE salary_structures 
                SET end_date = DATE_SUB(:effective_date, INTERVAL 1 DAY) 
                WHERE employee_id = :eid AND end_date IS NULL
            ");
            $closeStmt->execute([
                'effective_date' => $effectiveDate,
                'eid' => $employeeId
            ]);

            // 2. Insert new active salary structure
            $insertStmt = $db->prepare("
                INSERT INTO salary_structures (employee_id, base_salary, currency_code, effective_date)
                VALUES (:eid, :salary, :currency, :effective_date)
            ");
            $insertStmt->execute([
                'eid' => $employeeId,
                'salary' => $baseSalary,
                'currency' => $currencyCode,
                'effective_date' => $effectiveDate
            ]);

            $db->commit();
            return $this->jsonResponse(['message' => 'Salary structure updated successfully (Historical records preserved).']);

        } catch (\Exception $e) {
            if (isset($db))
                $db->rollBack();
            return $this->jsonResponse(null, 500, "Database error during salary update: " . $e->getMessage());
        }
    }

    /**
     * Upload and update professional profile photo
     */
    public function uploadProfilePhoto($employeeId)
    {
        if (!$employeeId) {
            return $this->jsonResponse(null, 400, "Employee ID is required.");
        }

        // Security Scope Check
        $this->verifyDataScope(null, null, $employeeId);

        if (!isset($_FILES['photo'])) {
            return $this->jsonResponse(null, 400, "No photo file provided.");
        }

        // Use UploadHelper for consistent validation and handling
        $uploadResult = \App\Helpers\UploadHelper::upload($_FILES['photo'], 'avatars', [
            'prefix' => 'avatar_' . $employeeId . '_'
        ]);

        if (!$uploadResult['success']) {
            return $this->jsonResponse(null, 400, $uploadResult['message']);
        }

        $dbFilePath = $uploadResult['file_path'];

        try {
            $db = \Database::getInstance()->getConnection();
            
            // Get old photo path to delete it
            $stmt = $db->prepare("SELECT profile_image_path FROM employees WHERE id = ?");
            $stmt->execute([$employeeId]);
            $oldPath = $stmt->fetchColumn();

            // Update employee record
            $updStmt = $db->prepare("UPDATE employees SET profile_image_path = ? WHERE id = ?");
            $updStmt->execute([$dbFilePath, $employeeId]);

            // Cleanup old photo if update was successful
            if ($oldPath) {
                \App\Helpers\UploadHelper::delete($oldPath);
            }

            return $this->jsonResponse([
                'message' => 'Profile photo updated successfully.', 
                'file_path' => $dbFilePath
            ]);
        } catch (\Exception $e) {
            // Clean up the newly uploaded file if DB update fails
            \App\Helpers\UploadHelper::delete($dbFilePath);
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
        }
    }

    /**
     * Delete professional profile photo
     */
    public function deleteProfilePhoto($employeeId)
    {
        if (!$employeeId) {
            return $this->jsonResponse(null, 400, "Employee ID is required.");
        }

        // Security Scope Check
        $this->verifyDataScope(null, null, $employeeId);

        try {
            $db = \Database::getInstance()->getConnection();
            
            // Get old photo path to delete it
            $stmt = $db->prepare("SELECT profile_image_path FROM employees WHERE id = ?");
            $stmt->execute([$employeeId]);
            $oldPath = $stmt->fetchColumn();

            if (!$oldPath) {
                return $this->jsonResponse(null, 404, "No profile photo found to delete.");
            }

            // Update employee record to NULL
            $updStmt = $db->prepare("UPDATE employees SET profile_image_path = NULL WHERE id = ?");
            $updStmt->execute([$employeeId]);

            // Cleanup physical file
            \App\Helpers\UploadHelper::delete($oldPath);

            return $this->jsonResponse(['message' => 'Profile photo deleted successfully.']);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Database error: " . $e->getMessage());
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
                'status_stats' => [],
                'country_stats' => [],
                'milestones' => []
            ];

            $params = [];
            $companyFilter = "";
            $isGlobalAdmin = $this->isGlobalAdmin();
            if (!$isGlobalAdmin) {
                $isMultiOffice = $this->hasAnyRole(['HRManager', 'HRAssistant', 'CountryManager', 'COUNTRY MANAGER']);

                $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;

                if ($isMultiOffice && !empty($associatedCompanyIds)) {
                    $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                    $companyFilter = " AND ec.company_id IN ($companyIdList)";
                } else if ($this->hasAnyRole(['CountryManager', 'COUNTRY MANAGER']) && $sessionCountryId) {
                    // Fallback for Country Managers: Filter by Country ID via Company join
                    $companyFilter = " AND EXISTS (SELECT 1 FROM companies c2 WHERE ec.company_id = c2.id AND c2.country_id = :session_country_id)";
                    $params['session_country_id'] = $sessionCountryId;
                } else {
                    $sessionCompanyId = $_SESSION['scope_company_id'] ?? null;
                    if ($sessionCompanyId) {
                        $companyFilter = " AND ec.company_id = :session_company_id";
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


            $queryConsolidated = "
                SELECT 'status' as metric, e.status as label, NULL as extra, NULL as id, COUNT(*) as count
                FROM employees e
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                WHERE 1=1 $companyFilter
                GROUP BY e.status
                
                UNION ALL
                
                SELECT 'country' as metric, cn.name as label, cn.iso_code as extra, cn.id as id, COUNT(*) as count
                FROM employees e
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                JOIN companies comp ON ec.company_id = comp.id
                JOIN countries cn ON comp.country_id = cn.id
                WHERE 1=1 $companyFilter
                GROUP BY cn.name, cn.iso_code, cn.id
            ";

            $stmt = $db->prepare($queryConsolidated);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                if ($row['metric'] === 'status') {
                    $data['status_stats'][] = ['status' => $row['label'], 'count' => (int)$row['count']];
                } else {
                    $data['country_stats'][] = ['country_id' => $row['id'], 'country_name' => $row['label'], 'country_iso' => $row['extra'], 'count' => (int)$row['count']];
                }
            }

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
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
                WHERE e.status = 'active' $companyFilter
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
}
