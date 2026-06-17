import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\backend\app\Services\PayrollService.php'
with open(filepath, 'r') as f:
    content = f.read()

target = "(SELECT currency_code FROM employee_salary_components WHERE employee_id = e.id AND currency_code IS NOT NULL LIMIT 1) as employee_currency,"

replacement = """(SELECT esc.currency_code 
                   FROM employee_salary_components esc 
                   JOIN payroll_components pc ON esc.component_id = pc.id 
                   WHERE esc.employee_id = e.id AND pc.company_id = pr.company_id AND esc.currency_code IS NOT NULL 
                   LIMIT 1) as employee_currency,"""

content = content.replace(target, replacement)

with open(filepath, 'w') as f:
    f.write(content)
print("Fixed employee_currency subquery.")
