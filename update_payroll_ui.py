import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Payroll.jsx'
with open(filepath, 'r') as f:
    content = f.read()

target1 = """                                    <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Total Gross ({currentCurrency})</th>
                                    <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Total Net Pay ({currentCurrency})</th>"""
replacement1 = """                                    <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Total Gross</th>
                                    <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Total Net Pay</th>"""

content = content.replace(target1, replacement1)


target2 = """                                        <td style={{ padding: '16px 24px', fontSize: '14px', color: '#475569' }}>
                                            <div>{formatCurrency(run.total_gross_pay)}</div>
                                            
                                        </td>
                                        <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '600', color: '#10b981' }}>
                                            <div>{formatCurrency(run.total_net_pay)}</div>
                                            
                                        </td>"""
replacement2 = """                                        <td style={{ padding: '16px 24px', fontSize: '14px', color: '#475569' }}>
                                            <div>{currentCurrency} {formatCurrency(run.total_gross_pay)}</div>
                                            {run.reporting_currency && run.reporting_currency !== currentCurrency && run.exchange_rate && (
                                                <div style={{ fontSize: '12px', color: '#94a3b8', marginTop: '4px' }}>
                                                    {run.reporting_currency} {formatCurrency(run.total_gross_pay / run.exchange_rate)}
                                                </div>
                                            )}
                                        </td>
                                        <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '600', color: '#10b981' }}>
                                            <div>{currentCurrency} {formatCurrency(run.total_net_pay)}</div>
                                            {run.reporting_currency && run.reporting_currency !== currentCurrency && run.exchange_rate && (
                                                <div style={{ fontSize: '12px', color: '#6ee7b7', marginTop: '4px' }}>
                                                    {run.reporting_currency} {formatCurrency(run.total_net_pay / run.exchange_rate)}
                                                </div>
                                            )}
                                        </td>"""

content = content.replace(target2, replacement2)

with open(filepath, 'w') as f:
    f.write(content)
print("Updated Payroll.jsx to show both local and reporting currency.")
