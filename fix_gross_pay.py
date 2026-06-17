import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Payroll.jsx'
with open(filepath, 'r') as f:
    content = f.read()

# Replace totals.gross calculation
content = content.replace(
    'totals.gross += parseFloat(record.gross_chargeable_income) || 0;',
    'totals.gross += (parseFloat(record.basic_pay || 0) + parseFloat(record.commissions || 0) + parseFloat(record.other_earnings || 0));'
)

# Replace record.gross_chargeable_income rendering in dashboard view
content = content.replace(
    'renderCurrency(record.gross_chargeable_income)',
    'renderCurrency((parseFloat(record.basic_pay || 0) + parseFloat(record.commissions || 0) + parseFloat(record.other_earnings || 0)))'
)

# Replace record.gross_chargeable_income division in dashboard view
content = content.replace(
    '(record.gross_chargeable_income /',
    '((parseFloat(record.basic_pay || 0) + parseFloat(record.commissions || 0) + parseFloat(record.other_earnings || 0)) /'
)

# Replace previewRecords totals
content = content.replace(
    'previewRecords.filter(r => r.selected).reduce((sum, r) => sum + r.gross_chargeable_income, 0)',
    'previewRecords.filter(r => r.selected).reduce((sum, r) => sum + (parseFloat(r.basic_pay || 0) + parseFloat(r.commissions || 0) + parseFloat(r.other_earnings || 0)), 0)'
)

with open(filepath, 'w') as f:
    f.write(content)

print("Replaced gross_chargeable_income with actual gross pay calculation.")
