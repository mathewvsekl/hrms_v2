<?php

namespace App\Controllers;

use App\Core\Controller;

class AppraisalTemplateController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get all templates
     */
    public function index()
    {
        $user = \App\Middleware\AuthMiddleware::getUser();
        if (!$user) {
            return $this->jsonResponse(null, 401, 'Unauthorized');
        }

        try {
            $stmt = $this->db->query("SELECT * FROM appraisal_templates ORDER BY created_at_utc DESC");
            $templates = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Fetch soft skills for each template from template_questions
            foreach ($templates as &$template) {
                $stmtQ = $this->db->prepare("SELECT * FROM template_questions WHERE template_id = ? AND section = 'B_SOFT_SKILL' ORDER BY display_order ASC");
                $stmtQ->execute([$template['id']]);
                $questions = $stmtQ->fetchAll(\PDO::FETCH_ASSOC);
                
                $softSkills = [];
                foreach ($questions as $q) {
                    $softSkills[] = [
                        'id' => $q['id'],
                        'skill_name' => $q['question_text'],
                        'description' => $q['description'] ?? '',
                        'rating_scale_max' => 10 // default
                    ];
                }
                $template['soft_skills'] = $softSkills;
            }

            return $this->jsonResponse($templates);
        } catch (\PDOException $e) {
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Get single template
     */
    public function show($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM appraisal_templates WHERE id = ?");
            $stmt->execute([$id]);
            $template = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$template) {
                return $this->jsonResponse(null, 404, 'Template not found');
            }

            $stmtQ = $this->db->prepare("SELECT * FROM template_questions WHERE template_id = ? AND section = 'B_SOFT_SKILL' ORDER BY display_order ASC");
            $stmtQ->execute([$template['id']]);
            $questions = $stmtQ->fetchAll(\PDO::FETCH_ASSOC);
            
            $softSkills = [];
            foreach ($questions as $q) {
                $softSkills[] = [
                    'id' => $q['id'],
                    'skill_name' => $q['question_text'],
                    'description' => $q['description'] ?? '',
                    'rating_scale_max' => 10
                ];
            }
            $template['soft_skills'] = $softSkills;

            return $this->jsonResponse($template);
        } catch (\PDOException $e) {
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Create template
     */
    public function store($data)
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            // Ensure schema exists
            try { $this->db->exec("ALTER TABLE appraisal_templates ADD COLUMN min_kpis INT DEFAULT 3"); } catch (\Exception $e) {}
            try { $this->db->exec("ALTER TABLE appraisal_templates ADD COLUMN max_kpis INT DEFAULT 10"); } catch (\Exception $e) {}
            try { $this->db->exec("ALTER TABLE template_questions ADD COLUMN display_order INT DEFAULT 0"); } catch (\Exception $e) {}

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("INSERT INTO appraisal_templates (name, min_kpis, max_kpis) VALUES (?, ?, ?)");
            $stmt->execute([
                $data['name'] ?? 'Untitled Template',
                $data['min_kpis'] ?? 1,
                $data['max_kpis'] ?? 5
            ]);
            $templateId = $this->db->lastInsertId();

            if (!empty($data['soft_skills'])) {
                $stmtQ = $this->db->prepare("INSERT INTO template_questions (template_id, section, question_text, description, display_order) VALUES (?, 'B_SOFT_SKILL', ?, ?, ?)");
                foreach ($data['soft_skills'] as $idx => $skill) {
                    $stmtQ->execute([
                        $templateId,
                        $skill['skill_name'],
                        $skill['description'] ?? null,
                        $idx + 1
                    ]);
                }
            }

            $this->db->commit();
            return $this->jsonResponse(['id' => $templateId], 201, 'Template created successfully');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Update template
     */
    public function update($id, $data)
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            // Ensure schema exists
            try { $this->db->exec("ALTER TABLE appraisal_templates ADD COLUMN min_kpis INT DEFAULT 3"); } catch (\Exception $e) {}
            try { $this->db->exec("ALTER TABLE appraisal_templates ADD COLUMN max_kpis INT DEFAULT 10"); } catch (\Exception $e) {}
            try { $this->db->exec("ALTER TABLE template_questions ADD COLUMN display_order INT DEFAULT 0"); } catch (\Exception $e) {}

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE appraisal_templates SET name = ?, min_kpis = ?, max_kpis = ? WHERE id = ?");
            $stmt->execute([
                $data['name'] ?? 'Untitled Template',
                $data['min_kpis'] ?? 1,
                $data['max_kpis'] ?? 5,
                $id
            ]);

            // Replace soft skills
            $this->db->prepare("DELETE FROM template_questions WHERE template_id = ? AND section = 'B_SOFT_SKILL'")->execute([$id]);

            if (!empty($data['soft_skills'])) {
                $stmtQ = $this->db->prepare("INSERT INTO template_questions (template_id, section, question_text, description, display_order) VALUES (?, 'B_SOFT_SKILL', ?, ?, ?)");
                foreach ($data['soft_skills'] as $idx => $skill) {
                    $stmtQ->execute([
                        $id,
                        $skill['skill_name'],
                        $skill['description'] ?? null,
                        $idx + 1
                    ]);
                }
            }

            $this->db->commit();
            return $this->jsonResponse(null, 200, 'Template updated successfully');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Delete template
     */
    public function destroy($id)
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            $this->db->beginTransaction();
            $this->db->prepare("DELETE FROM template_questions WHERE template_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM appraisal_templates WHERE id = ?")->execute([$id]);
            $this->db->commit();
            return $this->jsonResponse(null, 200, 'Template deleted successfully');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }
}
