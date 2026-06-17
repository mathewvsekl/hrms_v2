import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\components\UgandaPayslipTemplate.jsx'
with open(filepath, 'r') as f:
    content = f.read()

# Replace hardcoded 'Shillings' in amount-in-words logic
content = content.replace("word = word.trim() + ' Shillings';", "word = word.trim() + ' ' + (data.payslip_currency || 'Shillings');")

# Replace the AMOUNT headers
content = content.replace('<th style={{ textAlign: \'right\', padding: \'8px\', width: \'25%\' }}>AMOUNT</th>', 
                          '<th style={{ textAlign: \'right\', padding: \'8px\', width: \'25%\' }}>AMOUNT ({data.payslip_currency || \'UGX\'})</th>')

with open(filepath, 'w') as f:
    f.write(content)
print("Updated UgandaPayslipTemplate.jsx with payslip_currency.")
