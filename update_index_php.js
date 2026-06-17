const fs = require('fs');
const path = 'c:\\\\Users\\\\AneeshMathew\\\\HRMS V2\\\\backend\\\\public\\\\index.php';
let content = fs.readFileSync(path, 'utf8');

if (!content.includes('/api/companies/logo/')) {
    const route = `
    // API: POST /api/companies/logo/{id}
    if ($method === 'POST' && strpos($uri, '/api/companies/logo/') === 0) {
        $controller = new \\App\\Controllers\\CompanyController();
        $parts = explode('/', trim($uri, '/'));
        $id = end($parts);
        if (is_numeric($id)) {
            return call_user_func([$controller, 'updateLogo'], $id);
        }
    }
`;
    // Insert after templates route or anywhere in API routing block
    content = content.replace('// API: GET /api/companies/templates', route + '\\n    // API: GET /api/companies/templates');
    fs.writeFileSync(path, content);
}
console.log('index.php updated');
