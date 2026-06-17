import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\backend\app\Controllers\SalaryAdvanceController.php'
with open(filepath, 'r') as f:
    content = f.read()

target1 = """    private function getAdvances()
    {
        $companyId = $_GET['company_id'] ?? null;
        if (!$companyId) {
            http_response_code(400);
            return ['success' => false, 'message' => 'company_id is required'];
        }

        $stmt = $this->db->prepare("
            SELECT sa.*, e.first_name, e.last_name, e.employee_code 
            FROM salary_advances sa 
            JOIN employees e ON sa.employee_id = e.id 
            WHERE e.company_id = ? 
            ORDER BY sa.created_at DESC
        ");
        $stmt->execute([$companyId]);
        $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'data' => $advances];
    }"""
replacement1 = """    private function getAdvances()
    {
        $companyId = $_GET['company_id'] ?? null;
        if (!$companyId) {
            http_response_code(400);
            return ['success' => false, 'message' => 'company_id is required'];
        }

        $stmt = $this->db->prepare("
            SELECT sa.*, e.first_name, e.last_name, e.employee_code 
            FROM salary_advances sa 
            JOIN employees e ON sa.employee_id = e.id 
            JOIN employee_companies ec ON e.id = ec.employee_id
            WHERE ec.company_id = ? AND ec.is_primary = 1
            ORDER BY sa.created_at DESC
        ");
        $stmt->execute([$companyId]);
        $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['success' => true, 'data' => $advances];
    }"""
content = content.replace(target1, replacement1)

target2 = """        $stmt = $this->db->prepare("
            INSERT INTO salary_advances (employee_id, amount, currency_code, date_requested, status) 
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$employeeId, $amount, $currencyCode, $dateRequested]);"""
replacement2 = """        // Validate that the employee belongs to the company context (or just let it insert since it's linked by primary company)
        // If we want to strictly add company_id to salary_advances table we can, but since the request says
        // "Salary advance is linked with primary company of the employee", we can rely on the JOIN.
        // Wait, did I add company_id to salary_advances table? No. 
        // So the insert is fine as is.
        $stmt = $this->db->prepare("
            INSERT INTO salary_advances (employee_id, amount, currency_code, date_requested, status) 
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([$employeeId, $amount, $currencyCode, $dateRequested]);"""
content = content.replace(target2, replacement2)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated SalaryAdvanceController to join employee_companies and filter by primary company.")
