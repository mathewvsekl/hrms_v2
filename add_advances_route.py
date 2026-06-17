import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\backend\public\index.php'
with open(filepath, 'r') as f:
    content = f.read()

target = """    if (strpos($uri, '/api/payroll-config') === 0) {
        require_once BASE_PATH . '/app/Controllers/PayrollConfigController.php';
        return (new PayrollConfigController())->handleRequest($method, str_replace('/api/payroll-config', '', $uri));
    }"""

replacement = """    if (strpos($uri, '/api/payroll-config') === 0) {
        require_once BASE_PATH . '/app/Controllers/PayrollConfigController.php';
        return (new PayrollConfigController())->handleRequest($method, str_replace('/api/payroll-config', '', $uri));
    }

    if (strpos($uri, '/api/salary-advances') === 0) {
        require_once BASE_PATH . '/app/Controllers/SalaryAdvanceController.php';
        return (new SalaryAdvanceController())->handleRequest($method, str_replace('/api/salary-advances', '', $uri));
    }"""

content = content.replace(target, replacement)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated index.php with SalaryAdvanceController routing.")
