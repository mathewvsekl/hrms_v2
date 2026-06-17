import { getSecureMediaUrl } from '../utils/mediaHelper';
import { useState, useEffect } from 'react';
import { Upload, FileText, User, Trash2, Download, Building, Loader, Search, Plus, X, Globe, ChevronDown, ChevronRight } from 'lucide-react';
import api from '../services/api';
import useNotificationStore from '../store/useNotificationStore';
import useLayoutStore from '../store/useLayoutStore';
import { formatDate } from '../utils/dateUtils';
import EmployeePayslips from './EmployeePayslips';

const renderFlag = (country) => {
    if (!country) return <span>🌐</span>;
    const name = country.name?.toLowerCase() || '';
    const iso = country.iso_code?.toUpperCase() || '';

    let code = '';
    if (iso === 'ARE' || name.includes('emirates')) code = 'ae';
    else if (iso === 'IND' || name.includes('india')) code = 'in';
    else if (iso === 'UGA' || name.includes('uganda')) code = 'ug';
    else if (iso === 'KEN' || name.includes('kenya')) code = 'ke';
    else if (iso === 'TZA' || name.includes('tanzania')) code = 'tz';
    else if (iso === 'GBR' || name.includes('united kingdom')) code = 'gb';
    else if (iso === 'USA' || name.includes('united states')) code = 'us';
    else if (iso === 'BGD' || name.includes('bangladesh')) code = 'bd';
    else if (iso === 'PAK' || name.includes('pakistan')) code = 'pk';
    else if (iso === 'PHL' || name.includes('philippines')) code = 'ph';
    else if (iso.length === 2) code = iso.toLowerCase();
    else if (iso.length === 3) code = iso.toLowerCase().slice(0, 2);

    if (!code) return <span>🏳️</span>;

    return (
        <img
            src={`https://flagcdn.com/w40/${code}.png`}
            srcSet={`https://flagcdn.com/w80/${code}.png 2x`}
            width="20"
            style={{
                borderRadius: '3px',
                boxShadow: '0 1px 2px rgba(0,0,0,0.1)',
                display: 'block'
            }}
            alt={country.name}
            onError={(e) => { e.target.style.display = 'none'; e.target.nextSibling.style.display = 'inline'; }}
        />
    );
};

