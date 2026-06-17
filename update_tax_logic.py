import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\backend\app\Services\PayrollService.php'
with open(filepath, 'r') as f:
    content = f.read()

# Fix 1: update calculatePAYE signature and logic
target_calculate_paye = """    public function calculatePAYE(float $grossPay, ?int $companyId = null): float
    {
        static $slabs = null;
        if ($slabs === null) {
            // Fetch slabs from database, ordered by min_amount ASC
            // In a real multi-company setup, we would filter by companyId if applicable,
            // but for now, we'll fetch the global or default slabs.
            $stmt = $this->db->query("SELECT * FROM tax_slabs ORDER BY min_amount ASC");
            $slabs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }"""
replacement_calculate_paye = """    public function calculatePAYE(float $grossPay, ?int $companyId = null): float
    {
        static $slabsCache = [];
        $cacheKey = $companyId ? (string)$companyId : 'all';
        
        if (!isset($slabsCache[$cacheKey])) {
            if ($companyId) {
                $stmt = $this->db->prepare("SELECT * FROM tax_slabs WHERE company_id = ? ORDER BY min_amount ASC");
                $stmt->execute([$companyId]);
            } else {
                $stmt = $this->db->query("SELECT * FROM tax_slabs ORDER BY min_amount ASC");
            }
            $slabsCache[$cacheKey] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $slabs = $slabsCache[$cacheKey];"""
content = content.replace(target_calculate_paye, replacement_calculate_paye)

# Fix 2: passing companyId in generatePayroll (line 263 approx)
target_generate_paye = """                if ($isUganda) {
                    $nssf = $this->calculateNSSF($totalEarningsAmt);
                    $paye = $this->calculatePAYE($grossChargeable);
                }"""
replacement_generate_paye = """                if ($isUganda) {
                    $nssf = $this->calculateNSSF($totalEarningsAmt);
                    $paye = $this->calculatePAYE($grossChargeable, $companyId);
                }"""
content = content.replace(target_generate_paye, replacement_generate_paye)

# Fix 3: passing companyId in previewPayroll (line 451 approx)
target_preview_paye = """                if ($isUganda) {
                    $nssf = $this->calculateNSSF($totalEarningsAmt);
                    $paye = $this->calculatePAYE($grossChargeable);
                }"""
replacement_preview_paye = """                if ($isUganda) {
                    $nssf = $this->calculateNSSF($totalEarningsAmt);
                    $paye = $this->calculatePAYE($grossChargeable, $companyId);
                }"""
content = content.replace(target_preview_paye, replacement_preview_paye)

# Fix 4: in updatePayrollRecord, check if Uganda and pass company_id
target_update_paye = """        $nssf = $this->calculateNSSF($totalEarningsAmt);
        $paye = $this->calculatePAYE($grossChargeable);
        
        $advanceDeductions = (float)$record['advance_deductions'];
        $netPay = $totalEarningsAmt - $paye - $nssf['employee'] - $advanceDeductions - $totalOtherDeductions;"""

replacement_update_paye = """        $companyId = (int)$record['company_id'];
        
        // Determine if company is in Uganda
        $compStmt = $this->db->prepare("SELECT c.country_id, co.currency_code FROM companies c LEFT JOIN countries co ON c.country_id = co.id WHERE c.id = ?");
        $compStmt->execute([$companyId]);
        $companyData = $compStmt->fetch(PDO::FETCH_ASSOC);
        $isUganda = ($companyData && ($companyData['country_id'] == 1 || $companyData['currency_code'] === 'UGX'));

        $nssf = ['employee' => 0, 'employer' => 0];
        $paye = 0;
        
        if ($isUganda) {
            $nssf = $this->calculateNSSF($totalEarningsAmt);
            $paye = $this->calculatePAYE($grossChargeable, $companyId);
        }
        
        $advanceDeductions = (float)$record['advance_deductions'];
        $netPay = $totalEarningsAmt - $paye - $nssf['employee'] - $advanceDeductions - $totalOtherDeductions;"""
content = content.replace(target_update_paye, replacement_update_paye)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated calculatePAYE logic, added companyId and isUganda checks.")
