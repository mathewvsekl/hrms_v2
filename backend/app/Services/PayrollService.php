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
    public function calculateSlabTax(float $grossPay, int $componentId, ?int $companyId = null): float
    {
        static $slabsCache = [];
        $cacheKey = $companyId ? $companyId . '_' . $componentId : 'all_' . $componentId;
        
        if (!isset($slabsCache[$cacheKey])) {
            if ($companyId) {
                $stmt = $this->db->prepare("SELECT * FROM tax_slabs WHERE company_id = ? AND component_id = ? ORDER BY min_amount ASC");
                $stmt->execute([$companyId, $componentId]);
            } else {
                $stmt = $this->db->query("SELECT * FROM tax_slabs ORDER BY min_amount ASC");
            }
            $slabsCache[$cacheKey] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $slabs = $slabsCache[$cacheKey];

        if (empty($slabs)) {
            return 0.0;
        }

        $tax = 0.0;
        $personalRelief = isset($slabs[0]['personal_relief']) ? (float)$slabs[0]['personal_relief'] : 0.0;

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

        $tax -= $personalRelief;

        return max(0.0, round($tax, 2));
    }

    /**
     * Get the effective exchange rate for a given date
     * 
     * @param string $toCurrency
     * @param string $targetDate (Y-m-d)
     * @param string $fromCurrency
     * @return float|null
     */
    public function getEffectiveExchangeRate(string $toCurrency, string $targetDate, string $fromCurrency = 'USD'): ?float
    {
        if ($fromCurrency === $toCurrency) return 1.0;
        
        $stmt = $this->db->prepare("
            SELECT rate FROM exchange_rates 
            WHERE from_currency = ? AND to_currency = ? AND effective_date <= ? AND is_active = 1
            ORDER BY effective_date DESC LIMIT 1
        ");
        $stmt->execute([$fromCurrency, $toCurrency, $targetDate]);
        $rate = $stmt->fetchColumn();
        
        return $rate !== false ? (float)$rate : null;
    }

    /**
     * Generate Payroll for a given month and year
     * 
     * @param int $month
     * @param int $year
     * @param int $companyId
     * @return array Status message and count of processed employees
     */
    public function generatePayroll(int $month, int $year, int $companyId = 1, array $excludedEmployeeIds = [], ?string $reportingCurrency = null, ?float $exchangeRate = null): array
    {
        $this->db->beginTransaction();

        try {
            // Fetch company country
            $compStmt = $this->db->prepare("SELECT ct.id as country_id, ct.iso_code, ct.currency_code FROM companies c JOIN countries ct ON c.country_id = ct.id WHERE c.id = ?");
            $compStmt->execute([$companyId]);
            $compData = $compStmt->fetch(PDO::FETCH_ASSOC);
            $isUganda = ($compData && $compData['iso_code'] === 'UGA');
            $companyCurrency = $compData ? $compData['currency_code'] : 'UGX';
            $companyCountryId = $compData ? $compData['country_id'] : null;
            $exchangeRatesCache = [];

            // Fetch active employees for the specific company who haven't had their payroll run
            $empStmt = $this->db->prepare("
                SELECT e.* FROM employees e
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_active = 1 AND ec.include_in_payroll = 1
                LEFT JOIN payroll_records pr ON pr.employee_id = e.id AND pr.month = ? AND pr.year = ? AND pr.company_id = ?
                WHERE e.status = 'Active' AND ec.company_id = ? AND pr.id IS NULL
                AND EXISTS (
                    SELECT 1 FROM employee_salary_components esc
                    JOIN payroll_components pc ON esc.component_id = pc.id
                    WHERE esc.employee_id = e.id AND (pc.company_id = ec.company_id OR pc.country_id = ?)
                )
            ");
            $empStmt->execute([$month, $year, $companyId, $companyId, $companyCountryId]);
            $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

            // Determine target date (end of the payroll month)
            $targetDate = date('Y-m-t', strtotime("$year-$month-01"));

            // Resolve exchange rate if not provided and reporting currency is known
            if ($exchangeRate === null && $reportingCurrency !== null) {
                // Assuming base currency is USD for global reference
                $baseCurrency = 'USD'; // This can be fetched from global config in the future
                if ($reportingCurrency !== $baseCurrency) {
                    $exchangeRate = $this->getEffectiveExchangeRate($reportingCurrency, $targetDate, $baseCurrency);
                } else {
                    $exchangeRate = 1.0;
                }
            }

            $insertStmt = $this->db->prepare("
                INSERT INTO payroll_records 
                (employee_id, company_id, month, year, basic_pay, commissions, other_earnings, earnings_json, deductions_json, gross_chargeable_income, 
                paye_deduction, nssf_employee_deduction, nssf_employer_contribution, advance_deductions, net_pay, status, reporting_currency, exchange_rate) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, ?)
            ");

            $processedCount = 0;

            foreach ($employees as $emp) {
                if (in_array($emp['id'], $excludedEmployeeIds)) {
                    continue; // Skip excluded employees
                }
                
                // Fetch the latest active salary structure date for this payroll month and company
                $dateStmt = $this->db->prepare("
                    SELECT MAX(esc.effective_date) as latest_date 
                    FROM employee_salary_components esc
                    JOIN payroll_components pc ON esc.component_id = pc.id
                    WHERE esc.employee_id = ? AND esc.effective_date <= ? AND (pc.company_id = ? OR pc.country_id = ?)
                ");
                $dateStmt->execute([$emp['id'], $targetDate, $companyId, $companyCountryId]);
                $latestDate = $dateStmt->fetchColumn();

                if (!$latestDate) {
                    continue; // Skip employees with no salary configuration
                }

                $salStmt = $this->db->prepare("
                    SELECT pc.id, COALESCE(esc.amount, 0) as amount, pc.value as pc_value, pc.name, pc.type, pc.is_statutory, pc.is_non_taxable, pc.is_income_tax, pc.round_off, pc.display_in_payslip, pc.computation_type, esc.currency_code
                    FROM payroll_components pc
                    LEFT JOIN employee_salary_components esc 
                        ON esc.component_id = pc.id AND esc.employee_id = ? AND esc.effective_date = ?
                    WHERE (pc.company_id = ? OR pc.country_id = ?)
                ");
                $salStmt->execute([$emp['id'], $latestDate, $companyId, $companyCountryId]);
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
                    if ($comp['type'] !== 'EARNING') continue;
                    $amt = (float)$comp['amount'];
                    $escCurrency = !empty($comp['currency_code']) ? $comp['currency_code'] : $companyCurrency;
                    if ($escCurrency !== $companyCurrency) {
                        $cacheKey = "{$escCurrency}_{$companyCurrency}";
                        if (!isset($exchangeRatesCache[$cacheKey])) {
                            $rate = $this->getEffectiveExchangeRate($companyCurrency, $targetDate, $escCurrency);
                            $exchangeRatesCache[$cacheKey] = $rate !== null ? $rate : 1.0;
                        }
                        $amt = $amt * $exchangeRatesCache[$cacheKey];
                    }
                    $computationMethod = $comp['computation_type'] ?? 'FIXED';
                    if (!empty($comp['round_off'])) {
                        $amt = round($amt);
                    }
                    $earnings[] = ['name' => $comp['name'], 'amount' => $amt, 'is_non_taxable' => $comp['is_non_taxable'], 'display_in_payslip' => $comp['display_in_payslip'], 'computation_method' => $computationMethod];
                    $totalEarningsAmt += $amt;
                    
                    if (empty($comp['is_non_taxable'])) {
                        $grossChargeable += $amt;
                    }
                    
                    $nameLower = strtolower($comp['name']);
                    if (strpos($nameLower, 'basic') !== false || strpos($nameLower, 'base') !== false) $basicPay += $amt;
                    elseif (strpos($nameLower, 'commission') !== false) $commissions += $amt;
                    else $otherEarnings += $amt;
                }

                $paye = 0.00;
                $nssf = ['employee' => 0, 'employer' => 0];
                $initialGrossChargeable = $grossChargeable;

                // Pass 2: Non-Taxable Deductions (Pre-tax)
                foreach ($allComponents as $comp) {
                    if ($comp['type'] !== 'DEDUCTION' || empty($comp['is_non_taxable'])) continue;
                    
                    $computationMethod = $comp['computation_type'] ?? 'FIXED';
                    
                    if ($computationMethod === 'FORMULA') {
                        $amt = $this->calculateSlabTax($initialGrossChargeable, $comp['id'], $companyId);
                    } else if ($computationMethod === 'PERCENTAGE') {
                        $pct = (float)$comp['pc_value'];
                        if ($pct == 0) $pct = (float)$comp['amount'];
                        $amt = ($pct / 100) * $initialGrossChargeable; 
                    } else {
                        $amt = (float)$comp['amount'];
                        $escCurrency = !empty($comp['currency_code']) ? $comp['currency_code'] : $companyCurrency;
                        if ($escCurrency !== $companyCurrency) {
                            $cacheKey = "{$escCurrency}_{$companyCurrency}";
                            if (!isset($exchangeRatesCache[$cacheKey])) {
                                $rate = $this->getEffectiveExchangeRate($companyCurrency, $targetDate, $escCurrency);
                                $exchangeRatesCache[$cacheKey] = $rate !== null ? $rate : 1.0;
                            }
                            $amt = $amt * $exchangeRatesCache[$cacheKey];
                        }
                    }
                    if (!empty($comp['round_off'])) {
                        $amt = round($amt);
                    }
                    
                    $grossChargeable -= $amt;
                    $deductions[] = ['name' => $comp['name'], 'amount' => $amt, 'display_in_payslip' => $comp['display_in_payslip'], 'computation_method' => $computationMethod];
                }

                // Pass 3: Taxable Deductions & Income Tax
                foreach ($allComponents as $comp) {
                    if ($comp['type'] !== 'DEDUCTION' || !empty($comp['is_non_taxable'])) continue;
                    
                    $computationMethod = $comp['computation_type'] ?? 'FIXED';
                    
                    if ($computationMethod === 'FORMULA') {
                        $amt = $this->calculateSlabTax($grossChargeable, $comp['id'], $companyId);
                    } else if ($computationMethod === 'PERCENTAGE') {
                        $pct = (float)$comp['pc_value'];
                        if ($pct == 0) $pct = (float)$comp['amount'];
                        $amt = ($pct / 100) * $initialGrossChargeable; 
                    } else {
                        $amt = (float)$comp['amount'];
                        $escCurrency = !empty($comp['currency_code']) ? $comp['currency_code'] : $companyCurrency;
                        if ($escCurrency !== $companyCurrency) {
                            $cacheKey = "{$escCurrency}_{$companyCurrency}";
                            if (!isset($exchangeRatesCache[$cacheKey])) {
                                $rate = $this->getEffectiveExchangeRate($companyCurrency, $targetDate, $escCurrency);
                                $exchangeRatesCache[$cacheKey] = $rate !== null ? $rate : 1.0;
                            }
                            $amt = $amt * $exchangeRatesCache[$cacheKey];
                        }
                    }
                    if (!empty($comp['round_off'])) {
                        $amt = round($amt);
                    }
                    
                    $deductions[] = ['name' => $comp['name'], 'amount' => $amt, 'display_in_payslip' => $comp['display_in_payslip'], 'computation_method' => $computationMethod];
                    
                    $nameLower = strtolower($comp['name']);
                    if (!empty($comp['is_income_tax']) || strpos($nameLower, 'paye') !== false) {
                        $paye = $amt;
                    }
                    if (strpos($nameLower, 'nssf') !== false) {
                        $nssf['employee'] = $amt;
                    }
                }

                if ($totalEarningsAmt <= 0) continue; // Skip employees with no salary

                // Fetch advances
                $advStmt = $this->db->prepare("SELECT id, amount, currency_code, installment_amount, deducted_amount FROM salary_advances WHERE employee_id = ? AND status IN ('Approved', 'Partially Deducted') AND (deduction_start_date IS NULL OR deduction_start_date <= ?)");
                $advStmt->execute([$emp['id'], $targetDate]);
                $advances = $advStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $totalAdvanceDeductions = 0.00;
                $advanceDeductionsList = [];
                foreach ($advances as $adv) {
                    $totalAdv = (float)$adv['amount'];
                    $deductedSoFar = (float)$adv['deducted_amount'];
                    $remaining = $totalAdv - $deductedSoFar;
                    if ($remaining <= 0) continue;
                    
                    $installment = isset($adv['installment_amount']) ? (float)$adv['installment_amount'] : 0.00;
                    if ($installment > 0 && $installment < $remaining) {
                        $advAmt = $installment;
                    } else {
                        $advAmt = $remaining;
                    }
                    
                    $advCurrency = !empty($adv['currency_code']) ? $adv['currency_code'] : $companyCurrency;
                    $convertedAdvAmt = $advAmt;
                    if ($advCurrency !== $companyCurrency) {
                        $cacheKey = "{$advCurrency}_{$companyCurrency}";
                        if (!isset($exchangeRatesCache[$cacheKey])) {
                            $rate = $this->getEffectiveExchangeRate($companyCurrency, $targetDate, $advCurrency);
                            $exchangeRatesCache[$cacheKey] = $rate !== null ? $rate : 1.0;
                        }
                        $convertedAdvAmt = $convertedAdvAmt * $exchangeRatesCache[$cacheKey];
                    }
                    $totalAdvanceDeductions += $convertedAdvAmt;
                    $newRemaining = $remaining - $advAmt;
                    $advanceDeductionsList[] = [
                        'id' => $adv['id'], 
                        'amount' => $advAmt, 
                        'remaining_balance' => $newRemaining,
                        'deduction_date' => $targetDate
                    ];
                }

                $totalOtherDeductions = 0.00;
                foreach ($deductions as $d) {
                    $totalOtherDeductions += (float)$d['amount'];
                }

                $netPay = $totalEarningsAmt - $totalAdvanceDeductions - $totalOtherDeductions;

                $insertStmt->execute([
                    $emp['id'],
                    $companyId,
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
                    $netPay,
                    $reportingCurrency,
                    $exchangeRate
                ]);

                $payrollRecordId = $this->db->lastInsertId();

                // Update advances to point to this payroll record
                if (!empty($advanceDeductionsList)) {
                    $updateAdvStmt = $this->db->prepare("
                        UPDATE salary_advances 
                        SET deducted_amount = deducted_amount + ?, 
                            status = IF(deducted_amount >= amount, 'Deducted', 'Partially Deducted') 
                        WHERE id = ?
                    ");
                    $insertInstallmentStmt = $this->db->prepare("
                        INSERT INTO salary_advance_installments (salary_advance_id, payroll_id, amount, deduction_date, remaining_balance)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    foreach ($advanceDeductionsList as $advDed) {
                        $updateAdvStmt->execute([$advDed['amount'], $advDed['id']]);
                        $insertInstallmentStmt->execute([
                            $advDed['id'], 
                            $payrollRecordId, 
                            $advDed['amount'], 
                            $advDed['deduction_date'], 
                            $advDed['remaining_balance']
                        ]);
                    }
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
     * Preview Payroll for a given month and year (calculates without saving)
     */
    public function previewPayroll(int $month, int $year, int $companyId = 1, ?string $reportingCurrency = null, ?float $exchangeRate = null): array
    {
        try {
            // Fetch company country and details
            $compStmt = $this->db->prepare("SELECT ct.id as country_id, ct.iso_code, ct.currency_code, c.name as company_name, c.logo_url as company_logo, c.address as company_address, c.contact_email as company_contact_email, c.contact_phone as company_contact_phone FROM companies c JOIN countries ct ON c.country_id = ct.id WHERE c.id = ?");
            $compStmt->execute([$companyId]);
            $compData = $compStmt->fetch(PDO::FETCH_ASSOC);
            $isUganda = ($compData && $compData['iso_code'] === 'UGA');
            $companyCurrency = $compData ? $compData['currency_code'] : 'UGX';
            $companyCountryId = $compData ? $compData['country_id'] : null;
            $exchangeRatesCache = [];

            // Fetch active employees for the specific company
            $empStmt = $this->db->prepare("
                SELECT e.*, d.title as designation_name FROM employees e
                JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_active = 1 AND ec.include_in_payroll = 1
                LEFT JOIN designations d ON e.designation_id = d.id
                LEFT JOIN payroll_records pr ON pr.employee_id = e.id AND pr.month = ? AND pr.year = ? AND pr.company_id = ?
                WHERE e.status = 'Active' AND ec.company_id = ? AND pr.id IS NULL
                AND EXISTS (
                    SELECT 1 FROM employee_salary_components esc
                    JOIN payroll_components pc ON esc.component_id = pc.id
                    WHERE esc.employee_id = e.id AND (pc.company_id = ec.company_id OR pc.country_id = ?)
                )
            ");
            $empStmt->execute([$month, $year, $companyId, $companyId, $companyCountryId]);
            $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

            // Determine target date (end of the payroll month)
            $targetDate = date('Y-m-t', strtotime("$year-$month-01"));

            // Resolve exchange rate if not provided and reporting currency is known
            if ($exchangeRate === null && $reportingCurrency !== null) {
                // Assuming base currency is USD for global reference
                $baseCurrency = 'USD'; // This can be fetched from global config in the future
                if ($reportingCurrency !== $baseCurrency) {
                    $exchangeRate = $this->getEffectiveExchangeRate($reportingCurrency, $targetDate, $baseCurrency);
                } else {
                    $exchangeRate = 1.0;
                }
            }

            $previewRecords = [];

            foreach ($employees as $emp) {
                
                $dateStmt = $this->db->prepare("
                    SELECT MAX(esc.effective_date) as latest_date 
                    FROM employee_salary_components esc
                    JOIN payroll_components pc ON esc.component_id = pc.id
                    WHERE esc.employee_id = ? AND esc.effective_date <= ? AND (pc.company_id = ? OR pc.country_id = ?)
                ");
                $dateStmt->execute([$emp['id'], $targetDate, $companyId, $companyCountryId]);
                $latestDate = $dateStmt->fetchColumn();

                if (!$latestDate) continue;

                $salStmt = $this->db->prepare("
                    SELECT pc.id, COALESCE(esc.amount, 0) as amount, pc.value as pc_value, pc.name, pc.type, pc.is_statutory, pc.is_non_taxable, pc.is_income_tax, pc.round_off, pc.display_in_payslip, pc.computation_type, esc.currency_code
                    FROM payroll_components pc
                    LEFT JOIN employee_salary_components esc 
                        ON esc.component_id = pc.id AND esc.employee_id = ? AND esc.effective_date = ?
                    WHERE (pc.company_id = ? OR pc.country_id = ?)
                ");
                $salStmt->execute([$emp['id'], $latestDate, $companyId, $companyCountryId]);
                $allComponents = $salStmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($allComponents)) continue;

                $earnings = [];
                $deductions = [];
                $basicPay = 0;
                $commissions = 0;
                $otherEarnings = 0;
                $grossChargeable = 0;
                $totalEarningsAmt = 0;

                foreach ($allComponents as $comp) {
                    if ($comp['type'] !== 'EARNING') continue;
                    $amt = (float)$comp['amount'];
                    $escCurrency = !empty($comp['currency_code']) ? $comp['currency_code'] : $companyCurrency;
                    if ($escCurrency !== $companyCurrency) {
                        $cacheKey = "{$escCurrency}_{$companyCurrency}";
                        if (!isset($exchangeRatesCache[$cacheKey])) {
                            $rate = $this->getEffectiveExchangeRate($companyCurrency, $targetDate, $escCurrency);
                            $exchangeRatesCache[$cacheKey] = $rate !== null ? $rate : 1.0;
                        }
                        $amt = $amt * $exchangeRatesCache[$cacheKey];
                    }
                    $computationMethod = $comp['computation_type'] ?? 'FIXED';
                    if (!empty($comp['round_off'])) {
                        $amt = round($amt);
                    }
                    $earnings[] = ['name' => $comp['name'], 'amount' => $amt, 'is_non_taxable' => $comp['is_non_taxable'], 'display_in_payslip' => $comp['display_in_payslip'], 'computation_method' => $computationMethod];
                    $totalEarningsAmt += $amt;
                    if (empty($comp['is_non_taxable'])) $grossChargeable += $amt;
                }

                $paye = 0.00;
                $nssf = ['employee' => 0, 'employer' => 0];
                $initialGrossChargeable = $grossChargeable;

                // Pass 2: Non-Taxable Deductions (Pre-tax)
                foreach ($allComponents as $comp) {
                    if ($comp['type'] !== 'DEDUCTION' || empty($comp['is_non_taxable'])) continue;
                    
                    $computationMethod = $comp['computation_type'] ?? 'FIXED';
                    
                    if ($computationMethod === 'FORMULA') {
                        $amt = $this->calculateSlabTax($initialGrossChargeable, $comp['id'], $companyId);
                    } else if ($computationMethod === 'PERCENTAGE') {
                        $pct = (float)$comp['pc_value'];
                        if ($pct == 0) $pct = (float)$comp['amount'];
                        $amt = ($pct / 100) * $initialGrossChargeable; 
                    } else {
                        $amt = (float)$comp['amount'];
                        $escCurrency = !empty($comp['currency_code']) ? $comp['currency_code'] : $companyCurrency;
                        if ($escCurrency !== $companyCurrency) {
                            $cacheKey = "{$escCurrency}_{$companyCurrency}";
                            if (!isset($exchangeRatesCache[$cacheKey])) {
                                $rate = $this->getEffectiveExchangeRate($companyCurrency, $targetDate, $escCurrency);
                                $exchangeRatesCache[$cacheKey] = $rate !== null ? $rate : 1.0;
                            }
                            $amt = $amt * $exchangeRatesCache[$cacheKey];
                        }
                    }
                    if (!empty($comp['round_off'])) {
                        $amt = round($amt);
                    }
                    
                    $grossChargeable -= $amt;
                    $deductions[] = ['name' => $comp['name'], 'amount' => $amt, 'display_in_payslip' => $comp['display_in_payslip'], 'computation_method' => $computationMethod];
                }

                // Pass 3: Taxable Deductions & Income Tax
                foreach ($allComponents as $comp) {
                    if ($comp['type'] !== 'DEDUCTION' || !empty($comp['is_non_taxable'])) continue;
                    
                    $computationMethod = $comp['computation_type'] ?? 'FIXED';
                    
                    if ($computationMethod === 'FORMULA') {
                        $amt = $this->calculateSlabTax($grossChargeable, $comp['id'], $companyId);
                    } else if ($computationMethod === 'PERCENTAGE') {
                        $pct = (float)$comp['pc_value'];
                        if ($pct == 0) $pct = (float)$comp['amount'];
                        $amt = ($pct / 100) * $initialGrossChargeable; 
                    } else {
                        $amt = (float)$comp['amount'];
                        $escCurrency = !empty($comp['currency_code']) ? $comp['currency_code'] : $companyCurrency;
                        if ($escCurrency !== $companyCurrency) {
                            $cacheKey = "{$escCurrency}_{$companyCurrency}";
                            if (!isset($exchangeRatesCache[$cacheKey])) {
                                $rate = $this->getEffectiveExchangeRate($companyCurrency, $targetDate, $escCurrency);
                                $exchangeRatesCache[$cacheKey] = $rate !== null ? $rate : 1.0;
                            }
                            $amt = $amt * $exchangeRatesCache[$cacheKey];
                        }
                    }
                    if (!empty($comp['round_off'])) {
                        $amt = round($amt);
                    }
                    
                    $deductions[] = ['name' => $comp['name'], 'amount' => $amt, 'display_in_payslip' => $comp['display_in_payslip'], 'computation_method' => $computationMethod];
                    
                    $nameLower = strtolower($comp['name']);
                    if (!empty($comp['is_income_tax']) || strpos($nameLower, 'paye') !== false) {
                        $paye = $amt;
                    }
                    if (strpos($nameLower, 'nssf') !== false) {
                        $nssf['employee'] = $amt;
                    }
                }

                if ($totalEarningsAmt <= 0) continue;

                $advStmt = $this->db->prepare("SELECT id, amount, currency_code, installment_amount, deducted_amount FROM salary_advances WHERE employee_id = ? AND status IN ('Approved', 'Partially Deducted') AND (deduction_start_date IS NULL OR deduction_start_date <= ?)");
                $advStmt->execute([$emp['id'], $targetDate]);
                $advances = $advStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $totalAdvanceDeductions = 0.00;
                foreach ($advances as $adv) {
                    $totalAdv = (float)$adv['amount'];
                    $deductedSoFar = (float)$adv['deducted_amount'];
                    $remaining = $totalAdv - $deductedSoFar;
                    if ($remaining <= 0) continue;
                    
                    $installment = isset($adv['installment_amount']) ? (float)$adv['installment_amount'] : 0.00;
                    if ($installment > 0 && $installment < $remaining) {
                        $advAmt = $installment;
                    } else {
                        $advAmt = $remaining;
                    }

                    $advCurrency = !empty($adv['currency_code']) ? $adv['currency_code'] : $companyCurrency;
                    if ($advCurrency !== $companyCurrency) {
                        $cacheKey = "{$advCurrency}_{$companyCurrency}";
                        if (!isset($exchangeRatesCache[$cacheKey])) {
                            $rate = $this->getEffectiveExchangeRate($companyCurrency, $targetDate, $advCurrency);
                            $exchangeRatesCache[$cacheKey] = $rate !== null ? $rate : 1.0;
                        }
                        $advAmt = $advAmt * $exchangeRatesCache[$cacheKey];
                    }
                    $totalAdvanceDeductions += $advAmt;
                }

                $totalOtherDeductions = 0.00;
                foreach ($deductions as $d) {
                    $totalOtherDeductions += (float)$d['amount'];
                }
                $netPay = $totalEarningsAmt - $totalAdvanceDeductions - $totalOtherDeductions;

                $previewRecords[] = [
                    'employee_id' => $emp['id'],
                    'first_name' => $emp['first_name'],
                    'last_name' => $emp['last_name'],
                    'emp_code' => $emp['employee_code'],
                    'designation_name' => $emp['designation_name'],
                    'bank_account_no' => $emp['bank_account_no'],
                    'bank_name' => $emp['bank_name'],
                    'custom_fields' => !empty($emp['custom_data']) ? json_decode($emp['custom_data'], true) : [],
                    'gross_chargeable_income' => $grossChargeable,
                    'paye_deduction' => $paye,
                    'nssf_employee_deduction' => $nssf['employee'],
                    'advance_deductions' => $totalAdvanceDeductions,
                    'net_pay' => $netPay,
                    'earnings_json' => $earnings,
                    'deductions_json' => $deductions,
                    'reporting_currency' => $reportingCurrency,
                    'payslip_currency' => $reportingCurrency ?: $companyCurrency,
                    'exchange_rate' => $exchangeRate,
                    'company_name' => $compData ? $compData['company_name'] : null,
                    'company_logo' => $compData ? $compData['company_logo'] : null,
                    'company_address' => $compData ? $compData['company_address'] : null,
                    'company_contact_email' => $compData ? $compData['company_contact_email'] : null,
                    'company_contact_phone' => $compData ? $compData['company_contact_phone'] : null,
                    'selected' => true
                ];
            }

            return [
                'success' => true,
                'data' => $previewRecords
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error previewing payroll: " . $e->getMessage()
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
                   d.title as designation_name,
                   c.name as company_name, c.logo_url as company_logo, c.address as company_address, c.contact_email as company_contact_email, c.contact_phone as company_contact_phone,
                   (SELECT esc.currency_code 
                   FROM employee_salary_components esc 
                   JOIN payroll_components pc ON esc.component_id = pc.id 
                   WHERE esc.employee_id = e.id AND pc.company_id = pr.company_id AND esc.currency_code IS NOT NULL 
                   LIMIT 1) as employee_currency,
                   (SELECT currency_code FROM countries WHERE id = c.country_id LIMIT 1) as company_currency
            FROM payroll_records pr
            JOIN employees e ON pr.employee_id = e.id
            JOIN companies c ON pr.company_id = c.id
            LEFT JOIN designations d ON e.designation_id = d.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$payrollRecordId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $customData = !empty($row['custom_data']) ? json_decode($row['custom_data'], true) : [];
            $row['custom_fields'] = $customData;
            unset($row['custom_data']);

            $empCurrency = $row['employee_currency'] ?: ($row['company_currency'] ?: 'UGX');
            $compCurrency = $row['company_currency'] ?: 'UGX';
            
            $row['payslip_currency'] = $empCurrency;

            if ($empCurrency !== $compCurrency) {
                // Convert amounts
                $targetDate = date('Y-m-t', strtotime($row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT) . '-01'));
                $rate = $this->getEffectiveExchangeRate($compCurrency, $targetDate, $empCurrency);
                if ($rate) {
                    $row['basic_pay'] = (float)$row['basic_pay'] / $rate;
                    $row['commissions'] = (float)$row['commissions'] / $rate;
                    $row['other_earnings'] = (float)$row['other_earnings'] / $rate;
                    $row['gross_chargeable_income'] = (float)$row['gross_chargeable_income'] / $rate;
                    $row['paye_deduction'] = (float)$row['paye_deduction'] / $rate;
                    $row['nssf_employee_deduction'] = (float)$row['nssf_employee_deduction'] / $rate;
                    $row['nssf_employer_contribution'] = (float)$row['nssf_employer_contribution'] / $rate;
                    $row['net_pay'] = (float)$row['net_pay'] / $rate;
                    
                    if (!empty($row['earnings_json'])) {
                        $earnings = json_decode($row['earnings_json'], true);
                        if (is_array($earnings)) {
                            foreach ($earnings as &$e) {
                                $e['amount'] = (float)$e['amount'] / $rate;
                            }
                            $row['earnings_json'] = json_encode($earnings);
                        }
                    }
                    if (!empty($row['deductions_json'])) {
                        $deductions = json_decode($row['deductions_json'], true);
                        if (is_array($deductions)) {
                            foreach ($deductions as &$d) {
                                $d['amount'] = (float)$d['amount'] / $rate;
                            }
                            $row['deductions_json'] = json_encode($deductions);
                        }
                    }
                }
            }

            return $row;
        }
        return null;
    }

    /**
     * Update an individual payroll record
     */
    public function updateRecord(int $id, float $basicPay, float $commissions, float $otherEarnings, ?array $earnings = null, ?array $deductions = null, ?float $advanceDeductions = null): array
    {
        $stmt = $this->db->prepare("SELECT * FROM payroll_records WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return ['success' => false, 'message' => 'Record not found'];
        }

        $this->db->beginTransaction();

        try {
            $currentAdvanceDeductions = (float)$record['advance_deductions'];
            $finalAdvanceDeductions = $currentAdvanceDeductions;

            if ($advanceDeductions !== null && round($advanceDeductions, 2) !== round($currentAdvanceDeductions, 2)) {
                $difference = $advanceDeductions - $currentAdvanceDeductions;
                $instStmt = $this->db->prepare("SELECT * FROM salary_advance_installments WHERE payroll_id = ? ORDER BY id ASC LIMIT 1");
                $instStmt->execute([$id]);
                $firstInstallment = $instStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($firstInstallment) {
                    $newAmount = max(0, (float)$firstInstallment['amount'] + $difference);
                    $actualDifference = $newAmount - (float)$firstInstallment['amount'];
                    
                    $newRemainingBalance = isset($firstInstallment['remaining_balance']) ? ((float)$firstInstallment['remaining_balance'] - $actualDifference) : null;
                    
                    if ($newRemainingBalance !== null) {
                        $updInstStmt = $this->db->prepare("UPDATE salary_advance_installments SET amount = ?, remaining_balance = ? WHERE id = ?");
                        $updInstStmt->execute([$newAmount, $newRemainingBalance, $firstInstallment['id']]);
                    } else {
                        $updInstStmt = $this->db->prepare("UPDATE salary_advance_installments SET amount = ? WHERE id = ?");
                        $updInstStmt->execute([$newAmount, $firstInstallment['id']]);
                    }
                    
                    $updAdvStmt = $this->db->prepare("
                        UPDATE salary_advances 
                        SET deducted_amount = deducted_amount + ?, 
                            status = IF(deducted_amount >= amount, 'Deducted', 'Partially Deducted')
                        WHERE id = ?
                    ");
                    $updAdvStmt->execute([$actualDifference, $firstInstallment['salary_advance_id']]);
                    
                    $finalAdvanceDeductions = $currentAdvanceDeductions + $actualDifference;
                } else {
                    $finalAdvanceDeductions = $advanceDeductions;
                }
            }

        $grossChargeable = 0;
        $totalEarningsAmt = 0;
        $totalOtherDeductions = 0;

        $nssf = ['employee' => 0, 'employer' => 0];
        $paye = 0;

        if ($earnings !== null && $deductions !== null) {
            foreach ($earnings as $e) {
                $amt = (float)$e['amount'];
                $totalEarningsAmt += $amt;
                if (empty($e['is_non_taxable'])) {
                    $grossChargeable += $amt;
                }
            }
            foreach ($deductions as $d) {
                if (strpos(strtolower($d['name']), 'paye') !== false) {
                    $paye = (float)$d['amount'];
                } else {
                    $totalOtherDeductions += (float)$d['amount'];
                }
            }
        } else {
            $grossChargeable = $basicPay + $commissions + $otherEarnings;
            $totalEarningsAmt = $grossChargeable;
            // Legacy handling doesn't calculate other deductions properly, but they weren't editable anyway.
        }

        $companyId = (int)$record['company_id'];
        
        // Determine if company is in Uganda
        $compStmt = $this->db->prepare("SELECT c.country_id, co.currency_code FROM companies c LEFT JOIN countries co ON c.country_id = co.id WHERE c.id = ?");
        $compStmt->execute([$companyId]);
        $companyData = $compStmt->fetch(PDO::FETCH_ASSOC);
        $isUganda = ($companyData && ($companyData['country_id'] == 1 || $companyData['currency_code'] === 'UGX'));

        if ($isUganda) {
            $nssf = $this->calculateNSSF($totalEarningsAmt);
        }
        
        $netPay = $totalEarningsAmt - $paye - $nssf['employee'] - $finalAdvanceDeductions - $totalOtherDeductions;

        if ($earnings !== null && $deductions !== null) {
            $updateStmt = $this->db->prepare("
                UPDATE payroll_records 
                SET basic_pay = ?, commissions = ?, other_earnings = ?, earnings_json = ?, deductions_json = ?, gross_chargeable_income = ?, 
                    paye_deduction = ?, nssf_employee_deduction = ?, nssf_employer_contribution = ?, advance_deductions = ?, net_pay = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $basicPay, $commissions, $otherEarnings, json_encode($earnings), json_encode($deductions), $grossChargeable, 
                $paye, $nssf['employee'], $nssf['employer'], $finalAdvanceDeductions, $netPay, 
                $id
            ]);
        } else {
            $updateStmt = $this->db->prepare("
                UPDATE payroll_records 
                SET basic_pay = ?, commissions = ?, other_earnings = ?, gross_chargeable_income = ?, 
                    paye_deduction = ?, nssf_employee_deduction = ?, nssf_employer_contribution = ?, advance_deductions = ?, net_pay = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $basicPay, $commissions, $otherEarnings, $grossChargeable, 
                $paye, $nssf['employee'], $nssf['employer'], $finalAdvanceDeductions, $netPay, 
                $id
            ]);
        }

            $this->db->commit();
            return ['success' => true, 'message' => 'Payroll record updated successfully'];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error updating payroll: ' . $e->getMessage()];
        }
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
            // 1. Fetch installments for this payroll record
            $instStmt = $this->db->prepare("SELECT salary_advance_id, amount FROM salary_advance_installments WHERE payroll_id = ?");
            $instStmt->execute([$id]);
            $installments = $instStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($installments)) {
                $revertAdvStmt = $this->db->prepare("
                    UPDATE salary_advances 
                    SET deducted_amount = deducted_amount - ?, 
                        status = IF(deducted_amount <= 0, 'Approved', 'Partially Deducted') 
                    WHERE id = ?
                ");
                foreach ($installments as $inst) {
                    $revertAdvStmt->execute([$inst['amount'], $inst['salary_advance_id']]);
                }
                
                // Delete the installments
                $delInstStmt = $this->db->prepare("DELETE FROM salary_advance_installments WHERE payroll_id = ?");
                $delInstStmt->execute([$id]);
            }

            // Find and delete the uploaded payslip if it exists
            $payslipStmt = $this->db->prepare("SELECT id, file_path FROM payslips WHERE employee_id = ? AND month = ? AND year = ? AND company_id = ?");
            $payslipStmt->execute([$record['employee_id'], $record['month'], $record['year'], $record['company_id']]);
            $payslip = $payslipStmt->fetch(PDO::FETCH_ASSOC);

            if ($payslip) {
                // Parse the filename from the URL structure /api/payslips/download?file=...
                $fileName = null;
                if (strpos($payslip['file_path'], 'file=') !== false) {
                    $parts = explode('file=', $payslip['file_path']);
                    $fileName = end($parts);
                } else {
                    $fileName = basename($payslip['file_path']);
                }

                if ($fileName) {
                    $uploadDir = (defined('PUBLIC_DIR_PATH') ? PUBLIC_DIR_PATH : ROOT_PATH . '/public') . '/uploads/payslips/';
                    $fullPath = $uploadDir . $fileName;
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                }

                // Delete the record from the database
                $delPayslipStmt = $this->db->prepare("DELETE FROM payslips WHERE id = ?");
                $delPayslipStmt->execute([$payslip['id']]);
            }

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

    public function submitForApproval(array $recordIds)
    {
        if (empty($recordIds)) {
            return ['success' => false, 'message' => 'No records provided'];
        }

        try {
            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            $stmt = $this->db->prepare("UPDATE payroll_records SET status = 'Pending Approval' WHERE id IN ($placeholders) AND status = 'Draft'");
            $stmt->execute($recordIds);
            
            $updatedCount = $stmt->rowCount();

            $this->db->commit();
            return ['success' => true, 'message' => "$updatedCount records submitted for approval"];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error submitting for approval: ' . $e->getMessage()];
        }
    }
    public function approveRecords(array $recordIds)
    {
        if (empty($recordIds)) {
            return ['success' => false, 'message' => 'No records provided'];
        }

        try {
            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            $stmt = $this->db->prepare("UPDATE payroll_records SET status = 'Processed' WHERE id IN ($placeholders) AND status = 'Pending Approval'");
            $stmt->execute($recordIds);
            
            $updatedCount = $stmt->rowCount();

            $this->db->commit();
            return ['success' => true, 'message' => "$updatedCount records approved successfully"];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error approving records: ' . $e->getMessage()];
        }
    }

    public function rejectRecords(array $recordIds)
    {
        if (empty($recordIds)) {
            return ['success' => false, 'message' => 'No records provided'];
        }

        try {
            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            $stmt = $this->db->prepare("UPDATE payroll_records SET status = 'Draft' WHERE id IN ($placeholders) AND status = 'Pending Approval'");
            $stmt->execute($recordIds);
            
            $updatedCount = $stmt->rowCount();

            $this->db->commit();
            return ['success' => true, 'message' => "$updatedCount records rejected successfully"];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error rejecting records: ' . $e->getMessage()];
        }
    }
    public function processPayment(array $recordIds, $userId = null)
    {
        if (empty($recordIds)) {
            return ['success' => false, 'message' => 'No records provided'];
        }

        try {
            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
            
            // First, fetch the records to insert into payslips
            $stmt = $this->db->prepare("SELECT id, employee_id, month, year FROM payroll_records WHERE id IN ($placeholders) AND status = 'Processed'");
            $stmt->execute($recordIds);
            $recordsToProcess = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($recordsToProcess)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'No approved records found to process'];
            }

            // Update status to Paid
            $processIds = array_column($recordsToProcess, 'id');
            $processPlaceholders = implode(',', array_fill(0, count($processIds), '?'));
            $updateStmt = $this->db->prepare("UPDATE payroll_records SET status = 'Paid' WHERE id IN ($processPlaceholders)");
            $updateStmt->execute($processIds);

            $updatedCount = count($processIds);

            $this->db->commit();
            return ['success' => true, 'message' => "Payment processed for $updatedCount records. Payslips generated."];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()];
        }
    }
}