const Payslips = () => {
    const [countries, setCountries] = useState([]);
    const [companies, setCompanies] = useState([]);
    const [employees, setEmployees] = useState([]);
    const [allPayslips, setAllPayslips] = useState([]);
    
    // Main Page State
    const [loading, setLoading] = useState(false);
    const [globalSearchTerm, setGlobalSearchTerm] = useState('');
    const [activeCountry, setActiveCountry] = useState('Global');
    
    // Modal State
    const [showUploadModal, setShowUploadModal] = useState(false);
    const [selectedCompany, setSelectedCompany] = useState('');
    const [selectedEmployee, setSelectedEmployee] = useState('');
    const [employeeSearchTerm, setEmployeeSearchTerm] = useState('');
    const [month, setMonth] = useState(new Date().getMonth() + 1);
    const [year, setYear] = useState(new Date().getFullYear());
    const [file, setFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    
    const { showAlert, showConfirm } = useNotificationStore();
    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();

    const viewMode = localStorage.getItem('adminViewMode') || 'admin';

    if (viewMode === 'employee') {
        return <EmployeePayslips />;
    }

    useEffect(() => {
        setPageTitle('Payslips');
        setPageSubtitle('Manage and upload employee payslips');
        fetchInitialData();
        fetchAllPayslips();
        return () => resetPageHeader();
    }, []);

    const fetchInitialData = async () => {
        try {
            const [countriesRes, companiesRes, employeesRes] = await Promise.all([
                api.get('/organization/countries').catch(() => ({ data: [] })),
                api.get('/organization/companies').catch(() => ({ data: [] })),
                api.get('/employees').catch(() => ({ data: [] }))
            ]);
            
            setCountries(countriesRes.data?.data || countriesRes.data || []);
            setCompanies(companiesRes.data?.data || companiesRes.data || []);
            setEmployees(employeesRes.data?.data || employeesRes.data || []);
        } catch (error) {
            console.error('Failed to fetch initial data:', error);
        }
    };

    const fetchAllPayslips = async () => {
        try {
            setLoading(true);
            const res = await api.get('/payslips');
            setAllPayslips(res.data?.data || res.data || []);
        } catch (error) {
            console.error('Failed to fetch payslips:', error);
            showAlert('Error', 'Failed to load payslips.', 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleClearForm = () => {
        setSelectedCompany('');
        setSelectedEmployee('');
        setEmployeeSearchTerm('');
        setFile(null);
        setMonth(new Date().getMonth() + 1);
        setYear(new Date().getFullYear());
    };

    const handleCloseModal = () => {
        setShowUploadModal(false);
        handleClearForm();
    };

    const handleUpload = async (e) => {
        e.preventDefault();
        if (!selectedEmployee || !file) {
            showAlert('Required', 'Please select an employee and a file', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('document', file);
        formData.append('employee_id', selectedEmployee);
        formData.append('month', month);
        formData.append('year', year);

        try {
            setUploading(true);
            await api.post('/payslips', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            showAlert('Success', 'Payslip uploaded successfully', 'success');
            
            // Reset modal state
            handleCloseModal();
            
            // Refresh list
            fetchAllPayslips();
        } catch (err) {
            console.error(err);
            showAlert('Error', err.response?.data?.message || 'Failed to upload payslip', 'error');
        } finally {
            setUploading(false);
        }
    };

    const handleDelete = (id) => {
        showConfirm(
            'Delete Payslip',
            'Are you sure you want to delete this payslip? This action cannot be undone.',
            async () => {
                try {
                    await api.delete(`/payslips/${id}`);
                    showAlert('Success', 'Payslip deleted successfully', 'success');
                    fetchAllPayslips();
                } catch (err) {
                    console.error(err);
                    showAlert('Error', 'Failed to delete payslip', 'error');
                }
            }
        );
    };

    const getMonthName = (monthNumber) => {
        const date = new Date();
        date.setMonth(monthNumber - 1);
        return date.toLocaleString('default', { month: 'long' });
    };

    // Preview Modal State
    const [previewFile, setPreviewFile] = useState(null);

    // Accordion State
    const [expandedGroups, setExpandedGroups] = useState({});

    const toggleGroup = (groupKey) => {
        setExpandedGroups(prev => ({
            ...prev,
            [groupKey]: !prev[groupKey]
        }));
    };

    // Derived Data for Main View
    const displayedPayslips = allPayslips.filter(ps => {
        // Search Filter
        const search = globalSearchTerm.toLowerCase();
        if (search) {
            const fullName = `${ps.first_name || ''} ${ps.last_name || ''}`.toLowerCase();
            const code = (ps.employee_code || '').toLowerCase();
            if (!fullName.includes(search) && !code.includes(search)) return false;
        }
        
        // Country Filter
        if (activeCountry !== 'Global') {
            const compId = ps.company_id;
            if (compId) {
                const comp = companies.find(c => c.id == compId);
                if (comp && comp.country_id != activeCountry) return false;
            } else {
                // fallback for legacy payslips
                const emp = employees.find(e => e.id == ps.employee_id);
                if (emp) {
                    const comp = companies.find(c => c.id == emp.primary_company_id);
                    if (comp && comp.country_id != activeCountry) return false;
                }
            }
        }
        
        return true;
    });

    // Group payslips by Company, Year and Month
    const groupedPayslips = displayedPayslips.reduce((acc, ps) => {
        const compName = ps.company_name || (ps.company_id ? companies.find(c => c.id == ps.company_id)?.name : null) || 'Legacy / Unknown Company';
        const key = `${compName} - ${getMonthName(ps.month)} ${ps.year}`;
        if (!acc[key]) {
            acc[key] = [];
        }
        acc[key].push(ps);
        return acc;
    }, {});

    // Sort the groups (alphabetical by company, then newest month/year first)
    const sortedGroupKeys = Object.keys(groupedPayslips).sort((a, b) => {
        const [compA, datePartA] = a.split(' - ');
        const [compB, datePartB] = b.split(' - ');
        
        if (compA !== compB) return compA.localeCompare(compB);
        
        const [monthAStr, yearAStr] = datePartA.split(' ');
        const [monthBStr, yearBStr] = datePartB.split(' ');
        const dateA = new Date(`${monthAStr} 1, ${yearAStr}`);
        const dateB = new Date(`${monthBStr} 1, ${yearBStr}`);
        return dateB - dateA;
    });

    // Derived Data for Modal
    const filteredEmployeesForModal = employees.filter(e => {
        if (!selectedCompany) return false;
        if (e.primary_company_id != selectedCompany) return false;
        
        if (employeeSearchTerm) {
            const search = employeeSearchTerm.toLowerCase();
            if (!(
                (e.first_name + ' ' + e.last_name).toLowerCase().includes(search) ||
                (e.employee_code || '').toLowerCase().includes(search)
            )) {
                return false;
            }
        }
        return true;
    });

    return (
        <div>
            {/* Actions Bar */}
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '24px' }}>
                <button className="btn btn-primary" onClick={() => setShowUploadModal(true)}>
                    <Plus size={18} /> Add Payslip
                </button>
            </div>

            {/* Country Chips */}
            <div style={{ display: 'flex', gap: '12px', marginBottom: '32px', overflowX: 'auto', paddingBottom: '8px' }}>
                <button 
                    onClick={() => setActiveCountry('Global')}
                    style={{
                        padding: '8px 20px',
                        borderRadius: '20px',
                        border: 'none',
                        background: activeCountry === 'Global' ? '#065f46' : 'white',
                        color: activeCountry === 'Global' ? 'white' : 'var(--text-main)',
                        fontWeight: '600',
                        fontSize: '14px',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        boxShadow: activeCountry === 'Global' ? 'none' : '0 1px 3px rgba(0,0,0,0.1)',
                        transition: 'all 0.2s'
                    }}
                >
                    <Globe size={16} /> Global
                </button>
                {countries.map(country => (
                    <button 
                        key={country.id}
                        onClick={() => setActiveCountry(country.id)}
                        style={{
                            padding: '8px 20px',
                            borderRadius: '20px',
                            border: 'none',
                            background: activeCountry === country.id ? '#065f46' : 'white',
                            color: activeCountry === country.id ? 'white' : 'var(--text-main)',
                            fontWeight: '600',
                            fontSize: '14px',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            whiteSpace: 'nowrap',
                            boxShadow: activeCountry === country.id ? 'none' : '0 1px 3px rgba(0,0,0,0.1)',
                            transition: 'all 0.2s'
                        }}
                    >
                        {renderFlag(country)}
                        <span>{country.name}</span>
                    </button>
                ))}
            </div>

            {/* Search and List */}
            <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                <div style={{ background: '#fff', borderRadius: '12px', padding: '16px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)', display: 'flex', gap: '1rem' }}>
                    <div style={{ position: 'relative', flex: 1, maxWidth: '400px' }}>
                        <Search size={18} style={{ position: 'absolute', left: '14px', top: '11px', color: '#94a3b8' }} />
                        <input 
                            type="text" 
                            className="form-input"
                            placeholder="Search by name, email, or ID..." 
                            value={globalSearchTerm}
                            onChange={(e) => setGlobalSearchTerm(e.target.value)}
                            style={{ paddingLeft: '40px', borderRadius: '8px', border: '1px solid #e2e8f0' }}
                        />
                    </div>
                </div>

                {loading ? (
                    <div style={{ padding: '4rem', textAlign: 'center', color: '#94a3b8', background: '#fff', borderRadius: '12px' }}>
                        <Loader className="spin" size={32} style={{ margin: '0 auto 1rem' }} />
                        <p>Loading payslips...</p>
                    </div>
                ) : sortedGroupKeys.length === 0 ? (
                    <div style={{ padding: '4rem', textAlign: 'center', color: '#94a3b8', background: '#fff', borderRadius: '12px' }}>
                        <FileText size={48} style={{ margin: '0 auto 1rem', opacity: 0.5 }} />
                        <p style={{ fontSize: '1.1rem', margin: 0, color: '#64748b' }}>No payslips found.</p>
                        <p style={{ fontSize: '0.9rem', marginTop: '8px' }}>Upload a new payslip to get started.</p>
                    </div>
                ) : (
                    sortedGroupKeys.map((groupKey) => (
                        <div key={groupKey} className="section-card" style={{ background: '#fff', borderRadius: '12px', boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)', overflow: 'hidden' }}>
                            <div 
                                onClick={() => toggleGroup(groupKey)}
                                style={{ background: '#f8fafc', padding: '16px 24px', borderBottom: expandedGroups[groupKey] ? '1px solid #e2e8f0' : 'none', display: 'flex', alignItems: 'center', gap: '12px', cursor: 'pointer' }}
                            >
                                {expandedGroups[groupKey] ? <ChevronDown size={20} style={{ color: '#64748b' }} /> : <ChevronRight size={20} style={{ color: '#64748b' }} />}
                                <FileText size={20} style={{ color: 'var(--color-primary)' }} />
                                <h2 style={{ margin: 0, fontSize: '1.1rem', fontWeight: 'bold', color: 'var(--color-charcoal)' }}>{groupKey}</h2>
                                <span style={{ background: '#e2e8f0', color: '#475569', padding: '4px 10px', borderRadius: '20px', fontSize: '12px', fontWeight: '600' }}>
                                    {groupedPayslips[groupKey].length} {groupedPayslips[groupKey].length === 1 ? 'payslip' : 'payslips'}
                                </span>
                            </div>
                            {expandedGroups[groupKey] && (
                            <div style={{ overflowX: 'auto' }}>
                                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                                    <thead>
                                        <tr style={{ background: '#fff', borderBottom: '1px solid #e2e8f0' }}>
                                            <th style={{ padding: '12px 1.5rem', textAlign: 'left', fontSize: '13px', color: '#64748b', fontWeight: '600' }}>Employee</th>
                                            <th style={{ padding: '12px 1.5rem', textAlign: 'left', fontSize: '13px', color: '#64748b', fontWeight: '600' }}>Uploaded Date</th>
                                            <th style={{ padding: '12px 1.5rem', textAlign: 'right', fontSize: '13px', color: '#64748b', fontWeight: '600' }}>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {groupedPayslips[groupKey].map(ps => (
                                            <tr key={ps.id} style={{ borderBottom: '1px solid #f1f5f9', transition: 'background 0.2s' }}>
                                                <td style={{ padding: '12px 1.5rem' }}>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                        <div style={{ width: '36px', height: '36px', borderRadius: '50%', background: 'var(--color-primary-light, #e0e7ff)', color: 'var(--color-primary)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold', fontSize: '14px' }}>
                                                            {ps.first_name?.[0]}{ps.last_name?.[0]}
                                                        </div>
                                                        <div>
                                                            <div style={{ fontWeight: '500', color: 'var(--color-charcoal)' }}>{ps.first_name} {ps.last_name}</div>
                                                            <div style={{ fontSize: '12px', color: '#94a3b8' }}>{ps.employee_code || 'N/A'}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style={{ padding: '12px 1.5rem', color: '#64748b', fontSize: '14px' }}>
                                                    {formatDate(ps.uploaded_at || ps.created_at || Date.now())}
                                                </td>
                                                <td style={{ padding: '12px 1.5rem', textAlign: 'right' }}>
                                                    <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                                        <button 
                                                            onClick={() => {
                                                                const fullPath = getSecureMediaUrl(ps.file_path);
                                                                setPreviewFile(`${fullPath}#toolbar=0&navpanes=0&scrollbar=0`);
                                                            }}
                                                            className="btn-icon"
                                                            style={{ color: '#475569', padding: '8px', borderRadius: '8px', background: '#f1f5f9', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', fontWeight: '500' }}
                                                            title="Preview"
                                                        >
                                                            Preview
                                                        </button>
                                                        <a 
                                                            href={getSecureMediaUrl(ps.file_path)}
                                                            download 
                                                            className="btn-icon"
                                                            style={{ color: 'var(--color-primary)', padding: '8px', borderRadius: '8px', background: 'rgba(37, 99, 235, 0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                                                            title="Download"
                                                        >
                                                            <Download size={16} />
                                                        </a>
                                                        <button 
                                                            onClick={() => handleDelete(ps.id)}
                                                            className="btn-icon"
                                                            style={{ color: '#ef4444', padding: '8px', borderRadius: '8px', background: 'rgba(239, 68, 68, 0.1)', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                                                            title="Delete"
                                                        >
                                                            <Trash2 size={16} />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            )}
                        </div>
                    ))
                )}
            </div>

            {/* Upload Modal */}
            {showUploadModal && (
                <div style={{ 
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, 
                    background: 'rgba(15, 23, 42, 0.6)', backdropFilter: 'blur(4px)', zIndex: 1000, 
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    padding: '1rem'
                }}>
                    <div style={{ 
                        background: '#fff', 
                        borderRadius: '24px', 
                        width: '100%', 
                        maxWidth: '560px', 
                        boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)', 
                        overflow: 'hidden', 
                        display: 'flex', 
                        flexDirection: 'column', 
                        maxHeight: '90vh' 
                    }}>
                        
                        <div style={{ padding: '2rem 2rem 1.5rem', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <div>
                                <h3 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 'bold', color: 'var(--color-charcoal)' }}>
                                    Upload Payslip
                                </h3>
                                <p style={{ margin: '4px 0 0', fontSize: '13px', color: '#64748b' }}>Select an employee and upload their monthly payslip document.</p>
                            </div>
                            <button 
                                onClick={handleCloseModal} 
                                style={{ background: '#f1f5f9', border: 'none', cursor: 'pointer', color: '#64748b', width: '36px', height: '36px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', transition: 'background 0.2s' }}
                                onMouseOver={(e) => e.currentTarget.style.background = '#e2e8f0'}
                                onMouseOut={(e) => e.currentTarget.style.background = '#f1f5f9'}
                            >
                                <X size={18} />
                            </button>
                        </div>

                        <div style={{ padding: '2rem', overflowY: 'auto', flex: 1 }}>
                            <form id="uploadForm" onSubmit={handleUpload} style={{ display: 'flex', flexDirection: 'column', gap: '1.75rem' }}>
                                
                                {/* Step 1: Employee Selection */}
                                <div style={{ background: '#f8fafc', padding: '1.5rem', borderRadius: '16px', border: '1px solid #e2e8f0' }}>
                                    <h4 style={{ margin: '0 0 1rem 0', fontSize: '14px', fontWeight: '600', color: '#334155', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                        <User size={16} style={{ color: 'var(--color-primary)' }}/> 1. Select Employee
                                    </h4>
                                    
                                    {selectedEmployee ? (
                                        // Selected Employee Card
                                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', background: '#fff', padding: '12px 16px', borderRadius: '12px', border: '1px solid var(--color-primary)', boxShadow: '0 4px 6px -1px rgba(37, 99, 235, 0.1)' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                <div style={{ width: '40px', height: '40px', borderRadius: '50%', background: 'var(--color-primary-light, #e0e7ff)', color: 'var(--color-primary)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold', fontSize: '14px' }}>
                                                    {employees.find(e => e.id === selectedEmployee)?.first_name?.[0]}
                                                    {employees.find(e => e.id === selectedEmployee)?.last_name?.[0]}
                                                </div>
                                                <div>
                                                    <div style={{ fontWeight: '600', color: '#1e293b' }}>
                                                        {employees.find(e => e.id === selectedEmployee)?.first_name} {employees.find(e => e.id === selectedEmployee)?.last_name}
                                                    </div>
                                                    <div style={{ fontSize: '12px', color: '#64748b' }}>
                                                        ID: {employees.find(e => e.id === selectedEmployee)?.employee_code || 'N/A'}
                                                    </div>
                                                </div>
                                            </div>
                                            <button 
                                                type="button"
                                                onClick={() => setSelectedEmployee('')}
                                                style={{ background: 'none', border: 'none', color: '#ef4444', fontSize: '13px', fontWeight: '500', cursor: 'pointer', padding: '6px 12px', borderRadius: '6px' }}
                                                onMouseOver={(e) => e.currentTarget.style.background = '#fee2e2'}
                                                onMouseOut={(e) => e.currentTarget.style.background = 'none'}
                                            >
                                                Change
                                            </button>
                                        </div>
                                    ) : (
                                        // Selection Inputs
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
                                            <div>
                                                <div style={{ position: 'relative' }}>
                                                    <Building size={18} style={{ position: 'absolute', left: '14px', top: '11px', color: '#94a3b8' }} />
                                                    <select 
                                                        className="form-input"
                                                        value={selectedCompany}
                                                        onChange={(e) => {
                                                            setSelectedCompany(e.target.value);
                                                            setSelectedEmployee('');
                                                            setEmployeeSearchTerm('');
                                                        }}
                                                        style={{ paddingLeft: '42px', borderRadius: '10px', height: '42px', border: '1px solid #cbd5e1' }}
                                                    >
                                                        <option value="">-- Choose Company --</option>
                                                        {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                                    </select>
                                                </div>
                                            </div>

                                            {selectedCompany && (
                                                <div>
                                                    <div style={{ position: 'relative', marginBottom: '8px' }}>
                                                        <Search size={18} style={{ position: 'absolute', left: '14px', top: '11px', color: '#94a3b8' }} />
                                                        <input 
                                                            type="text" 
                                                            className="form-input"
                                                            placeholder="Search employee by name or ID..." 
                                                            value={employeeSearchTerm}
                                                            onChange={(e) => setEmployeeSearchTerm(e.target.value)}
                                                            style={{ paddingLeft: '42px', borderRadius: '10px', height: '42px', border: '1px solid #cbd5e1' }}
                                                        />
                                                    </div>
                                                    <div style={{ maxHeight: '160px', overflowY: 'auto', borderRadius: '10px', background: '#fff', border: '1px solid #e2e8f0', boxShadow: '0 1px 3px rgba(0,0,0,0.05)' }}>
                                                        {filteredEmployeesForModal.length === 0 ? (
                                                            <div style={{ padding: '20px', textAlign: 'center', color: '#94a3b8', fontSize: '13px' }}>
                                                                {employeeSearchTerm ? 'No employees match your search.' : 'No employees found for this company.'}
                                                            </div>
                                                        ) : (
                                                            filteredEmployeesForModal.map(e => (
                                                                <div 
                                                                    key={e.id} 
                                                                    onClick={() => setSelectedEmployee(e.id)}
                                                                    style={{ 
                                                                        padding: '10px 16px', 
                                                                        cursor: 'pointer', 
                                                                        borderBottom: '1px solid #f1f5f9',
                                                                        display: 'flex',
                                                                        alignItems: 'center',
                                                                        gap: '12px',
                                                                        transition: 'background 0.2s'
                                                                    }}
                                                                    onMouseOver={(ev) => ev.currentTarget.style.background = '#f8fafc'}
                                                                    onMouseOut={(ev) => ev.currentTarget.style.background = 'transparent'}
                                                                >
                                                                    <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: '#e2e8f0', color: '#64748b', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold', fontSize: '12px' }}>
                                                                        {e.first_name?.[0]}{e.last_name?.[0]}
                                                                    </div>
                                                                    <div style={{ flex: 1, display: 'flex', flexDirection: 'column' }}>
                                                                        <span style={{ fontWeight: '500', fontSize: '14px', color: '#334155' }}>{e.first_name} {e.last_name}</span>
                                                                        <span style={{ fontSize: '12px', color: '#94a3b8' }}>
                                                                            {e.employee_code || 'No ID'}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            ))
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Step 2: Period & File */}
                                <div style={{ opacity: selectedEmployee ? 1 : 0.5, pointerEvents: selectedEmployee ? 'auto' : 'none', transition: 'opacity 0.3s' }}>
                                    <h4 style={{ margin: '0 0 1rem 0', fontSize: '14px', fontWeight: '600', color: '#334155', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                        <FileText size={16} style={{ color: 'var(--color-primary)' }}/> 2. Payslip Details
                                    </h4>
                                    
                                    <div style={{ display: 'flex', gap: '1rem', marginBottom: '1.5rem' }}>
                                        <div style={{ flex: 1 }}>
                                            <label style={{ display: 'block', marginBottom: '6px', fontWeight: '500', fontSize: '13px', color: '#64748b' }}>Month</label>
                                            <select 
                                                className="form-input"
                                                value={month} 
                                                onChange={(e) => setMonth(e.target.value)}
                                                style={{ borderRadius: '10px', height: '42px', border: '1px solid #cbd5e1' }}
                                            >
                                                {Array.from({length: 12}, (_, i) => i + 1).map(m => (
                                                    <option key={m} value={m}>{getMonthName(m)}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div style={{ flex: 1 }}>
                                            <label style={{ display: 'block', marginBottom: '6px', fontWeight: '500', fontSize: '13px', color: '#64748b' }}>Year</label>
                                            <input 
                                                type="number" 
                                                className="form-input"
                                                value={year} 
                                                onChange={(e) => setYear(e.target.value)}
                                                style={{ borderRadius: '10px', height: '42px', border: '1px solid #cbd5e1' }}
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <label style={{ display: 'block', marginBottom: '6px', fontWeight: '500', fontSize: '13px', color: '#64748b' }}>Upload Document (PDF, JPG, PNG)</label>
                                        <div style={{ 
                                            position: 'relative', 
                                            border: '2px dashed #cbd5e1', 
                                            borderRadius: '12px', 
                                            background: '#f8fafc',
                                            padding: '2rem',
                                            textAlign: 'center',
                                            transition: 'all 0.2s',
                                            cursor: 'pointer'
                                        }}
                                        onMouseOver={(e) => { e.currentTarget.style.borderColor = 'var(--color-primary)'; e.currentTarget.style.background = '#f0f9ff'; }}
                                        onMouseOut={(e) => { e.currentTarget.style.borderColor = '#cbd5e1'; e.currentTarget.style.background = '#f8fafc'; }}
                                        >
                                            <input 
                                                type="file" 
                                                onChange={(e) => setFile(e.target.files[0])}
                                                accept=".pdf,.png,.jpg,.jpeg"
                                                style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', opacity: 0, cursor: 'pointer' }}
                                            />
                                            {file ? (
                                                <div style={{ color: 'var(--color-primary)', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px' }}>
                                                    <FileText size={32} />
                                                    <span style={{ fontWeight: '500', fontSize: '14px' }}>{file.name}</span>
                                                    <span style={{ fontSize: '12px', color: '#64748b' }}>{(file.size / 1024 / 1024).toFixed(2)} MB</span>
                                                </div>
                                            ) : (
                                                <div style={{ color: '#94a3b8', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px' }}>
                                                    <Upload size={32} style={{ color: '#cbd5e1' }} />
                                                    <span style={{ fontWeight: '500', fontSize: '14px', color: '#475569' }}>Click or drag file to upload</span>
                                                    <span style={{ fontSize: '12px' }}>Max file size: 5MB</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div style={{ padding: '1.5rem 2rem', background: '#f8fafc', borderTop: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <div style={{ display: 'flex', gap: '8px' }}>
                                <button 
                                    type="button" 
                                    onClick={handleCloseModal}
                                    style={{ background: '#fff', border: '1px solid #cbd5e1', color: '#64748b', fontWeight: '500', cursor: 'pointer', padding: '8px 16px', borderRadius: '8px', transition: 'background 0.2s' }}
                                    onMouseOver={(e) => e.currentTarget.style.background = '#f1f5f9'}
                                    onMouseOut={(e) => e.currentTarget.style.background = '#fff'}
                                >
                                    Close
                                </button>
                                <button 
                                    type="button" 
                                    onClick={handleClearForm}
                                    style={{ background: 'none', border: 'none', color: '#64748b', fontWeight: '500', cursor: 'pointer', padding: '8px 16px', borderRadius: '8px', transition: 'background 0.2s' }}
                                    onMouseOver={(e) => e.currentTarget.style.background = '#e2e8f0'}
                                    onMouseOut={(e) => e.currentTarget.style.background = 'none'}
                                >
                                    Clear
                                </button>
                            </div>
                            <button 
                                type="submit" 
                                form="uploadForm"
                                disabled={uploading || !file || !selectedEmployee}
                                className="btn btn-primary"
                                style={{ display: 'flex', alignItems: 'center', gap: '8px', minWidth: '140px', justifyContent: 'center', padding: '10px 24px', borderRadius: '10px', fontSize: '14px', fontWeight: '600' }}
                            >
                                {uploading ? <Loader className="spin" size={16} /> : <Upload size={16} />} 
                                {uploading ? 'Uploading...' : 'Confirm Upload'}
                            </button>
                        </div>

                    </div>
                </div>
            )}

            {/* Preview Modal */}
            {previewFile && (
                <div style={{ 
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, 
                    background: 'rgba(15, 23, 42, 0.75)', backdropFilter: 'blur(4px)', zIndex: 1100, 
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    padding: '2rem'
                }}>
                    <div style={{ 
                        background: '#fff', 
                        borderRadius: '24px', 
                        width: '100%', 
                        maxWidth: '900px', 
                        height: '85vh',
                        boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)', 
                        display: 'flex', 
                        flexDirection: 'column', 
                        overflow: 'hidden'
                    }}>
                        <div style={{ padding: '1.5rem 2rem', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc' }}>
                            <h3 style={{ margin: 0, fontSize: '1.25rem', fontWeight: 'bold', color: 'var(--color-charcoal)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <FileText size={20} style={{ color: 'var(--color-primary)' }} /> Document Preview
                            </h3>
                            <button 
                                onClick={() => setPreviewFile(null)} 
                                style={{ background: '#e2e8f0', border: 'none', cursor: 'pointer', color: '#475569', width: '36px', height: '36px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', transition: 'background 0.2s' }}
                                onMouseOver={(e) => e.currentTarget.style.background = '#cbd5e1'}
                                onMouseOut={(e) => e.currentTarget.style.background = '#e2e8f0'}
                            >
                                <X size={18} />
                            </button>
                        </div>
                        <div style={{ flex: 1, background: '#e2e8f0', padding: '1rem', display: 'flex', justifyContent: 'center' }}>
                            <iframe 
                                src={previewFile} 
                                style={{ width: '100%', height: '100%', border: 'none', borderRadius: '12px', background: '#fff', boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)' }} 
                                title="Payslip Preview"
                            />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Payslips;
