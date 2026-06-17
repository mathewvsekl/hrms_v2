import re
import os

filepath = r'c:\Users\AneeshMathew\HRMS V2\backend\app\Services\PayrollService.php'
with open(filepath, 'r') as f:
    content = f.read()

# 1. In fetch company country, also fetch currency_code
content = content.replace(
    'SELECT ct.iso_code FROM companies c JOIN countries ct ON c.country_id = ct.id WHERE c.id = ?',
    'SELECT ct.iso_code, ct.currency_code FROM companies c JOIN countries ct ON c.country_id = ct.id WHERE c.id = ?'
)

# 2. Extract companyCurrency
content = content.replace(
    '$isUganda = ($compData && $compData[\'iso_code\'] === \'UGA\');',
    '$isUganda = ($compData && $compData[\'iso_code\'] === \'UGA\');\n            $companyCurrency = $compData ? $compData[\'currency_code\'] : \'UGX\';\n            $exchangeRatesCache = [];'
)

# 3. Add esc.currency_code to queries
content = content.replace(
    'SELECT COALESCE(esc.amount, 0) as amount, pc.name, pc.type, pc.is_statutory, pc.is_non_taxable, pc.display_in_payslip, pc.computation_type',
    'SELECT COALESCE(esc.amount, 0) as amount, pc.name, pc.type, pc.is_statutory, pc.is_non_taxable, pc.display_in_payslip, pc.computation_type, esc.currency_code'
)

# 4. Modify the loop to do the currency conversion
loop_replacement = """                    $amt = (float)$comp['amount'];
                    $escCurrency = !empty($comp['currency_code']) ? $comp['currency_code'] : $companyCurrency;
                    if ($escCurrency !== $companyCurrency) {
                        $cacheKey = "{$escCurrency}_{$companyCurrency}";
                        if (!isset($exchangeRatesCache[$cacheKey])) {
                            // Convert FROM escCurrency TO companyCurrency
                            $rate = $this->getEffectiveExchangeRate($companyCurrency, $targetDate, $escCurrency);
                            $exchangeRatesCache[$cacheKey] = $rate !== null ? $rate : 1.0;
                        }
                        $amt = $amt * $exchangeRatesCache[$cacheKey];
                    }
                    $computationMethod = $comp['computation_type'] ?? 'FIXED';"""

content = content.replace(
    "                    $amt = (float)$comp['amount'];\n                    $computationMethod = $comp['computation_type'] ?? 'FIXED';",
    loop_replacement
)

with open(filepath, 'w') as f:
    f.write(content)

print("Modifications done.")
