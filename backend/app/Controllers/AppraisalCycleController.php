<?php

namespace App\Controllers;

use App\Core\Controller;

class AppraisalCycleController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get all cycles
     */
    public function index()
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            // Apply Database schema updates safely
            try { $this->db->exec("ALTER TABLE appraisal_cycles ADD COLUMN year INT NULL AFTER name"); } catch (\Exception $e) {}
            try { $this->db->exec("ALTER TABLE appraisal_cycles ADD COLUMN period VARCHAR(50) NULL AFTER frequency"); } catch (\Exception $e) {}
            try { $this->db->exec("ALTER TABLE appraisal_cycles ADD COLUMN employee_deadline DATE NULL AFTER period"); } catch (\Exception $e) {}
            try { $this->db->exec("ALTER TABLE appraisal_cycles ADD COLUMN manager_deadline DATE NULL AFTER employee_deadline"); } catch (\Exception $e) {}
            try { $this->db->exec("ALTER TABLE appraisal_cycles ADD COLUMN hr_deadline DATE NULL AFTER manager_deadline"); } catch (\Exception $e) {}
            try { $this->db->exec("ALTER TABLE appraisal_cycles ADD COLUMN management_deadline DATE NULL AFTER hr_deadline"); } catch (\Exception $e) {}

            $stmt = $this->db->query("SELECT * FROM appraisal_cycles ORDER BY id DESC");
            $cycles = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // extract year from name if not present
            foreach ($cycles as &$c) {
                if (!isset($c['year'])) {
                    preg_match('/\d{4}/', $c['name'], $matches);
                    $c['year'] = $matches[0] ?? date('Y', strtotime($c['created_at_utc']));
                }
            }

            return $this->jsonResponse($cycles);
        } catch (\PDOException $e) {
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Generate / Initiate cycle
     */
    public function generate($data)
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            $service = new \App\Services\AppraisalService();
            
            // Format data to match what the service expects
            $year = $data['year'] ?? date('Y');
            $period = !empty($data['period']) ? ' ' . $data['period'] : '';
            $data['name'] = "Appraisal Cycle {$year}{$period}";
            $data['start_date'] = "{$year}-01-01";
            $data['end_date'] = "{$year}-12-31";
            
            $msg = $service->initiateCycle($data);
            return $this->jsonResponse(null, 201, $msg);
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, $e->getMessage());
        }
    }

    /**
     * Cancel (Withdraw) cycle
     */
    public function cancel($id)
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            $this->db->beginTransaction();
            $this->db->prepare("UPDATE appraisal_cycles SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            $this->db->prepare("UPDATE employee_appraisals SET status = 'withdrawn' WHERE cycle_id = ? AND status != 'finalized'")->execute([$id]);
            $this->db->commit();
            return $this->jsonResponse(null, 200, 'Cycle cancelled successfully');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Update an active cycle's deadlines
     */
    public function update($id, $data)
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            $stmt = $this->db->prepare("
                UPDATE appraisal_cycles 
                SET employee_deadline = ?, manager_deadline = ?, hr_deadline = ?, management_deadline = ?
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([
                $data['employee_deadline'] ?? null,
                $data['manager_deadline'] ?? null,
                $data['hr_deadline'] ?? null,
                $data['management_deadline'] ?? null,
                $id
            ]);
            return $this->jsonResponse(null, 200, 'Cycle updated successfully');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    /**
     * Delete cycle completely
     */
    public function destroy($id)
    {
        \App\Middleware\RoleMiddleware::requirePermission('Appraisals', 'approve');

        try {
            $this->db->beginTransaction();
            // Check if any appraisals exist for this cycle that are not draft? 
            // Or just allow deletion if they are all draft/withdrawn.
            // For safety, we'll just try to delete. Foreign keys might restrict it.
            $this->db->prepare("DELETE FROM employee_appraisals WHERE cycle_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM appraisal_cycles WHERE id = ?")->execute([$id]);
            $this->db->commit();
            return $this->jsonResponse(null, 200, 'Cycle deleted successfully');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->jsonResponse(null, 400, 'Failed to delete. Appraisals might be actively linked.');
        }
    }
}
