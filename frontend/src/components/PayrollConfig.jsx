import { useState, useEffect } from 'react';
import { Settings, X, Plus, Trash2, Edit2, ShieldAlert, FileText, ClipboardList, PlusCircle, Save, Loader, UploadCloud } from 'lucide-react';
import useNotificationStore from '../store/useNotificationStore';
import api from '../services/api';
import TaxSlabsConfig from './TaxSlabsConfig';

const PayrollConfig = ({ companies, companyId, setCompanyId }) => {
    const { showAlert } = useNotificationStore();
    const [components, setComponents] = useState([]);
    const [countries, setCountries] = useState([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [logo, setLogo] = useState(null);
    
    // Sidebar tabs within configuration
    const [configTab, setConfigTab] = useState('components');
    const [isAdding, setIsAdding] = useState(false);

    const activeCompany = companies?.find(c => c.id == companyId) || {};
    
    const [form, setForm] = useState({
        id: null,
        name: '',
        type: 'EARNING',
        computation_type: 'FIXED',
        value: 0,
        formula: '',
        company_id: '',
        country_id: '',
        is_statutory: false,
        is_non_taxable: false,
        is_income_tax: false,
        display_in_payslip: true,
        round_off: false
    });

    useEffect(() => {
        fetchCountries();
    }, []);

    useEffect(() => {
        if (companyId) {
            fetchComponents();
            const activeComp = companies?.find(c => c.id == companyId);
            setLogo(activeComp?.logo_url || null);
        }
    }, [companyId]);

    const handleLogoUpload = async (e) => {
        const file = e.target.files[0];
        if (file) {
            const formData = new FormData();
            formData.append('logo', file);
            
            try {
                // Use standard fetch to guarantee correct multipart boundary generation by the browser
                // (Axios sometimes strips the boundary if defaults are set to application/json)
                const token = localStorage.getItem('hrms_auth_token');
                const res = await fetch(`${import.meta.env.VITE_API_BASE_URL || '/api'}/companies/logo/${companyId}`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`
                    },
                    body: formData
                });
                
                const data = await res.json();
                
                if (data?.status === 'success') {
                    setLogo(data.data.logo_url);
                    showAlert('Success', 'Logo updated for this company', 'success');
                    window.dispatchEvent(new Event('logo-updated'));
                } else {
                    showAlert('Error', data?.message || 'Failed to upload logo', 'error');
                }
            } catch (err) {
                console.error(err);
                showAlert('Error', err.message || 'Failed to upload logo', 'error');
            }
        }
    };

    const fetchCountries = async () => {
        try {
            const res = await api.get('/organization/countries');
            setCountries(res.data?.data || []);
        } catch (error) {
            console.error("Failed to fetch countries", error);
        }
    };

    const fetchComponents = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/payroll/components?company_id=${companyId}`);
            setComponents(res.data?.data || []);
        } catch (error) {
            console.error("Failed to fetch components", error);
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async () => {
        if (!form.name) return showAlert('Required', 'Name is required', 'warning');
        setSaving(true);
        try {
            const payload = {
                ...form,
                company_id: form.is_statutory ? null : companyId,
                country_id: form.country_id || null
            };
            if (form.id) {
                await api.put(`/payroll/components/${form.id}`, payload);
            } else {
                await api.post('/payroll/components', payload);
            }
            showAlert('Success', 'Component saved successfully', 'success');
            setForm({ id: null, name: '', type: 'EARNING', computation_type: 'FIXED', value: 0, formula: '', company_id: '', country_id: '', is_statutory: false, is_non_taxable: false, is_income_tax: false, display_in_payslip: true, round_off: false });
            setIsAdding(false);
            fetchComponents();
        } catch (error) {
            showAlert('Error', error.response?.data?.message || 'Failed to save component', 'error');
        } finally {
            setSaving(false);
        }
    };

    const handleEdit = (comp) => {
        setForm({
            id: comp.id,
            ...comp,
            is_statutory: comp.is_statutory == 1,
            is_non_taxable: comp.is_non_taxable == 1,
            is_income_tax: comp.is_income_tax == 1,
            display_in_payslip: comp.display_in_payslip == 1 || comp.display_in_payslip === undefined,
            round_off: comp.round_off == 1
        });
        setIsAdding(true);
    };

    const handleDelete = async (comp) => {
        if (comp.is_statutory == 1) {
            return showAlert('Error', 'Cannot delete statutory components', 'error');
        }
        if (!window.confirm(`Are you sure you want to delete ${comp.name}?`)) return;
        
        try {
            await api.delete(`/payroll/components/${comp.id}`);
            showAlert('Success', 'Component deleted', 'success');
            fetchComponents();
        } catch (error) {
            showAlert('Error', error.response?.data?.message || 'Failed to delete component', 'error');
        }
    };

    return (
        <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
            {/* Header matches Policy Architect */}
            <div style={{ padding: '20px', borderBottom: '1px solid var(--color-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#fcfcfc' }}>
                <div>
                    <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <Settings size={18} style={{ color: 'var(--color-rose-gold)' }} /> Payroll Architect
                    </h3>
                    <p style={{ margin: '4px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>Configure dynamic earnings and deductions</p>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <label style={{ fontSize: '13px', fontWeight: '600' }}>Office Scope:</label>
                        <select className="form-input" style={{ width: 'auto', minWidth: '180px' }} value={companyId} onChange={e => setCompanyId(e.target.value)}>
                            {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                </div>
            </div>

            <div style={{ display: 'flex', background: '#fff', minHeight: '60vh' }}>
                {/* Left Sidebar */}
                <div style={{ width: '200px', borderRight: '1px solid var(--color-border)', padding: '10px' }}>
                    <button 
                        className={`nav-item ${configTab === 'components' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'components' ? '#fff' : '#475569', background: configTab === 'components' ? '#1e293b' : 'transparent', borderRadius: '6px', padding: '8px 12px', display: 'flex', alignItems: 'center', gap: '8px', border: 'none', cursor: 'pointer', fontWeight: configTab === 'components' ? '600' : '400' }}
                        onClick={() => setConfigTab('components')}
                    >
                        <ClipboardList size={16} /> Salary Heads
                    </button>
                    <button 
                        className={`nav-item ${configTab === 'tax_slabs' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'tax_slabs' ? '#fff' : '#475569', background: configTab === 'tax_slabs' ? '#1e293b' : 'transparent', borderRadius: '6px', padding: '8px 12px', display: 'flex', alignItems: 'center', gap: '8px', border: 'none', cursor: 'pointer', fontWeight: configTab === 'tax_slabs' ? '600' : '400' }}
                        onClick={() => setConfigTab('tax_slabs')}
                    >
                        <FileText size={16} /> Tax Slabs
                    </button>
                    <button 
                        className={`nav-item ${configTab === 'templates' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'templates' ? '#fff' : '#475569', background: configTab === 'templates' ? '#1e293b' : 'transparent', borderRadius: '6px', padding: '8px 12px', display: 'flex', alignItems: 'center', gap: '8px', border: 'none', cursor: 'pointer', fontWeight: configTab === 'templates' ? '600' : '400' }}
                        onClick={() => setConfigTab('templates')}
                    >
                        <FileText size={16} /> Payslip Templates
                    </button>
                </div>

                {/* Main Content */}
                <div style={{ flex: 1, padding: '20px' }}>
                    {configTab === 'tax_slabs' && <TaxSlabsConfig showAlert={showAlert} components={components} companyId={companyId} />}
                    {configTab === 'components' && (
                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                                <h4 style={{ margin: 0, fontSize: '15px' }}>Salary Components</h4>
                                <button className="btn btn-secondary" style={{ padding: '6px 12px', fontSize: '13px' }} onClick={() => {
                                    if (isAdding) {
                                        setIsAdding(false);
                                        setForm({ id: null, name: '', type: 'EARNING', computation_type: 'FIXED', value: 0, formula: '', company_id: '', country_id: '', is_statutory: false, is_non_taxable: false, is_income_tax: false, display_in_payslip: true });
                                    } else {
                                        setIsAdding(true);
                                    }
                                }}>
                                    <PlusCircle size={14} /> {isAdding ? 'Cancel' : 'Add Component'}
                                </button>
                            </div>

                            {isAdding && (
                                <div style={{ background: 'var(--color-ivory)', padding: '16px', borderRadius: '8px', border: '1px solid var(--color-border)', marginBottom: '16px' }}>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr auto', gap: '12px', alignItems: 'end' }}>
                                        <div>
                                            <label className="form-label" style={{ fontSize: '12px' }}>Name</label>
                                            <input className="form-input" value={form.name} onChange={e => setForm({...form, name: e.target.value})} placeholder="e.g. Transport" />
                                        </div>
                                        <div>
                                            <label className="form-label" style={{ fontSize: '12px' }}>Type</label>
                                            <select className="form-input" value={form.type} onChange={e => setForm({...form, type: e.target.value})}>
                                                <option value="EARNING">Earning</option>
                                                <option value="DEDUCTION">Deduction</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="form-label" style={{ fontSize: '12px' }}>Computation</label>
                                            <select className="form-input" value={form.computation_type} onChange={e => setForm({...form, computation_type: e.target.value})}>
                                                <option value="FIXED">Fixed Amount</option>
                                                <option value="PERCENTAGE">Percentage (%)</option>
                                                <option value="FORMULA">Formula</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="form-label" style={{ fontSize: '12px' }}>Value / Formula</label>
                                            {form.computation_type === 'FORMULA' ? (
                                                <input type="text" className="form-input" value={form.formula || ''} onChange={e => setForm({...form, formula: e.target.value})} placeholder="e.g. =IF(GROSS<235000, 0, ...)" style={{ width: '250px' }} />
                                            ) : (
                                                <input type="number" step="0.01" className="form-input" value={form.value} onChange={e => setForm({...form, value: e.target.value})} />
                                            )}
                                        </div>
                                        <button className="btn btn-primary" style={{ height: '38px', padding: '0 16px' }} onClick={handleSave} disabled={saving}>
                                            {saving ? <Loader size={14} className="spin" /> : 'Save'}
                                        </button>
                                    </div>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '12px' }}>
                                        <div style={{ fontSize: '12px', color: '#0369a1', display: 'flex', alignItems: 'center', gap: '4px' }}>
                                            <ShieldAlert size={12} /> This component will exclusively apply to <strong>{activeCompany.name}</strong>.
                                        </div>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                <input type="checkbox" id="display_in_payslip" checked={form.display_in_payslip} onChange={e => setForm({...form, display_in_payslip: e.target.checked})} style={{ cursor: 'pointer' }} />
                                                <label htmlFor="display_in_payslip" style={{ fontSize: '12px', margin: 0, cursor: 'pointer', color: '#475569', fontWeight: '500' }}>Show in Payslip</label>
                                            </div>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                <input type="checkbox" id="round_off" checked={form.round_off} onChange={e => setForm({...form, round_off: e.target.checked})} style={{ cursor: 'pointer' }} />
                                                <label htmlFor="round_off" style={{ fontSize: '12px', margin: 0, cursor: 'pointer', color: '#475569', fontWeight: '500' }}>Round Off</label>
                                            </div>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                <input type="checkbox" id="non_taxable" checked={form.is_non_taxable} onChange={e => setForm({...form, is_non_taxable: e.target.checked})} style={{ cursor: 'pointer' }} />
                                                <label htmlFor="non_taxable" style={{ fontSize: '12px', margin: 0, cursor: 'pointer', color: '#475569', fontWeight: '500' }}>Non-Taxable (Pre-tax)</label>
                                            </div>
                                            {form.type === 'DEDUCTION' && (
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                    <input type="checkbox" id="is_income_tax" checked={form.is_income_tax} onChange={e => setForm({...form, is_income_tax: e.target.checked})} style={{ cursor: 'pointer' }} />
                                                    <label htmlFor="is_income_tax" style={{ fontSize: '12px', margin: 0, cursor: 'pointer', color: '#475569', fontWeight: '500' }}>Income Tax Component</label>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}

                            <div className="table-container">
                                <table className="data-table">
                                    <thead>
                                        <tr>
                                            <th>Component Name</th>
                                            <th>Type</th>
                                            <th>Computation</th>
                                            <th>Value</th>
                                            <th style={{ textAlign: 'center' }}>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {loading ? (
                                            <tr><td colSpan={5} style={{ textAlign: 'center', padding: '20px' }}>Loading components...</td></tr>
                                        ) : components.length === 0 ? (
                                            <tr><td colSpan={5} style={{ textAlign: 'center', padding: '20px', color: 'var(--color-text-muted)' }}>No salary components configured.</td></tr>
                                        ) : components.map(comp => (
                                            <tr key={comp.id}>
                                                <td>
                                                    <div style={{ fontWeight: '500' }}>{comp.name}</div>
                                                    <div style={{ display: 'flex', gap: '6px', alignItems: 'center', marginTop: '4px' }}>
                                                        {comp.is_statutory == 1 && (
                                                            <span style={{ fontSize: '10px', background: '#fef3c7', color: '#d97706', padding: '1px 4px', borderRadius: '3px' }}>STATUTORY</span>
                                                        )}
                                                        {comp.type === 'EARNING' && comp.is_non_taxable == 1 && (
                                                            <span style={{ fontSize: '10px', background: '#e0e7ff', color: '#4338ca', padding: '1px 4px', borderRadius: '3px' }}>NON-TAXABLE</span>
                                                        )}
                                                        {comp.type === 'DEDUCTION' && comp.is_non_taxable == 1 && (
                                                            <span style={{ fontSize: '10px', background: '#dcfce7', color: '#15803d', padding: '1px 4px', borderRadius: '3px' }}>PRE-TAX</span>
                                                        )}
                                                        {comp.is_income_tax == 1 && (
                                                            <span style={{ fontSize: '10px', background: '#fee2e2', color: '#b91c1c', padding: '1px 4px', borderRadius: '3px' }}>INCOME TAX</span>
                                                        )}
                                                        {comp.round_off == 1 && (
                                                            <span style={{ fontSize: '10px', background: '#f3e8ff', color: '#7e22ce', padding: '1px 4px', borderRadius: '3px' }}>ROUND OFF</span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span className={`badge ${comp.type === 'EARNING' ? 'badge-success' : 'badge-danger'}`}>
                                                        {comp.type}
                                                    </span>
                                                </td>
                                                <td>{comp.computation_type}</td>
                                                <td>{comp.computation_type === 'FORMULA' ? <code style={{ fontSize: '11px', background: '#f1f5f9', padding: '4px 6px', borderRadius: '4px' }}>{comp.formula}</code> : comp.value}</td>
                                                <td>
                                                    <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                                        <button className="btn-icon" title="Edit" onClick={() => handleEdit(comp)}>
                                                            <Edit2 size={14} />
                                                        </button>
                                                        {comp.is_statutory != 1 && (
                                                            <button className="btn-icon text-danger" title="Delete" onClick={() => handleDelete(comp)}>
                                                                <Trash2 size={14} />
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {configTab === 'templates' && (
                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                                <h4 style={{ margin: 0, fontSize: '15px' }}>Payslip Templates</h4>
                            </div>
                            
                            <div style={{ background: '#fff', border: '1px solid var(--color-border)', borderRadius: '8px', padding: '24px', marginBottom: '24px' }}>
                                <h5 style={{ margin: '0 0 16px 0', fontSize: '14px' }}>Base Template</h5>
                                <div style={{ display: 'flex', gap: '24px', alignItems: 'flex-start' }}>
                                    <div style={{ flex: 1 }}>
                                        <p style={{ margin: '0 0 12px 0', fontSize: '13px', color: '#64748b' }}>
                                            The system is currently using the standard <strong>Uganda Standard Layout</strong> (extracted from PS APR 2026 001).
                                        </p>
                                        
                                        <label style={{ display: 'block', marginBottom: '8px', fontSize: '13px', fontWeight: '500' }}>Custom Logo (Optional)</label>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                                            {logo ? (
                                                <div style={{ position: 'relative', border: '1px solid #e2e8f0', borderRadius: '4px', padding: '8px' }}>
                                                    <img 
                                                        src={logo} 
                                                        alt="Logo" 
                                                        style={{ height: '40px', objectFit: 'contain' }} 
                                                        onError={(e) => { e.target.onerror = null; e.target.src = '/LOGO.png'; }}
                                                    />
                                                    <button 
                                                        onClick={() => {
                                                            setLogo(null);
                                                            api.post(`/companies/logo/${companyId}`, { logo: null })
                                                                .then(() => window.dispatchEvent(new Event('logo-updated')));
                                                        }}
                                                        style={{ position: 'absolute', top: '-8px', right: '-8px', background: 'red', color: 'white', border: 'none', borderRadius: '50%', width: '20px', height: '20px', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
                                                    >
                                                        <X size={12} />
                                                    </button>
                                                </div>
                                            ) : (
                                                <div style={{ position: 'relative', border: '1px solid #e2e8f0', borderRadius: '4px', padding: '8px', background: '#f8fafc' }}>
                                                    <img src="/api/logo" alt="Default Logo" style={{ height: '40px', objectFit: 'contain', opacity: 0.5 }} />
                                                    <div style={{ position: 'absolute', bottom: '-20px', left: 0, right: 0, textAlign: 'center', fontSize: '11px', color: '#94a3b8' }}>Default (Fallback)</div>
                                                </div>
                                            )}
                                            
                                            <div>
                                                <input type="file" id="logoUpload" accept="image/*" style={{ display: 'none' }} onChange={handleLogoUpload} />
                                                <label htmlFor="logoUpload" className="btn btn-outline" style={{ cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '8px' }}>
                                                    <UploadCloud size={16} /> Upload Logo
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div style={{ background: '#fff', border: '1px solid var(--color-border)', borderRadius: '8px', padding: '24px' }}>
                                <h5 style={{ margin: '0 0 16px 0', fontSize: '14px' }}>Template Fields Configuration</h5>
                                <p style={{ margin: '0 0 16px 0', fontSize: '13px', color: '#64748b' }}>
                                    Select which salary components should be visible on the employee's payslip. Unchecked items will still be calculated in the payroll but hidden from the printed payslip document.
                                </p>
                                <div style={{ display: 'grid', gap: '12px' }}>
                                    {components.map(comp => (
                                        <div key={comp.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px', border: '1px solid #e2e8f0', borderRadius: '6px' }}>
                                            <div>
                                                <div style={{ fontWeight: '500', fontSize: '14px' }}>{comp.name}</div>
                                                <div style={{ fontSize: '12px', color: '#64748b' }}>Type: {comp.type}</div>
                                            </div>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                <input 
                                                    type="checkbox" 
                                                    id={`display_${comp.id}`} 
                                                    checked={comp.display_in_payslip !== 0} 
                                                    onChange={async (e) => {
                                                        const checked = e.target.checked;
                                                        try {
                                                            await api.put(`/payroll/components/${comp.id}`, { ...comp, display_in_payslip: checked ? 1 : 0 });
                                                            fetchComponents();
                                                        } catch (error) {
                                                            console.error("Failed to update component visibility", error);
                                                        }
                                                    }} 
                                                    style={{ width: '16px', height: '16px', cursor: 'pointer' }}
                                                />
                                                <label htmlFor={`display_${comp.id}`} style={{ margin: 0, cursor: 'pointer', fontSize: '13px', fontWeight: '500', color: '#334155' }}>Show on Payslip</label>
                                            </div>
                                        </div>
                                    ))}
                                    {components.length === 0 && (
                                        <div style={{ padding: '20px', textAlign: 'center', color: '#94a3b8', fontSize: '13px' }}>
                                            No salary components configured yet.
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default PayrollConfig;
