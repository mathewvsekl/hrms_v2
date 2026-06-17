import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\components\UgandaPayslipTemplate.jsx'
with open(filepath, 'r') as f:
    content = f.read()

# Make sure formatCurrency doesn't append its own currency prefix since we put it in the header
content = content.replace("style: 'currency',", "")
content = content.replace("currency: currencyCode,", "")

content = content.replace('formatCurrency(row.earning.amount)', 'formatCurrency(row.earning.amount, data.payslip_currency)')
content = content.replace('formatCurrency(row.deduction.amount)', 'formatCurrency(row.deduction.amount, data.payslip_currency)')
content = content.replace('formatCurrency(gross_chargeable_income)', 'formatCurrency(gross_chargeable_income, data.payslip_currency)')
content = content.replace('formatCurrency(totalDeductions)', 'formatCurrency(totalDeductions, data.payslip_currency)')
content = content.replace('formatCurrency(net_pay)', 'formatCurrency(net_pay, data.payslip_currency)')

with open(filepath, 'w') as f:
    f.write(content)
print("Updated UgandaPayslipTemplate.jsx formatCurrency calls.")
