import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\backend\app\Services\PayrollService.php'
with open(filepath, 'r') as f:
    content = f.read()

target = """    public function getPayslipData(int $payrollRecordId): ?array
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
            $row['tin_no'] = $customData['tin'] ?? $customData['tin_no'] ?? $customData['tin_number'] ?? null;
            $row['nssf_no'] = $customData['nssf_no'] ?? $customData['nssf_number'] ?? null;
            unset($row['custom_data']);
            return $row;
        }
        return null;
    }"""

replacement = """    public function getPayslipData(int $payrollRecordId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pr.*, e.employee_code as emp_code, e.first_name, e.last_name, e.bank_account_no, e.bank_name,
                   e.custom_data,
                   d.title as designation_name,
                   (SELECT currency_code FROM employee_salary_components WHERE employee_id = e.id AND currency_code IS NOT NULL LIMIT 1) as employee_currency,
                   (SELECT currency_code FROM countries WHERE id = c.country_id LIMIT 1) as company_currency
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
            $row['tin_no'] = $customData['tin'] ?? $customData['tin_no'] ?? $customData['tin_number'] ?? null;
            $row['nssf_no'] = $customData['nssf_no'] ?? $customData['nssf_number'] ?? null;
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
    }"""

content = content.replace(target, replacement)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated PayrollService.php with payslip currency conversion.")
