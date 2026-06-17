import { useState, useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';
import { Wallet, Settings, Play, FileText, CheckCircle2, Download, Printer, X, Loader, Trash2, ChevronDown, MoreVertical } from 'lucide-react';
import useLayoutStore from '../store/useLayoutStore';
import useNotificationStore from '../store/useNotificationStore';
import useAuthStore from '../store/useAuthStore';
import api from '../services/api';
import GenericPayslipTemplate from '../components/GenericPayslipTemplate';
import PayrollConfig from '../components/PayrollConfig';
import { useReactToPrint } from 'react-to-print';

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-UG', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount || 0);
};

const renderCurrency = (amount) => {
    const formatted = new Intl.NumberFormat('en-UG', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount || 0);
    return (
        <div style={{ textAlign: 'right', width: '100%', minWidth: '70px' }}>{formatted}</div>
    );
};

const Payroll = () => {
    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();
    const { showAlert } = useNotificationStore();
    const { user } = useAuthStore();
    const canEditPayroll = useAuthStore.getState().hasPermission('payroll', 'edit');
    const isAdmin = canEditPayroll;
    const location = useLocation();

    const [month, setMonth] = useState(new Date().getMonth() + 1);
    const [year, setYear] = useState(new Date().getFullYear());
    const [records, setRecords] = useState([]);
    const [runs, setRuns] = useState([]);
    const [selectedRun, setSelectedRun] = useState(null);
    const [selectedApprovalIds, setSelectedApprovalIds] = useState([]);
    const [submitting, setSubmitting] = useState(false);
    const [companies, setCompanies] = useState([]);
    const [companyId, setCompanyId] = useState('');
    const [exchangeRates, setExchangeRates] = useState([]);
    const [loading, setLoading] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [previewing, setPreviewing] = useState(false);
    const [allComponents, setAllComponents] = useState([]);
    const [selectedPayslip, setSelectedPayslip] = useState(null);
    const [fetchingPayslip, setFetchingPayslip] = useState(false);
    const [editingRecord, setEditingRecord] = useState(null);
    const [editForm, setEditForm] = useState({ basic_pay: 0, commissions: 0, other_earnings: 0 });
    const [savingRecord, setSavingRecord] = useState(false);
    const [activeTab, setActiveTab] = useState('processing');
    const [previewRecords, setPreviewRecords] = useState([]);
    const [showPreviewModal, setShowPreviewModal] = useState(false);
    const [reportingCurrency, setReportingCurrency] = useState('USD');
    const [exchangeRate, setExchangeRate] = useState(1);
    const [activeDropdown, setActiveDropdown] = useState(null);
    const [processingPayslips, setProcessingPayslips] = useState(false);
    const [processingRecordData, setProcessingRecordData] = useState(null);
    const hiddenPayslipRef = useRef(null);

    const currentCompany = companies.find(c => c.id == (selectedRun?.company_id || companyId));
    const currentCurrency = currentCompany?.currency_code || "UGX";
    const isUganda = currentCompany?.country_id === 1 || currentCurrency === "UGX";
    const filteredRuns = companyId ? runs.filter(r => String(r.company_id) === String(companyId)) : runs;

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (!e.target.closest('.action-dropdown-container')) {
                setActiveDropdown(null);
            }
        };
        document.addEventListener('click', handleClickOutside);
        return () => document.removeEventListener('click', handleClickOutside);
    }, []);

    const payslipRef = useRef();

    useEffect(() => {
        setPageTitle("Payroll Dashboard");
        setPageSubtitle("Manage payroll processing and payslips");
        fetchCompanies();
        fetchExchangeRates();
        return () => resetPageHeader();
    }, []);

    useEffect(() => {
        if (!exchangeRates.length || !companyId || !reportingCurrency) return;
        const comp = companies.find(c => c.id == companyId);
        if (!comp) return;
        
        const compCurrency = comp.currency_code || 'UGX';
        const rateObj = exchangeRates.find(r => r.from_currency === reportingCurrency && r.to_currency === compCurrency);
        if (rateObj) {
            setExchangeRate(rateObj.rate);
        } else {
            setExchangeRate(1); // Default if not configured
        }
    }, [reportingCurrency, companyId, exchangeRates, companies]);

    useEffect(() => {
        if (companyId) {
            fetchComponents();
        }
    }, [companyId]);

    const fetchCompanies = async () => {
        try {
            const res = await api.get('/organization/companies');
            const data = res.data?.data || [];
            setCompanies(data);
            if (data.length > 0) {
                setCompanyId(data[0].id);
            }
        } catch (error) {
            console.error("Failed to fetch companies", error);
        }
    };

    const fetchExchangeRates = async () => {
        try {
            const res = await api.get('/organization/exchange-rates');
            setExchangeRates(res.data?.data || res.data || []);
        } catch (error) {
            console.error("Failed to fetch exchange rates", error);
        }
    };

    const fetchComponents = async () => {
        if (!companyId) return;
        try {
            const res = await api.get(`/payroll/components?company_id=${companyId}`);
            setAllComponents(res.data?.data || []);
        } catch (error) {
            console.error("Failed to fetch components", error);
        }
    };

    const [companyLogo, setCompanyLogo] = useState(null);

    const updateLogo = () => {
        if (companyId) {
            const c = companies.find(c => c.id == companyId);
            setCompanyLogo(c?.logo_url || null);
        } else {
            setCompanyLogo(null);
        }
    };

    useEffect(() => {
        updateLogo();
        window.addEventListener('logo-updated', updateLogo);
        return () => window.removeEventListener('logo-updated', updateLogo);
    }, [companyId]);

    const fetchRecords = async (m, y, c) => {
        setLoading(true);
        try {
            const res = await api.get(`/payroll/records?month=${m}&year=${y}&company_id=${c}`);
            const data = res.data?.data || [];
            setRecords(data);
            
            // Auto-select drafts for approval
            const drafts = data.filter(r => r.status === 'Draft' || r.run_status === 'Draft').map(r => r.id);
            setSelectedApprovalIds(drafts);
        } catch (error) {
            console.error("Failed to fetch records", error);
            showAlert('Error', 'Failed to fetch payroll records', 'error');
        } finally {
            setLoading(false);
        }
    };

    const fetchRuns = async () => {
        try {
            const res = await api.get('/payroll/runs');
            setRuns(res.data?.data || res.data || []);
        } catch (error) {
            console.error("Failed to fetch runs", error);
        }
    };

    useEffect(() => {
        fetchRuns();
    }, []);

    useEffect(() => {
        if (runs.length > 0) {
            const queryParams = new URLSearchParams(location.search);
            const qCompanyId = queryParams.get('company_id');
            const qMonth = queryParams.get('month');
            const qYear = queryParams.get('year');
            
            if (qCompanyId && qMonth && qYear && !selectedRun) {
                const run = runs.find(r => r.company_id == qCompanyId && r.month == qMonth && r.year == qYear);
                if (run) {
                    setSelectedRun(run);
                    fetchRecords(run.month, run.year, run.company_id);
                }
            }
        }
    }, [runs, location.search, selectedRun]);

    const handlePreview = async () => {
        setPreviewing(true);
        try {
            const res = await api.post('/payroll/preview', { month, year, company_id: companyId });
            const data = res.data?.data || res.data || []; // Handle structure variations
            // default all to selected
            setPreviewRecords(data.map(r => ({ ...r, selected: true })));
            setShowPreviewModal(true);
        } catch (error) {
            console.error("Failed to preview payroll", error);
            showAlert('Error', error.response?.data?.message || 'Failed to preview payroll', 'error');
        } finally {
            setPreviewing(false);
        }
    };

    const handleConfirmGenerate = async () => {
        const excludedIds = previewRecords.filter(r => !r.selected).map(r => r.employee_id);
        setGenerating(true);
        try {
            const res = await api.post('/payroll/generate', { 
                month, 
                year, 
                company_id: companyId, 
                excluded_employee_ids: excludedIds,
                reporting_currency: reportingCurrency,
                exchange_rate: exchangeRate
            });
            showAlert('Success', res.data?.message || 'Payroll generated successfully', 'success');
            setShowPreviewModal(false);
            fetchRuns();
        } catch (error) {
            console.error("Failed to generate payroll", error);
            showAlert('Error', error.response?.data?.message || 'Failed to generate payroll', 'error');
        } finally {
            setGenerating(false);
        }
    };

    const handleViewPayslip = async (recordId) => {
        setFetchingPayslip(true);
        try {
            const res = await api.get(`/payroll/${recordId}/payslip`);
            const data = res.data?.data;
            if (data) {
                setSelectedPayslip(data);
                const compId = data.company_id || (selectedRun ? selectedRun.company_id : companyId);
                if (compId) {
                    const c = companies.find(c => c.id == compId);
                    setCompanyLogo(c?.logo_url || null);
                }
            }
        } catch (error) {
            console.error("Failed to fetch payslip", error);
            showAlert('Error', 'Failed to fetch payslip details', 'error');
        } finally {
            setFetchingPayslip(false);
        }
    };

    const handleSubmitApproval = async () => {
        if (selectedApprovalIds.length === 0) {
            showAlert('Info', 'No records selected for approval', 'info');
            return;
        }
        
        const confirmSubmit = window.confirm(`Are you sure you want to submit ${selectedApprovalIds.length} record(s) for approval?`);
        if (!confirmSubmit) return;
        
        setSubmitting(true);
        try {
            const res = await api.post('/payroll/submit-approval', {
                record_ids: selectedApprovalIds
            });
            if (res.data?.success || res.data?.status === 'success') {
                showAlert('Success', 'Records submitted for approval successfully', 'success');
                fetchRecords(selectedRun.month, selectedRun.year, selectedRun.company_id);
                fetchRuns();
            } else {
                showAlert('Error', res.data?.message || 'Failed to submit', 'error');
            }
        } catch (error) {
            console.error(error);
            showAlert('Error', 'An error occurred during submission', 'error');
        } finally {
            setSubmitting(false);
            setSelectedApprovalIds([]);
        }
    };

    const handleApprove = async () => {
        const pendingIds = selectedApprovalIds.filter(id => {
            const r = records.find(x => x.id === id);
            return r && r.status === 'Pending Approval';
        });

        if (pendingIds.length === 0) {
            showAlert('Info', 'No pending records selected for approval', 'info');
            return;
        }
        
        const confirmApprove = window.confirm(`Are you sure you want to approve ${pendingIds.length} record(s)?`);
        if (!confirmApprove) return;
        
        setSubmitting(true);
        try {
            const res = await api.post('/payroll/approve-records', {
                record_ids: pendingIds
            });
            if (res.data?.success || res.data?.status === 'success') {
                showAlert('Success', 'Records approved successfully', 'success');
                fetchRecords(selectedRun.month, selectedRun.year, selectedRun.company_id);
                fetchRuns();
            } else {
                showAlert('Error', res.data?.message || 'Failed to approve', 'error');
            }
        } catch (error) {
            console.error(error);
            showAlert('Error', 'An error occurred during approval', 'error');
        } finally {
            setSubmitting(false);
            setSelectedApprovalIds([]);
        }
    };

    const handleReject = async () => {
        const pendingIds = selectedApprovalIds.filter(id => {
            const r = records.find(x => x.id === id);
            return r && r.status === 'Pending Approval';
        });

        if (pendingIds.length === 0) {
            showAlert('Info', 'No pending records selected for rejection', 'info');
            return;
        }
        
        const confirmReject = window.confirm(`Are you sure you want to reject ${pendingIds.length} record(s)?`);
        if (!confirmReject) return;
        
        setSubmitting(true);
        try {
            const res = await api.post('/payroll/reject-records', {
                record_ids: pendingIds
            });
            if (res.data?.success || res.data?.status === 'success') {
                showAlert('Success', 'Records rejected successfully', 'success');
                fetchRecords(selectedRun.month, selectedRun.year, selectedRun.company_id);
                fetchRuns();
            } else {
                showAlert('Error', res.data?.message || 'Failed to reject', 'error');
            }
        } catch (error) {
            console.error(error);
            showAlert('Error', 'An error occurred during rejection', 'error');
        } finally {
            setSubmitting(false);
            setSelectedApprovalIds([]);
        }
    };

    const handleEditClick = (record) => {
        setEditingRecord(record);
        
        let parsedEarnings = [];
        let parsedDeductions = [];
        try {
            parsedEarnings = typeof record.earnings_json === 'string' ? JSON.parse(record.earnings_json) : (record.earnings_json || []);
            parsedDeductions = typeof record.deductions_json === 'string' ? JSON.parse(record.deductions_json) : (record.deductions_json || []);
        } catch (e) {}

        // Populate missing fixed components from allComponents
        const fixedEarnings = allComponents.filter(c => c.type === 'EARNING' && c.computation_type === 'FIXED');
        const fixedDeductions = allComponents.filter(c => c.type === 'DEDUCTION' && c.computation_type === 'FIXED');

        fixedEarnings.forEach(c => {
            if (!parsedEarnings.find(e => e.name === c.name)) {
                parsedEarnings.push({
                    name: c.name,
                    amount: 0,
                    is_non_taxable: c.is_non_taxable,
                    display_in_payslip: c.display_in_payslip,
                    computation_type: 'FIXED'
                });
            }
        });

        fixedDeductions.forEach(c => {
            if (!parsedDeductions.find(d => d.name === c.name)) {
                parsedDeductions.push({
                    name: c.name,
                    amount: 0,
                    display_in_payslip: c.display_in_payslip,
                    computation_type: 'FIXED'
                });
            }
        });

        setEditForm({
            basic_pay: parseFloat(record.basic_pay) || 0,
            commissions: parseFloat(record.commissions) || 0,
            other_earnings: parseFloat(record.other_earnings) || 0,
            earnings: parsedEarnings,
            deductions: parsedDeductions,
            advance_deductions: parseFloat(record.advance_deductions) || 0
        });
    };

    const handleSaveRecord = async () => {
        setSavingRecord(true);
        try {
            await api.put(`/payroll/records/${editingRecord.id}`, editForm);
            showAlert('Success', 'Payroll record updated successfully', 'success');
            setEditingRecord(null);
            fetchRecords();
        } catch (error) {
            console.error("Failed to update record", error);
            showAlert('Error', 'Failed to update payroll record', 'error');
        } finally {
            setSavingRecord(false);
        }
    };

    const handleDeleteSelected = async () => {
        if (!selectedApprovalIds.length) return;
        if (!window.confirm(`Are you sure you want to delete ${selectedApprovalIds.length} selected payroll record(s)?`)) return;
        
        setSubmitting(true);
        try {
            for (let id of selectedApprovalIds) {
                await api.delete(`/payroll/records/${id}`);
            }
            showAlert('Success', `${selectedApprovalIds.length} payroll record(s) deleted successfully`, 'success');
            setSelectedApprovalIds([]);
            fetchRecords();
        } catch (error) {
            console.error("Failed to delete records", error);
            showAlert('Error', 'Failed to delete some records', 'error');
        } finally {
            setSubmitting(false);
        }
    };

    const handleProcessPayment = async () => {
        const approvedIds = records.filter(r => r.status === 'Processed').map(r => r.id);
        if (!approvedIds.length) return;
        
        if (!window.confirm(`Are you sure you want to process payment for ${approvedIds.length} approved record(s)? This will generate PDF payslips and mark them as Paid. This may take a moment.`)) return;
        
        setSubmitting(true);
        setProcessingPayslips(true);
        try {
            const html2pdf = (await import('html2pdf.js')).default;
            
            for (let id of approvedIds) {
                // Fetch the detailed payslip data
                const dataRes = await api.get(`/payroll/${id}/payslip`);
                const data = dataRes.data?.data || dataRes.data;
                setProcessingRecordData(data);
                
                // Wait for React to render the component into the hidden ref
                await new Promise(resolve => setTimeout(resolve, 800));
                
                if (hiddenPayslipRef.current) {
                    const opt = {
                        margin: 0,
                        filename: `payslip_${data.first_name}_${data.last_name}_${data.month}_${data.year}.pdf`,
                        image: { type: 'jpeg', quality: 0.98 },
                        html2canvas: { scale: 2, useCORS: true, logging: false },
                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                    };
                    
                    const pdfBlob = await html2pdf().from(hiddenPayslipRef.current).set(opt).output('blob');
                    
                    const formData = new FormData();
                    formData.append('document', pdfBlob, opt.filename);
                    formData.append('employee_id', data.employee_id);
                    formData.append('company_id', data.company_id || selectedRun?.company_id);
                    formData.append('month', data.month);
                    formData.append('year', data.year);
                    
                    await api.post('/payslips/upload', formData, {
                        headers: { 'Content-Type': 'multipart/form-data' }
                    });
                }
            }
            
            const res = await api.post('/payroll/process', { record_ids: approvedIds });
            showAlert('Success', res.data.message || 'Payment processed successfully', 'success');
            
            // Reload runs and records to reflect Paid status
            fetchRuns();
            fetchRecords();
        } catch (error) {
            console.error("Failed to process payment", error);
            showAlert('Error', error.response?.data?.message || 'Failed to process payment', 'error');
        } finally {
            setSubmitting(false);
            setProcessingPayslips(false);
            setProcessingRecordData(null);
        }
    };

    const getFilename = () => {
        if (!selectedPayslip) return 'Payslip';
        const month = selectedPayslip.month || new Date().getMonth() + 1;
        const year = selectedPayslip.year || new Date().getFullYear();
        const monthName = new Date(year, month - 1).toLocaleString('default', { month: 'short' });
        return `Payslip_${selectedPayslip.first_name}_${selectedPayslip.last_name}_${monthName}_${year}`;
    };

    const handlePrint = useReactToPrint({
        contentRef: payslipRef,
        documentTitle: getFilename(),
    });

    const handleDownloadPdf = async () => {
        if (!payslipRef.current) return;
        const html2pdf = (await import('html2pdf.js')).default;
        const element = payslipRef.current;
        const opt = {
            margin: 0,
            filename: `${getFilename()}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        html2pdf().from(element).set(opt).save();
    };

    // Calculate UI constants from records
    const dynamicEarnings = new Set();
    const dynamicDeductions = new Set();
    const parsedRecords = records.map(record => {
        let parsedE = [];
        let parsedD = [];
        try {
            parsedE = typeof record.earnings_json === 'string' ? JSON.parse(record.earnings_json) : (record.earnings_json || []);
            parsedD = typeof record.deductions_json === 'string' ? JSON.parse(record.deductions_json) : (record.deductions_json || []);
        } catch (e) {}
        return { ...record, parsedEarnings: parsedE, parsedDeductions: parsedD };
    });

    parsedRecords.forEach(record => {
        record.parsedEarnings.forEach(e => {
            if (e.amount > 0) dynamicEarnings.add(e.name);
        });
        record.parsedDeductions.forEach(d => {
            if (d.amount > 0) dynamicDeductions.add(d.name);
        });
    });

    const earningCols = Array.from(dynamicEarnings);
    const deductionCols = Array.from(dynamicDeductions);

    // Calculate totals
    const totals = {
        gross: 0, paye: 0, nssf: 0, advances: 0, net: 0,
        earnings: {}, deductions: {}
    };
    earningCols.forEach(c => totals.earnings[c] = 0);
    deductionCols.forEach(c => totals.deductions[c] = 0);

    parsedRecords.forEach(record => {
        totals.gross += (parseFloat(record.basic_pay || 0) + parseFloat(record.commissions || 0) + parseFloat(record.other_earnings || 0));
        totals.paye += parseFloat(record.paye_deduction) || 0;
        totals.nssf += parseFloat(record.nssf_employee_deduction) || 0;
        totals.advances += parseFloat(record.advance_deductions) || 0;
        totals.net += parseFloat(record.net_pay) || 0;

        earningCols.forEach(col => {
            const e = record.parsedEarnings.find(x => x.name === col);
            if (e) totals.earnings[col] += parseFloat(e.amount) || 0;
        });
        deductionCols.forEach(col => {
            const d = record.parsedDeductions.find(x => x.name === col);
            if (d) totals.deductions[col] += parseFloat(d.amount) || 0;
        });
    });

    return (
        <div>
            {!selectedRun && (
                /* Tabs matching Leave Management style */
                <div style={{ display: 'flex', gap: '20px', marginBottom: '24px', borderBottom: '2px solid var(--color-border)' }}>
                    <button 
                        className={`tab-btn ${activeTab === 'processing' ? 'active' : ''}`} 
                        onClick={() => setActiveTab('processing')}
                        style={{ 
                            background: 'none', border: 'none', padding: '10px 4px', fontSize: '14px', fontWeight: '500', cursor: 'pointer',
                            color: activeTab === 'processing' ? 'var(--color-rose-gold)' : 'var(--color-text-muted)',
                            borderBottom: activeTab === 'processing' ? '2px solid var(--color-rose-gold)' : '2px solid transparent',
                            marginBottom: '-2px'
                        }}
                    >
                        Processing & Payslips
                    </button>
                    <button 
                        className={`tab-btn ${activeTab === 'config' ? 'active' : ''}`} 
                        onClick={() => setActiveTab('config')}
                        style={{ 
                            background: 'none', border: 'none', padding: '10px 4px', fontSize: '14px', fontWeight: '500', cursor: 'pointer',
                            color: activeTab === 'config' ? 'var(--color-rose-gold)' : 'var(--color-text-muted)',
                            borderBottom: activeTab === 'config' ? '2px solid var(--color-rose-gold)' : '2px solid transparent',
                            marginBottom: '-2px'
                        }}
                    >
                        Configuration
                    </button>
                </div>
            )}

            {activeTab === 'config' ? (
                <PayrollConfig 
                    companies={companies} 
                    companyId={companyId} 
                    setCompanyId={setCompanyId} 
                />
            ) : (
                <>
                    {/* Top Bar for Processing */}
                    {!selectedRun && (
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px', flexWrap: 'wrap', gap: '16px' }}>
                            <div style={{ display: 'flex', gap: '12px', alignItems: 'center', background: '#fff', padding: '8px 16px', borderRadius: '12px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    <span style={{ fontSize: '14px', fontWeight: '500', color: '#64748b' }}>Period:</span>
                                    <select 
                                        className="form-input" 
                                        style={{ width: '120px', height: '36px', padding: '0 12px', borderRadius: '6px', border: '1px solid #cbd5e1' }}
                                        value={month}
                                        onChange={e => setMonth(parseInt(e.target.value))}
                                    >
                                        {Array.from({length: 12}, (_, i) => i + 1).map(m => (
                                            <option key={m} value={m}>{new Date(2000, m - 1).toLocaleString('default', { month: 'long' })}</option>
                                        ))}
                                    </select>
                                    <input 
                                        type="number" 
                                        className="form-input" 
                                        style={{ width: '80px', height: '36px', padding: '0 12px', borderRadius: '6px', border: '1px solid #cbd5e1' }}
                                        value={year}
                                        onChange={e => setYear(parseInt(e.target.value))}
                                    />
                                    <div style={{ width: '1px', height: '24px', background: '#cbd5e1', margin: '0 8px' }}></div>
                                    <span style={{ fontSize: '14px', fontWeight: '500', color: '#64748b' }}>Company:</span>
                                    <select 
                                        className="form-input" 
                                        style={{ width: '220px', height: '36px', padding: '0 12px', borderRadius: '6px', border: '1px solid #cbd5e1' }}
                                        value={companyId}
                                        onChange={e => setCompanyId(e.target.value)}
                                    >
                                        {companies.map(c => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                    <div style={{ width: '1px', height: '24px', background: '#cbd5e1', margin: '0 8px' }}></div>
                                    <span style={{ fontSize: '14px', fontWeight: '500', color: '#64748b' }}>Reporting Currency:</span>
                                    <select 
                                        className="form-input" 
                                        style={{ width: '100px', height: '36px', padding: '0 12px', borderRadius: '6px', border: '1px solid #cbd5e1' }}
                                        value={reportingCurrency}
                                        onChange={e => setReportingCurrency(e.target.value)}
                                    >
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="GBP">GBP</option>
                                        <option value="UGX">UGX</option>
                                    </select>
                                    <input 
                                        type="number" 
                                        placeholder="Ex. Rate"
                                        className="form-input" 
                                        style={{ width: '100px', height: '36px', padding: '0 12px', borderRadius: '6px', border: '1px solid #cbd5e1' }}
                                        value={exchangeRate}
                                        onChange={e => setExchangeRate(e.target.value)}
                                        title="Exchange Rate"
                                    />
                                </div>
                            </div>

                            <div style={{ display: 'flex', gap: '12px' }}>
                                <button 
                                    className="btn btn-primary" 
                                    onClick={handlePreview}
                                    disabled={previewing}
                                    style={{ display: 'flex', alignItems: 'center', gap: '8px', border: 'none' }}
                                >
                                    {previewing ? <Loader size={16} className="spin" /> : <Play size={16} />}
                                    {previewing ? 'Previewing...' : 'Run Payroll'}
                                </button>
                            </div>
                        </div>
                    )}
            {selectedRun ? (
                <div>
                    <div style={{ marginBottom: '16px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <button 
                                className="btn btn-secondary" 
                                onClick={() => setSelectedRun(null)}
                                style={{ padding: '6px 12px', background: '#e2e8f0', border: 'none', borderRadius: '6px', cursor: 'pointer' }}
                            >
                                &larr; Back to Runs
                            </button>
                            <h2 style={{ margin: 0, fontSize: '18px', color: '#1e293b' }}>
                                {new Date(selectedRun.year, selectedRun.month - 1).toLocaleString('default', { month: 'long' })} {selectedRun.year} - {selectedRun.company_name}
                            </h2>
                        </div>
                        
                        <div style={{ display: 'flex', gap: '12px' }}>
                            {records.some(r => r.status === 'Draft' || r.run_status === 'Draft') && (
                                <button
                                    className="btn btn-primary"
                                    onClick={handleSubmitApproval}
                                    disabled={submitting || selectedApprovalIds.filter(id => records.find(r => r.id === id)?.status === 'Draft').length === 0}
                                    style={{ padding: '8px 16px', borderRadius: '8px', border: 'none', cursor: submitting ? 'not-allowed' : 'pointer' }}
                                >
                                    {submitting ? '...' : `Submit ${selectedApprovalIds.filter(id => records.find(r => r.id === id)?.status === 'Draft').length} for Approval`}
                                </button>
                            )}
                            {records.some(r => r.status === 'Pending Approval') && (
                                <>
                                    <button
                                        className="btn btn-primary"
                                        onClick={handleApprove}
                                        disabled={submitting || selectedApprovalIds.filter(id => records.find(r => r.id === id)?.status === 'Pending Approval').length === 0}
                                        style={{ padding: '8px 16px', borderRadius: '8px', border: 'none', cursor: submitting ? 'not-allowed' : 'pointer', background: '#10b981' }}
                                    >
                                        {submitting ? '...' : `Approve ${selectedApprovalIds.filter(id => records.find(r => r.id === id)?.status === 'Pending Approval').length}`}
                                    </button>
                                    <button
                                        className="btn btn-outline"
                                        onClick={handleReject}
                                        disabled={submitting || selectedApprovalIds.filter(id => records.find(r => r.id === id)?.status === 'Pending Approval').length === 0}
                                        style={{ padding: '8px 16px', borderRadius: '8px', cursor: submitting ? 'not-allowed' : 'pointer', color: '#ef4444', borderColor: '#ef4444' }}
                                    >
                                        {submitting ? '...' : `Reject ${selectedApprovalIds.filter(id => records.find(r => r.id === id)?.status === 'Pending Approval').length}`}
                                    </button>
                                </>
                            )}
                            {records.some(r => r.status === 'Processed') && (
                                <button
                                    className="btn btn-primary"
                                    onClick={handleProcessPayment}
                                    disabled={submitting || records.filter(r => r.status === 'Processed').length === 0}
                                    style={{ padding: '8px 16px', borderRadius: '8px', border: 'none', cursor: submitting ? 'not-allowed' : 'pointer', background: '#3b82f6' }}
                                >
                                    {processingPayslips ? <><Loader size={14} className="spin" style={{marginRight: '6px'}}/> Processing Payslips...</> : submitting ? '...' : `Process Payment`}
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Summary Bar */}
                    {records.length > 0 && (
                        <div style={{ display: 'flex', gap: '16px', marginBottom: '24px', flexWrap: 'wrap' }}>
                            <div className="card" style={{ padding: '16px 20px', flex: '1', minWidth: '200px' }}>
                                <p style={{ margin: '0 0 4px', fontSize: '13px', color: '#64748b' }}>Total Gross Pay</p>
                                <h4 style={{ margin: 0, fontSize: '20px', color: '#1e293b' }}><div style={{ textAlign: 'right', width: '100%' }}>{currentCurrency} {formatCurrency(totals.gross)}</div></h4>
                                {selectedRun?.reporting_currency && selectedRun?.exchange_rate && (
                                    <div style={{ fontSize: '13px', color: '#64748b', marginTop: '4px' }}>
                                        {selectedRun.reporting_currency} {(totals.gross / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </div>
                                )}
                            </div>
                            {isUganda && (
                                <>
                                    <div className="card" style={{ padding: '16px 20px', flex: '1', minWidth: '200px' }}>
                                        <p style={{ margin: '0 0 4px', fontSize: '13px', color: '#64748b' }}>Total PAYE</p>
                                        <h4 style={{ margin: 0, fontSize: '20px', color: '#ef4444' }}><div style={{ textAlign: 'right', width: '100%' }}>{currentCurrency} {formatCurrency(totals.paye)}</div></h4>
                                        {selectedRun?.reporting_currency && selectedRun?.exchange_rate && (
                                            <div style={{ fontSize: '13px', color: '#64748b', marginTop: '4px' }}>
                                                {selectedRun.reporting_currency} {(totals.paye / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                        )}
                                    </div>
                                    <div className="card" style={{ padding: '16px 20px', flex: '1', minWidth: '200px' }}>
                                        <p style={{ margin: '0 0 4px', fontSize: '13px', color: '#64748b' }}>Total NSSF</p>
                                        <h4 style={{ margin: 0, fontSize: '20px', color: '#ef4444' }}><div style={{ textAlign: 'right', width: '100%' }}>{currentCurrency} {formatCurrency(totals.nssf)}</div></h4>
                                        {selectedRun?.reporting_currency && selectedRun?.exchange_rate && (
                                            <div style={{ fontSize: '13px', color: '#64748b', marginTop: '4px' }}>
                                                {selectedRun.reporting_currency} {(totals.nssf / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                            </div>
                                        )}
                                    </div>
                                </>
                            )}
                            <div className="card" style={{ padding: '16px 20px', flex: '1', minWidth: '200px' }}>
                                <p style={{ margin: '0 0 4px', fontSize: '13px', color: '#64748b' }}>Total Net Pay</p>
                                <h4 style={{ margin: 0, fontSize: '20px', color: '#10b981' }}><div style={{ textAlign: 'right', width: '100%' }}>{currentCurrency} {formatCurrency(totals.net)}</div></h4>
                                {selectedRun?.reporting_currency && selectedRun?.exchange_rate && (
                                    <div style={{ fontSize: '13px', color: '#64748b', marginTop: '4px' }}>
                                        {selectedRun.reporting_currency} {(totals.net / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Data Table */}
                    <div className="card" style={{ padding: '0', overflow: 'hidden' }}>
                        <div style={{ padding: '20px 24px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc' }}>
                            <h3 style={{ margin: 0, fontSize: '16px', fontWeight: '600', color: '#334155', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <Wallet size={18} style={{ color: 'var(--color-primary)' }}/>
                                Payroll Register
                            </h3>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <span style={{ fontSize: '13px', color: '#64748b', fontWeight: '500', background: '#e2e8f0', padding: '4px 10px', borderRadius: '12px' }}>
                                    {records.length} Employees
                                </span>
                                {selectedApprovalIds.length > 0 && (
                                    <button 
                                        onClick={handleDeleteSelected}
                                        disabled={submitting}
                                        style={{ 
                                            background: '#fef2f2', 
                                            border: '1px solid #fecaca', 
                                            color: '#ef4444', 
                                            padding: '4px 10px', 
                                            borderRadius: '6px', 
                                            fontSize: '12px', 
                                            fontWeight: '600', 
                                            cursor: submitting ? 'not-allowed' : 'pointer',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '4px',
                                            transition: 'all 0.2s'
                                        }}
                                    >
                                        <Trash2 size={12} /> Delete Selected ({selectedApprovalIds.length})
                                    </button>
                                )}
                            </div>
                        </div>


                {loading ? (
                    <div style={{ padding: '40px', textAlign: 'center', color: '#94a3b8' }}>
                        <Loader size={32} className="spin" style={{ margin: '0 auto 16px' }} />
                        <p>Loading payroll records...</p>
                    </div>
                ) : records.length === 0 ? (
                    <div style={{ padding: '60px', textAlign: 'center', color: '#94a3b8' }}>
                        <Wallet size={48} style={{ margin: '0 auto 16px', color: '#cbd5e1' }} />
                        <h3 style={{ margin: '0 0 8px', color: '#475569' }}>No Payroll Records</h3>
                        <p style={{ margin: '0 0 24px' }}>Click "Run Payroll" to generate records for the selected month.</p>
                        <button 
                            className="btn btn-primary" 
                            onClick={handlePreview}
                            disabled={previewing}
                            style={{ display: 'inline-flex', alignItems: 'center', gap: '8px', border: 'none', padding: '10px 20px', borderRadius: '8px', fontWeight: '500', cursor: 'pointer' }}
                        >
                            {previewing ? <Loader size={16} className="spin" /> : <Play size={16} />}
                            {previewing ? 'Previewing...' : 'Run Payroll for this Month'}
                        </button>
                    </div>
                ) : (
                    <div style={{ overflowX: 'auto' }}>
                                <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', minWidth: '1000px' }}>
                                    <thead>
                                        <tr style={{ borderBottom: '1px solid #e2e8f0', background: '#fff' }}>
                                            <th style={{ padding: '16px 24px', width: '40px' }}>
                                                <input 
                                                    type="checkbox" 
                                                    checked={selectedApprovalIds.length > 0 && selectedApprovalIds.length === records.filter(r => r.status === 'Draft' || r.status === 'Pending Approval' || r.run_status === 'Draft' || isAdmin).length}
                                                    onChange={(e) => {
                                                        if (e.target.checked) {
                                                            setSelectedApprovalIds(records.filter(r => r.status === 'Draft' || r.status === 'Pending Approval' || r.run_status === 'Draft' || isAdmin).map(r => r.id));
                                                        } else {
                                                            setSelectedApprovalIds([]);
                                                        }
                                                    }}
                                                />
                                            </th>
                                            <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Employee</th>
                                            {earningCols.map(col => (
                                                <th key={`h_earn_${col}`} style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>{col} ({currentCurrency})</th>
                                            ))}
                                            <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Gross Pay ({currentCurrency})</th>
                                            <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Gross Pay ({selectedRun?.reporting_currency || 'Reporting'})</th>
                                            {isUganda && <>
                                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>PAYE ({currentCurrency})</th>
                                            <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>NSSF (5%) ({currentCurrency})</th>
                                            </>}
                                            {deductionCols.map(col => (
                                                <th key={`h_ded_${col}`} style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>{col} ({currentCurrency})</th>
                                            ))}
                                            <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Net Pay ({currentCurrency})</th>
                                            <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Net Pay ({selectedRun?.reporting_currency || 'Reporting'})</th>
                                            <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b', textAlign: 'center' }}>Status</th>
                                            <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b', textAlign: 'right' }}>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {parsedRecords.map(record => (
                                            <tr key={record.id} style={{ borderBottom: '1px solid #f1f5f9', transition: 'background 0.2s' }}>
                                                <td style={{ padding: '16px 24px' }}>
                                                    <input 
                                                        type="checkbox"
                                                        checked={selectedApprovalIds.includes(record.id)}
                                                        disabled={record.status === 'Processed' && !isAdmin}
                                                        onChange={(e) => {
                                                            if (e.target.checked) {
                                                                setSelectedApprovalIds([...selectedApprovalIds, record.id]);
                                                            } else {
                                                                setSelectedApprovalIds(selectedApprovalIds.filter(id => id !== record.id));
                                                            }
                                                        }}
                                                    />
                                                </td>
                                                <td style={{ padding: '16px 24px' }}>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                        <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-primary-light, #e0e7ff)', color: 'var(--color-primary)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold', fontSize: '12px' }}>
                                                            {record.first_name?.[0]}{record.last_name?.[0]}
                                                        </div>
                                                        <div>
                                                            <div style={{ fontWeight: '500', color: '#334155', fontSize: '14px' }}>{record.first_name} {record.last_name}</div>
                                                            <div style={{ fontSize: '12px', color: '#94a3b8' }}>{record.emp_code} • {record.designation_name || 'N/A'}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                {earningCols.map(col => {
                                                    const e = record.parsedEarnings.find(x => x.name === col);
                                                    return (
                                                        <td key={`r_earn_${col}`} style={{ padding: '16px 24px', fontSize: '14px', color: '#334155' }}>
                                                            <div>{renderCurrency(e ? e.amount : 0)}</div>
                                                            
                                                        </td>
                                                    );
                                                })}
                                                <td style={{ padding: '16px 24px', fontSize: '14px', color: '#334155', fontWeight: '500' }}>
                                                    <div>{renderCurrency((parseFloat(record.basic_pay || 0) + parseFloat(record.commissions || 0) + parseFloat(record.other_earnings || 0)))}</div>
                                                    
                                                </td>
                                                <td style={{ padding: '16px 24px', fontSize: '14px', color: '#3b82f6', fontWeight: '500' }}>
                                                    {selectedRun?.reporting_currency && selectedRun?.exchange_rate ? (
                                                        <span>{((parseFloat(record.basic_pay || 0) + parseFloat(record.commissions || 0) + parseFloat(record.other_earnings || 0)) / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                    ) : '-'}
                                                </td>
                                                {isUganda && <>
                                                  <td style={{ padding: '16px 24px', fontSize: '14px', color: '#ef4444' }}>
                                                    <div>{renderCurrency(record.paye_deduction)}</div>
                                                    
                                                </td>
                                                <td style={{ padding: '16px 24px', fontSize: '14px', color: '#ef4444' }}>
                                                    <div>{renderCurrency(record.nssf_employee_deduction)}</div>
                                                    
                                                </td>
                                                  </>}
                                                {deductionCols.map(col => {
                                                    const d = record.parsedDeductions.find(x => x.name === col);
                                                    return (
                                                        <td key={`r_ded_${col}`} style={{ padding: '16px 24px', fontSize: '14px', color: '#ef4444' }}>
                                                            <div>{renderCurrency(d ? d.amount : 0)}</div>
                                                            
                                                        </td>
                                                    );
                                                })}
                                                <td style={{ padding: '16px 24px', fontSize: '14px', color: '#10b981', fontWeight: '600' }}>
                                                    <div>{renderCurrency(record.net_pay)}</div>
                                                    
                                                </td>
                                                <td style={{ padding: '16px 24px', fontSize: '14px', color: '#3b82f6', fontWeight: '600' }}>
                                                    {selectedRun?.reporting_currency && selectedRun?.exchange_rate ? (
                                                        <span>{(record.net_pay / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                    ) : '-'}
                                                </td>
                                                <td style={{ padding: '16px 24px', textAlign: 'center' }}>
                                                    <span style={{ 
                                                        background: record.status === 'Draft' ? '#fef3c7' : '#dcfce7', 
                                                        color: record.status === 'Draft' ? '#b45309' : '#166534',
                                                        padding: '4px 10px', 
                                                        borderRadius: '20px', 
                                                        fontSize: '12px', 
                                                        fontWeight: '600' 
                                                    }}>
                                                        {record.status}
                                                    </span>
                                                </td>
                                                <td style={{ padding: '16px 24px', textAlign: 'right' }}>
                                                    <div className="action-dropdown-container" style={{ position: 'relative', display: 'inline-block', textAlign: 'left' }}>
                                                        <button 
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                setActiveDropdown(activeDropdown === record.id ? null : record.id);
                                                            }}
                                                            style={{ background: 'white', border: '1px solid #e2e8f0', color: '#64748b', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', fontWeight: '500', padding: '6px 12px', borderRadius: '6px', boxShadow: '0 1px 2px rgba(0,0,0,0.05)' }}
                                                        >
                                                            Actions <ChevronDown size={14} />
                                                        </button>
                                                        {activeDropdown === record.id && (
                                                            <div className="action-dropdown-menu" style={{ position: 'absolute', right: 0, top: '100%', marginTop: '4px', background: 'white', border: '1px solid #e2e8f0', borderRadius: '8px', boxShadow: '0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)', padding: '4px', zIndex: 50, minWidth: '140px', display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                                {record.status !== 'Processed' && (
                                                                    <button 
                                                                        onClick={(e) => { e.stopPropagation(); handleEditClick(record); setActiveDropdown(null); }}
                                                                        style={{ width: '100%', textAlign: 'left', background: 'none', border: 'none', padding: '8px 12px', borderRadius: '4px', color: '#64748b', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px' }}
                                                                        onMouseEnter={(e) => e.target.style.background = '#f1f5f9'}
                                                                        onMouseLeave={(e) => e.target.style.background = 'none'}
                                                                    >
                                                                        <Settings size={14} /> Edit
                                                                    </button>
                                                                )}
                                                                <button 
                                                                    onClick={(e) => { e.stopPropagation(); handleViewPayslip(record.id); setActiveDropdown(null); }}
                                                                    style={{ width: '100%', textAlign: 'left', background: 'none', border: 'none', padding: '8px 12px', borderRadius: '4px', color: 'var(--color-primary)', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px' }}
                                                                    onMouseEnter={(e) => e.target.style.background = '#f1f5f9'}
                                                                    onMouseLeave={(e) => e.target.style.background = 'none'}
                                                                >
                                                                    <FileText size={14} /> Preview
                                                                </button>
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr style={{ background: '#f8fafc', borderTop: '2px solid #e2e8f0' }}>
                                            <td></td>
                                            <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '700', color: '#334155' }}>TOTAL</td>
                                            {earningCols.map(col => (
                                                <td key={`tot_earn_${col}`} style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '700', color: '#334155' }}>{renderCurrency(totals.earnings[col])}</td>
                                            ))}
                                            <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '700', color: '#334155' }}>{renderCurrency(totals.gross)}</td>
                                            <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '700', color: '#3b82f6' }}>
                                                {selectedRun?.reporting_currency && selectedRun?.exchange_rate ? (
                                                    <span>{(totals.gross / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                ) : '-'}
                                            </td>
                                            {isUganda && <>
                                                <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '700', color: '#ef4444' }}>{renderCurrency(totals.paye)}</td>
                                                <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '700', color: '#ef4444' }}>{renderCurrency(totals.nssf)}</td>
                                            </>}
                                            {deductionCols.map(col => (
                                                <td key={`tot_ded_${col}`} style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '700', color: '#ef4444' }}>{renderCurrency(totals.deductions[col])}</td>
                                            ))}
                                            <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '700', color: '#10b981' }}>{renderCurrency(totals.net)}</td>
                                            <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '700', color: '#3b82f6' }}>
                                                {selectedRun?.reporting_currency && selectedRun?.exchange_rate ? (
                                                    <span>{(totals.net / selectedRun.exchange_rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                ) : '-'}
                                            </td>
                                            <td colSpan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                    </div>
                )}
                </div>
                </div>
            ) : (
                <div className="card" style={{ padding: '0', overflow: 'hidden' }}>
                    <div style={{ padding: '20px 24px', borderBottom: '1px solid #e2e8f0', background: '#f8fafc' }}>
                        <h3 style={{ margin: 0, fontSize: '16px', fontWeight: '600', color: '#334155', display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <FileText size={18} style={{ color: 'var(--color-primary)' }}/>
                            Payroll Runs History
                        </h3>
                    </div>
                    {filteredRuns.length === 0 ? (
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
                                    <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Total Gross</th>
                                    <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Total Net Pay</th>
                                    <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredRuns.map((run, idx) => (
                                    <tr 
                                        key={idx} 
                                        style={{ cursor: 'pointer', borderBottom: '1px solid #e2e8f0', transition: 'background 0.2s' }}
                                        onMouseEnter={(e) => e.currentTarget.style.background = '#f8fafc'}
                                        onMouseLeave={(e) => e.currentTarget.style.background = 'transparent'}
                                        onClick={() => {
                                            setSelectedRun(run);
                                            fetchRecords(run.month, run.year, run.company_id);
                                        }}
                                    >
                                        <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '500', color: '#1e293b' }}>
                                            {new Date(run.year, run.month - 1).toLocaleString('default', { month: 'long' })} {run.year}
                                        </td>
                                        <td style={{ padding: '16px 24px', fontSize: '14px', color: '#475569' }}>{run.company_name}</td>
                                        <td style={{ padding: '16px 24px', fontSize: '14px', color: '#475569' }}>
                                            <span style={{ background: '#e2e8f0', padding: '4px 12px', borderRadius: '12px', fontSize: '13px', fontWeight: '500' }}>
                                                {run.total_employees}
                                            </span>
                                        </td>
                                        <td style={{ padding: '16px 24px', fontSize: '14px', color: '#475569' }}>
                                            <div>{currentCurrency} {formatCurrency(run.total_gross_pay)}</div>
                                        </td>
                                        <td style={{ padding: '16px 24px', fontSize: '14px', color: '#94a3b8' }}>
                                            {run.reporting_currency && run.reporting_currency !== currentCurrency && run.exchange_rate ? (
                                                <div>{run.reporting_currency} {formatCurrency(run.total_gross_pay / run.exchange_rate)}</div>
                                            ) : '-'}
                                        </td>
                                        <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '600', color: '#10b981' }}>
                                            <div>{currentCurrency} {formatCurrency(run.total_net_pay)}</div>
                                        </td>
                                        <td style={{ padding: '16px 24px', fontSize: '14px', fontWeight: '600', color: '#34d399' }}>
                                            {run.reporting_currency && run.reporting_currency !== currentCurrency && run.exchange_rate ? (
                                                <div>{run.reporting_currency} {formatCurrency(run.total_net_pay / run.exchange_rate)}</div>
                                            ) : '-'}
                                        </td>
                                        <td style={{ padding: '16px 24px' }}>
                                            <span style={{ 
                                                padding: '4px 12px', 
                                                borderRadius: '12px', 
                                                fontSize: '13px', 
                                                fontWeight: '500',
                                                background: run.status === 'Processed' ? '#dcfce7' : '#f1f5f9',
                                                color: run.status === 'Processed' ? '#166534' : '#475569'
                                            }}>
                                                {run.status || 'Processed'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}

            {/* Payslip Modal */}
            {selectedPayslip && (
                <div style={{ 
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, 
                    background: 'rgba(15, 23, 42, 0.75)', backdropFilter: 'blur(4px)', zIndex: 1100, 
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    padding: '2rem'
                }}>
                    <div style={{ 
                        background: '#fff', 
                        borderRadius: '16px', 
                        width: '100%', 
                        maxWidth: '900px', 
                        maxHeight: '90vh',
                        boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)', 
                        display: 'flex', 
                        flexDirection: 'column', 
                        overflow: 'hidden'
                    }}>
                        <div style={{ padding: '1.5rem 2rem', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc' }}>
                            <h3 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 'bold', color: 'var(--color-charcoal)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <FileText size={20} style={{ color: 'var(--color-primary)' }} /> Payslip Preview
                            </h3>
                            <div style={{ display: 'flex', gap: '12px' }}>
                                <button 
                                    className="btn btn-outline" 
                                    onClick={handlePrint}
                                    style={{ display: 'flex', alignItems: 'center', gap: '8px' }}
                                >
                                    <Printer size={16} /> Print
                                </button>
                                <button 
                                    className="btn btn-primary" 
                                    onClick={handleDownloadPdf}
                                    style={{ display: 'flex', alignItems: 'center', gap: '8px', border: 'none' }}
                                >
                                    <Download size={16} /> Download PDF
                                </button>
                                <button 
                                    onClick={() => setSelectedPayslip(null)} 
                                    style={{ background: '#e2e8f0', border: 'none', cursor: 'pointer', color: '#475569', width: '36px', height: '36px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', transition: 'background 0.2s' }}
                                    onMouseOver={(e) => e.currentTarget.style.background = '#cbd5e1'}
                                    onMouseOut={(e) => e.currentTarget.style.background = '#e2e8f0'}
                                >
                                    <X size={18} />
                                </button>
                            </div>
                        </div>
                        <div style={{ flex: 1, overflowY: 'auto', background: '#94a3b8', padding: '2rem', display: 'flex', justifyContent: 'center' }}>
                            <div style={{ boxShadow: '0 10px 25px rgba(0,0,0,0.1)', background: '#fff' }} ref={payslipRef}>
                                <GenericPayslipTemplate 
                                    data={selectedPayslip} 
                                    companyLogo={companyLogo} 
                                    companyName={companies.find(c => c.id == selectedRun?.company_id)?.name}
                                    companyData={companies.find(c => c.id == (selectedRun?.company_id || companyId))}
                                />
                            </div>
                        </div>
                    </div>
                </div>
            )}
            
            {/* Edit Record Modal */}
            {editingRecord && (
                <div style={{ 
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, 
                    background: 'rgba(15, 23, 42, 0.75)', backdropFilter: 'blur(4px)', zIndex: 1200, 
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    padding: '2rem'
                }}>
                    <div style={{ 
                        background: '#fff', 
                        borderRadius: '12px', 
                        width: '100%', 
                        maxWidth: '400px', 
                        maxHeight: '90vh',
                        boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1)', 
                        display: 'flex', 
                        flexDirection: 'column', 
                        overflowY: 'auto'
                    }}>
                        <div style={{ padding: '1.5rem', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <h3 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 'bold', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                Edit Salary Heads
                            </h3>
                            <button 
                                onClick={() => setEditingRecord(null)} 
                                style={{ background: 'none', border: 'none', cursor: 'pointer', color: '#64748b' }}
                            >
                                <X size={20} />
                            </button>
                        </div>
                        <div style={{ padding: '1.5rem', display: 'flex', flexDirection: 'column', gap: '16px' }}>
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '8px', fontSize: '13px', color: '#475569' }}>
                                <strong>{editingRecord.first_name} {editingRecord.last_name}</strong><br/>
                                Changes here will recalculate PAYE and NSSF automatically.
                            </div>
                            
                            <div>
                                {editForm.earnings && editForm.earnings.length > 0 ? (
                                    <>
                                        <h4 style={{ fontSize: '13px', margin: '0 0 10px', color: '#64748b', textTransform: 'uppercase' }}>Earnings</h4>
                                        {editForm.earnings.map((earning, index) => {
                                            const isAutoCalculated = earning.computation_method === 'FORMULA' || earning.computation_method === 'PERCENTAGE' || earning.computation_type === 'FORMULA' || earning.computation_type === 'PERCENTAGE';
                                            return (
                                            <div key={`e-${index}`} style={{ marginBottom: '10px' }}>
                                                <label style={{ display: 'block', marginBottom: '6px', fontWeight: '500', fontSize: '13px', color: '#334155' }}>
                                                    {earning.name} ({currentCurrency}) {earning.is_non_taxable ? <span style={{ color: '#0369a1', fontSize: '11px' }}>(Non-Taxable)</span> : ''} {isAutoCalculated && <span style={{ color: '#94a3b8', fontSize: '11px', fontStyle: 'italic' }}> - Auto-Calculated</span>}
                                                </label>
                                                <input 
                                                    type="number" 
                                                    className="form-input" 
                                                    style={{ width: '100%', padding: '10px', borderRadius: '6px', border: '1px solid #cbd5e1', background: isAutoCalculated ? '#f1f5f9' : '#fff', cursor: isAutoCalculated ? 'not-allowed' : 'text' }}
                                                    value={earning.amount}
                                                    disabled={isAutoCalculated}
                                                    onChange={(e) => {
                                                        const newEarnings = [...editForm.earnings];
                                                        newEarnings[index].amount = parseFloat(e.target.value) || 0;
                                                        
                                                        // Update legacy basic_pay/commissions/other_earnings for backwards compatibility
                                                        let basic = 0, comm = 0, other = 0;
                                                        newEarnings.forEach(eItem => {
                                                            const nameLower = eItem.name.toLowerCase();
                                                            if (nameLower.includes('basic') || nameLower.includes('base')) basic += eItem.amount;
                                                            else if (nameLower.includes('commission')) comm += eItem.amount;
                                                            else other += eItem.amount;
                                                        });
                                                        setEditForm({...editForm, earnings: newEarnings, basic_pay: basic, commissions: comm, other_earnings: other});
                                                    }}
                                                />
                                            </div>
                                        )})}
                                    </>
                                ) : (
                                    <>
                                        <div style={{ marginBottom: '10px' }}>
                                            <label style={{ display: 'block', marginBottom: '6px', fontWeight: '500', fontSize: '13px', color: '#334155' }}>Basic Pay ({currentCurrency})</label>
                                            <input 
                                                type="number" 
                                                className="form-input" 
                                                style={{ width: '100%', padding: '10px', borderRadius: '6px', border: '1px solid #cbd5e1' }}
                                                value={editForm.basic_pay}
                                                onChange={(e) => setEditForm({...editForm, basic_pay: e.target.value})}
                                            />
                                        </div>
                                        <div style={{ marginBottom: '10px' }}>
                                            <label style={{ display: 'block', marginBottom: '6px', fontWeight: '500', fontSize: '13px', color: '#334155' }}>Commissions / Bonus ({currentCurrency})</label>
                                            <input 
                                                type="number" 
                                                className="form-input" 
                                                style={{ width: '100%', padding: '10px', borderRadius: '6px', border: '1px solid #cbd5e1' }}
                                                value={editForm.commissions}
                                                onChange={(e) => setEditForm({...editForm, commissions: e.target.value})}
                                            />
                                        </div>
                                        <div style={{ marginBottom: '10px' }}>
                                            <label style={{ display: 'block', marginBottom: '6px', fontWeight: '500', fontSize: '13px', color: '#334155' }}>Other Earnings ({currentCurrency})</label>
                                            <input 
                                                type="number" 
                                                className="form-input" 
                                                style={{ width: '100%', padding: '10px', borderRadius: '6px', border: '1px solid #cbd5e1' }}
                                                value={editForm.other_earnings}
                                                onChange={(e) => setEditForm({...editForm, other_earnings: e.target.value})}
                                            />
                                        </div>
                                    </>
                                )}
                                
                                {editForm.deductions && editForm.deductions.length > 0 && (
                                    <>
                                        <h4 style={{ fontSize: '13px', margin: '16px 0 10px', color: '#64748b', textTransform: 'uppercase' }}>Other Deductions</h4>
                                        {editForm.deductions.map((deduction, index) => {
                                            const isAutoCalculated = deduction.computation_method === 'FORMULA' || deduction.computation_method === 'PERCENTAGE' || deduction.computation_type === 'FORMULA' || deduction.computation_type === 'PERCENTAGE';
                                            return (
                                            <div key={`d-${index}`} style={{ marginBottom: '10px' }}>
                                                <label style={{ display: 'block', marginBottom: '6px', fontWeight: '500', fontSize: '13px', color: '#334155' }}>
                                                    {deduction.name} ({currentCurrency}) {isAutoCalculated && <span style={{ color: '#94a3b8', fontSize: '11px', fontStyle: 'italic' }}> - Auto-Calculated</span>}
                                                </label>
                                                <input 
                                                    type="number" 
                                                    className="form-input" 
                                                    style={{ width: '100%', padding: '10px', borderRadius: '6px', border: '1px solid #cbd5e1', background: isAutoCalculated ? '#f1f5f9' : '#fff', cursor: isAutoCalculated ? 'not-allowed' : 'text' }}
                                                    value={deduction.amount}
                                                    disabled={isAutoCalculated}
                                                    onChange={(e) => {
                                                        const newDeductions = [...editForm.deductions];
                                                        newDeductions[index].amount = parseFloat(e.target.value) || 0;
                                                        setEditForm({...editForm, deductions: newDeductions});
                                                    }}
                                                />
                                            </div>
                                        )})}
                                    </>
                                )}
                                
                                <div style={{ marginBottom: '10px', marginTop: '16px' }}>
                                    <h4 style={{ fontSize: '13px', margin: '0 0 10px', color: '#64748b', textTransform: 'uppercase' }}>Salary Advance</h4>
                                    <label style={{ display: 'block', marginBottom: '6px', fontWeight: '500', fontSize: '13px', color: '#334155' }}>
                                        Deduction Amount ({currentCurrency})
                                    </label>
                                    <input 
                                        type="number" 
                                        className="form-input" 
                                        style={{ width: '100%', padding: '10px', borderRadius: '6px', border: '1px solid #cbd5e1' }}
                                        value={editForm.advance_deductions !== undefined ? editForm.advance_deductions : (editingRecord.advance_deductions || 0)}
                                        onChange={(e) => {
                                            setEditForm({...editForm, advance_deductions: parseFloat(e.target.value) || 0});
                                        }}
                                    />
                                </div>
                            </div>
                        </div>
                        <div style={{ padding: '1rem 1.5rem', background: '#f8fafc', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'flex-end', gap: '12px' }}>
                            <button className="btn btn-outline" onClick={() => setEditingRecord(null)}>Cancel</button>
                            <button 
                                className="btn btn-primary" 
                                onClick={handleSaveRecord} 
                                disabled={savingRecord}
                                style={{ border: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                            >
                                {savingRecord ? <Loader size={14} className="spin" /> : <CheckCircle2 size={14} />}
                                Save & Recalculate
                            </button>
                        </div>
                    </div>
                </div>
            )}
            {/* Preview Modal */}
            {showPreviewModal && (
                <div style={{ 
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, 
                    background: 'rgba(15, 23, 42, 0.75)', backdropFilter: 'blur(4px)', zIndex: 1200, 
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    padding: '2rem'
                }}>
                    <div style={{ 
                        background: '#fff', 
                        borderRadius: '16px', 
                        width: '100%', 
                        maxWidth: '900px', 
                        maxHeight: '90vh',
                        boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)', 
                        display: 'flex', 
                        flexDirection: 'column', 
                        overflow: 'hidden'
                    }}>
                        <div style={{ padding: '1.5rem 2rem', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc' }}>
                            <h3 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 'bold', color: 'var(--color-charcoal)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <FileText size={20} style={{ color: 'var(--color-primary)' }} /> Payroll Preview
                            </h3>
                            <button 
                                onClick={() => setShowPreviewModal(false)} 
                                style={{ background: '#e2e8f0', border: 'none', cursor: 'pointer', color: '#475569', width: '36px', height: '36px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', transition: 'background 0.2s' }}
                                onMouseOver={(e) => e.currentTarget.style.background = '#cbd5e1'}
                                onMouseOut={(e) => e.currentTarget.style.background = '#e2e8f0'}
                            >
                                <X size={18} />
                            </button>
                        </div>
                        
                        <div style={{ flex: 1, overflowY: 'auto', padding: '0' }}>
                            {previewRecords.length === 0 ? (
                                <div style={{ padding: '40px', textAlign: 'center', color: '#94a3b8' }}>
                                    <p>No eligible employees found for this month.</p>
                                </div>
                            ) : (() => {
                                const dynamicEarnings = new Set();
                                const dynamicDeductions = new Set();
                                previewRecords.forEach(record => {
                                    if (record.earnings_json) {
                                        record.earnings_json.forEach(e => {
                                            if (e.amount > 0) dynamicEarnings.add(e.name);
                                        });
                                    }
                                    if (record.deductions_json) {
                                        record.deductions_json.forEach(d => {
                                            if (d.amount > 0) dynamicDeductions.add(d.name);
                                        });
                                    }
                                });
                                const earningCols = Array.from(dynamicEarnings);
                                const deductionCols = Array.from(dynamicDeductions);
                                return (
                                <div style={{ width: '100%', overflowX: 'auto' }}>
                                <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', minWidth: '1000px' }}>
                                    <thead style={{ position: 'sticky', top: 0, background: '#f8fafc', zIndex: 1 }}>
                                        <tr>
                                            <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0', position: 'sticky', left: 0, background: '#f8fafc', zIndex: 2 }}>
                                                Include
                                            </th>
                                            <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0', position: 'sticky', left: '80px', background: '#f8fafc', zIndex: 2 }}>Employee</th>
                                            {earningCols.map(col => (
                                                <th key={`h_earn_${col}`} style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0' }}>{col} ({currentCurrency})</th>
                                            ))}
                                            <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0', background: '#f1f5f9' }}>Gross Pay ({currentCurrency})</th>
                                            <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0', background: '#f1f5f9' }}>Gross Pay ({reportingCurrency || 'Reporting'})</th>
                                            {isUganda && <>
                                                <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0' }}>PAYE ({currentCurrency})</th>
                                            <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0' }}>NSSF ({currentCurrency})</th>
                                            </>}
                                            {deductionCols.map(col => (
                                                <th key={`h_ded_${col}`} style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0' }}>{col} ({currentCurrency})</th>
                                            ))}
                                            <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0', background: '#f1f5f9' }}>Net Pay ({currentCurrency})</th>
                                            <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0', background: '#f1f5f9' }}>Net Pay ({reportingCurrency || 'Reporting'})</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {previewRecords.map((record, index) => (
                                            <tr key={record.employee_id} style={{ borderBottom: '1px solid #f1f5f9', opacity: record.selected ? 1 : 0.5 }}>
                                                <td style={{ padding: '12px 16px', position: 'sticky', left: 0, background: '#fff', zIndex: 1, borderRight: '1px solid #f1f5f9' }}>
                                                    <input 
                                                        type="checkbox" 
                                                        checked={record.selected} 
                                                        onChange={(e) => {
                                                            const newRecords = [...previewRecords];
                                                            newRecords[index].selected = e.target.checked;
                                                            setPreviewRecords(newRecords);
                                                        }}
                                                        style={{ width: '16px', height: '16px', cursor: 'pointer' }}
                                                    />
                                                </td>
                                                <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: '500', color: '#334155', position: 'sticky', left: '80px', background: '#fff', zIndex: 1, borderRight: '1px solid #f1f5f9' }}>
                                                    {record.first_name} {record.last_name}
                                                </td>
                                                {earningCols.map(col => {
                                                    const e = record.earnings_json?.find(x => x.name === col);
                                                    return (
                                                        <td key={`r_earn_${col}`} style={{ padding: '12px 16px', fontSize: '14px', color: '#334155' }}>
                                                            <div>{renderCurrency(e ? e.amount : 0)}</div>
                                                            
                                                        </td>
                                                    );
                                                })}
                                                <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: '600', color: '#334155', background: '#f8fafc' }}>
                                                    <div>{renderCurrency((parseFloat(record.basic_pay || 0) + parseFloat(record.commissions || 0) + parseFloat(record.other_earnings || 0)))}</div>
                                                    
                                                </td>
                                                <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: '600', color: '#3b82f6', background: '#f8fafc' }}>
                                                    {reportingCurrency && exchangeRate ? (
                                                        <span>{((parseFloat(record.basic_pay || 0) + parseFloat(record.commissions || 0) + parseFloat(record.other_earnings || 0)) / exchangeRate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                    ) : '-'}
                                                </td>
                                                {isUganda && <>
                                                  <td style={{ padding: '12px 16px', fontSize: '14px', color: '#ef4444' }}>
                                                    <div>{renderCurrency(record.paye_deduction)}</div>
                                                    
                                                </td>
                                                <td style={{ padding: '12px 16px', fontSize: '14px', color: '#ef4444' }}>
                                                    <div>{renderCurrency(record.nssf_employee_deduction)}</div>
                                                    
                                                </td>
                                                  </>}
                                                {deductionCols.map(col => {
                                                    const d = record.deductions_json?.find(x => x.name === col);
                                                    return (
                                                        <td key={`r_ded_${col}`} style={{ padding: '12px 16px', fontSize: '14px', color: '#ef4444' }}>
                                                            <div>{renderCurrency(d ? d.amount : 0)}</div>
                                                            
                                                        </td>
                                                    );
                                                })}
                                                <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: '600', color: '#10b981', background: '#f8fafc' }}>
                                                    <div>{renderCurrency(record.net_pay)}</div>
                                                    
                                                </td>
                                                <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: '600', color: '#3b82f6', background: '#f8fafc' }}>
                                                    {reportingCurrency && exchangeRate ? (
                                                        <span>{(record.net_pay / exchangeRate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                    ) : '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot style={{ position: 'sticky', bottom: 0, background: '#f8fafc', zIndex: 1, borderTop: '2px solid #cbd5e1', boxShadow: '0 -2px 4px rgba(0,0,0,0.05)' }}>
                                        <tr>
                                            <td colSpan="2" style={{ padding: '12px 16px', fontSize: '13px', fontWeight: 'bold', color: '#334155', position: 'sticky', left: 0, background: '#f8fafc', zIndex: 2 }}>
                                                Total (Selected)
                                            </td>
                                            {earningCols.map(col => {
                                                const total = previewRecords.filter(r => r.selected).reduce((sum, r) => {
                                                    const e = r.earnings_json?.find(x => x.name === col);
                                                    return sum + (e ? e.amount : 0);
                                                }, 0);
                                                return <td key={`t_earn_${col}`} style={{ padding: '12px 16px', fontSize: '14px', fontWeight: 'bold', color: '#334155' }}>{renderCurrency(total)}</td>;
                                            })}
                                            <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: 'bold', color: '#334155' }}>
                                                {renderCurrency(previewRecords.filter(r => r.selected).reduce((sum, r) => sum + (parseFloat(r.basic_pay || 0) + parseFloat(r.commissions || 0) + parseFloat(r.other_earnings || 0)), 0))}
                                            </td>
                                            <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: 'bold', color: '#3b82f6' }}>
                                                {reportingCurrency && exchangeRate ? (
                                                    <span>{(previewRecords.filter(r => r.selected).reduce((sum, r) => sum + (parseFloat(r.basic_pay || 0) + parseFloat(r.commissions || 0) + parseFloat(r.other_earnings || 0)), 0) / exchangeRate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                ) : '-'}
                                            </td>
                                            {isUganda && <>
                                              <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: 'bold', color: '#ef4444' }}>
                                                {renderCurrency(previewRecords.filter(r => r.selected).reduce((sum, r) => sum + r.paye_deduction, 0))}
                                            </td>
                                            <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: 'bold', color: '#ef4444' }}>
                                                {renderCurrency(previewRecords.filter(r => r.selected).reduce((sum, r) => sum + r.nssf_employee_deduction, 0))}
                                            </td>
                                              </>}
                                            {deductionCols.map(col => {
                                                const total = previewRecords.filter(r => r.selected).reduce((sum, r) => {
                                                    const d = r.deductions_json?.find(x => x.name === col);
                                                    return sum + (d ? d.amount : 0);
                                                }, 0);
                                                return <td key={`t_ded_${col}`} style={{ padding: '12px 16px', fontSize: '14px', fontWeight: 'bold', color: '#ef4444' }}>{renderCurrency(total)}</td>;
                                            })}
                                            <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: 'bold', color: '#10b981' }}>
                                                {renderCurrency(previewRecords.filter(r => r.selected).reduce((sum, r) => sum + r.net_pay, 0))}
                                            </td>
                                            <td style={{ padding: '12px 16px', fontSize: '14px', fontWeight: 'bold', color: '#3b82f6' }}>
                                                {reportingCurrency && exchangeRate ? (
                                                    <span>{(previewRecords.filter(r => r.selected).reduce((sum, r) => sum + r.net_pay, 0) / exchangeRate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                                ) : '-'}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                                </div>
                                );
                            })()}
                        </div>

                        <div style={{ padding: '1rem 1.5rem', background: '#f8fafc', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <div style={{ fontSize: '14px', color: '#475569', fontWeight: '500' }}>
                                Selected: {previewRecords.filter(r => r.selected).length} / {previewRecords.length} Employees
                            </div>
                            <div style={{ display: 'flex', gap: '12px' }}>
                                <button className="btn btn-outline" onClick={() => setShowPreviewModal(false)}>Cancel</button>
                                <button 
                                    className="btn btn-primary" 
                                    onClick={handleConfirmGenerate} 
                                    disabled={generating || previewRecords.filter(r => r.selected).length === 0}
                                    style={{ border: 'none', display: 'flex', alignItems: 'center', gap: '8px' }}
                                >
                                    {generating ? <Loader size={14} className="spin" /> : <Play size={14} />}
                                    {generating ? 'Generating...' : 'Confirm & Generate'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
            </>
            )}
            {/* Hidden component for silent PDF generation */}
            <div style={{ position: 'absolute', top: '-9999px', left: '-9999px', zIndex: -1 }}>
                <div ref={hiddenPayslipRef}>
                    {processingRecordData && (
                        <GenericPayslipTemplate 
                            data={processingRecordData} 
                            companyLogo={companies.find(c => c.id == selectedRun?.company_id)?.logo_url} 
                            companyName={companies.find(c => c.id == selectedRun?.company_id)?.name}
                            companyData={companies.find(c => c.id == (selectedRun?.company_id || companyId))}
                        />
                    )}
                </div>
            </div>
        </div>
    );
};

export default Payroll;
