import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\backend\app\Controllers\PayrollController.php'
with open(filepath, 'r') as f:
    content = f.read()

target = "SUM(pr.gross_chargeable_income) as total_gross_pay,"
replacement = "SUM(pr.basic_pay + pr.commissions + pr.other_earnings) as total_gross_pay,"

content = content.replace(target, replacement)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated PayrollController.php total_gross_pay calculation.")
