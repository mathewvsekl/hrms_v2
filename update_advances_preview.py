import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\backend\app\Services\PayrollService.php'
with open(filepath, 'r') as f:
    content = f.read()

target = """                $advStmt = $this->db->prepare("SELECT id, amount FROM salary_advances WHERE employee_id = ? AND status = 'Pending'");
                $advStmt->execute([$emp['id']]);
                $advances = $advStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $totalAdvanceDeductions = 0.00;
                foreach ($advances as $adv) {
                    $totalAdvanceDeductions += (float)$adv['amount'];
                }"""

replacement = """                $advStmt = $this->db->prepare("SELECT id, amount, currency_code FROM salary_advances WHERE employee_id = ? AND status = 'Pending'");
                $advStmt->execute([$emp['id']]);
                $advances = $advStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $totalAdvanceDeductions = 0.00;
                foreach ($advances as $adv) {
                    $advAmt = (float)$adv['amount'];
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
                }"""

content = content.replace(target, replacement)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated PayrollService.php to apply currency conversion for salary advances in previewPayroll.")
