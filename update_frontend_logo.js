const fs = require('fs');
const pathConfig = 'c:\\\\Users\\\\AneeshMathew\\\\HRMS V2\\\\frontend\\\\src\\\\components\\\\PayrollConfig.jsx';
const pathPayroll = 'c:\\\\Users\\\\AneeshMathew\\\\HRMS V2\\\\frontend\\\\src\\\\pages\\\\Payroll.jsx';

// Update PayrollConfig.jsx
let contentConfig = fs.readFileSync(pathConfig, 'utf8');

// Replace the effect that sets logo from local storage
contentConfig = contentConfig.replace(
    /const savedLogo = localStorage\.getItem\(`company_logo_\$\{companyId\}`\);\s*setLogo\(savedLogo\);/,
    "const activeComp = companies?.find(c => c.id == companyId);\n            setLogo(activeComp?.logo_url || null);"
);

// Replace handleLogoUpload
contentConfig = contentConfig.replace(
    /const handleLogoUpload = \(e\) => \{[\s\S]*?reader\.readAsDataURL\(file\);\s*\}/,
    `const handleLogoUpload = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onloadend = async () => {
                const base64 = reader.result;
                try {
                    await api.post(\`/companies/logo/\${companyId}\`, { logo: base64 });
                    setLogo(base64);
                    showAlert('Success', 'Logo updated for this company', 'success');
                    window.dispatchEvent(new Event('logo-updated'));
                } catch (err) {
                    showAlert('Error', 'Failed to upload logo', 'error');
                }
            };
            reader.readAsDataURL(file);
        }
    }`
);

// Replace logo removal
contentConfig = contentConfig.replace(
    /setLogo\(null\);\s*localStorage\.removeItem\(`company_logo_\$\{companyId\}`\);\s*window\.dispatchEvent\(new Event\('logo-updated'\)\);/,
    `setLogo(null);
                                                            api.post(\`/companies/logo/\${companyId}\`, { logo: null })
                                                                .then(() => window.dispatchEvent(new Event('logo-updated')));`
);

fs.writeFileSync(pathConfig, contentConfig);


// Update Payroll.jsx
let contentPayroll = fs.readFileSync(pathPayroll, 'utf8');

// Update fetchCompanies/updateLogo to use the company's logo_url
contentPayroll = contentPayroll.replace(
    /setCompanyLogo\(localStorage\.getItem\(`company_logo_\$\{companyId\}`\)\);/g,
    `const c = companies.find(c => c.id == companyId);\n            setCompanyLogo(c?.logo_url || null);`
);

contentPayroll = contentPayroll.replace(
    /setCompanyLogo\(localStorage\.getItem\(`company_logo_\$\{compId\}`\)\);/g,
    `const c = companies.find(c => c.id == compId);\n                    setCompanyLogo(c?.logo_url || null);`
);

fs.writeFileSync(pathPayroll, contentPayroll);

console.log('Frontend files updated');
