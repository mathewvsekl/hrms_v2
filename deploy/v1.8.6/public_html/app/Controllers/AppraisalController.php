<?php

namespace App\Controllers;

use App\Core\Controller;

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
        // Require authentication
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user) {
            $this->jsonResponse(null, 401, 'Unauthorized');
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT ea.*, ac.name as cycle_name, ac.frequency, ac.employee_deadline, ac.manager_deadline, ac.hr_deadline FROM employee_appraisals ea JOIN appraisal_cycles ac ON ea.cycle_id = ac.id WHERE ea.id = ?");
            $stmt->execute([$id]);
            $appraisal = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$appraisal) {
                $this->jsonResponse(null, 404, 'Appraisal not found');
                return;
            }

            // Fetch dynamic KPI requirement for this appraisal's department
            $stmtDept = $this->db->prepare("SELECT department_id FROM employees WHERE id = ?");
            $stmtDept->execute([$appraisal['employee_id']]);
            $deptId = $stmtDept->fetchColumn();
            
            $stmtMin = $this->db->prepare("SELECT min_kpis FROM department_kpi_requirements WHERE department_id = ?");
            $stmtMin->execute([$deptId]);
            $minKPIs = $stmtMin->fetchColumn();
            
            if ($minKPIs === false) {
                $stmtGlobal = $this->db->prepare("SELECT setting_value FROM appraisal_system_settings WHERE setting_key = 'default_min_kpis_global'");
                $stmtGlobal->execute();
                $minKPIs = $stmtGlobal->fetchColumn() ?: 3;
            }
            $appraisal['min_kpis_required'] = (int)$minKPIs;

            // RBAC logic
            $role = strtoupper($user['role'] ?? '');
            if ($role === 'EMPLOYEE' && $appraisal['employee_id'] !== $user['employee_id']) {
                $this->jsonResponse(null, 403, 'Forbidden');
                return;
            }

            // Fetch related data
            $stmtRatings = $this->db->prepare("SELECT * FROM appraisal_ratings WHERE appraisal_id = ?");
            $stmtRatings->execute([$id]);
            $ratings = $stmtRatings->fetchAll(\PDO::FETCH_ASSOC);

            // Filter ratings visibility based on RBAC: Section D is confidential
            if ($role === 'EMPLOYEE') {
                foreach ($ratings as &$r) {
                    // Predefined soft skills or KPIs might have manager ratings we need to hide
                    // In Section D, we hide everything from employee
                    $stmtQ = $this->db->prepare("SELECT section FROM template_questions WHERE id = ?");
                    $stmtQ->execute([$r['question_id']]);
                    $section = $stmtQ->fetchColumn();
                    if ($section === 'D_MANAGER' || $section === 'E_HR') {
                        $r['manager_rating'] = null;
                        $r['manager_comment'] = null;
                        $r['hr_adjusted_rating'] = null;
                    }
                }
            }

            $stmtComments = $this->db->prepare("SELECT * FROM appraisal_comments WHERE appraisal_id = ?");
            $stmtComments->execute([$id]);
            $comments = $stmtComments->fetchAll(\PDO::FETCH_ASSOC);

            // Filter comments based on RBAC: Section D is confidential
            if ($role === 'EMPLOYEE') {
                $comments = array_filter($comments, function ($c) {
                    return !str_contains($c['section'], 'D_MANAGER') && !str_contains($c['section'], 'E_HR');
                });
            }

            // Fetch matrix approvals
            $stmtApprovals = $this->db->prepare("SELECT aa.*, e.first_name, e.last_name FROM appraisal_approvals aa JOIN employees e ON aa.approver_id = e.id WHERE aa.appraisal_id = ? ORDER BY aa.id ASC");
            $stmtApprovals->execute([$id]);
            $approvals = $stmtApprovals->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonResponse([
                'appraisal' => $appraisal,
                'ratings' => $ratings,
                'comments' => array_values($comments),
                'approvals' => $approvals
            ]);
        } catch (\PDOException $e) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * List appraisals (for Manager or HR or Admin)
     */
    public function listAppraisals($params)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        try {
            $role = strtoupper($user['role'] ?? '');
            if ($role === 'EMPLOYEE' || str_contains($role, 'MANAGER') || $role === 'DEPARTMENT HEAD') {
                $stmt = $this->db->prepare("SELECT * FROM employee_appraisals WHERE manager_id = ? OR employee_id = ?");
                $stmt->execute([$user['employee_id'], $user['employee_id']]);
            } else {
                // HR or Super Admin
                $stmt = $this->db->query("SELECT * FROM employee_appraisals");
            }

            $appraisals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->jsonResponse($appraisals, 200, 'Appraisals retrieved successfully');
        } catch (\PDOException $e) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
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

            $this->jsonResponse($template);
        } catch (\PDOException $e) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save Draft: Transactional Upsert for Ratings and Comments
     */
    public function saveDraft($data)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        $appraisalId = $data['appraisal_id'] ?? null;
        if (!$appraisalId) {
            $this->jsonResponse(null, 400, 'Appraisal ID required');
            return;
        }

        try {
            $this->db->beginTransaction();

            // 1. Check if appraisal exists and is editable
            $stmtCheck = $this->db->prepare("SELECT status, employee_id, manager_id FROM employee_appraisals WHERE id = ?");
            $stmtCheck->execute([$appraisalId]);
            $appraisal = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            if (!$appraisal) {
                $this->jsonResponse(null, 404, 'Appraisal not found');
                return;
            }

            if ($appraisal['status'] === 'finalized') {
                $this->jsonResponse(null, 403, 'Cannot modify finalized appraisal');
                return;
            }

            // 2. Handle Ratings (Section B)
            if (isset($data['ratings']) && is_array($data['ratings'])) {
                // Validation: Minimum 5 KPIs for Section B_KPI
                if ($user['role'] === 'Employee') {
                    // Fetch dynamic requirement for this employee's department
                    $stmtDept = $this->db->prepare("SELECT department_id FROM employees WHERE id = ?");
                    $stmtDept->execute([$appraisal['employee_id']]);
                    $deptId = $stmtDept->fetchColumn();
                    
                    $stmtMin = $this->db->prepare("SELECT min_kpis FROM department_kpi_requirements WHERE department_id = ?");
                    $stmtMin->execute([$deptId]);
                    $minKPIs = $stmtMin->fetchColumn();
                    
                    if ($minKPIs === false) {
                        $stmtGlobal = $this->db->prepare("SELECT setting_value FROM appraisal_system_settings WHERE setting_key = 'default_min_kpis_global'");
                        $stmtGlobal->execute();
                        $minKPIs = $stmtGlobal->fetchColumn() ?: 3;
                    }
                    $minKPIs = (int)$minKPIs;

                    $kpiCount = 0;
                    foreach ($data['ratings'] as $r) {
                        if (isset($r['kra_name']) && !empty($r['kra_name'])) $kpiCount++;
                    }
                    if ($kpiCount < $minKPIs) {
                        $this->jsonResponse(null, 400, "Minimum $minKPIs KPIs are required for your department.");
                        return;
                    }
                }

                foreach ($data['ratings'] as $rating) {
                    $qId = $rating['question_id'] ?? null;
                    $kraName = $rating['kra_name'] ?? null;
                    
                    if (!$qId && !$kraName) continue;

                    // Upsert logic for ratings
                    if ($qId) {
                        $stmtExist = $this->db->prepare("SELECT id FROM appraisal_ratings WHERE appraisal_id = ? AND question_id = ?");
                        $stmtExist->execute([$appraisalId, $qId]);
                    } else {
                        $stmtExist = $this->db->prepare("SELECT id FROM appraisal_ratings WHERE appraisal_id = ? AND kra_name = ?");
                        $stmtExist->execute([$appraisalId, $kraName]);
                    }
                    $ratingId = $stmtExist->fetchColumn();

                    if ($ratingId) {
                        $updateFields = [];
                        $params = [];
                        if (isset($rating['employee_rating'])) {
                            $updateFields[] = "employee_rating = ?";
                            $params[] = $rating['employee_rating'];
                        }
                        if (isset($rating['manager_rating'])) {
                            $updateFields[] = "manager_rating = ?";
                            $params[] = $rating['manager_rating'];
                        }
                        if (isset($rating['manager_comment'])) {
                            $updateFields[] = "manager_comment = ?";
                            $params[] = $rating['manager_comment'];
                        }
                        if (isset($rating['achievements'])) {
                            $updateFields[] = "achievements = ?";
                            $params[] = $rating['achievements'];
                        }

                        if ($updateFields) {
                            $params[] = $ratingId;
                            $this->db->prepare("UPDATE appraisal_ratings SET " . implode(', ', $updateFields) . " WHERE id = ?")->execute($params);
                        }
                    } else {
                        $stmtIns = $this->db->prepare("INSERT INTO appraisal_ratings (appraisal_id, question_id, kra_name, achievements, employee_rating, manager_rating, manager_comment) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmtIns->execute([
                            $appraisalId,
                            $qId,
                            $kraName,
                            $rating['achievements'] ?? null,
                            $rating['employee_rating'] ?? null,
                            $rating['manager_rating'] ?? null,
                            $rating['manager_comment'] ?? null
                        ]);
                    }
                }
            }

            // 3. Handle Comments (Section C, D, E)
            if (isset($data['comments']) && is_array($data['comments'])) {
                foreach ($data['comments'] as $comment) {
                    $section = $comment['section'] ?? null;
                    if (!$section) continue;

                    $stmtExistC = $this->db->prepare("SELECT id FROM appraisal_comments WHERE appraisal_id = ? AND section = ? AND author_id = ?");
                    $stmtExistC->execute([$appraisalId, $section, $user['id']]);
                    $commentId = $stmtExistC->fetchColumn();

                    if ($commentId) {
                        $stmtUpdC = $this->db->prepare("UPDATE appraisal_comments SET comment_text = ? WHERE id = ?");
                        $stmtUpdC->execute([$comment['comment_text'] ?? '', $commentId]);
                    } else {
                        $stmtInsC = $this->db->prepare("INSERT INTO appraisal_comments (appraisal_id, section, author_id, comment_text) VALUES (?, ?, ?, ?)");
                        $stmtInsC->execute([$appraisalId, $section, $user['id'], $comment['comment_text'] ?? '']);
                    }
                }
            }

            $this->db->commit();
            $this->jsonResponse(null, 200, 'Draft saved successfully');

        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function initiateCycle($data)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        $role = strtoupper($user['role'] ?? '');
        if (!str_contains($role, 'HR') && !str_contains($role, 'ADMIN')) {
            $this->jsonResponse(null, 403, 'Unauthorized. Must be HR or Admin.');
            return;
        }

        $name = $data['name'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        $frequency = $data['frequency'] ?? 'Annual';
        $officeIds = $data['office_ids'] ?? []; // Array of company IDs
        $templateId = $data['template_id'] ?? null;
        $employeeDeadline = !empty($data['employee_deadline']) ? $data['employee_deadline'] : null;
        $managerDeadline = !empty($data['manager_deadline']) ? $data['manager_deadline'] : null;
        $hrDeadline = !empty($data['hr_deadline']) ? $data['hr_deadline'] : null;

        if (!$name || !$startDate || !$endDate || !$templateId) {
            $this->jsonResponse(null, 400, 'Missing required fields');
            return;
        }

        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO appraisal_cycles (name, frequency, start_date, end_date, status, selected_offices, employee_deadline, manager_deadline, hr_deadline) 
                    VALUES (:name, :frequency, :start_date, :end_date, 'active', :offices, :emp_dl, :mgr_dl, :hr_dl)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'name' => $name,
                'frequency' => $frequency,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'offices' => json_encode($officeIds),
                'emp_dl' => $employeeDeadline,
                'mgr_dl' => $managerDeadline,
                'hr_dl' => $hrDeadline
            ]);
            $cycleId = $this->db->lastInsertId();

            // Fetch employees in selected offices
            if (empty($officeIds)) {
                $employees = [];
            } else {
                $officeCsv = implode(',', array_map('intval', $officeIds));
                $sql = "SELECT e.id, e.reporting_manager_id, e.email FROM employees e 
                        JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_active = 1
                        WHERE ec.company_id IN ($officeCsv) AND e.status = 'active'";
                $employees = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            }

            foreach ($employees as $emp) {
                // 1. Create appraisal record
                $stmtAppr = $this->db->prepare("INSERT INTO employee_appraisals (employee_id, manager_id, cycle_id, template_id, status) VALUES (?, ?, ?, ?, 'draft')");
                $stmtAppr->execute([$emp['id'], $emp['reporting_manager_id'], $cycleId, $templateId]);
                $appraisalId = $this->db->lastInsertId();

                // 2. Auto-populate KPIs from Configuration
                $stmtGetKpis = $this->db->prepare("SELECT * FROM employee_kpi_configs WHERE employee_id = ? AND is_active = 1");
                $stmtGetKpis->execute([$emp['id']]);
                $kpiConfigs = $stmtGetKpis->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($kpiConfigs as $kpi) {
                    $stmtInsKpi = $this->db->prepare("INSERT INTO appraisal_ratings (appraisal_id, kra_name) VALUES (?, ?)");
                    $stmtInsKpi->execute([$appraisalId, $kpi['kpi_name']]);
                }

                // 3. Create Matrix Approvals (Level 1: Direct Manager, Level 2: Manager's Manager)
                if ($emp['reporting_manager_id']) {
                    $stmtAp1 = $this->db->prepare("INSERT INTO appraisal_approvals (appraisal_id, approver_id, status) VALUES (?, ?, 'pending')");
                    $stmtAp1->execute([$appraisalId, $emp['reporting_manager_id']]);

                    // Level 2: Dept Head / Manager's Manager
                    $stmtHOD = $this->db->prepare("SELECT reporting_manager_id FROM employees WHERE id = ?");
                    $stmtHOD->execute([$emp['reporting_manager_id']]);
                    $hodId = $stmtHOD->fetchColumn();
                    if ($hodId && $hodId != $emp['reporting_manager_id']) {
                        $stmtAp2 = $this->db->prepare("INSERT INTO appraisal_approvals (appraisal_id, approver_id, status) VALUES (?, ?, 'pending')");
                        $stmtAp2->execute([$appraisalId, $hodId]);
                    }
                }

                // Notify Employee
                \App\Helpers\MailHelper::sendNotification(
                    $emp['email'], 
                    "Appraisal Cycle Initiated: $name", 
                    "Hello, a new appraisal cycle ($name) has been initiated. Please log in to complete your self-assessment (Sections A, B, & C)."
                );

                // In-App Notification
                $stmtUserC = $this->db->prepare("SELECT id FROM users WHERE employee_id = ?");
                $stmtUserC->execute([$emp['id']]);
                $recpId = $stmtUserC->fetchColumn();
                if ($recpId) {
                    \App\Helpers\NotificationHelper::send(
                        $recpId,
                        'appraisal_initiated',
                        'Appraisal Cycle Initiated',
                        "A new appraisal cycle ({$name}) has been initiated. Please complete your self-assessment.",
                        ['link' => "/appraisals/{$appraisalId}"]
                    );
                }
            }

            $this->db->commit();
            $message = "Cycle initiated with " . count($employees) . " appraisals created.";
            $this->jsonResponse(null, 200, $message);
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
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
        $this->updateStatus($id, 'manager_review');
        
        // Notify Manager
        $stmtAppr = $this->db->prepare("SELECT employee_id, manager_id FROM employee_appraisals WHERE id = ?");
        $stmtAppr->execute([$id]);
        $appr = $stmtAppr->fetch(\PDO::FETCH_ASSOC);

        if ($appr && $appr['manager_id']) {
            $stmtUser = $this->db->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmtUser->execute([$appr['manager_id']]);
            $mgrUserId = $stmtUser->fetchColumn();
            
            if ($mgrUserId) {
                \App\Helpers\NotificationHelper::send(
                    $mgrUserId,
                    'appraisal_submitted',
                    'Appraisal Submitted',
                    "An employee has submitted their appraisal for review.",
                    ['link' => "/appraisals/{$id}"]
                );
            }
        }
    }

    public function approveAppraisal($id, $data)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        $managerId = $user['employee_id'];
        $comment = $data['comment'] ?? '';

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE appraisal_approvals SET status = 'approved', comments = ? WHERE appraisal_id = ? AND manager_id = ?");
            $stmt->execute([$comment, $id, $managerId]);

            // Check if all matrix managers have approved
            $stmtAll = $this->db->prepare("SELECT COUNT(*) FROM appraisal_approvals WHERE appraisal_id = ? AND status != 'approved'");
            $stmtAll->execute([$id]);
            $pendingCount = $stmtAll->fetchColumn();

            if ($pendingCount == 0) {
                // Move to HR Review
                $this->db->prepare("UPDATE employee_appraisals SET status = 'hr_review' WHERE id = ?")->execute([$id]);
            }

            // Notify Employee of manager approval
            $stmtEmp = $this->db->prepare("SELECT employee_id FROM employee_appraisals WHERE id = ?");
            $stmtEmp->execute([$id]);
            $empId = $stmtEmp->fetchColumn();
            
            $stmtUserE = $this->db->prepare("SELECT id FROM users WHERE employee_id = ?");
            $stmtUserE->execute([$empId]);
            $empUserId = $stmtUserE->fetchColumn();
            
            if ($empUserId) {
                \App\Helpers\NotificationHelper::send(
                    $empUserId,
                    'appraisal_approved',
                    'Appraisal Approved (Manager)',
                    "Your manager has approved your appraisal.",
                    ['link' => "/appraisals/{$id}"]
                );
            }

            $this->db->commit();
            $this->jsonResponse(null, 200, 'Appraisal approved.');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function returnAppraisal($id, $data)
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        $managerId = $user['employee_id'];
        $comment = $data['comment'] ?? 'Returned for review';

        try {
            $this->db->beginTransaction();

            $this->db->prepare("UPDATE appraisal_approvals SET status = 'returned', comments = ? WHERE appraisal_id = ? AND manager_id = ?")->execute([$comment, $id, $managerId]);
            
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
                    ['link' => "/appraisals/{$id}"]
                );
            }

            $this->db->commit();
            $this->jsonResponse(null, 200, 'Appraisal returned to employee for review.');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function submitToHR($id)
    {
        $this->updateStatus($id, 'hr_review');
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

            $increment = isset($data['eligible_for_increment']) && $data['eligible_for_increment'] ? 1 : 0;
            $bonus = isset($data['eligible_for_bonus']) && $data['eligible_for_bonus'] ? 1 : 0;
            $rating = $data['final_rating'] ?? null;

            $stmt = $this->db->prepare("UPDATE employee_appraisals SET status = 'finalized', eligible_for_increment = ?, eligible_for_bonus = ?, final_rating = ? WHERE id = ?");
            $stmt->execute([$increment, $bonus, $rating, $id]);

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
                    ['link' => "/appraisals/{$id}"]
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

            $this->db->commit();
            $this->jsonResponse(null, 200, 'Status updated to finalized with Payroll sync triggered.');

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
                // Try to decode JSON for complex settings
                $val = $s['setting_value'];
                if ($s['category'] === 'soft_skills' || $s['category'] === 'rating') {
                    $decoded = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $val = $decoded;
                    }
                }
                $formattedSettings[$s['setting_key']] = $val;
            }

            // Fetch department requirements
            $stmt = $this->db->query("SELECT d.id, d.name, r.min_kpis 
                                     FROM departments d 
                                     LEFT JOIN department_kpi_requirements r ON d.id = r.department_id");
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
                // If value is array, encode to JSON
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                
                $stmt = $this->db->prepare("INSERT INTO appraisal_system_settings (setting_key, setting_value) 
                                         VALUES (?, ?) 
                                         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at_utc = CURRENT_TIMESTAMP");
                $stmt->execute([$key, $value, $value]);
            }

            // Update Department Requirements
            foreach ($deptRequirements as $dept) {
                if (!isset($dept['id'])) continue;
                
                $stmt = $this->db->prepare("INSERT INTO department_kpi_requirements (department_id, min_kpis) 
                                         VALUES (?, ?) 
                                         ON DUPLICATE KEY UPDATE min_kpis = ?, updated_at_utc = CURRENT_TIMESTAMP");
                $stmt->execute([$dept['id'], $dept['min_kpis'] ?? 3, $dept['min_kpis'] ?? 3]);
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
        $scope = $data['scope'] ?? 'all'; // all, or company_id
        
        try {
            if ($scope === 'all') {
                $stmt = $this->db->prepare("UPDATE appraisals SET status = 'deactivated' WHERE status != 'finalized'");
                $stmt->execute();
            } else {
                // Find all appraisals for employees belonging to this company/office
                $stmt = $this->db->prepare("UPDATE appraisals a 
                                         JOIN employees e ON a.employee_id = e.id
                                         SET a.status = 'deactivated' 
                                         WHERE e.company_id = ? AND a.status != 'finalized'");
                $stmt->execute([$scope]);
            }
            
            $this->jsonResponse(null, 200, 'Mass deactivation completed successfully');
        } catch (\PDOException $e) {
            $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }
}
