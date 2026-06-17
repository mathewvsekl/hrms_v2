import re

filepath = r'c:\Users\AneeshMathew\HRMS V2\frontend\src\pages\Payroll.jsx'
with open(filepath, 'r') as f:
    content = f.read()

# Replace Total Gross Pay
content = content.replace(
    '<h4 style={{ margin: 0, fontSize: \'20px\', color: \'#1e293b\' }}>{renderCurrency(totals.gross)}</h4>',
    '<h4 style={{ margin: 0, fontSize: \'20px\', color: \'#1e293b\' }}><div style={{ textAlign: \'right\', width: \'100%\' }}>{currentCurrency} {formatCurrency(totals.gross)}</div></h4>'
)

# Replace Total PAYE
content = content.replace(
    '<h4 style={{ margin: 0, fontSize: \'20px\', color: \'#ef4444\' }}>{renderCurrency(totals.paye)}</h4>',
    '<h4 style={{ margin: 0, fontSize: \'20px\', color: \'#ef4444\' }}><div style={{ textAlign: \'right\', width: \'100%\' }}>{currentCurrency} {formatCurrency(totals.paye)}</div></h4>'
)

# Replace Total NSSF
content = content.replace(
    '<h4 style={{ margin: 0, fontSize: \'20px\', color: \'#ef4444\' }}>{renderCurrency(totals.nssf)}</h4>',
    '<h4 style={{ margin: 0, fontSize: \'20px\', color: \'#ef4444\' }}><div style={{ textAlign: \'right\', width: \'100%\' }}>{currentCurrency} {formatCurrency(totals.nssf)}</div></h4>'
)

# Replace Total Net Pay
content = content.replace(
    '<h4 style={{ margin: 0, fontSize: \'20px\', color: \'#10b981\' }}>{renderCurrency(totals.net)}</h4>',
    '<h4 style={{ margin: 0, fontSize: \'20px\', color: \'#10b981\' }}><div style={{ textAlign: \'right\', width: \'100%\' }}>{currentCurrency} {formatCurrency(totals.net)}</div></h4>'
)

with open(filepath, 'w') as f:
    f.write(content)

print("Added currentCurrency to the summary cards.")
