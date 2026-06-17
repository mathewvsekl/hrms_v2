import os
import re

files = [
    r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Appraisal\AppraisalCycleList.jsx',
    r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Appraisal\AppraisalForm.jsx',
    r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Appraisal\AppraisalSettings.jsx'
]

replacements = [
    (r'var\(--text-secondary\)', r'var(--color-text-muted)'),
    (r'var\(--primary\)', r'var(--color-rose-gold)'),
    (r'var\(--border-gray\)', r'var(--color-border)'),
    (r'form-control', r'form-input'),
    (r'#3b82f6', r'var(--color-rose-gold)'),
    (r'#10b981', r'var(--color-charcoal)'),
    (r'#f59e0b', r'var(--color-rose-gold)'),
    (r'#8b5cf6', r'var(--color-rose-gold)'),
    (r'<table style={{ width: \'100%\', borderCollapse: \'collapse\' }}>', r'<div className="table-container">\n                            <table className="data-table">'),
    (r'</table>\n                    \)}', r'</table>\n                        </div>\n                    )}'),
    (r'<th style={{ textAlign: \'left\', padding: \'16px 20px\', color: \'var\(--color-text-muted\)\', fontSize: \'0.8rem\', textTransform: \'uppercase\' }}>', r'<th>'),
    (r'<th style={{ textAlign: \'right\', padding: \'16px 20px\', color: \'var\(--color-text-muted\)\', fontSize: \'0.8rem\', textTransform: \'uppercase\' }}>', r'<th style={{ textAlign: \'center\' }}>'),
    (r'<tr style={{ borderBottom: \'1px solid var\(--color-border\)\', background: \'#f9fafb\' }}>', r'<tr>'),
    (r'#f9fafb', r'var(--color-white)'),
    (r'<td style={{ padding: \'16px 20px\' }}>', r'<td>'),
    (r'<td colSpan="5" style={{ padding: \'40px\', textAlign: \'center\', color: \'var\(--color-text-muted\)\' }}>', r'<td colSpan="5" style={{ textAlign: \'center\', padding: \'20px\' }}>'),
    (r'<td style={{ padding: \'16px 20px\', textAlign: \'right\' }}>', r'<td style={{ textAlign: \'center\' }}>'),
    (r'<tr key=\{item\.id\} style={{ borderBottom: \'1px solid var\(--color-border\)\', cursor: \'pointer\' }}', r'<tr key={item.id} style={{ cursor: \'pointer\' }}'),
    (r'className="page-header" style={{ display: \'flex\', justifyContent: \'space-between\', alignItems: \'center\', marginBottom: \'24px\' }}', r'className="dashboard-header"'),
    (r'className="page-header" style={{ marginBottom: \'32px\' }}', r'className="dashboard-header"'),
    (r'<h1 className="page-title"', r'<h1'),
    (r'<p className="page-subtitle" style={{ color: \'var\(--color-text-muted\)\', marginTop: \'4px\' }}>', r'<p>'),
    (r'borderBottom: \'1px solid var\(--color-border\)\'', r'borderBottom: \'none\''),
    (r'btn-outline', r'btn-secondary'),
    (r'btn-ghost', r'btn-secondary')
]

for filepath in files:
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    for old, new in replacements:
        content = re.sub(old, new, content)

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)

print('Success')
