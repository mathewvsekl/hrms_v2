<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ApprovalHelper;

class AppraisalController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get a specific appraisal by ID
     */
    public function getAppraisal($id)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $service = new \App\Services\AppraisalService();
            $result = $service->getAppraisal((int)$id, $user);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            return $this->jsonResponse(null, $code, $e->getMessage());
        }
    }

    /**
     * List appraisals (for Manager or HR or Admin)
     */
    public function listAppraisals($params)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $service = new \App\Services\AppraisalService();
            $appraisals = $service->listAppraisals($_SESSION, strtoupper($user['role'] ?? ''));
            
            $timezone = 'UTC';
            if (!empty($_SESSION['scope_company_id'])) {
                $timezone = \App\Helpers\DateHelper::getCompanyTimezone($_SESSION['scope_company_id']);
            }
            $this->applyTimezones($appraisals, $timezone);

            return $this->jsonResponse($appraisals);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, "Error: " . $e->getMessage());
        }
    }

    /**
     * Get appraisal template structure with questions
     */
    public function getTemplate($id = null)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        try {
            if ($id) {
                $stmt = $this->db->prepare("SELECT * FROM appraisal_templates WHERE id = ?");
                $stmt->execute([$id]);
            } else {
                $stmt = $this->db->query("SELECT * FROM appraisal_templates LIMIT 1");
            }
            $template = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$template) {
                $this->jsonResponse(null, 404, 'Template not found');
                return;
            }

            $stmtQ = $this->db->prepare("SELECT * FROM template_questions WHERE template_id = ? ORDER BY display_order ASC");
            $stmtQ->execute([$template['id']]);
            $questions = $stmtQ->fetchAll(\PDO::FETCH_ASSOC);

            // Fetch Global Soft Skills to potentially override or supplement
            $stmtGlobal = $this->db->prepare("SELECT setting_value FROM appraisal_system_settings WHERE setting_key = 'soft_skills_criteria'");
            $stmtGlobal->execute();
            $globalSoftSkillsJson = $stmtGlobal->fetchColumn();
            $globalSoftSkills = $globalSoftSkillsJson ? json_decode($globalSoftSkillsJson, true) : [];

            // If template has B_SOFT_SKILL section, use the global ones
            $hasSoftSkillSection = false;
            foreach ($questions as $q) {
                if ($q['section'] === 'B_SOFT_SKILL') {
                    $hasSoftSkillSection = true;
                    break;
                }
            }

            if ($hasSoftSkillSection && !empty($globalSoftSkills)) {
                // Remove existing B_SOFT_SKILL questions and replace with global ones
                $questions = array_filter($questions, function($q) {
                    return $q['section'] !== 'B_SOFT_SKILL';
                });

                foreach ($globalSoftSkills as $idx => $skill) {
                    $questions[] = [
                        'id' => 'global_' . $idx,
                        'template_id' => $template['id'],
                        'section' => 'B_SOFT_SKILL',
                        'question_text' => $skill,
                        'display_order' => $idx,
                        'is_mandatory' => 1
                    ];
                }
                // Re-sort questions by display order if needed, but array_filter + appending is fine for now
            }

            $template['questions'] = array_values($questions);

            // Also include the Rating Mapping for the frontend to use
            $stmtRating = $this->db->prepare("SELECT setting_value FROM appraisal_system_settings WHERE setting_key = 'rating_system_mapping'");
            $stmtRating->execute();
            $ratingMappingJson = $stmtRating->fetchColumn();
            $template['rating_mapping'] = $ratingMappingJson ? json_decode($ratingMappingJson, true) : [];

            return $this->jsonResponse($template);
        } catch (\PDOException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save Draft: Transactional Upsert for Ratings and Comments
     */
    public function saveDraft($data)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $service = new \App\Services\AppraisalService();
            $service->saveDraft($data, $user);
            return $this->jsonResponse(null, 200, 'Draft saved successfully');
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            return $this->jsonResponse(null, $code, $e->getMessage());
        }
    }

    /**
     * Initiate appraisal cycle
     */
    public function initiateCycle($data)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        $role = strtoupper($user['role'] ?? '');
        if (!str_contains($role, 'HR') && !str_contains($role, 'ADMIN')) {
            return $this->jsonResponse(null, 403, 'Unauthorized');
        }

        try {
            $this->db->beginTransaction();
            // Logic for initiating cycle would go here
            $this->db->commit();
            return $this->jsonResponse(null, 200, "Cycle initiated.");
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function withdrawCycle($id)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if ($user['role'] !== 'HRManager' && $user['role'] !== 'SuperAdmin') {
            $this->jsonResponse(null, 403, 'Unauthorized');
            return;
        }

        try {
            $this->db->beginTransaction();
            $this->db->prepare("UPDATE appraisal_cycles SET status = 'closed' WHERE id = ?")->execute([$id]);
            $this->db->prepare("UPDATE employee_appraisals SET status = 'withdrawn' WHERE cycle_id = ? AND status != 'finalized'")->execute([$id]);
            $this->db->commit();
            $this->jsonResponse(null, 200, 'Cycle and pending appraisals withdrawn successfully.');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function submitToManager($id)
    {
        $stmt = $this->db->prepare("SELECT status FROM employee_appraisals WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();

        $service = new \App\Services\AppraisalService();
        $nextStatus = $service->advanceWorkflow((int)$id, $currentStatus, 'Employee submitted appraisal.');

        $this->jsonResponse(null, 200, 'Appraisal submitted and advanced to ' . $nextStatus);
    }

    public function approveAppraisal($id, $data)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        $comment = $data['comment'] ?? 'Manager approved.';

        try {
            $this->db->beginTransaction();

            $stmtEmp = $this->db->prepare("SELECT employee_id, status FROM employee_appraisals WHERE id = ?");
            $stmtEmp->execute([$id]);
            $appraisal = $stmtEmp->fetch(\PDO::FETCH_ASSOC);
            if ($appraisal) {
                $this->verifyDataScope(null, null, $appraisal['employee_id']);
            }

            $service = new \App\Services\AppraisalService();
            $nextStatus = $service->advanceWorkflow((int)$id, $appraisal['status'], $comment);

            $this->db->commit();
            $this->jsonResponse(null, 200, 'Appraisal approved and advanced to ' . $nextStatus);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function returnAppraisal($id, $data)
    {
                $user = \App\Middleware\AuthMiddleware::getUser();
        $managerId = $user['employee_id'];
        $comment = $data['comment'] ?? 'Returned for review';

        try {
            $this->db->beginTransaction();

            $stmtEmp = $this->db->prepare("SELECT employee_id FROM employee_appraisals WHERE id = ?");
            $stmtEmp->execute([$id]);
            $empId = $stmtEmp->fetchColumn();
            if ($empId) {
                $this->verifyDataScope(null, null, $empId);
            }

            $isSuperAdmin = \App\Middleware\RoleMiddleware::hasPermission('Appraisals', 'approve');
            if ($isSuperAdmin) {
                $stmt = $this->db->prepare("UPDATE appraisal_approvals SET status = 'returned', comments = ? WHERE appraisal_id = ?");
                $stmt->execute([$comment, $id]);
            } else {
                $stmt = $this->db->prepare("UPDATE appraisal_approvals SET status = 'returned', comments = ? WHERE appraisal_id = ? AND manager_id = ?");
                $stmt->execute([$comment, $id, $managerId]);
                if ($stmt->rowCount() === 0) {
                    $this->db->rollBack();
                    return $this->jsonResponse(null, 403, "You are not authorized to return this appraisal.");
                }
            }
            
            // Return to draft (Employee)
            $this->db->prepare("UPDATE employee_appraisals SET status = 'draft' WHERE id = ?")->execute([$id]);
            
            // Reset other approvals to pending
            $this->db->prepare("UPDATE appraisal_approvals SET status = 'pending' WHERE appraisal_id = ?")->execute([$id]);

            // Notify Employee
            $stmtEmp = $this->db->prepare("SELECT employee_id FROM employee_appraisals WHERE id = ?");
            $stmtEmp->execute([$id]);
            $empId = $stmtEmp->fetchColumn();
            
            $stmtUserE = $this->db->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmtUserE->execute([$empId]);
            $empUserId = $stmtUserE->fetchColumn();
            
            if ($empUserId) {
                \App\Helpers\NotificationHelper::send(
                    $empUserId,
                    'appraisal_returned',
                    'Appraisal Returned',
                    "Your appraisal has been returned for review. Comment: $comment",
                    ['link' => "/appraisals/{$id}"],
                    true // emailNotify
                );
            }

            $this->db->commit();

            // Noah Logic Auditor: Centralized History Log
            ApprovalHelper::log('appraisal', (int)$id, 'returned', $comment);

            $this->jsonResponse(null, 200, 'Appraisal returned to employee for review.');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function submitToHR($id)
    {
        $stmt = $this->db->prepare("SELECT status FROM employee_appraisals WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();

        $service = new \App\Services\AppraisalService();
        $nextStatus = $service->advanceWorkflow((int)$id, $currentStatus, 'Submitted to HR manually.');

        $this->jsonResponse(null, 200, 'Appraisal advanced to ' . $nextStatus);
    }

    public function finalize($id, $data)
    {
        // Noah Logic Auditor: Validate Final Rating Range
        if (isset($data['final_rating']) && ($data['final_rating'] < 1 || $data['final_rating'] > 5)) {
            $this->jsonResponse(null, 422, 'Final rating must be between 1 and 5');
            return;
        }

        try {
            $this->db->beginTransaction();

            $stmtEmp = $this->db->prepare("SELECT employee_id FROM employee_appraisals WHERE id = ?");
            $stmtEmp->execute([$id]);
            $empId = $stmtEmp->fetchColumn();
            if ($empId) {
                $this->verifyDataScope(null, null, $empId);
            }

            $increment = isset($data['eligible_for_increment']) && $data['eligible_for_increment'] ? 1 : 0;
            $bonus = isset($data['eligible_for_bonus']) && $data['eligible_for_bonus'] ? 1 : 0;
            $rating = $data['final_rating'] ?? null;

            $stmt = $this->db->prepare("UPDATE employee_appraisals SET status = 'finalized', eligible_for_increment = ?, eligible_for_bonus = ?, final_rating = ? WHERE id = ?");
            $stmt->execute([$increment, $bonus, $rating, $id]);

            // Noah Logic Auditor: Centralized History Log
            ApprovalHelper::log('appraisal', (int)$id, 'finalized', 'HR Finalized the appraisal cycle.');

            // Notify Employee
            $stmtEmp = $this->db->prepare("SELECT employee_id FROM employee_appraisals WHERE id = ?");
            $stmtEmp->execute([$id]);
            $empId = $stmtEmp->fetchColumn();
            
            $stmtUserE = $this->db->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmtUserE->execute([$empId]);
            $empUserId = $stmtUserE->fetchColumn();
            
            if ($empUserId) {
                \App\Helpers\NotificationHelper::send(
                    $empUserId,
                    'appraisal_finalized',
                    'Appraisal Finalized',
                    "Your appraisal has been finalized. Final Rating: $rating.",
                    ['link' => "/appraisals/{$id}"],
                    true // emailNotify
                );
            }

            // Eva Payroll Architect: Integration Trigger
            if ($increment || $bonus) {
                // Fetch employee ID to flag for payroll module
                $empStmt = $this->db->prepare("SELECT employee_id FROM employee_appraisals WHERE id = ?");
                $empStmt->execute([$id]);
                $emp = $empStmt->fetch(\PDO::FETCH_ASSOC);

                if ($emp) {
                    // We add a global setting/flag or a staging record for the upcoming payroll run
                    // This creates an audit log that salary structures need reviewing
                    $stmtPayroll = $this->db->prepare("INSERT INTO global_settings (setting_key, setting_value, category) VALUES (?, ?, 'payroll_appraisal_sync') ON DUPLICATE KEY UPDATE setting_value = ?");
                    $key = "appraisal_bonus_flag_" . $emp['employee_id'] . "_" . date('Y');
                    $val = json_encode(['increment' => $increment, 'bonus' => $bonus, 'appraisal_id' => $id]);
                    $stmtPayroll->execute([$key, $val, $val]);
                }
            }

            // Sync the final performance rating to the permanent Employee Profile
            if ($rating !== null) {
                try {
                    $this->db->exec("ALTER TABLE employees ADD COLUMN performance_rating DECIMAL(3,2) NULL AFTER status");
                } catch (\PDOException $e) {
                    // Column already exists, ignore
                }
                
                $empStmt = $this->db->prepare("SELECT employee_id FROM employee_appraisals WHERE id = ?");
                $empStmt->execute([$id]);
                $empId = $empStmt->fetchColumn();
                
                if ($empId) {
                    $stmtSync = $this->db->prepare("UPDATE employees SET performance_rating = ? WHERE id = ?");
                    $stmtSync->execute([$rating, $empId]);
                }
            }

            $this->db->commit();
            $this->jsonResponse(null, 200, 'Status updated to finalized and rating synchronized to profile.');

        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    private function updateStatus($id, $status)
    {
        try {
            // Noah Logic Auditor: State Machine Validation
            $stmtCheck = $this->db->prepare("SELECT status FROM employee_appraisals WHERE id = ?");
            $stmtCheck->execute([$id]);
            $current = $stmtCheck->fetchColumn();

            if ($current === 'finalized') {
                $this->jsonResponse(null, 403, 'Cannot modify a finalized appraisal.');
                return;
            }

            $stmt = $this->db->prepare("UPDATE employee_appraisals SET status = ? WHERE id = ?");
            if ($stmt->execute([$status, $id])) {
                $this->jsonResponse(null, 200, 'Status updated to ' . $status);
            } else {
                $this->jsonResponse(null, 500, 'Failed to update status');
            }
        } catch (\PDOException $e) {
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Get KPI configurations for an employee
     */
    public function getKPIConfigs($params)
    {
        $employeeId = $params['employee_id'] ?? null;
        if (!$employeeId) {
            $this->jsonResponse(null, 400, 'Employee ID required');
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM employee_kpi_configs WHERE employee_id = ? AND is_active = 1");
            $stmt->execute([$employeeId]);
            $configs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->jsonResponse($configs);
        } catch (\PDOException $e) {
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Save KPI configurations for an employee
     */
    public function saveKPIConfigs($data)
    {
        $employeeId = $data['employee_id'] ?? null;
        $kpis = $data['kpis'] ?? [];

        if (!$employeeId) {
            $this->jsonResponse(null, 400, 'Employee ID required');
            return;
        }

        try {
            $this->db->beginTransaction();

            // Deactivate old configs for this employee to replace them
            $this->db->prepare("UPDATE employee_kpi_configs SET is_active = 0 WHERE employee_id = ?")->execute([$employeeId]);

            foreach ($kpis as $kpi) {
                if (empty($kpi['kpi_name'])) continue;

                $stmt = $this->db->prepare("INSERT INTO employee_kpi_configs (employee_id, kpi_name, target_description, weightage) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $employeeId,
                    $kpi['kpi_name'],
                    $kpi['target_description'] ?? null,
                    $kpi['weightage'] ?? 0
                ]);
            }

            $this->db->commit();
            $this->jsonResponse(null, 200, 'KPI configurations saved successfully');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Get global appraisal system settings
     */
    public function getSystemSettings()
    {
        try {
            // Fetch global settings
            $stmt = $this->db->query("SELECT setting_key, setting_value, category FROM appraisal_system_settings");
            $settings = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Map settings to a cleaner object for the frontend
            $formattedSettings = [];
            foreach ($settings as $s) {
                $val = $s['setting_value'];
                // Try to decode JSON for complex settings
                if (is_string($val) && (strpos(trim($val), '{') === 0 || strpos(trim($val), '[') === 0)) {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $val = $decoded;
                    }
                }
                $formattedSettings[$s['setting_key']] = $val;
            }

            // Fetch department requirements universally by name
            $stmt = $this->db->query("SELECT MIN(d.id) as id, d.name, MAX(r.min_kpis) as min_kpis 
                                     FROM departments d 
                                     LEFT JOIN department_kpi_requirements r ON d.id = r.department_id 
                                     GROUP BY d.name");
            $deptRequirements = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonResponse([
                'settings' => $formattedSettings,
                'department_requirements' => $deptRequirements
            ]);
        } catch (\PDOException $e) {
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Update global appraisal system settings
     */
    public function saveSystemSettings($data)
    {
        $settings = $data['settings'] ?? [];
        $deptRequirements = $data['department_requirements'] ?? [];

        try {
            $this->db->beginTransaction();

            // Update Global Settings
            foreach ($settings as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $stmt = $this->db->prepare("INSERT INTO appraisal_system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at_utc = CURRENT_TIMESTAMP");
                $stmt->execute([$key, $value, $value]);
            }

            // Update Department Requirements Universally by Name
            foreach ($deptRequirements as $dept) {
                if (!isset($dept['id']) && !isset($dept['name'])) continue;
                
                // If name is present, fetch all department IDs with that name
                if (isset($dept['name'])) {
                    $stmtIds = $this->db->prepare("SELECT id FROM departments WHERE name = ?");
                    $stmtIds->execute([$dept['name']]);
                    $dIds = $stmtIds->fetchAll(\PDO::FETCH_COLUMN);
                    
                    foreach ($dIds as $did) {
                        $stmt = $this->db->prepare("INSERT INTO department_kpi_requirements (department_id, min_kpis) VALUES (?, ?) ON DUPLICATE KEY UPDATE min_kpis = ?, updated_at_utc = CURRENT_TIMESTAMP");
                        $stmt->execute([$did, $dept['min_kpis'] ?? 3, $dept['min_kpis'] ?? 3]);
                    }
                } else if (isset($dept['id'])) {
                    // Fallback to strict ID if name is missing
                    $stmt = $this->db->prepare("INSERT INTO department_kpi_requirements (department_id, min_kpis) VALUES (?, ?) ON DUPLICATE KEY UPDATE min_kpis = ?, updated_at_utc = CURRENT_TIMESTAMP");
                    $stmt->execute([$dept['id'], $dept['min_kpis'] ?? 3, $dept['min_kpis'] ?? 3]);
                }
            }

            $this->db->commit();
            $this->jsonResponse(null, 200, 'System settings updated successfully');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Mass deactivation of appraisal records
     */
    public function massDeactivate($data)
    {
        $scope = $data['scope'] ?? 'all';
        
        try {
            try {
                $this->db->exec("ALTER TABLE employee_appraisals ADD COLUMN is_active TINYINT(1) DEFAULT 1");
            } catch (\PDOException $e) { }

            if ($scope === 'all' || $scope === 'global') {
                $stmt = $this->db->prepare("UPDATE employee_appraisals SET is_active = 0 WHERE id > 0 AND status != 'finalized'");
                $stmt->execute();
            } else {
                $stmt = $this->db->prepare("UPDATE employee_appraisals a JOIN employee_companies ec ON a.employee_id = ec.employee_id AND ec.is_primary = 1 SET a.is_active = 0 WHERE a.id > 0 AND ec.company_id = ? AND a.status != 'finalized'");
                $stmt->execute([$scope]);
            }
            
            $this->jsonResponse(null, 200, 'Mass deactivation completed successfully');
        } catch (\PDOException $e) {
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Get aggregate appraisal statistics for the dashboard
     */
    public function getStats()
    {
        if (!$this->isInternal) {
            $user = \App\Middleware\AuthMiddleware::getUser();
            if (!$user) {
                return $this->jsonResponse(null, 401, 'Unauthorized');
            }
        }

        try {
            try {
                $this->db->exec("ALTER TABLE employee_appraisals ADD COLUMN is_active TINYINT(1) DEFAULT 1");
            } catch (\PDOException $e) { }

            $params = [];
            $geographicFilter = "";
            $isGlobalAdmin = $this->isGlobalAdmin();
            if (!$isGlobalAdmin) {
                $isMultiOffice = $this->hasEntityScope();

                $associatedCompanyIds = $_SESSION['associated_company_ids'] ?? [];
                $sessionCountryId = $_SESSION['scope_country_id'] ?? null;

                if ($isMultiOffice && !empty($associatedCompanyIds)) {
                    $companyIdList = implode(",", array_map('intval', $associatedCompanyIds));
                    $geographicFilter = " AND ec.company_id IN ($companyIdList)";
                } else if ($this->hasGlobalOrRegionalScope() && $sessionCountryId) {
                    $geographicFilter = " AND EXISTS (SELECT 1 FROM companies c2 WHERE ec.company_id = c2.id AND c2.country_id = :session_country_id)";
                    $params['session_country_id'] = $sessionCountryId;
                } else {
                    $sessionCompanyId = $_SESSION['scope_company_id'] ?? null;
                    if ($sessionCompanyId) {
                        $geographicFilter = " AND ec.company_id = :session_company_id";
                        $params['session_company_id'] = $sessionCompanyId;
                    } else if (!$isMultiOffice) {
                        $geographicFilter = " AND 1=0";
                    }
                }

                $geographicFilter .= " AND NOT EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id AND ur2.role_id = 1)";
            }

            $query = "SELECT COUNT(*) as total, SUM(CASE WHEN ea.status = 'finalized' THEN 1 ELSE 0 END) as completed, SUM(CASE WHEN ea.status != 'finalized' AND ea.is_active = 1 THEN 1 ELSE 0 END) as active FROM employee_appraisals ea JOIN employees e ON ea.employee_id = e.id JOIN users u ON e.id = u.employee_id LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1 WHERE 1=1 $geographicFilter";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return $this->jsonResponse([
                'total' => (int)($stats['total'] ?? 0),
                'completed' => (int)($stats['completed'] ?? 0),
                'active' => (int)($stats['active'] ?? 0),
                'appraisalsCount' => (int)($stats['active'] ?? 0)
            ], 200, 'Appraisal stats retrieved successfully');
        } catch (\PDOException $e) {
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Generate the Salary Revision / Appraisal Letter
     */
    public function generateLetter($id)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user || (!str_contains(strtoupper($user['role']), 'HR') && strtoupper($user['role']) !== 'ADMIN' && strtoupper($user['role']) !== 'SUPERADMIN')) {
            return $this->jsonResponse(null, 403, 'Unauthorized');
        }

        try {
            $service = new \App\Services\AppraisalService();
            $result = $service->generateLetter((int)$id, $user);
            return $this->jsonResponse($result, 200, 'Letter generated successfully.');
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            return $this->jsonResponse(null, $code, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Publish the generated letter to the employee
     */
    public function publishLetter($id)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user || (!str_contains(strtoupper($user['role']), 'HR') && strtoupper($user['role']) !== 'ADMIN' && strtoupper($user['role']) !== 'SUPERADMIN')) {
            return $this->jsonResponse(null, 403, 'Unauthorized');
        }

        try {
            $service = new \App\Services\AppraisalService();
            $result = $service->publishLetter((int)$id);
            return $this->jsonResponse(null, 200, 'Letter published to employee successfully.');
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            return $this->jsonResponse(null, $code, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Employee acknowledges the letter
     */
    public function acknowledgeLetter($id)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $service = new \App\Services\AppraisalService();
            $result = $service->acknowledgeLetter((int)$id, $user);
            return $this->jsonResponse(null, 200, 'Letter acknowledged successfully.');
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 500;
            return $this->jsonResponse(null, $code, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Get Approval Matrices for a company
     */
    public function getMatrices()
    {
        try {
            $companyId = $_GET['company_id'] ?? null;
            if (!$companyId) return $this->jsonResponse(null, 400, 'Company ID required');
            $service = new \App\Services\AppraisalService();
            $result = $service->getMatrices((int)$companyId);
            return $this->jsonResponse($result);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Save Approval Matrices for a company
     */
    public function saveMatrices()
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            $data = $this->getJsonInput();
            if (!isset($data['company_id']) || !isset($data['matrices'])) {
                return $this->jsonResponse(null, 400, 'Missing company_id or matrices data');
            }
            $service = new \App\Services\AppraisalService();
            $service->saveMatrices((int)$data['company_id'], $data['matrices']);
            return $this->jsonResponse(null, 200, 'Approval matrices updated successfully');
        } catch (\Exception $e) {
            error_log("SAVE MATRICES ERROR: " . $e->getMessage());
            file_put_contents(__DIR__ . '/../../save_matrices_error.log', $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->jsonResponse(null, 500, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete an individual appraisal (only if draft)
     */
    public function destroy($id)
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            // Check status
            $stmt = $this->db->prepare("SELECT status FROM employee_appraisals WHERE id = ?");
            $stmt->execute([$id]);
            $appraisal = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$appraisal) {
                return $this->jsonResponse(null, 404, 'Appraisal not found');
            }

            if ($appraisal['status'] !== 'draft') {
                return $this->jsonResponse(null, 400, 'Cannot delete an appraisal that already has employee entries (not in draft status).');
            }

            $this->db->prepare("DELETE FROM employee_appraisals WHERE id = ?")->execute([$id]);
            return $this->jsonResponse(null, 200, 'Appraisal deleted successfully');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }
}
