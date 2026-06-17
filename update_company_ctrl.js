const fs = require('fs');
const path = 'c:\\\\Users\\\\AneeshMathew\\\\HRMS V2\\\\backend\\\\app\\\\Controllers\\\\CompanyController.php';
let content = fs.readFileSync(path, 'utf8');

if (!content.includes('public function updateLogo')) {
    const fn = `
    public function updateLogo($companyId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $logoBase64 = $data['logo'] ?? null;
            
            $stmt = $this->db->prepare('UPDATE companies SET logo_url = ? WHERE id = ?');
            $stmt->execute([$logoBase64, $companyId]);
            
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Logo updated successfully']);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
        }
    }
`;
    const lastBraceIndex = content.lastIndexOf('}');
    content = content.substring(0, lastBraceIndex) + fn + content.substring(lastBraceIndex);
    fs.writeFileSync(path, content);
}
console.log('CompanyController.php updated');
