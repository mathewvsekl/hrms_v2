const fs = require('fs');
const path = 'c:\\\\Users\\\\AneeshMathew\\\\HRMS V2\\\\backend\\\\public\\\\index.php';
let content = fs.readFileSync(path, 'utf8');

const regex = /    \/\/ API: POST \/api\/companies\/logo\/\{id\}([\s\S]*?)(        \$companyId = \$_GET\['company_id'\] \?\? null;)/;

const fixedBlock = `    // API: POST /api/companies/logo/{id}
    if ($method === 'POST' && strpos($uri, '/api/companies/logo/') === 0) {
        $controller = new \\App\\Controllers\\CompanyController();
        $parts = explode('/', trim($uri, '/'));
        $id = end($parts);
        if (is_numeric($id)) {
            return call_user_func([$controller, 'updateLogo'], $id);
        }
    }

    // API: GET /api/companies/templates
    if ($method === 'GET' && strpos($uri, '/api/companies/templates') === 0) {
        $controller = new \\App\\Controllers\\CompanyController();
        // Extract ID if provided via /api/companies/templates/X
        $parts = explode('/', trim($uri, '/'));
        $id = end($parts);
        if (is_numeric($id)) {
            return call_user_func([$controller, 'getTemplate'], $id);
        }
        return call_user_func([$controller, 'getTemplate']);
    }

    // API: GET /api/custom_fields?company_id=X
    if ($method === 'GET' && strpos($uri, '/api/custom_fields') === 0) {
        $controller = new \\App\\Controllers\\CustomFieldController();
$2`;

content = content.replace(regex, fixedBlock);
fs.writeFileSync(path, content);
console.log('Fixed index.php');
