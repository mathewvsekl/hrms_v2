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
            $this->jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
            return;
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM employee_appraisals WHERE id = ?");
            $stmt->execute([$id]);
            $appraisal = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$appraisal) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Appraisal not found'], 404);
                return;
            }

            // RBAC logic
            if ($user['role'] === 'Employee' && $appraisal['employee_id'] !== $user['employee_id']) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Forbidden'], 403);
                return;
            }

            // Fetch related data
            $stmtRatings = $this->db->prepare("SELECT * FROM appraisal_ratings WHERE appraisal_id = ?");
            $stmtRatings->execute([$id]);
            $ratings = $stmtRatings->fetchAll(\PDO::FETCH_ASSOC);

            // Filter ratings visibility based on RBAC and status
            if ($user['role'] === 'Employee' && in_array($appraisal['status'], ['draft', 'manager_review'])) {
                foreach ($ratings as &$r) {
                    unset($r['manager_rating']);
                    unset($r['hr_adjusted_rating']);
                    unset($r['manager_comment']);
                }
            }

            $stmtComments = $this->db->prepare("SELECT * FROM appraisal_comments WHERE appraisal_id = ?");
            $stmtComments->execute([$id]);
            $comments = $stmtComments->fetchAll(\PDO::FETCH_ASSOC);

            // Filter comments based on RBAC
            if ($user['role'] === 'Employee' && $appraisal['status'] !== 'finalized') {
                $comments = array_filter($comments, function ($c) {
                    return $c['section'] === 'C_SUMMARY'; // Employees only see their own comments until final
                });
            }

            $this->jsonResponse([
                'appraisal' => $appraisal,
                'ratings' => $ratings,
                'comments' => array_values($comments)
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
            if ($user['role'] === 'Employee') {
                $stmt = $this->db->prepare("SELECT * FROM employee_appraisals WHERE employee_id = ?");
                $stmt->execute([$user['employee_id']]);
            } elseif ($user['role'] === 'Manager' || $user['role'] === 'Department Head') {
                $stmt = $this->db->prepare("SELECT * FROM employee_appraisals WHERE manager_id = ? OR employee_id = ?");
                $stmt->execute([$user['employee_id'], $user['employee_id']]);
            } else {
                // HR or Super Admin
                $stmt = $this->db->query("SELECT * FROM employee_appraisals");
            }

            $appraisals = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->jsonResponse($appraisals);
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
                $this->jsonResponse(['status' => 'error', 'message' => 'Template not found'], 404);
                return;
            }

            $stmtQ = $this->db->prepare("SELECT * FROM template_questions WHERE template_id = ? ORDER BY display_order ASC");
            $stmtQ->execute([$template['id']]);
            $template['questions'] = $stmtQ->fetchAll(\PDO::FETCH_ASSOC);

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
            $this->jsonResponse(['status' => 'error', 'message' => 'Appraisal ID required'], 400);
            return;
        }

        try {
            $this->db->beginTransaction();

            // 1. Check if appraisal exists and is editable
            $stmtCheck = $this->db->prepare("SELECT status, employee_id, manager_id FROM employee_appraisals WHERE id = ?");
            $stmtCheck->execute([$appraisalId]);
            $appraisal = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            if (!$appraisal) {
                $this->jsonResponse(['status' => 'error', 'message' => 'Appraisal not found'], 404);
                return;
            }

            if ($appraisal['status'] === 'finalized') {
                $this->jsonResponse(['status' => 'error', 'message' => 'Cannot modify finalized appraisal'], 403);
                return;
            }

            // 2. Handle Ratings (Section B)
            if (isset($data['ratings']) && is_array($data['ratings'])) {
                foreach ($data['ratings'] as $rating) {
                    $qId = $rating['question_id'] ?? null;
                    if (!$qId)
                        continue;

                    // Fetch existing to decide if update or insert
                    $stmtExist = $this->db->prepare("SELECT id FROM appraisal_ratings WHERE appraisal_id = ? AND question_id = ?");
                    $stmtExist->execute([$appraisalId, $qId]);
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

                        if ($updateFields) {
                            $params[] = $ratingId;
                            $this->db->prepare("UPDATE appraisal_ratings SET " . implode(', ', $updateFields) . " WHERE id = ?")->execute($params);
                        }
                    } else {
                        $stmtIns = $this->db->prepare("INSERT INTO appraisal_ratings (appraisal_id, question_id, employee_rating, manager_rating, manager_comment) VALUES (?, ?, ?, ?, ?)");
                        $stmtIns->execute([
                            $appraisalId,
                            $qId,
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
                    if (!$section)
                        continue;

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
            $this->jsonResponse(['status' => 'success', 'message' => 'Draft saved successfully']);

        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function submitToManager($id, $data)
    {
        $this->updateStatus($id, 'manager_review');
    }

    public function submitToHR($id, $data)
    {
        $this->updateStatus($id, 'hr_review');
    }

    public function finalize($id, $data)
    {
        // Noah Logic Auditor: Validate Final Rating Range
        if (isset($data['final_rating']) && ($data['final_rating'] < 1 || $data['final_rating'] > 5)) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Final rating must be between 1 and 5'], 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            $increment = isset($data['eligible_for_increment']) && $data['eligible_for_increment'] ? 1 : 0;
            $bonus = isset($data['eligible_for_bonus']) && $data['eligible_for_bonus'] ? 1 : 0;
            $rating = $data['final_rating'] ?? null;

            $stmt = $this->db->prepare("UPDATE employee_appraisals SET status = 'finalized', eligible_for_increment = ?, eligible_for_bonus = ?, final_rating = ? WHERE id = ?");
            $stmt->execute([$increment, $bonus, $rating, $id]);

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
            $this->jsonResponse(['status' => 'success', 'message' => 'Status updated to finalized with Payroll sync triggered.']);

        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
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
                $this->jsonResponse(['status' => 'error', 'message' => 'Cannot modify a finalized appraisal.'], 403);
                return;
            }

            $stmt = $this->db->prepare("UPDATE employee_appraisals SET status = ? WHERE id = ?");
            if ($stmt->execute([$status, $id])) {
                $this->jsonResponse(['status' => 'success', 'message' => 'Status updated to ' . $status]);
            } else {
                $this->jsonResponse(['status' => 'error', 'message' => 'Failed to update status'], 500);
            }
        } catch (\PDOException $e) {
            $this->jsonResponse(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
