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
        // Allow public read access (frontend strictly governs visual data masking)
        $this->verifyDataScope(null, null, $id);
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

            // Fetch associated companies
            $compStmt = $db->prepare("
                SELECT c.id, c.name, cn.currency_code, ec.is_primary 
                FROM companies c
                JOIN employee_companies ec ON ec.company_id = c.id
                JOIN countries cn ON c.country_id = cn.id
                WHERE ec.employee_id = :id
            ");
            $compStmt->execute(['id' => $id]);
            $employee['companies'] = $compStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Parse custom data back to JSON object
            if ($employee['custom_data']) {
                $employee['custom_data'] = json_decode($employee['custom_data'], true);
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
        $stmt = $db->prepare("SELECT custom_data FROM employees WHERE id = :id");
        $stmt->execute(['id' => $employeeId]);
        $existingCustomData = json_decode($stmt->fetchColumn() ?: '{}', true) ?: [];
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
                'hire_date'
            ];

            $updates = [];
            $params = ['id' => $employeeId];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $requestData)) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $requestData[$field] === '' ? null : $requestData[$field];
                }
            }

            // Sync multi-company memberships
            if (isset($requestData['company_ids'])) {
                $primaryId = $requestData['primary_company_id'] ?? ($companyIds[0] ?? null);
                $db->prepare("DELETE FROM employee_companies WHERE employee_id = ?")->execute([$employeeId]);
                $insComp = $db->prepare("INSERT INTO employee_companies (employee_id, company_id, is_primary) VALUES (?, ?, ?)");
                foreach ($companyIds as $compId) {
                    $isPrimary = ($compId == $primaryId);
                    $insComp->execute([$employeeId, $compId, $isPrimary ? 1 : 0]);
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
     * Store a new employee (or update an existing one)
     */
    public function save($requestData)
    {
        $companyIds = $requestData['company_ids'] ?? [];
        $employeeId = $requestData['id'] ?? null;

        // Context check using primary company
        $primaryCompanyId = $companyIds[0] ?? null;
        $this->verifyDataScope($primaryCompanyId, null, $employeeId);

        $customFieldsPayload = $requestData['custom_fields'] ?? [];
        $companyCustomFields = $this->getCompanyCustomFields($primaryCompanyId);
        $validationResult = CustomFieldValidator::validatePayload($customFieldsPayload, $companyCustomFields);

        if (!$validationResult['is_valid']) {
            return $this->jsonResponse(['errors' => $validationResult['errors']], 400, 'Custom field validation failed');
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

            $firstName = $requestData['first_name'] ?? '';
            $lastName = $requestData['last_name'] ?? '';
            $email = $requestData['email'] ?? '';
            $status = $requestData['status'] ?? 'onboarding';

            $empStmt = $db->prepare("
                INSERT INTO employees (
                    employee_code, reporting_manager_id, first_name, last_name, 
                    email, phone, date_of_birth, gender, nationality, 
                    tin_number, nssf_number, bank_account_no, bank_name,
                    employment_type, hire_date, status, custom_data,
                    department_id, designation_id
                ) 
                VALUES (
                    :emp_code, :manager_id, :first_name, :last_name, 
                    :email, :phone, :dob, :gender, :nationality, 
                    :tin, :nssf, :bank_acc, :bank_name,
                    :emp_type, :hire_date, :status, :custom_data,
                    :dept_id, :desig_id
                )
            ");

            $empStmt->execute([
                'emp_code' => 'EMP-' . strtoupper(bin2hex(random_bytes(3))),
                'manager_id' => $reportingManagerId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $requestData['phone'] ?? null,
                'dob' => $requestData['date_of_birth'] ?? null,
                'gender' => $requestData['gender'] ?? null,
                'nationality' => $requestData['nationality'] ?? null,
                'tin' => $requestData['tin_number'] ?? null,
                'nssf' => $requestData['nssf_number'] ?? null,
                'bank_acc' => $requestData['bank_account_no'] ?? null,
                'bank_name' => $requestData['bank_name'] ?? null,
                'emp_type' => $requestData['employment_type'] ?? 'full_time',
                'hire_date' => $requestData['hire_date'] ?? date('Y-m-d'),
                'status' => $status,
                'custom_data' => json_encode($customFieldsPayload),
                'dept_id' => !empty($requestData['department_id']) ? $requestData['department_id'] : null,
                'desig_id' => !empty($requestData['designation_id']) ? $requestData['designation_id'] : null
            ]);

            $newEmployeeId = $db->lastInsertId();

            // Insert Many-to-Many memberships
            $primaryId = $requestData['primary_company_id'] ?? ($companyIds[0] ?? null);
            $insComp = $db->prepare("INSERT INTO employee_companies (employee_id, company_id, is_primary) VALUES (?, ?, ?)");
            foreach ($companyIds as $compId) {
                $isPrimary = ($compId == $primaryId);
                $insComp->execute([$newEmployeeId, $compId, $isPrimary ? 1 : 0]);
            }

            $rawPassword = bin2hex(random_bytes(6));
            $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);

            $usrStmt = $db->prepare("
                INSERT INTO users (employee_id, username, password_hash) 
                VALUES (:emp_id, :username, :password)
            ");

            $usrStmt->execute([
                'emp_id' => $newEmployeeId,
                'username' => $email,
                'password' => $hashedPassword
            ]);

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
            
            if ($context !== 'onboarding') {
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
                $whereClause .= " AND (ur.role_id IS NULL OR ur.role_id != 1)";
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
                       MAX(cn.iso_code) as country_iso
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN designations dg ON e.designation_id = dg.id
                LEFT JOIN users u ON e.id = u.employee_id
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
                LEFT JOIN companies c ON ec.company_id = c.id
                LEFT JOIN countries cn ON c.country_id = cn.id
                $whereClause
                GROUP BY e.id
                $orderBy
            ");
            $stmt->execute($params);
            $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
}
