import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Payroll.jsx'
with open(filepath, 'r') as f:
    content = f.read()

# Add currentCurrency definition
if 'const currentCurrency =' not in content:
    content = re.sub(
        r'(const isAdmin = [^;]+;)',
        r'\1\n    const currentCompany = companies.find(c => c.id == (selectedRun?.company_id || companyId));\n    const currentCurrency = currentCompany?.currency_code || "UGX";',
        content
    )

# Replace (UGX) with ({currentCurrency}) in JSX text
content = content.replace('(UGX)', '({currentCurrency})')
content = content.replace('UGX)', '{currentCurrency})')

# We want to conditionally render PAYE and NSSF if the company is Uganda, or if sums > 0.
# We have currentCompany. We can check if currentCompany.country_id === 1 (Uganda).
if 'const isUganda =' not in content:
    content = re.sub(
        r'(const currentCurrency = [^;]+;)',
        r'\1\n    const isUganda = currentCompany?.country_id === 1 || currentCurrency === "UGX";',
        content
    )

# Hide PAYE and NSSF in preview table headers
content = re.sub(
    r'(<th[^>]*>PAYE \(\{currentCurrency\}\)</th>\s*<th[^>]*>NSSF \(5%\) \(\{currentCurrency\}\)</th>)',
    r'{isUganda && <>\n                                                \1\n                                            </>}',
    content
)

# Hide NSSF (UGX) in preview modal headers
content = re.sub(
    r'(<th[^>]*>PAYE \(\{currentCurrency\}\)</th>\s*<th[^>]*>NSSF \(\{currentCurrency\}\)</th>)',
    r'{isUganda && <>\n                                                \1\n                                            </>}',
    content
)

# Hide PAYE and NSSF in preview table body cells
content = re.sub(
    r'(<td[^>]*>\s*<div>\{renderCurrency\(record\.paye_deduction\)\}</div>\s*</td>\s*<td[^>]*>\s*<div>\{renderCurrency\(record\.nssf_employee_deduction\)\}</div>\s*</td>)',
    r'{isUganda && <>\n                                                  \1\n                                                  </>}',
    content
)

# Hide PAYE and NSSF in preview table totals
content = re.sub(
    r'(<td[^>]*>\s*\{renderCurrency\(previewRecords\.filter\(r => r\.selected\)\.reduce\(\(sum, r\) => sum \+ r\.paye_deduction, 0\)\)\}\s*</td>\s*<td[^>]*>\s*\{renderCurrency\(previewRecords\.filter\(r => r\.selected\)\.reduce\(\(sum, r\) => sum \+ r\.nssf_employee_deduction, 0\)\)\}\s*</td>)',
    r'{isUganda && <>\n                                              \1\n                                              </>}',
    content
)

# Hide PAYE and NSSF in Dashboard Total summary cards
content = re.sub(
    r'(<div style=\{\{ flex: 1, minWidth: \'200px\', background: \'#fff\', padding: \'20px\', borderRadius: \'8px\', border: \'1px solid #e2e8f0\' \}\}>\s*<p style=\{\{ margin: \'0 0 16px\', color: \'#94a3b8\', fontSize: \'13px\', fontWeight: \'500\' \}\}>Total PAYE</p>[\s\S]*?<div style=\{\{ flex: 1, minWidth: \'200px\', background: \'#fff\', padding: \'20px\', borderRadius: \'8px\', border: \'1px solid #e2e8f0\' \}\}>\s*<p style=\{\{ margin: \'0 0 16px\', color: \'#94a3b8\', fontSize: \'13px\', fontWeight: \'500\' \}\}>Total NSSF</p>[\s\S]*?</p>\s*</div>\s*</div>)',
    r'{isUganda && (\n                                <>\n                                    \1\n                                </>\n                            )}',
    content
)

with open(filepath, 'w') as f:
    f.write(content)

print("Modifications done.")
