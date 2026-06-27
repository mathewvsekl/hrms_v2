import React from 'react';

// Utility for formatting currency
const formatCurrency = (amount, currencyCode = 'UGX') => {
    return new Intl.NumberFormat('en-US', {
        
        
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount || 0);
};

const numberToWords = (amount, currency = 'Shillings') => {
    if (amount === 0 || !amount) return `Zero ${currency} and No Cents`;
    
    const a = ['', 'One ', 'Two ', 'Three ', 'Four ', 'Five ', 'Six ', 'Seven ', 'Eight ', 'Nine ', 'Ten ', 'Eleven ', 'Twelve ', 'Thirteen ', 'Fourteen ', 'Fifteen ', 'Sixteen ', 'Seventeen ', 'Eighteen ', 'Nineteen '];
    const b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    const convertChunk = (num) => {
        if (num === 0) return '';
        if (num < 20) return a[num];
        if (num < 100) {
            let str = b[Math.floor(num / 10)];
            if (num % 10 !== 0) str += ' ' + a[num % 10];
            else str += ' ';
            return str;
        }
        return a[Math.floor(num / 100)] + 'Hundred ' + (num % 100 !== 0 ? convertChunk(num % 100) : '');
    };

    const numStr = parseFloat(amount).toFixed(2);
    const parts = numStr.split('.');
    let num = parseInt(parts[0], 10);
    const cents = parseInt(parts[1], 10);

    if (num === 0) {
        return `Zero ${currency} and ${cents > 0 ? convertChunk(cents).trim() + ' Cents' : 'No Cents'}`;
    }

    let word = '';
    if (num >= 1000000000) {
        word += convertChunk(Math.floor(num / 1000000000)) + 'Billion ';
        num %= 1000000000;
    }
    if (num >= 1000000) {
        word += convertChunk(Math.floor(num / 1000000)) + 'Million ';
        num %= 1000000;
    }
    if (num >= 1000) {
        word += convertChunk(Math.floor(num / 1000)) + 'Thousand ';
        num %= 1000;
    }
    if (num > 0) {
        word += convertChunk(num);
    }

    word = word.trim() + ' ' + currency;
    if (cents > 0) {
        word += ` and ${convertChunk(cents).trim()} Cents`;
    } else {
        word += ` and No Cents`;
    }

    return word;
};

const GenericPayslipTemplate = ({ data, companyLogo, companyName, companyData }) => {
    if (!data) return null;

    const {
        emp_code = "",
        first_name = "",
        last_name = "",
        tin_no = "",
        bank_account_no = "",
        nssf_no = "",
        bank_name = "",
        designation_name = "",
        month = new Date().getMonth() + 1,
        year = new Date().getFullYear(),
        basic_pay = 0.00,
        commissions = 0.00,
        other_earnings = 0.00,
        gross_chargeable_income = 0.00,
        paye_deduction = 0.00,
        nssf_employee_deduction = 0.00,
        advance_deductions = 0.00,
        net_pay = 0.00
    } = data;

    let parsedEarnings = [];
    let parsedDeductions = [];
    try {
        parsedEarnings = data.earnings_json ? (typeof data.earnings_json === 'string' ? JSON.parse(data.earnings_json) : data.earnings_json) : [];
        parsedDeductions = data.deductions_json ? (typeof data.deductions_json === 'string' ? JSON.parse(data.deductions_json) : data.deductions_json) : [];
    } catch (e) {
        console.error("Error parsing earnings/deductions", e);
    }
    
    const displayEarnings = parsedEarnings.filter(e => e.display_in_payslip !== 0 && parseFloat(e.amount) !== 0);
    const displayDeductions = parsedDeductions.filter(d => d.display_in_payslip !== 0 && parseFloat(d.amount) !== 0);

    const formatLabel = (key) => {
        if (!key) return '';
        return key.replace(/_/g, ' ')
            .replace(/\b\w/g, c => c.toUpperCase())
            .replace('Tin', 'TIN')
            .replace('Kra', 'KRA')
            .replace('Nssf', 'NSSF')
            .replace('Nhif', 'NHIF')
            .replace('Pin', 'PIN');
    };

    const customFieldsRender = data.custom_fields && Object.keys(data.custom_fields).length > 0 
        ? Object.entries(data.custom_fields).map(([k, v]) => (
            <React.Fragment key={k}>
                <div>{formatLabel(k)}</div>
                <div>: {v}</div>
            </React.Fragment>
        )) : null;

    // Include statutory and advance in deductions ONLY if not already present
    const hasPaye = displayDeductions.some(d => d.name.toUpperCase().includes('PAYE'));
    const hasNssf = displayDeductions.some(d => d.name.toUpperCase().includes('NSSF'));
    const hasAdvance = displayDeductions.some(d => d.name.toUpperCase().includes('ADVANCE'));

    if (parseFloat(paye_deduction) > 0 && !hasPaye) displayDeductions.push({ name: 'PAYE', amount: parseFloat(paye_deduction) });
    if (parseFloat(nssf_employee_deduction) > 0 && !hasNssf) displayDeductions.push({ name: 'NSSF', amount: parseFloat(nssf_employee_deduction) });
    if (parseFloat(advance_deductions) > 0 && !hasAdvance) displayDeductions.push({ name: 'Advance', amount: parseFloat(advance_deductions) });

    const maxRows = Math.max(displayEarnings.length, displayDeductions.length);
    const rows = [];
    for (let i = 0; i < maxRows; i++) {
        rows.push({
            earning: displayEarnings[i] || null,
            deduction: displayDeductions[i] || null
        });
    }

    let totalDeductions = parsedDeductions.reduce((sum, d) => sum + parseFloat(d.amount), 0);
    if (!hasPaye) totalDeductions += parseFloat(paye_deduction || 0);
    if (!hasNssf) totalDeductions += parseFloat(nssf_employee_deduction || 0);
    if (!hasAdvance) totalDeductions += parseFloat(advance_deductions || 0);
    const totalGrossEarnings = parseFloat(data.basic_pay || 0) + parseFloat(data.commissions || 0) + parseFloat(data.other_earnings || 0);
    const monthName = new Date(year, month - 1).toLocaleString('default', { month: 'long' });
    const payDate = `30/${month.toString().padStart(2, '0')}/${year}`; // Rough approx

    return (
        <div style={{
            position: 'relative',
            fontFamily: 'Arial, sans-serif',
            width: '210mm',
            minHeight: '296mm',
            height: '296mm',
            overflow: 'hidden',
            margin: '0 auto',
            padding: '20mm',
            background: 'white',
            color: '#000',
            fontSize: '14px',
            lineHeight: '1.5',
            boxSizing: 'border-box',
            boxShadow: '0 0 10px rgba(0,0,0,0.1)'
        }}>
            {(!['approved', 'processed', 'paid'].includes((data.status || '').toLowerCase()) && !['approved', 'processed', 'paid'].includes((data.run_status || '').toLowerCase())) && (
                <div style={{
                    position: 'absolute',
                    top: '50%',
                    left: '50%',
                    transform: 'translate(-50%, -50%) rotate(-45deg)',
                    fontSize: '150px',
                    color: 'rgba(239, 68, 68, 0.1)',
                    fontWeight: 'bold',
                    pointerEvents: 'none',
                    zIndex: 0,
                    letterSpacing: '20px',
                    whiteSpace: 'nowrap'
                }}>
                    DRAFT
                </div>
            )}
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '30px', position: 'relative', zIndex: 1 }}>
                <div>
                    <img 
                        src={companyLogo || '/LOGO.png'} 
                        alt="Company Logo" 
                        style={{ maxHeight: '60px', maxWidth: '200px' }} 
                        onError={(e) => { e.target.onerror = null; e.target.src = '/LOGO.png'; }}
                    />
                </div>
                <div style={{ textAlign: 'right' }}>
                    <div style={{ fontWeight: 'bold', fontSize: '15px', color: '#0f172a' }}>{data?.company_name || companyData?.name || companyName || "Company Name Not Set"}</div>
                    {(data?.company_address || companyData?.address) && <div style={{ fontSize: '13px', color: '#475569', marginTop: '4px' }}>{data?.company_address || companyData?.address}</div>}
                    {(data?.company_contact_email || companyData?.contact_email) && <div style={{ fontSize: '13px', color: '#475569', marginTop: '2px' }}>Email: {data?.company_contact_email || companyData?.contact_email}</div>}
                    {(data?.company_contact_phone || companyData?.contact_phone) && <div style={{ fontSize: '13px', color: '#475569', marginTop: '2px' }}>Contact: {data?.company_contact_phone || companyData?.contact_phone}</div>}
                </div>
            </div>

            {/* Title */}
            <div style={{ textAlign: 'center', fontWeight: 'bold', fontSize: '16px', textDecoration: 'underline', marginBottom: '30px' }}>
                Payslip for the Month of {monthName}, {year}
            </div>

            {/* Employee Details */}
            <div style={{ display: 'grid', gridTemplateColumns: '150px 1fr 120px 1fr', gap: '8px', marginBottom: '20px', fontWeight: '500' }}>
                <div>Employee ID</div>
                <div>: {emp_code}</div>
                <div>Pay Date</div>
                <div>: {payDate}</div>

                <div>Employee Name</div>
                <div>: {first_name} {last_name}</div>
                <div>Designation</div>
                <div>: {designation_name}</div>

                {customFieldsRender}

                <div>Bank Account No</div>
                <div>: {bank_account_no}</div>
                <div>Bank Name</div>
                <div>: {bank_name}</div>
            </div>

            {/* Pay Summary Table */}
            <div style={{ fontWeight: 'bold', marginBottom: '5px' }}>Employee Pay Summary</div>
            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid black', borderTop: '2px solid black', borderBottom: '2px solid black' }}>
                <thead>
                    <tr style={{ borderBottom: '1px solid black' }}>
                        <th style={{ textAlign: 'left', padding: '8px', width: '25%' }}>EARNINGS</th>
                        <th style={{ textAlign: 'right', padding: '8px', width: '25%' }}>AMOUNT {data.payslip_currency ? `(${data.payslip_currency})` : ''}</th>
                        <th style={{ textAlign: 'left', padding: '8px', width: '25%', borderLeft: '1px solid black' }}>DEDUCTIONS</th>
                        <th style={{ textAlign: 'right', padding: '8px', width: '25%' }}>AMOUNT {data.payslip_currency ? `(${data.payslip_currency})` : ''}</th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={index} style={{ borderBottom: index === rows.length - 1 ? '1px solid black' : 'none' }}>
                            <td style={{ padding: '8px' }}>{row.earning ? row.earning.name : ''}</td>
                            <td style={{ textAlign: 'right', padding: '8px' }}>{row.earning ? formatCurrency(row.earning.amount, data.payslip_currency) : ''}</td>
                            <td style={{ padding: '8px', borderLeft: '1px solid black', fontWeight: row.deduction && row.deduction.name === 'Advance' ? 'bold' : 'normal' }}>
                                {row.deduction ? row.deduction.name : ''}
                            </td>
                            <td style={{ textAlign: 'right', padding: '8px' }}>{row.deduction ? formatCurrency(row.deduction.amount, data.payslip_currency) : ''}</td>
                        </tr>
                    ))}
                    <tr style={{ fontWeight: 'bold', borderBottom: '1px solid black' }}>
                        <td style={{ padding: '8px' }}>Gross Earnings</td>
                        <td style={{ textAlign: 'right', padding: '8px' }}>{formatCurrency(totalGrossEarnings, data.payslip_currency)}</td>
                        <td style={{ padding: '8px', borderLeft: '1px solid black' }}>Total Deductions</td>
                        <td style={{ textAlign: 'right', padding: '8px' }}>{formatCurrency(totalDeductions, data.payslip_currency)}</td>
                    </tr>
                    <tr style={{ fontWeight: 'bold', borderBottom: '3px double black' }}>
                        <td style={{ padding: '8px', textAlign: 'right', borderRight: '1px solid black' }} colSpan="3">Total Net Payable**</td>
                        <td style={{ textAlign: 'right', padding: '8px' }}>{formatCurrency(net_pay, data.payslip_currency)}</td>
                    </tr>
                </tbody>
            </table>

            {/* Footer / Payable in Words */}
            <div style={{ display: 'grid', gridTemplateColumns: '150px 1fr', gap: '8px', marginTop: '10px', paddingBottom: '10px', borderBottom: '1px solid black' }}>
                <div style={{ textAlign: 'right', fontWeight: '500' }}>
                    Total Net Amount<br />Payable in Words
                </div>
                <div style={{ fontWeight: 'bold', alignSelf: 'center' }}>
                    {numberToWords(net_pay, data.payslip_currency || '')}
                </div>
            </div>

            <div style={{ textAlign: 'center', fontSize: '12px', marginTop: '10px' }}>
                **Total Net Payable = Gross Earnings - Total Deductions
            </div>
        </div>
    );
};

export default GenericPayslipTemplate;
