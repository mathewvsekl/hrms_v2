<?php
namespace App\Controllers;

use App\Core\Controller;

class PayrollConfigController extends Controller
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        
        // Require HR or Admin roles
        if (!$this->hasAnyRole(['ADMIN', 'SUPERADMIN', 'SUPER_ADMIN', 'HRMANAGER', 'HR_MANAGER'])) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            exit;
        }
    }

    public function getComponents()
    {
        try {
            $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;
            $countryId = isset($_GET['country_id']) ? (int)$_GET['country_id'] : null;

            $sql = "SELECT pc.*, c.name as company_name, cn.name as country_name 
                    FROM payroll_components pc 
                    LEFT JOIN companies c ON pc.company_id = c.id
                    LEFT JOIN countries cn ON pc.country_id = cn.id
                    WHERE 1=1";
            $params = [];

            if ($companyId) {
                $sql .= " AND (pc.company_id = ? OR pc.company_id IS NULL)";
                $params[] = $companyId;
            }
            if ($countryId) {
                $sql .= " AND (pc.country_id = ? OR pc.country_id IS NULL)";
                $params[] = $countryId;
            }

            $sql .= " ORDER BY pc.type ASC, pc.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $components = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($components, 200, 'Components retrieved');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Database error: ' . $e->getMessage());
        }
    }

    public function addComponent($data)
    {
        try {
            if (empty($data['name']) || empty($data['type'])) {
                return $this->jsonResponse(null, 400, 'Name and Type are required');
            }
            if (empty($data['is_statutory']) && empty($data['company_id'])) {
                return $this->jsonResponse(null, 400, 'Company ID is required for non-statutory components');
            }

            $stmt = $this->db->prepare("
                INSERT INTO payroll_components (name, type, computation_type, value, formula, company_id, country_id, is_statutory, is_non_taxable, is_income_tax, round_off, status, display_in_payslip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['type'],
                $data['computation_type'] ?? 'FIXED',
                (float)($data['value'] ?? 0),
                $data['formula'] ?? null,
                !empty($data['company_id']) ? $data['company_id'] : null,
                !empty($data['country_id']) ? $data['country_id'] : null,
                !empty($data['is_statutory']) ? 1 : 0,
                !empty($data['is_non_taxable']) ? 1 : 0,
                !empty($data['is_income_tax']) ? 1 : 0,
                !empty($data['round_off']) ? 1 : 0,
                $data['status'] ?? 'Active',
                isset($data['display_in_payslip']) ? (int)$data['display_in_payslip'] : 1
            ]);

            return $this->jsonResponse(['id' => $this->db->lastInsertId()], 201, 'Component created successfully');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Failed to create component: ' . $e->getMessage());
        }
    }

    public function updateComponent($id, $data)
    {
        try {
            $stmtCheck = $this->db->prepare("SELECT is_statutory FROM payroll_components WHERE id = ?");
            $stmtCheck->execute([$id]);
            $comp = $stmtCheck->fetch();

            if (!$comp) return $this->jsonResponse(null, 404, 'Not found');
            if (empty($comp['is_statutory']) && empty($data['company_id'])) {
                return $this->jsonResponse(null, 400, 'Company ID is required for non-statutory components');
            }

            $stmt = $this->db->prepare("
                UPDATE payroll_components 
                SET name = ?, type = ?, computation_type = ?, value = ?, formula = ?, company_id = ?, country_id = ?, is_non_taxable = ?, is_income_tax = ?, round_off = ?, status = ?, display_in_payslip = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['type'],
                $data['computation_type'] ?? 'FIXED',
                (float)($data['value'] ?? 0),
                $data['formula'] ?? null,
                !empty($data['company_id']) ? $data['company_id'] : null,
                !empty($data['country_id']) ? $data['country_id'] : null,
                !empty($data['is_non_taxable']) ? 1 : 0,
                !empty($data['is_income_tax']) ? 1 : 0,
                !empty($data['round_off']) ? 1 : 0,
                $data['status'] ?? 'Active',
                isset($data['display_in_payslip']) ? (int)$data['display_in_payslip'] : 1,
                $id
            ]);

            return $this->jsonResponse(null, 200, 'Component updated');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Failed to update: ' . $e->getMessage());
        }
    }

    public function deleteComponent($id)
    {
        try {
            $stmtCheck = $this->db->prepare("SELECT is_statutory FROM payroll_components WHERE id = ?");
            $stmtCheck->execute([$id]);
            $comp = $stmtCheck->fetch();

            if (!$comp) return $this->jsonResponse(null, 404, 'Not found');
            if ($comp['is_statutory']) return $this->jsonResponse(null, 403, 'Cannot delete statutory components');

            $stmt = $this->db->prepare("DELETE FROM payroll_components WHERE id = ?");
            $stmt->execute([$id]);
            
            return $this->jsonResponse(null, 200, 'Component deleted');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Get all salary component values for a specific employee
     */
    public function getEmployeeComponents()
    {
        try {
            $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
            if (!$employeeId) return $this->jsonResponse(null, 400, 'employee_id is required');

            $stmt = $this->db->prepare("
                SELECT esc.*, pc.name as component_name, pc.type as component_type, 
                       pc.computation_type, pc.is_statutory, pc.company_id, c.name as company_name
                FROM employee_salary_components esc
                JOIN payroll_components pc ON esc.component_id = pc.id
                LEFT JOIN companies c ON pc.company_id = c.id
                WHERE esc.employee_id = ?
                ORDER BY esc.effective_date DESC, c.name ASC, pc.type ASC, pc.name ASC
            ");
            $stmt->execute([$employeeId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($rows, 200, 'Employee components retrieved');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Save (upsert) employee salary component values
     * Expects: { employee_id, effective_date, currency_code, components: [ { component_id, amount } ] }
     */
    public function saveEmployeeComponents($data)
    {
        try {
            $employeeId = $data['employee_id'] ?? null;
            $effectiveDate = $data['effective_date'] ?? null;
            $currencyCode = $data['currency_code'] ?? 'UGX';
            $components = $data['components'] ?? [];
            $companyId = $data['company_id'] ?? null;

            if (!$employeeId || !$effectiveDate || empty($components) || !$companyId) {
                return $this->jsonResponse(null, 400, 'employee_id, effective_date, company_id, and components are required');
            }

            $this->db->beginTransaction();

            // Delete existing entries for this employee + effective_date + company_id
            $stmtDel = $this->db->prepare("
                DELETE esc FROM employee_salary_components esc
                JOIN payroll_components pc ON esc.component_id = pc.id
                WHERE esc.employee_id = ? AND esc.effective_date = ? AND pc.company_id = ?
            ");
            $stmtDel->execute([$employeeId, $effectiveDate, $companyId]);

            // Insert new values
            $stmtInsert = $this->db->prepare("
                INSERT INTO employee_salary_components (employee_id, component_id, amount, effective_date, currency_code)
                VALUES (?, ?, ?, ?, ?)
            ");

                foreach ($components as $comp) {
                    if (empty($comp['component_id'])) continue;
                    
                    // Handle potential comma formatting from frontend
                    $amountVal = $comp['amount'] ?? 0;
                    if (is_string($amountVal)) {
                        $amountVal = str_replace(',', '', $amountVal);
                    }
                    
                    $stmtInsert->execute([
                        $employeeId,
                        (int)$comp['component_id'],
                        (float)$amountVal,
                        $effectiveDate,
                        $currencyCode
                    ]);
                }

            $this->db->commit();
            return $this->jsonResponse(null, 200, 'Employee salary components saved successfully');
        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->jsonResponse(null, 500, 'Failed to save: ' . $e->getMessage());
        }
    }

    /**
     * Delete a specific employee salary component entry
     */
    public function deleteEmployeeComponent($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM employee_salary_components WHERE id = ?");
            $stmt->execute([$id]);
            return $this->jsonResponse(null, 200, 'Entry deleted');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Delete all employee salary component entries for a given effective_date
     */
    public function deleteEmployeeComponentsByDate()
    {
        try {
            $employeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : null;
            $effectiveDate = $_GET['effective_date'] ?? null;
            $companyId = isset($_GET['company_id']) ? (int)$_GET['company_id'] : null;

            if (!$employeeId || !$effectiveDate || !$companyId) {
                return $this->jsonResponse(null, 400, 'employee_id, effective_date, and company_id are required');
            }

            $stmt = $this->db->prepare("
                DELETE esc FROM employee_salary_components esc
                JOIN payroll_components pc ON esc.component_id = pc.id
                WHERE esc.employee_id = ? AND esc.effective_date = ? AND pc.company_id = ?
            ");
            $stmt->execute([$employeeId, $effectiveDate, $companyId]);
            return $this->jsonResponse(null, 200, 'Salary record deleted');
        } catch (\Exception $e) {
            return $this->jsonResponse(null, 500, 'Failed to delete record: ' . $e->getMessage());
        }
    }
}
