<?php

namespace App\Services;

use Exception;
use PDO;

/**
 * PayrollService
 * 
 * Handles Uganda-specific payroll calculations including PAYE and NSSF.
 */
class PayrollService
{
    private $db;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
    }

    /**
     * Get aggregate payroll summary
     */
    public function getSummary(array $sessionData): array
    {
        // Keep existing stub plus maybe some counts
        $stmt = $this->db->query("SELECT COUNT(*) as cnt FROM payroll_records");
        $count = $stmt->fetchColumn();

        return [
            'integrity_perc' => 100,
            'total_runs' => (int)$count,
            'pending_audits' => 0,
            'last_run_date' => date('Y-m-d')
        ];
    }

    /**
     * Calculate Uganda NSSF
     * 
     * @param float $grossPay
     * @return array [employee_contribution, employer_contribution]
     */
    public function calculateNSSF(float $grossPay): array
    {
        return [
            'employee' => round($grossPay * 0.05, 2),
            'employer' => round($grossPay * 0.10, 2)
        ];
    }

    /**
     * Calculate Uganda PAYE based on URA tax brackets
     * 
     * @param float $grossPay
     * @return float
     */
    public function calculatePAYE(float $grossPay, ?int $companyId = null): float
    {
        static $slabs = null;
        if ($slabs === null) {
            // Fetch slabs from database, ordered by min_amount ASC
            // In a real multi-company setup, we would filter by companyId if applicable,
            // but for now, we'll fetch the global or default slabs.
            $stmt = $this->db->query("SELECT * FROM tax_slabs ORDER BY min_amount ASC");
            $slabs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        if (empty($slabs)) {
            return 0.0;
        }

        $tax = 0.0;
        foreach ($slabs as $slab) {
            $min = (float)$slab['min_amount'];
            
            if ($grossPay <= $min) {
                continue; // Wait, actually if grossPay <= min, we are done.
                // But the brackets might be 0-based.
                // If the bracket starts at $min and grossPay is less than or equal to $min, there is no taxable amount in this bracket.
            }

            $max = $slab['max_amount'] !== null ? (float)$slab['max_amount'] : INF;
            
            // Calculate how much of the gross pay falls within this specific slab
            $upperBound = min($grossPay, $max);
            $taxableInBracket = $upperBound - $min;
            
            if ($taxableInBracket > 0) {
                if (isset($slab['tax_type']) && $slab['tax_type'] === 'FIXED') {
                    $tax += (float)$slab['fixed_amount'];
                } else {
                    $tax += $taxableInBracket * ((float)$slab['percentage'] / 100);
                }
            }
        }

        return round($tax, 2);
    }

    /**
     * Generate Payroll for a given month and year
     * 
     * @param int $month
     * @param int $year
     * @param int $companyId
     * @return array Status message and count of processed employees
     */
    public function generatePayroll(int $month, int $year, int $companyId = 1): array
    {
        $this->db->beginTransaction();

        try {
            // Check if payroll already run for this month and company
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM payroll_records pr 
                JOIN employees e ON pr.employee_id = e.id 
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
                WHERE pr.month = ? AND pr.year = ? AND ec.company_id = ?
            ");
            $stmt->execute([$month, $year, $companyId]);
            if ($stmt->fetchColumn() > 0) {
                // Delete existing draft payroll for this month/year/company to regenerate
                $delStmt = $this->db->prepare("
                    DELETE pr FROM payroll_records pr 
                    JOIN employees e ON pr.employee_id = e.id 
                    JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
                    WHERE pr.month = ? AND pr.year = ? AND pr.status = 'Draft' AND ec.company_id = ?
                ");
                $delStmt->execute([$month, $year, $companyId]);
            }

            // Fetch active employees for the specific company
            $empStmt = $this->db->prepare("
                SELECT e.* FROM employees e
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
                WHERE e.status = 'Active' AND ec.company_id = ?
            ");
            $empStmt->execute([$companyId]);
            $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

            $insertStmt = $this->db->prepare("
                INSERT INTO payroll_records 
                (employee_id, month, year, basic_pay, commissions, other_earnings, earnings_json, deductions_json, gross_chargeable_income, 
                paye_deduction, nssf_employee_deduction, nssf_employer_contribution, advance_deductions, net_pay, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')
            ");

            $processedCount = 0;

            foreach ($employees as $emp) {
                // Fetch the latest active salary structure date for this payroll month
                $targetDate = date('Y-m-t', strtotime("$year-$month-01")); // Last day of the payroll month
                
                $dateStmt = $this->db->prepare("
                    SELECT MAX(effective_date) as latest_date 
                    FROM employee_salary_components 
                    WHERE employee_id = ? AND effective_date <= ?
                ");
                $dateStmt->execute([$emp['id'], $targetDate]);
                $latestDate = $dateStmt->fetchColumn();

                if (!$latestDate) {
                    continue; // Skip employees with no salary configuration
                }

                $salStmt = $this->db->prepare("
                    SELECT esc.amount, pc.name, pc.type, pc.is_statutory, pc.is_non_taxable, pc.display_in_payslip 
                    FROM employee_salary_components esc
                    JOIN payroll_components pc ON esc.component_id = pc.id
                    WHERE esc.employee_id = ? AND esc.effective_date = ?
                ");
                $salStmt->execute([$emp['id'], $latestDate]);
                $allComponents = $salStmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($allComponents)) {
                    continue; // Skip employees with no salary configuration
                }

                $earnings = [];
                $deductions = [];
                $basicPay = 0;
                $commissions = 0;
                $otherEarnings = 0;
                $grossChargeable = 0;
                $totalEarningsAmt = 0;

                foreach ($allComponents as $comp) {
                    $amt = (float)$comp['amount'];
                    if ($comp['type'] === 'EARNING') {
                        $earnings[] = ['name' => $comp['name'], 'amount' => $amt, 'is_non_taxable' => $comp['is_non_taxable'], 'display_in_payslip' => $comp['display_in_payslip']];
                        $totalEarningsAmt += $amt;
                        
                        if (empty($comp['is_non_taxable'])) {
                            $grossChargeable += $amt;
                        }
                        
                        // Try to map to legacy columns for backward compatibility if possible
                        $nameLower = strtolower($comp['name']);
                        if (strpos($nameLower, 'basic') !== false || strpos($nameLower, 'base') !== false) $basicPay += $amt;
                        elseif (strpos($nameLower, 'commission') !== false) $commissions += $amt;
                        else $otherEarnings += $amt;
                    } else if ($comp['type'] === 'DEDUCTION') {
                        $deductions[] = ['name' => $comp['name'], 'amount' => $amt, 'display_in_payslip' => $comp['display_in_payslip']];
                    }
                }

                if ($totalEarningsAmt <= 0) continue; // Skip employees with no salary
                
                $nssf = $this->calculateNSSF($totalEarningsAmt);
                $paye = $this->calculatePAYE($grossChargeable);

                // Fetch advances
                $advStmt = $this->db->prepare("SELECT id, amount FROM salary_advances WHERE employee_id = ? AND status = 'Pending'");
                $advStmt->execute([$emp['id']]);
                $advances = $advStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $totalAdvanceDeductions = 0.00;
                $advanceIds = [];
                foreach ($advances as $adv) {
                    $totalAdvanceDeductions += (float)$adv['amount'];
                    $advanceIds[] = $adv['id'];
                }

                $totalOtherDeductions = array_reduce($deductions, function($carry, $item) {
                    return $carry + $item['amount'];
                }, 0);

                $netPay = $totalEarningsAmt - $paye - $nssf['employee'] - $totalAdvanceDeductions - $totalOtherDeductions;

                $insertStmt->execute([
                    $emp['id'],
                    $month,
                    $year,
                    $basicPay,
                    $commissions,
                    $otherEarnings,
                    json_encode($earnings),
                    json_encode($deductions),
                    $grossChargeable,
                    $paye,
                    $nssf['employee'],
                    $nssf['employer'],
                    $totalAdvanceDeductions,
                    $netPay
                ]);

                $payrollRecordId = $this->db->lastInsertId();

                // Update advances to point to this payroll record
                if (!empty($advanceIds)) {
                    $marksStmt = implode(',', array_fill(0, count($advanceIds), '?'));
                    $updateAdvStmt = $this->db->prepare("UPDATE salary_advances SET deducted_in_payroll_id = ?, status = 'Deducted' WHERE id IN ($marksStmt)");
                    $params = array_merge([$payrollRecordId], $advanceIds);
                    $updateAdvStmt->execute($params);
                }

                $processedCount++;
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Payroll generated successfully for $processedCount employees.",
                'count' => $processedCount
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => "Error generating payroll: " . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get Payslip data for a given payroll record ID
     */
    public function getPayslipData(int $payrollRecordId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pr.*, e.employee_code as emp_code, e.first_name, e.last_name, e.bank_account_no, e.bank_name,
                   e.custom_data,
                   d.title as designation_name
            FROM payroll_records pr
            JOIN employees e ON pr.employee_id = e.id
            JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
            JOIN companies c ON ec.company_id = c.id
            LEFT JOIN designations d ON e.designation_id = d.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$payrollRecordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $customData = !empty($row['custom_data']) ? json_decode($row['custom_data'], true) : [];
            $row['tin_no'] = $customData['tin_no'] ?? null;
            $row['nssf_no'] = $customData['nssf_no'] ?? null;
            unset($row['custom_data']);
            return $row;
        }
        return null;
    }

    /**
     * Update an individual payroll record
     */
    public function updateRecord(int $id, float $basicPay, float $commissions, float $otherEarnings, ?array $earnings = null, ?array $deductions = null): array
    {
        $stmt = $this->db->prepare("SELECT * FROM payroll_records WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return ['success' => false, 'message' => 'Record not found'];
        }

        $grossChargeable = 0;
        $totalEarningsAmt = 0;
        $totalOtherDeductions = 0;

        if ($earnings !== null && $deductions !== null) {
            foreach ($earnings as $e) {
                $amt = (float)$e['amount'];
                $totalEarningsAmt += $amt;
                if (empty($e['is_non_taxable'])) {
                    $grossChargeable += $amt;
                }
            }
            foreach ($deductions as $d) {
                $totalOtherDeductions += (float)$d['amount'];
            }
        } else {
            $grossChargeable = $basicPay + $commissions + $otherEarnings;
            $totalEarningsAmt = $grossChargeable;
            // Legacy handling doesn't calculate other deductions properly, but they weren't editable anyway.
        }

        $nssf = $this->calculateNSSF($totalEarningsAmt);
        $paye = $this->calculatePAYE($grossChargeable);
        
        $advanceDeductions = (float)$record['advance_deductions'];
        $netPay = $totalEarningsAmt - $paye - $nssf['employee'] - $advanceDeductions - $totalOtherDeductions;

        if ($earnings !== null && $deductions !== null) {
            $updateStmt = $this->db->prepare("
                UPDATE payroll_records 
                SET basic_pay = ?, commissions = ?, other_earnings = ?, earnings_json = ?, deductions_json = ?, gross_chargeable_income = ?, 
                    paye_deduction = ?, nssf_employee_deduction = ?, nssf_employer_contribution = ?, net_pay = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $basicPay, $commissions, $otherEarnings, json_encode($earnings), json_encode($deductions), $grossChargeable, 
                $paye, $nssf['employee'], $nssf['employer'], $netPay, 
                $id
            ]);
        } else {
            $updateStmt = $this->db->prepare("
                UPDATE payroll_records 
                SET basic_pay = ?, commissions = ?, other_earnings = ?, gross_chargeable_income = ?, 
                    paye_deduction = ?, nssf_employee_deduction = ?, nssf_employer_contribution = ?, net_pay = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $basicPay, $commissions, $otherEarnings, $grossChargeable, 
                $paye, $nssf['employee'], $nssf['employer'], $netPay, 
                $id
            ]);
        }

        return ['success' => true, 'message' => 'Payroll record updated successfully'];
    }

    /**
     * Delete an individual payroll record
     */
    public function deleteRecord(int $id): array
    {
        try {
            $this->db->beginTransaction();

            // Check if record exists
            $stmt = $this->db->prepare("SELECT * FROM payroll_records WHERE id = ?");
            $stmt->execute([$id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return ['success' => false, 'message' => 'Record not found'];
            }

            // Unlink any salary advances that were deducted in this run
            $advStmt = $this->db->prepare("UPDATE salary_advances SET deducted_in_payroll_id = NULL, status = 'Pending' WHERE deducted_in_payroll_id = ?");
            $advStmt->execute([$id]);

            // Delete the payroll record
            $delStmt = $this->db->prepare("DELETE FROM payroll_records WHERE id = ?");
            $delStmt->execute([$id]);

            $this->db->commit();
            return ['success' => true, 'message' => 'Payroll record deleted successfully'];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error deleting record: ' . $e->getMessage()];
        }
    }
}

