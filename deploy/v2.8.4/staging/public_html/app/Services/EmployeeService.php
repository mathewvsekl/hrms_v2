<?php

namespace App\Services;

use App\Helpers\CustomFieldValidator;
use App\Helpers\ApprovalHelper;
use PDO;

/**
 * EmployeeService
 * 
 * Centralized business logic for employee management, multi-company associations,
 * and data scoping enforcement.
 */
class EmployeeService
{
    private $db;
    private $auditService;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->auditService = new AuditService();
    }

    /**
     * Retrieves detailed employee profile including associated companies
     */
    public function getEmployeeDetail(int $id, ?int $requestorEmployeeId = null, bool $isAdmin = false): array
    {
        $stmt = $this->db->prepare("
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
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            return [];
        }

        // Fetch associated companies
        $compStmt = $this->db->prepare("
            SELECT c.id, c.name, cn.name as country_name, cn.iso_code, cn.currency_code, ec.is_primary, ec.is_active, ec.deactivated_at_utc 
            FROM companies c
            JOIN employee_companies ec ON ec.company_id = c.id
            JOIN countries cn ON c.country_id = cn.id
            WHERE ec.employee_id = :id
            ORDER BY ec.is_active DESC, ec.is_primary DESC
        ");
        $compStmt->execute(['id' => $id]);
        $employee['companies'] = $compStmt->fetchAll(PDO::FETCH_ASSOC);

        // Security Masking for non-admins
        if (!$isAdmin && (int)$employee['id'] !== $requestorEmployeeId) {
            $this->maskSensitiveData($employee);
        } else {
            if (isset($employee['custom_data']) && is_string($employee['custom_data'])) {
                $employee['custom_data'] = json_decode($employee['custom_data'], true) ?: [];
            }
        }

        return $employee;
    }

    /**
     * Masks PII and financial data for public profile views
     */
    private function maskSensitiveData(array &$employee): void
    {
        $sensitiveFields = ['bank_account_no', 'bank_name', 'phone', 'date_of_birth', 'nationality'];
        foreach ($sensitiveFields as $field) {
            $employee[$field] = '***';
        }
        $employee['custom_data'] = [];
    }

    /**
     * Updates an employee profile with multi-company sync and hierarchy validation
     */
    public function updateEmployee(int $employeeId, array $data, bool $isAdmin = false): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Hierarchy Cycle Check
            if (!empty($data['reporting_manager_id'])) {
                if ($this->checkHierarchyCycle($employeeId, (int)$data['reporting_manager_id'])) {
                    throw new \Exception("Circular dependency detected in reporting hierarchy.");
                }
            }

            // 2. Custom Field Validation
            $primaryCompanyId = $data['company_ids'][0] ?? null;
            if ($primaryCompanyId) {
                $customFields = $this->getCompanyCustomFields((int)$primaryCompanyId);
                $mergedData = $this->getMergedCustomData($employeeId, $data['custom_fields'] ?? []);
                $validation = CustomFieldValidator::validatePayload($mergedData, $customFields);
                if (!$validation['is_valid']) {
                    throw new \Exception("Custom field validation failed: " . implode(", ", $validation['errors']));
                }
                $data['custom_data'] = json_encode($mergedData);
            }

            // 3. Update Employee Record
            $allowedFields = [
                'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'gender', 
                'nationality', 'bank_account_no', 'bank_name', 'employment_type', 'status', 
                'department_id', 'designation_id', 'reporting_manager_id', 'profile_image_path', 
                'hire_date', 'employee_code', 'job_description', 'custom_data'
            ];

            $updates = [];
            $params = ['id' => $employeeId];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = :$field";
                    $params[$field] = $data[$field] === '' ? null : $data[$field];
                }
            }

            if (!empty($updates)) {
                $stmt = $this->db->prepare("UPDATE employees SET " . implode(", ", $updates) . " WHERE id = :id");
                $stmt->execute($params);
            }

            // 4. Multi-Company Sync
            if (isset($data['company_ids'])) {
                $this->syncCompanies($employeeId, $data['company_ids'], $data['primary_company_id'] ?? null);
            }

            // 5. RBAC Update (Admin Only)
            if ($isAdmin && isset($data['role_id'])) {
                $this->syncUserRole($employeeId, (int)$data['role_id']);
            }

            // 6. Appraisal Reassignment (if employee leaving)
            if (isset($data['status']) && in_array($data['status'], ['inactive', 'offboarding'])) {
                $this->reassignPendingAppraisals($employeeId);
            }

            // 7. Approval History Logging
            if (isset($data['status'])) {
                $action = 'updated';
                if ($data['status'] === 'active') $action = 'approved';
                if ($data['status'] === 'onboarding') $action = 'rejected';
                if ($data['status'] === 'pending_approval') $action = 'submitted';
                
                ApprovalHelper::log('onboarding', $employeeId, $action, $data['comment'] ?? null);
            }

            $this->auditService->log(
                'UPDATE',
                'employees',
                $employeeId,
                null, // In a real scenario, we would fetch and diff old values
                $data
            );

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Handles employee onboarding, creating the profile, user account, and initial office links.
     */
    public function createEmployee(array $data): int
    {
        try {
            $this->db->beginTransaction();

            $primaryCompanyId = $data['company_ids'][0] ?? null;
            if (!$primaryCompanyId) throw new \Exception("At least one company must be associated.");

            // 1. Custom Field Validation
            $customFields = $this->getCompanyCustomFields((int)$primaryCompanyId);
            $validation = CustomFieldValidator::validatePayload($data['custom_fields'] ?? [], $customFields);
            if (!$validation['is_valid']) {
                throw new \Exception("Custom field validation failed: " . implode(", ", $validation['errors']));
            }

            // 2. Create Employee
            $stmt = $this->db->prepare("
                INSERT INTO employees (
                    employee_code, reporting_manager_id, first_name, last_name, 
                    email, phone, date_of_birth, gender, nationality, 
                    bank_account_no, bank_name,
                    employment_type, job_description, hire_date, status, custom_data,
                    department_id, designation_id
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $employeeCode = !empty($data['employee_code']) 
                ? $data['employee_code'] 
                : 'EMP-' . strtoupper(bin2hex(random_bytes(3)));

            $stmt->execute([
                $employeeCode,
                !empty($data['reporting_manager_id']) ? $data['reporting_manager_id'] : null,
                $data['first_name'] ?? '',
                $data['last_name'] ?? '',
                $data['email'] ?? '',
                (!empty($data['phone']) ? $data['phone'] : null),
                (!empty($data['date_of_birth']) ? $data['date_of_birth'] : null),
                (!empty($data['gender']) ? $data['gender'] : null),
                (!empty($data['nationality']) ? $data['nationality'] : null),
                (!empty($data['bank_account_no']) ? $data['bank_account_no'] : null),
                (!empty($data['bank_name']) ? $data['bank_name'] : null),
                $data['employment_type'] ?? 'full_time',
                (!empty($data['job_description']) ? $data['job_description'] : null),
                $data['hire_date'] ?? date('Y-m-d'),
                $data['status'] ?? 'onboarding',
                json_encode($data['custom_fields'] ?? []),
                !empty($data['department_id']) ? $data['department_id'] : null,
                !empty($data['designation_id']) ? $data['designation_id'] : null
            ]);

            $newId = (int)$this->db->lastInsertId();

            // 3. Office Links
            foreach ($data['company_ids'] as $index => $compId) {
                $isPrimary = ($index === 0) ? 1 : 0;
                $this->db->prepare("INSERT INTO employee_companies (employee_id, company_id, is_primary, is_active) VALUES (?, ?, ?, 1)")
                         ->execute([$newId, $compId, $isPrimary]);
            }

            // 4. User Account
            $rawPass = bin2hex(random_bytes(6));
            $hashedPass = password_hash($rawPass, PASSWORD_BCRYPT);
            $this->db->prepare("INSERT INTO users (employee_id, username, password_hash) VALUES (?, ?, ?)")
                     ->execute([$newId, $data['email'], $hashedPass]);

            $this->auditService->log(
                'CREATE',
                'employees',
                $newId,
                null,
                $data
            );

            $this->db->commit();
            return $newId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Detects circular reporting dependencies
     */
    public function checkHierarchyCycle(int $employeeId, int $managerId): bool
    {
        $currentId = $managerId;
        $visited = [$employeeId];

        while ($currentId) {
            if (in_array($currentId, $visited)) return true;
            $visited[] = $currentId;
            
            $stmt = $this->db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
            $stmt->execute([$currentId]);
            $currentId = $stmt->fetchColumn();
        }
        return false;
    }

    private function syncCompanies(int $employeeId, array $companyIds, ?int $primaryId): void
    {
        $existStmt = $this->db->prepare("SELECT company_id, is_active FROM employee_companies WHERE employee_id = ?");
        $existStmt->execute([$employeeId]);
        $existing = $existStmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($existing as $compId => $isActive) {
            if (!in_array($compId, $companyIds) && $isActive) {
                $this->db->prepare("UPDATE employee_companies SET is_active = 0, is_primary = 0, deactivated_at_utc = CURRENT_TIMESTAMP WHERE employee_id = ? AND company_id = ?")
                         ->execute([$employeeId, $compId]);
            }
        }

        foreach ($companyIds as $index => $compId) {
            $isPrimary = ($compId == $primaryId || (!$primaryId && $index === 0)) ? 1 : 0;
            if (array_key_exists($compId, $existing)) {
                $this->db->prepare("UPDATE employee_companies SET is_active = 1, is_primary = ?, deactivated_at_utc = NULL WHERE employee_id = ? AND company_id = ?")
                         ->execute([$isPrimary, $employeeId, $compId]);
            } else {
                $this->db->prepare("INSERT INTO employee_companies (employee_id, company_id, is_primary, is_active) VALUES (?, ?, ?, 1)")
                         ->execute([$employeeId, $compId, $isPrimary]);
            }
        }
    }

    private function syncUserRole(int $employeeId, int $roleId): void
    {
        $usrStmt = $this->db->prepare("SELECT id FROM users WHERE employee_id = ?");
        $usrStmt->execute([$employeeId]);
        $userId = $usrStmt->fetchColumn();

        if ($userId) {
            $this->db->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$userId]);
            $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$userId, $roleId]);
        }
    }

    private function reassignPendingAppraisals(int $employeeId): void
    {
        $stmt = $this->db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $escalationId = $stmt->fetchColumn();

        if ($escalationId) {
            $this->db->prepare("UPDATE employee_appraisals SET manager_id = ? WHERE manager_id = ? AND status IN ('draft', 'manager_review')")
                     ->execute([$escalationId, $employeeId]);
        }
    }

    private function getCompanyCustomFields(int $companyId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM company_custom_fields WHERE company_id = ? ORDER BY display_order ASC");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getMergedCustomData(int $employeeId, array $newFields): array
    {
        $stmt = $this->db->prepare("SELECT custom_data FROM employees WHERE id = ?");
        $stmt->execute([$employeeId]);
        $existingRaw = $stmt->fetchColumn();
        $existing = is_string($existingRaw) ? (json_decode($existingRaw, true) ?: []) : [];
        return array_merge($existing, $newFields);
    }
}
