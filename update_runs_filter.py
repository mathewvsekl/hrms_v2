import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Payroll.jsx'
with open(filepath, 'r') as f:
    content = f.read()

# I will find `{runs.length === 0 ? (` and change to check filtered runs.
# But first, insert the filteredRuns definition just before that.
# The table is inside the `else` of `selectedRun` condition.
target = "                    {runs.length === 0 ? ("
replacement = """                    { (() => {
                        const filteredRuns = companyId ? runs.filter(r => String(r.company_id) === String(companyId)) : runs;
                        return filteredRuns.length === 0 ? (
                            <div style={{ padding: '60px', textAlign: 'center', color: '#94a3b8' }}>
                                <Wallet size={48} style={{ margin: '0 auto 16px', color: '#cbd5e1' }} />
                                <h3 style={{ margin: '0 0 8px', color: '#475569' }}>No Payroll Runs Found</h3>
                                <p style={{ margin: '0 0 24px' }}>Use the controls above to generate your first payroll run.</p>
                            </div>
                        ) : (
                            <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                                <thead>
                                    <tr style={{ background: '#f1f5f9', borderBottom: '1px solid #e2e8f0' }}>
                                        <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Period</th>
                                        <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Company</th>
                                        <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Employees</th>
                                        <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Total Gross ({currentCurrency})</th>
                                        <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Total Net Pay ({currentCurrency})</th>
                                        <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredRuns.map((run, idx) => ("""

# Replace the block from `{runs.length === 0 ? (` to `{runs.map((run, idx) => (`
import re

target_regex = r"\{runs\.length === 0 \? \(.*?(?=\{runs\.map\(\(run, idx\) => \()"
match = re.search(target_regex, content, re.DOTALL)
if match:
    content = content.replace(match.group(0), replacement)
    
    # We also need to close the IIFE `})()}`
    # Where does `<tbody>` and `<table>` close?
    
    # Actually, it's easier to just do:
    # 1. Define `filteredRuns` right before `{runs.length === 0 ?`
    
    # Wait, in React, we can just define `filteredRuns` at the component top level!
    pass

# Let's do it safely
with open(filepath, 'r') as f:
    content = f.read()

# Define filteredRuns near the top, after `runs` is fetched
content = content.replace("const isUganda = currentCompany?.country_id === 1 || currentCurrency === \"UGX\";", 
                          "const isUganda = currentCompany?.country_id === 1 || currentCurrency === \"UGX\";\n    const filteredRuns = companyId ? runs.filter(r => String(r.company_id) === String(companyId)) : runs;")

# Replace runs.length with filteredRuns.length
content = content.replace("{runs.length === 0 ? (", "{filteredRuns.length === 0 ? (")

# Replace runs.map with filteredRuns.map
content = content.replace("{runs.map((run, idx) => (", "{filteredRuns.map((run, idx) => (")

with open(filepath, 'w') as f:
    f.write(content)

print("Updated Payroll.jsx to filter runs by companyId.")
