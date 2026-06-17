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
        
        $stmt = $db->prepare("
            SELECT ea.*, ac.name as cycle_name, ac.frequency, 
                   ac.employee_deadline, ac.manager_deadline, ac.hr_deadline 
            FROM employee_appraisals ea 
            JOIN appraisal_cycles ac ON ea.cycle_id = ac.id 
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
        $role = strtoupper($userData['role'] ?? '');
        if ($role === 'EMPLOYEE' && $appraisal['employee_id'] !== $userData['employee_id']) {
            throw new \Exception("Forbidden", 403);
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
        if ($role === 'EMPLOYEE') {
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

        if ($role === 'EMPLOYEE') {
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
        
        $role = strtoupper($role);
        $whereClauses = ["1=1"];
        $queryParams = [];

        if ($role === 'EMPLOYEE' || ($role !== 'SUPERADMIN' && $role !== 'SUPER_ADMIN' && $role !== 'ADMIN' && !str_contains($role, 'HR'))) {
            $whereClauses[] = "(ea.manager_id = :mid OR ea.employee_id = :eid)";
            $queryParams['mid'] = $sessionData['employee_id'] ?? 0;
            $queryParams['eid'] = $sessionData['employee_id'] ?? 0;
        } else if ($role === 'ADMIN' || str_contains($role, 'HR') || $role === 'COUNTRYMANAGER' || $role === 'COUNTRY_MANAGER' || $role === 'COUNTRY MANAGER') {
            $associatedCompanyIds = $sessionData['associated_company_ids'] ?? [];
            $sessionCountryId = $sessionData['scope_country_id'] ?? null;
            $isGlobalAdmin = ($role === 'SUPERADMIN' || $role === 'SUPER_ADMIN' || $role === 'ADMIN');

            if (!$isGlobalAdmin) {
                if (!empty($associatedCompanyIds)) {
                    $placeholders = [];
                    foreach ($associatedCompanyIds as $idx => $cid) {
                        $key = "assoc_cid_$idx";
                        $placeholders[] = ":$key";
                        $queryParams[$key] = $cid;
                    }
                    $whereClauses[] = "ec.company_id IN (" . implode(',', $placeholders) . ")";
                } else if ($sessionCountryId) {
                    $whereClauses[] = "c.country_id = :session_country_id";
                    $queryParams['session_country_id'] = $sessionCountryId;
                } else {
                    $whereClauses[] = "1=0";
                }
            }
        }

        // Hierarchical Isolation
        if ($role !== 'SUPERADMIN' && $role !== 'SUPER_ADMIN') {
            $whereClauses[] = "NOT EXISTS (
                SELECT 1 FROM user_roles ur_hier 
                JOIN roles r_hier ON ur_hier.role_id = r_hier.id 
                JOIN users u_hier ON ur_hier.user_id = u_hier.id
                WHERE u_hier.employee_id = ea.employee_id AND (UPPER(r_hier.name) = 'SUPERADMIN' OR UPPER(r_hier.name) = 'SUPER_ADMIN')
            )";
        }

        $query = "
            SELECT DISTINCT ea.*, e.first_name, e.last_name, ac.name as cycle_name
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

            // Handle Ratings
            if (isset($data['ratings']) && is_array($data['ratings'])) {
                foreach ($data['ratings'] as $rating) {
                    $qId = $rating['question_id'] ?? null;
                    $kraName = $rating['kra_name'] ?? null;
                    if (!$qId && !$kraName) continue;

                    if ($qId) {
                        $stmtExist = $db->prepare("SELECT id FROM appraisal_ratings WHERE appraisal_id = ? AND question_id = ?");
                        $stmtExist->execute([$appraisalId, $qId]);
                    } else {
                        $stmtExist = $db->prepare("SELECT id FROM appraisal_ratings WHERE appraisal_id = ? AND kra_name = ?");
                        $stmtExist->execute([$appraisalId, $kraName]);
                    }
                    $ratingId = $stmtExist->fetchColumn();

                    if ($ratingId) {
                        $updateFields = [];
                        $params = [];
                        foreach (['employee_rating', 'manager_rating', 'manager_comment', 'achievements'] as $field) {
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
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO appraisal_cycles (name, frequency, start_date, end_date, status, selected_offices, employee_deadline, manager_deadline, hr_deadline) 
                VALUES (?, ?, ?, ?, 'active', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'], $data['frequency'], $data['start_date'], $data['end_date'],
                json_encode($data['office_ids']),
                $data['employee_deadline'] ?? null,
                $data['manager_deadline'] ?? null,
                $data['hr_deadline'] ?? null
            ]);
            $cycleId = $db->lastInsertId();

            $officeCsv = implode(',', array_map('intval', $data['office_ids']));
            $sql = "SELECT e.id, e.reporting_manager_id FROM employees e 
                    JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_active = 1
                    WHERE ec.company_id IN ($officeCsv) AND e.status = 'active'";
            $employees = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($employees as $emp) {
                $stmtAppr = $db->prepare("INSERT INTO employee_appraisals (employee_id, manager_id, cycle_id, template_id, status) VALUES (?, ?, ?, ?, 'draft')");
                $stmtAppr->execute([$emp['id'], $emp['reporting_manager_id'], $cycleId, $data['template_id']]);
                $appraisalId = $db->lastInsertId();

                // Auto-populate KPIs
                $stmtGetKpis = $db->prepare("SELECT kpi_name FROM employee_kpi_configs WHERE employee_id = ? AND is_active = 1");
                $stmtGetKpis->execute([$emp['id']]);
                while ($kpi = $stmtGetKpis->fetchColumn()) {
                    $db->prepare("INSERT INTO appraisal_ratings (appraisal_id, kra_name) VALUES (?, ?)")->execute([$appraisalId, $kpi]);
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
}
