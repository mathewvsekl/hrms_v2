import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\backend\app\Services\PayrollService.php'
with open(filepath, 'r') as f:
    content = f.read()

target = """            FROM payroll_records pr
            JOIN employees e ON pr.employee_id = e.id
            JOIN employee_companies ec ON e.id = ec.employee_id AND ec.is_primary = 1
            JOIN companies c ON ec.company_id = c.id
            LEFT JOIN designations d ON e.designation_id = d.id"""

replacement = """            FROM payroll_records pr
            JOIN employees e ON pr.employee_id = e.id
            JOIN companies c ON pr.company_id = c.id
            LEFT JOIN designations d ON e.designation_id = d.id"""

content = content.replace(target, replacement)

with open(filepath, 'w') as f:
    f.write(content)
print("Fixed company join in getPayslipData.")
