import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\components\UgandaPayslipTemplate.jsx'
with open(filepath, 'r') as f:
    content = f.read()

target1 = """    const totalDeductions = parseFloat(paye_deduction) + parseFloat(nssf_employee_deduction) + parseFloat(advance_deductions) + parsedDeductions.reduce((sum, d) => sum + parseFloat(d.amount), 0);"""
replacement1 = """    const totalDeductions = parseFloat(paye_deduction) + parseFloat(nssf_employee_deduction) + parseFloat(advance_deductions) + parsedDeductions.reduce((sum, d) => sum + parseFloat(d.amount), 0);
    const totalGrossEarnings = parseFloat(data.basic_pay || 0) + parseFloat(data.commissions || 0) + parseFloat(data.other_earnings || 0);"""

content = content.replace(target1, replacement1)

target2 = """                    <tr style={{ fontWeight: 'bold', borderBottom: '1px solid black' }}>
                        <td style={{ padding: '8px' }}>Gross Earnings</td>
                        <td style={{ textAlign: 'right', padding: '8px' }}>{formatCurrency(gross_chargeable_income, data.payslip_currency)}</td>
                        <td style={{ padding: '8px', borderLeft: '1px solid black' }}>Total Deductions</td>
                        <td style={{ textAlign: 'right', padding: '8px' }}>{formatCurrency(totalDeductions, data.payslip_currency)}</td>
                    </tr>
                    <tr>
                        <td style={{ padding: '8px' }} colSpan="2">Gross Earnings</td>
                        <td style={{ textAlign: 'right', padding: '8px' }} colSpan="2">{formatCurrency(gross_chargeable_income, data.payslip_currency)}</td>
                    </tr>"""
replacement2 = """                    <tr style={{ fontWeight: 'bold', borderBottom: '1px solid black' }}>
                        <td style={{ padding: '8px' }}>Gross Earnings</td>
                        <td style={{ textAlign: 'right', padding: '8px' }}>{formatCurrency(totalGrossEarnings, data.payslip_currency)}</td>
                        <td style={{ padding: '8px', borderLeft: '1px solid black' }}>Total Deductions</td>
                        <td style={{ textAlign: 'right', padding: '8px' }}>{formatCurrency(totalDeductions, data.payslip_currency)}</td>
                    </tr>
                    <tr>
                        <td style={{ padding: '8px' }} colSpan="2">Gross Earnings</td>
                        <td style={{ textAlign: 'right', padding: '8px' }} colSpan="2">{formatCurrency(totalGrossEarnings, data.payslip_currency)}</td>
                    </tr>"""
content = content.replace(target2, replacement2)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated UgandaPayslipTemplate.jsx to display Total Gross Earnings.")
