const fs = require('fs');
const path = 'c:\\Users\\AneeshMathew\\HRMS V2\\frontend\\src\\pages\\Payroll.jsx';
let content = fs.readFileSync(path, 'utf8');

content = content.replace(/const formatCurrency = \(amount\) => \{[\s\S]*?return new Intl\.NumberFormat\('en-UG', \{[\s\S]*?style: 'currency',[\s\S]*?currency: 'UGX',[\s\S]*?minimumFractionDigits: 0,[\s\S]*?maximumFractionDigits: 0[\s\S]*?\}\)\.format\(amount \|\| 0\)\.replace\('USh', 'UGX'\);[\s\S]*?\};/,
`const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-UG', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount || 0);
};`);

content = content.replace(/const renderCurrency = \(amount\) => \{[\s\S]*?return \([\s\S]*?<div style=\{\{ display: 'flex'[\s\S]*?<span style=\{\{ color: '#94a3b8'[\s\S]*?<span style=\{\{ textAlign: 'right'[\s\S]*?<\/div>\s*\);\s*\};/,
`const renderCurrency = (amount) => {
    const formatted = new Intl.NumberFormat('en-UG', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount || 0);
    return (
        <div style={{ textAlign: 'right', width: '100%', minWidth: '70px' }}>{formatted}</div>
    );
};`);

content = content.replace(/>Gross Pay<\/th>/g, '>Gross Pay (UGX)</th>');
content = content.replace(/>PAYE<\/th>/g, '>PAYE (UGX)</th>');
content = content.replace(/>NSSF \(5%\)<\/th>/g, '>NSSF (5%) (UGX)</th>');
content = content.replace(/>NSSF<\/th>/g, '>NSSF (UGX)</th>');
content = content.replace(/>Net Pay<\/th>/g, '>Net Pay (UGX)</th>');
content = content.replace(/>Total Gross<\/th>/g, '>Total Gross (UGX)</th>');
content = content.replace(/>Total Net Pay<\/th>/g, '>Total Net Pay (UGX)</th>');
content = content.replace(/>\{col\}<\/th>/g, '>{col} (UGX)</th>');

content = content.replace(/\{[a-zA-Z0-9_?.& ]+\? \(\s*<div style=\{\{ fontSize: '11px'[\s\S]*?<\/div>\s*\) : null\}/g, '');
content = content.replace(/\{[a-zA-Z0-9_?.& ]+&& \(\s*<div style=\{\{ fontSize: '12px'[\s\S]*?<\/div>\s*\)\}/g, '');

fs.writeFileSync(path, content);
console.log('Replacements completed successfully.');
