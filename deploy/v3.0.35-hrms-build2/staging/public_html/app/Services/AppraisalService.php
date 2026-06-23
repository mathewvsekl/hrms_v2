<?php

namespace App\Services;

use App\Helpers\ApprovalHelper;
use App\Helpers\NotificationHelper;

/**
 * AppraisalService
 * 
 * Handles the performance appraisal lifecycle, cycles, and matrix approvals.
 */
class AppraisalService
{
    /**
     * Get a specific appraisal by ID with RBAC context
     */
    public function getAppraisal(int $id, array $userData): array
    {
        $db = \Database::getInstance()->getConnection();
        
        try { 
            $db->exec("ALTER TABLE appraisal_cycles ADD COLUMN hr_deadline DATE NULL AFTER manager_deadline"); 
            $db->exec("ALTER TABLE appraisal_cycles ADD COLUMN management_deadline DATE NULL AFTER hr_deadline"); 
            $db->exec("ALTER TABLE employee_appraisals MODIFY COLUMN status ENUM('draft', 'l1_review', 'l2_review', 'l3_review', 'hr_calibration', 'finalized', 'withdrawn', 'rejected') DEFAULT 'draft'");
        } catch (\Exception $e) {}

        $stmt = $db->prepare("
            SELECT ea.*, ac.name as cycle_name, ac.frequency, 
                   ac.employee_deadline, ac.manager_deadline, ac.hr_deadline,
                   e.first_name, e.last_name, e.employee_code, e.profile_image_path, e.reporting_manager_id,
                   d.name as department_name, des.title as designation_title 
            FROM employee_appraisals ea 
            JOIN appraisal_cycles ac ON ea.cycle_id = ac.id 
            JOIN employees e ON ea.employee_id = e.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN designations des ON e.designation_id = des.id
            WHERE ea.id = ?
        ");
        $stmt->execute([$id]);
        $appraisal = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$appraisal) {
            throw new \Exception("Appraisal not found", 404);
        }

        // Fetch dynamic KPI requirement
        $stmtDept = $db->prepare("SELECT department_id FROM employees WHERE id = ?");
        $stmtDept->execute([$appraisal['employee_id']]);
        $deptId = $stmtDept->fetchColumn();
        
        $stmtMin = $db->prepare("SELECT min_kpis FROM department_kpi_requirements WHERE department_id = ?");
        $stmtMin->execute([$deptId]);
        $minKPIs = $stmtMin->fetchColumn();
        
        if ($minKPIs === false) {
            $stmtGlobal = $db->prepare("SELECT setting_value FROM appraisal_system_settings WHERE setting_key = 'default_min_kpis_global'");
            $stmtGlobal->execute();
            $minKPIs = $stmtGlobal->fetchColumn() ?: 3;
        }
        $appraisal['min_kpis_required'] = (int)$minKPIs;

        // RBAC logic
        $roleId = (int)($userData['role_id'] ?? \App\Helpers\RoleConstants::EMPLOYEE);
        $isGlobalAdmin = \App\Middleware\RoleMiddleware::hasPermission('Appraisals', 'view');
        $isOwner = ($appraisal['employee_id'] == $userData['employee_id']);

        if (!$isGlobalAdmin && !$isOwner) {
            $isManager = false;
            $stmtMgr = $db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
            $stmtMgr->execute([$appraisal['employee_id']]);
            if ($stmtMgr->fetchColumn() == $userData['employee_id']) {
                $isManager = true;
            } else {
                $stmtApprCheck = $db->prepare("SELECT id FROM appraisal_approvals WHERE appraisal_id = ? AND approver_id = ?");
                $stmtApprCheck->execute([$id, $userData['employee_id']]);
                if ($stmtApprCheck->fetchColumn()) {
                    $isManager = true;
                }
            }
            if (!$isManager) {
                throw new \Exception("Forbidden: You do not have permission to view this appraisal.", 403);
            }
        }

        // Fetch related data
        $stmtRatings = $db->prepare("
            SELECT ar.*, tq.section 
            FROM appraisal_ratings ar 
            LEFT JOIN template_questions tq ON ar.question_id = tq.id 
            WHERE ar.appraisal_id = ?
        ");
        $stmtRatings->execute([$id]);
        $ratings = $stmtRatings->fetchAll(\PDO::FETCH_ASSOC);

        // Confidentiality filtering
        if ($roleId === \App\Helpers\RoleConstants::EMPLOYEE) {
            foreach ($ratings as &$r) {
                if ($r['section'] === 'D_MANAGER' || $r['section'] === 'E_HR') {
                    $r['manager_rating'] = null;
                    $r['manager_comment'] = null;
                    $r['hr_adjusted_rating'] = null;
                }
            }
        }

        $stmtComments = $db->prepare("SELECT * FROM appraisal_comments WHERE appraisal_id = ?");
        $stmtComments->execute([$id]);
        $comments = $stmtComments->fetchAll(\PDO::FETCH_ASSOC);

        if ($roleId === \App\Helpers\RoleConstants::EMPLOYEE) {
            $comments = array_filter($comments, function ($c) {
                return !str_contains($c['section'], 'D_MANAGER') && !str_contains($c['section'], 'E_HR');
            });
        }

        $stmtApprovals = $db->prepare("
            SELECT aa.*, e.first_name, e.last_name 
            FROM appraisal_approvals aa 
            JOIN employees e ON aa.approver_id = e.id 
            WHERE aa.appraisal_id = ? ORDER BY aa.id ASC
        ");
        $stmtApprovals->execute([$id]);
        $approvals = $stmtApprovals->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'appraisal' => $appraisal,
            'ratings' => $ratings,
            'comments' => array_values($comments),
            'approvals' => $approvals
        ];
    }

    /**
     * List appraisals with geographic scoping
     */
    public function listAppraisals(array $sessionData, string $role): array
    {
        $db = \Database::getInstance()->getConnection();
        
        try {
            $db->exec("ALTER TABLE employee_appraisals ADD COLUMN is_active TINYINT(1) DEFAULT 1");
        } catch (\PDOException $e) { }

        $role = strtoupper($role);
        $whereClauses = ["(ea.is_active = 1 OR ea.is_active IS NULL)"];
        $queryParams = [];

        $hasViewAll = \App\Middleware\RoleMiddleware::hasPermission('Appraisals', 'view');
        $roleId = (int)($sessionData['role_id'] ?? 0);

        if (!$hasViewAll) {
            $whereClauses[] = "(ea.manager_id = :mid OR ea.employee_id = :eid)";
            $queryParams['mid'] = $sessionData['employee_id'] ?? 0;
            $queryParams['eid'] = $sessionData['employee_id'] ?? 0;
        } else {
            $associatedCompanyIds = $sessionData['associated_company_ids'] ?? [];
            $sessionCountryId = $sessionData['scope_country_id'] ?? null;
            $isGlobalAdmin = ($roleId === 1 || $roleId === 2);

            if (!$isGlobalAdmin) {
                // Check if they have multi-company associated IDs
                if (!empty($associatedCompanyIds)) {
                    $placeholders = [];
                    foreach ($associatedCompanyIds as $idx => $cid) {
                        $key = "assoc_cid_$idx";
                        $placeholders[] = ":$key";
                        $queryParams[$key] = $cid;
                    }
                    $whereClauses[] = "ec.company_id IN (" . implode(',', $placeholders) . ")";
                } 
                // Fallback to Country Level Scope
                else if ($sessionCountryId) {
                    $whereClauses[] = "EXISTS (SELECT 1 FROM companies c2 WHERE ec.company_id = c2.id AND c2.country_id = :session_country_id)";
                    $queryParams['session_country_id'] = $sessionCountryId;
                }
                // Fallback to Single Company Scope
                else if (isset($sessionData['scope_company_id'])) {
                    $whereClauses[] = "ec.company_id = :session_company_id";
                    $queryParams['session_company_id'] = $sessionData['scope_company_id'];
                }
                // No Scope
                else {
                    $whereClauses[] = "1=0";
                }
            }
        }

        // Hierarchical Isolation
        $roleId = (int)($sessionData['role_id'] ?? 0);
        if ($roleId !== 1 && $roleId !== 2) {
            $whereClauses[] = "NOT EXISTS (
                SELECT 1 FROM user_roles ur_hier 
                JOIN roles r_hier ON ur_hier.role_id = r_hier.id 
                JOIN users u_hier ON ur_hier.user_id = u_hier.id
                WHERE u_hier.employee_id = ea.employee_id AND r_hier.id IN (1, 2)
            )";
        }

        $query = "
            SELECT DISTINCT ea.*, e.first_name, e.last_name, e.employee_code as emp_code, ac.name as cycle_name
            FROM employee_appraisals ea
            JOIN employees e ON ea.employee_id = e.id
            JOIN appraisal_cycles ac ON ea.cycle_id = ac.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            LEFT JOIN companies c ON ec.company_id = c.id
            WHERE " . implode(' AND ', $whereClauses) . "
            ORDER BY ea.created_at_utc DESC
        ";

        error_log("AppraisalService DEBUG: role=$role query=" . preg_replace('/\s+/', ' ', $query));
        error_log("AppraisalService DEBUG: params=" . json_encode($queryParams));

        $stmt = $db->prepare($query);
        $stmt->execute($queryParams);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        error_log("AppraisalService DEBUG: found=" . count($results));
        return $results;
    }

    /**
     * Save Draft: Transactional Upsert for Ratings and Comments
     */
    public function saveDraft(array $data, array $userData): void
    {
        $db = \Database::getInstance()->getConnection();
        $appraisalId = (int)$data['appraisal_id'];
        
        $db->beginTransaction();
        try {
            $stmtCheck = $db->prepare("SELECT status, employee_id FROM employee_appraisals WHERE id = ?");
            $stmtCheck->execute([$appraisalId]);
            $appraisal = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            if (!$appraisal) throw new \Exception("Appraisal not found", 404);
            if ($appraisal['status'] === 'finalized') throw new \Exception("Cannot modify finalized appraisal", 403);

            // RBAC logic for saving draft
            $role = strtoupper($userData['role'] ?? '');
            $isGlobalAdmin = \App\Middleware\RoleMiddleware::hasPermission('Appraisals', 'edit');
            $isOwner = ($appraisal['employee_id'] == $userData['employee_id']);

            $isManager = false;
            $stmtMgr = $db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
            $stmtMgr->execute([$appraisal['employee_id']]);
            if ($stmtMgr->fetchColumn() == $userData['employee_id']) {
                $isManager = true;
            } else {
                $stmtApprCheck = $db->prepare("SELECT id FROM appraisal_approvals WHERE appraisal_id = ? AND approver_id = ?");
                $stmtApprCheck->execute([$appraisalId, $userData['employee_id']]);
                if ($stmtApprCheck->fetchColumn()) {
                    $isManager = true;
                }
            }

            if (!$isGlobalAdmin && !$isOwner && !$isManager) {
                throw new \Exception("Forbidden: You do not have permission to edit this appraisal.", 403);
            }

            // Handle Ratings
            if (isset($data['ratings']) && is_array($data['ratings'])) {
                foreach ($data['ratings'] as $rating) {
                    $qId = $rating['question_id'] ?? null;
                    if ($qId !== null && !is_numeric($qId)) {
                        $qId = null;
                    }
                    $kraName = $rating['kra_name'] ?? null;
                    if (!$qId && !$kraName) continue;

                    $ratingId = $rating['id'] ?? null;

                    if ($ratingId) {
                        // Verify it belongs to this appraisal
                        $stmtCheck = $db->prepare("SELECT id FROM appraisal_ratings WHERE id = ? AND appraisal_id = ?");
                        $stmtCheck->execute([$ratingId, $appraisalId]);
                        if (!$stmtCheck->fetchColumn()) {
                            $ratingId = null;
                        }
                    }

                    if (!$ratingId) {
                        if ($qId) {
                            $stmtExist = $db->prepare("SELECT id FROM appraisal_ratings WHERE appraisal_id = ? AND question_id = ?");
                            $stmtExist->execute([$appraisalId, $qId]);
                        } else {
                            $stmtExist = $db->prepare("SELECT id FROM appraisal_ratings WHERE appraisal_id = ? AND kra_name = ?");
                            $stmtExist->execute([$appraisalId, $kraName]);
                        }
                        $ratingId = $stmtExist->fetchColumn();
                    }

                    if ($ratingId) {
                        $updateFields = [];
                        $params = [];
                        $allowedFields = ['employee_rating', 'achievements', 'kra_name'];
                        if ($isManager || $isGlobalAdmin) {
                            $allowedFields[] = 'manager_rating';
                            $allowedFields[] = 'manager_comment';
                        }
                        
                        foreach ($allowedFields as $field) {
                            if (isset($rating[$field])) {
                                $updateFields[] = "$field = ?";
                                $params[] = $rating[$field];
                            }
                        }
                        if ($updateFields) {
                            $params[] = $ratingId;
                            $db->prepare("UPDATE appraisal_ratings SET " . implode(', ', $updateFields) . " WHERE id = ?")->execute($params);
                        }
                    } else {
                        $stmtIns = $db->prepare("INSERT INTO appraisal_ratings (appraisal_id, question_id, kra_name, achievements, employee_rating, manager_rating, manager_comment) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmtIns->execute([
                            $appraisalId, $qId, $kraName,
                            $rating['achievements'] ?? null,
                            $rating['employee_rating'] ?? null,
                            $rating['manager_rating'] ?? null,
                            $rating['manager_comment'] ?? null
                        ]);
                    }
                }
            }

            // Handle Comments
            if (isset($data['comments']) && is_array($data['comments'])) {
                foreach ($data['comments'] as $comment) {
                    $section = $comment['section'] ?? null;
                    if (!$section) continue;

                    $stmtExistC = $db->prepare("SELECT id FROM appraisal_comments WHERE appraisal_id = ? AND section = ? AND author_id = ?");
                    $stmtExistC->execute([$appraisalId, $section, $userData['id']]);
                    $commentId = $stmtExistC->fetchColumn();

                    if ($commentId) {
                        $db->prepare("UPDATE appraisal_comments SET comment_text = ? WHERE id = ?")->execute([$comment['comment_text'] ?? '', $commentId]);
                    } else {
                        $db->prepare("INSERT INTO appraisal_comments (appraisal_id, section, author_id, comment_text) VALUES (?, ?, ?, ?)")->execute([$appraisalId, $section, $userData['id'], $comment['comment_text'] ?? '']);
                    }
                }
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Initiate appraisal cycle
     */
    public function initiateCycle(array $data): string
    {
        $db = \Database::getInstance()->getConnection();
        
        $year = $data['year'] ?? date('Y');
        $period = $data['period'] ?? null;
        $officeIds = $data['office_ids'] ?? [];

        if (!empty($officeIds) && $period) {
            $stmtCheck = $db->prepare("SELECT id, selected_offices FROM appraisal_cycles WHERE status = 'active' AND year = ? AND period = ?");
            $stmtCheck->execute([$year, $period]);
            $activeCycles = $stmtCheck->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($activeCycles as $cycle) {
                $cycleOffices = json_decode($cycle['selected_offices'], true) ?: [];
                $intersection = array_intersect($officeIds, $cycleOffices);
                if (!empty($intersection)) {
                    throw new \Exception("An active appraisal cycle already exists for the selected period ($period $year) in one or more of the selected companies.");
                }
            }
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO appraisal_cycles (name, year, frequency, period, start_date, end_date, status, selected_offices, employee_deadline, manager_deadline, hr_deadline, management_deadline) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'], 
                $data['year'] ?? date('Y'),
                $data['frequency'], 
                $data['period'] ?? null,
                $data['start_date'], 
                $data['end_date'],
                json_encode($data['office_ids']),
                $data['employee_deadline'] ?? null,
                $data['manager_deadline'] ?? null,
                $data['hr_deadline'] ?? null,
                $data['management_deadline'] ?? null
            ]);
            $cycleId = $db->lastInsertId();

            // 1. Fetch Global Settings
            $stmtGlobal = $db->query("SELECT setting_value FROM appraisal_system_settings WHERE setting_key = 'default_min_kpis'");
            $globalMinKpis = (int)($stmtGlobal->fetchColumn() ?: 3);

            // 2. Fetch Department Requirements
            $stmtDepts = $db->query("SELECT department_id, min_kpis FROM department_kpi_requirements");
            $deptReqs = [];
            while ($row = $stmtDepts->fetch(\PDO::FETCH_ASSOC)) {
                $deptReqs[$row['department_id']] = (int)$row['min_kpis'];
            }

            $officeCsv = implode(',', array_map('intval', $data['office_ids']));
            $sql = "SELECT e.id, e.reporting_manager_id, e.department_id FROM employees e 
                    JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_active = 1 AND ec.is_primary = 1
                    WHERE ec.company_id IN ($officeCsv) AND e.status = 'active'";
            $employees = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($employees as $emp) {
                $stmtAppr = $db->prepare("INSERT INTO employee_appraisals (employee_id, manager_id, cycle_id, template_id, status) VALUES (?, ?, ?, ?, 'draft')");
                $stmtAppr->execute([$emp['id'], $emp['reporting_manager_id'], $cycleId, $data['template_id']]);
                $appraisalId = $db->lastInsertId();

                // Determine min KPIs for this employee
                $minKpisRequired = $globalMinKpis;
                if ($emp['department_id'] && isset($deptReqs[$emp['department_id']])) {
                    $minKpisRequired = $deptReqs[$emp['department_id']];
                }

                // Auto-populate KPIs
                $stmtGetKpis = $db->prepare("SELECT kpi_name FROM employee_kpi_configs WHERE employee_id = ? AND is_active = 1");
                $stmtGetKpis->execute([$emp['id']]);
                
                $kpiCount = 0;
                while ($kpi = $stmtGetKpis->fetchColumn()) {
                    $db->prepare("INSERT INTO appraisal_ratings (appraisal_id, kra_name) VALUES (?, ?)")->execute([$appraisalId, $kpi]);
                    $kpiCount++;
                }

                // Pad with empty rows to meet minimum KPI requirement
                while ($kpiCount < $minKpisRequired) {
                    $db->prepare("INSERT INTO appraisal_ratings (appraisal_id, kra_name) VALUES (?, '')")->execute([$appraisalId]);
                    $kpiCount++;
                }

                // Matrix Approvals
                if ($emp['reporting_manager_id']) {
                    $db->prepare("INSERT INTO appraisal_approvals (appraisal_id, approver_id, status) VALUES (?, ?, 'pending')")->execute([$appraisalId, $emp['reporting_manager_id']]);
                    
                    $stmtHOD = $db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
                    $stmtHOD->execute([$emp['reporting_manager_id']]);
                    $hodId = $stmtHOD->fetchColumn();
                    if ($hodId && $hodId != $emp['reporting_manager_id']) {
                        $db->prepare("INSERT INTO appraisal_approvals (appraisal_id, approver_id, status) VALUES (?, ?, 'pending')")->execute([$appraisalId, $hodId]);
                    }
                }
            }

            $db->commit();
            return "Cycle initiated with " . count($employees) . " appraisals created.";
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }



    /**
     * Advance the workflow intelligently based on employee reporting matrix.
     */
    public function advanceWorkflow(int $appraisalId, string $currentStatus, ?string $comments = null): string
    {
        $db = \Database::getInstance()->getConnection();
        
        // Get Employee & L1 Manager
        $stmt = $db->prepare("
            SELECT ea.employee_id, ec.company_id, e.reporting_manager_id as l1 
            FROM employee_appraisals ea 
            JOIN employees e ON ea.employee_id = e.id
            LEFT JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1 AND ec.is_active = 1
            WHERE ea.id = ?
        ");
        $stmt->execute([$appraisalId]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) throw new \Exception("Appraisal not found");

        $l1 = $data['l1'] ? (int)$data['l1'] : null;
        $l2 = null;
        $l3 = null;

        if ($l1) {
            $stmtL2 = $db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
            $stmtL2->execute([$l1]);
            $l2 = $stmtL2->fetchColumn();
            $l2 = $l2 ? (int)$l2 : null;
        }

        if ($l2) {
            $stmtL3 = $db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
            $stmtL3->execute([$l2]);
            $l3 = $stmtL3->fetchColumn();
            $l3 = $l3 ? (int)$l3 : null;
        }

        $nextStatus = $this->determineNextStatus($currentStatus);

        // Auto-Skip Logic
        while (true) {
            if ($nextStatus === 'l1_review' && !$l1) {
                $nextStatus = $this->determineNextStatus($nextStatus);
            } else if ($nextStatus === 'l2_review' && !$l2) {
                $nextStatus = $this->determineNextStatus($nextStatus);
            } else if ($nextStatus === 'l3_review' && !$l3) {
                $nextStatus = $this->determineNextStatus($nextStatus);
            } else {
                break; // Found an active tier
            }
        }

        // Update Appraisal Status
        $stmtUp = $db->prepare("UPDATE employee_appraisals SET status = ? WHERE id = ?");
        $stmtUp->execute([$nextStatus, $appraisalId]);

        // Log the transition
        ApprovalHelper::log('appraisal', $appraisalId, $nextStatus, $comments ?: "Workflow advanced to {$nextStatus}");

        return $nextStatus;
    }

    private function determineNextStatus(string $current): string
    {
        switch ($current) {
            case 'draft': return 'l1_review';
            case 'l1_review': return 'l2_review';
            case 'l2_review': return 'l3_review';
            case 'l3_review': return 'hr_calibration';
            case 'hr_calibration': return 'finalized';
            default: return 'finalized';
        }
    }

    /**
     * Get Approval Matrices
     */
    public function getMatrices(int $companyId): array
    {
        $db = \Database::getInstance()->getConnection();
        
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS appraisal_approval_matrices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                step_order INT NOT NULL,
                role_required VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (\PDOException $e) {}

        $stmt = $db->prepare("SELECT * FROM appraisal_approval_matrices WHERE company_id = ? ORDER BY step_order ASC");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Save Approval Matrices
     */
    public function saveMatrices(int $companyId, array $matrices): void
    {
        $db = \Database::getInstance()->getConnection();
        
        // Ensure table exists
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS appraisal_approval_matrices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_id INT NOT NULL,
                step_order INT NOT NULL,
                role_required VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (\PDOException $e) {}

        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM appraisal_approval_matrices WHERE company_id = ?")->execute([$companyId]);
            
            $stmtIns = $db->prepare("INSERT INTO appraisal_approval_matrices (company_id, step_order, role_required) VALUES (?, ?, ?)");
            $order = 1;
            foreach ($matrices as $matrix) {
                if (!empty($matrix['role_required'])) {
                    $stmtIns->execute([$companyId, $order++, $matrix['role_required']]);
                }
            }
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Generate Letter data for the frontend to render as PDF
     */
    public function generateLetter(int $appraisalId, array $user): array
    {
        $db = \Database::getInstance()->getConnection();

        // Ensure table exists
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS appraisal_letters (
                id INT AUTO_INCREMENT PRIMARY KEY,
                appraisal_id INT NOT NULL,
                status ENUM('Draft', 'Published', 'Acknowledged') DEFAULT 'Draft',
                old_salary DECIMAL(10,2) NULL,
                new_salary DECIMAL(10,2) NULL,
                letter_content TEXT NULL,
                published_at TIMESTAMP NULL,
                acknowledged_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
        } catch (\PDOException $e) {}

        // Check if letter already exists
        $stmt = $db->prepare("SELECT * FROM appraisal_letters WHERE appraisal_id = ?");
        $stmt->execute([$appraisalId]);
        $letter = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$letter) {
            // Get data to populate default letter
            $stmtApp = $db->prepare("
                SELECT ea.*, e.first_name, e.last_name, e.employee_code, e.department_id, e.designation_id
                FROM employee_appraisals ea
                JOIN employees e ON ea.employee_id = e.id
                WHERE ea.id = ?
            ");
            $stmtApp->execute([$appraisalId]);
            $appraisal = $stmtApp->fetch(\PDO::FETCH_ASSOC);

            if (!$appraisal) throw new \Exception("Appraisal not found");
            if ($appraisal['status'] !== 'finalized') throw new \Exception("Appraisal must be finalized to generate a letter.");

            // Get current salary (mocking old_salary since payroll module integration is separate)
            $oldSalary = 5000.00; // Placeholder until Eva Payroll is linked
            $newSalary = $oldSalary;
            
            if ($appraisal['eligible_for_increment']) {
                $newSalary = $oldSalary * 1.10; // 10% increment
            }

            $content = "Dear " . $appraisal['first_name'] . " " . $appraisal['last_name'] . ",\n\nWe are pleased to inform you that your performance appraisal for the recent cycle has been finalized. Your final rating is " . $appraisal['final_rating'] . "/5.\n\n" . ($appraisal['eligible_for_increment'] ? "In recognition of your performance, your salary has been revised to " . number_format($newSalary, 2) . " effective immediately.\n\n" : "") . "Thank you for your continuous contributions.\n\nSincerely,\nHuman Resources";

            $stmtIns = $db->prepare("INSERT INTO appraisal_letters (appraisal_id, status, old_salary, new_salary, letter_content) VALUES (?, 'Draft', ?, ?, ?)");
            $stmtIns->execute([$appraisalId, $oldSalary, $newSalary, $content]);

            $stmt->execute([$appraisalId]);
            $letter = $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        return $letter;
    }

    /**
     * Publish the letter to the employee
     */
    public function publishLetter(int $appraisalId): array
    {
        $db = \Database::getInstance()->getConnection();
        
        $stmtCheck = $db->prepare("SELECT * FROM appraisal_letters WHERE appraisal_id = ?");
        $stmtCheck->execute([$appraisalId]);
        $letter = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

        if (!$letter) throw new \Exception("Letter not generated yet.");

        $stmt = $db->prepare("UPDATE appraisal_letters SET status = 'Published', published_at = NOW() WHERE appraisal_id = ?");
        $stmt->execute([$appraisalId]);

        // Notify Employee
        $stmtEmp = $db->prepare("SELECT employee_id FROM employee_appraisals WHERE id = ?");
        $stmtEmp->execute([$appraisalId]);
        $empId = $stmtEmp->fetchColumn();
        
        $stmtUserE = $db->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmtUserE->execute([$empId]);
        $empUserId = $stmtUserE->fetchColumn();
        
        if ($empUserId) {
            \App\Helpers\NotificationHelper::send(
                $empUserId,
                'letter_published',
                'Salary Revision Letter Published',
                "Your appraisal and salary revision letter has been published. Please review and acknowledge.",
                ['link' => "/appraisals/letter/{$appraisalId}"],
                true // emailNotify
            );
        }

        return ['status' => 'success', 'message' => 'Letter published.'];
    }

    /**
     * Employee acknowledges the letter
     */
    public function acknowledgeLetter(int $appraisalId, array $user): array
    {
        $db = \Database::getInstance()->getConnection();

        $stmtCheck = $db->prepare("SELECT * FROM appraisal_letters WHERE appraisal_id = ?");
        $stmtCheck->execute([$appraisalId]);
        $letter = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

        if (!$letter) throw new \Exception("Letter not found.");
        if ($letter['status'] !== 'Published') throw new \Exception("Letter is not available for acknowledgment.");

        $stmt = $db->prepare("UPDATE appraisal_letters SET status = 'Acknowledged', acknowledged_at = NOW() WHERE appraisal_id = ?");
        $stmt->execute([$appraisalId]);

        return ['status' => 'success', 'message' => 'Letter acknowledged successfully.'];
    }
}
