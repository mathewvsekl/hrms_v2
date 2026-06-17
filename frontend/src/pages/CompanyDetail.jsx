import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { 
    Building2, 
    ChevronLeft, 
    Save, 
    Plus, 
    List, 
    Settings, 
    Loader, 
    Trash2,
    Globe,
    MapPin,
    Phone,
    Mail,
    Clock,
    Activity
} from 'lucide-react';
import api from '../services/api';
import useLayoutStore from '../store/useLayoutStore';
import COUNTRY_DATA from '../data/countryData';
import { formatDate } from '../utils/dateUtils';
import PhoneInput from '../components/ui/PhoneInput';

const CompanyDetail = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const isEmployeeView = localStorage.getItem('adminViewMode') === 'employee';

    useEffect(() => {
        if (isEmployeeView) {
            navigate('/employee-profile');
        }
    }, [isEmployeeView, navigate]);

    
    const [company, setCompany] = useState(null);
    const [fields, setFields] = useState([]);
    const [countries, setCountries] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [activeTab, setActiveTab] = useState('general');
    
    // Field Builder State
    const [newField, setNewField] = useState({ field_key: '', field_name: '', field_type: 'text', is_required: false });
    const [editingField, setEditingField] = useState(null);
    const [submittingField, setSubmittingField] = useState(false);
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        if (company) {
            setPageTitle(company.name || 'Company Settings');
            setPageSubtitle("Administrative Configuration & Functional Parameters");
        } else {
            setPageTitle("Company Settings");
        }
        setBackPath('/admin');
        return () => resetPageHeader();
    }, [company]);

    useEffect(() => {
        fetchData();
    }, [id]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const [companyRes, fieldsRes, countriesRes] = await Promise.all([
                api.get(`/organization/companies/${id}`),
                api.get(`/organization/companies/${id}/custom_fields`),
                api.get('/organization/countries')
            ]);
            
            setCompany(companyRes.data?.data || companyRes.data);
            setFields(Array.isArray(fieldsRes.data?.data || fieldsRes.data) ? (fieldsRes.data?.data || fieldsRes.data) : []);
            setCountries(countriesRes.data?.data || countriesRes.data || []);
        } catch (error) {
            console.error('Error fetching company details:', error);
            alert('Failed to load company data.');
            navigate('/admin');
        } finally {
            setLoading(false);
        }
    };

    const handleUpdateCompany = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            await api.put(`/organization/companies/${id}`, company);
            alert('Company details updated successfully.');
        } catch (error) {
            alert('Failed to update company details.');
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteCompany = async () => {
        if (!confirm(`CRITICAL: Are you sure you want to permanently delete "${company.name}"? This will remove all associated data and cannot be undone.`)) return;
        
        const confirmation = prompt('Please type the company name to confirm deletion:');
        if (confirmation !== company.name) return alert('Confirmation failed. Deletion aborted.');

        try {
            await api.delete(`/organization/companies/${id}`);
            alert('Company deleted successfully.');
            navigate('/admin');
        } catch (error) {
            alert('Failed to delete company. Ensure no active dependencies exist.');
        }
    };

    const handleAddField = async () => {
        if (!newField.field_key || !newField.field_name) return alert('Key and Name are required');
        setSubmittingField(true);
        try {
            await api.post(`/organization/companies/${id}/custom_fields`, newField);
            setNewField({ field_key: '', field_name: '', field_type: 'text', is_required: false });
            // Refresh fields
            const res = await api.get(`/organization/companies/${id}/custom_fields`);
            setFields(res.data?.data || res.data || []);
        } catch (e) {
            alert(e.response?.data?.message || 'Failed to add field');
        } finally {
            setSubmittingField(false);
        }
    };

    const handleDeleteField = async (fId, name) => {
        if (!confirm(`Delete custom field "${name}"?`)) return;
        try {
            await api.delete(`/organization/companies/${id}/custom_fields/${fId}`);
            setFields(fields.filter(f => f.id !== fId));
        } catch (e) {
            alert('Failed to delete field');
        }
    };

    const saveFieldEdit = async () => {
        try {
            await api.put(`/organization/companies/${id}/custom_fields/${editingField.id}`, editingField);
            setFields(fields.map(f => f.id === editingField.id ? editingField : f));
            setEditingField(null);
        } catch (e) {
            alert('Failed to update field');
        }
    };

    if (loading) return (
        <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100%', minHeight: '400px' }}>
            <Loader size={32} className="spin" style={{ color: 'var(--color-rose-gold)' }} />
        </div>
    );

    return (
        <div style={{ animation: 'fadeIn 0.4s ease-out' }}>

            {/* Content Tabs */}
            <div style={{ display: 'flex', gap: '2px', background: '#f1f5f9', padding: '4px', borderRadius: '10px', marginBottom: '24px', width: 'fit-content' }}>
                <button 
                    onClick={() => setActiveTab('general')}
                    style={{ 
                        padding: '8px 20px', 
                        borderRadius: '8px', 
                        border: 'none', 
                        fontSize: '14px', 
                        fontWeight: '600',
                        cursor: 'pointer',
                        background: activeTab === 'general' ? 'white' : 'transparent',
                        color: activeTab === 'general' ? 'var(--color-charcoal)' : 'var(--color-text-muted)',
                        boxShadow: activeTab === 'general' ? '0 2px 4px rgba(0,0,0,0.05)' : 'none',
                        transition: 'all 0.2s'
                    }}
                >
                    General Details
                </button>
                <button 
                    onClick={() => setActiveTab('fields')}
                    style={{ 
                        padding: '8px 20px', 
                        borderRadius: '8px', 
                        border: 'none', 
                        fontSize: '14px', 
                        fontWeight: '600',
                        cursor: 'pointer',
                        background: activeTab === 'fields' ? 'white' : 'transparent',
                        color: activeTab === 'fields' ? 'var(--color-charcoal)' : 'var(--color-text-muted)',
                        boxShadow: activeTab === 'fields' ? '0 2px 4px rgba(0,0,0,0.05)' : 'none',
                        transition: 'all 0.2s'
                    }}
                >
                    Custom Fields
                </button>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: activeTab === 'general' ? '2fr 1fr' : '1fr', gap: '24px' }}>
                {activeTab === 'general' ? (
                    <>
                        <div className="card" style={{ padding: '24px' }}>
                            <h3 style={{ fontSize: '18px', fontWeight: '700', marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <Settings size={20} style={{ color: 'var(--color-rose-gold)' }} /> Core Profile
                            </h3>
                            <form onSubmit={handleUpdateCompany} style={{ display: 'grid', gap: '20px' }}>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                                    <div className="form-group">
                                        <label className="form-label">Full Legal Name</label>
                                        <input type="text" className="form-input" value={company.name || ''} onChange={e => setCompany({...company, name: e.target.value})} required />
                                    </div>
                                    <div className="form-group">
                                        <label className="form-label">Attendance Operating Mode</label>
                                        <select className="form-input" value={company.attendance_mode || 'time_based'} onChange={e => setCompany({...company, attendance_mode: e.target.value})}>
                                            <option value="time_based">Time Based (Fixed Hours)</option>
                                            <option value="status_based">Status Based (Result-oriented)</option>
                                        </select>
                                    </div>
                                </div>

                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                                    <div className="form-group">
                                        <label className="form-label">Contact Phone</label>
                                        <PhoneInput
                                            value={company.contact_phone || ''}
                                            defaultCountry={countries.find(c => c.id == company.country_id)?.iso_code || 'ae'}
                                            onChange={val => setCompany({...company, contact_phone: val})}
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label className="form-label">Administrative Email</label>
                                        <div style={{ position: 'relative' }}>
                                            <Mail size={14} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: '#94a3b8' }} />
                                            <input type="email" className="form-input" style={{ paddingLeft: '36px' }} value={company.contact_email || ''} onChange={e => setCompany({...company, contact_email: e.target.value})} />
                                        </div>
                                    </div>
                                </div>

                                <div className="form-group">
                                    <label className="form-label">Registered Office Address</label>
                                    <div style={{ position: 'relative' }}>
                                        <MapPin size={14} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: '#94a3b8' }} />
                                        <input type="text" className="form-input" style={{ paddingLeft: '36px' }} value={company.address || ''} onChange={e => setCompany({...company, address: e.target.value})} />
                                    </div>
                                </div>

                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                                    <div className="form-group">
                                        <label className="form-label">Jurisdiction (Country)</label>
                                        <select className="form-input" value={company.country_id || ''} onChange={e => setCompany({...company, country_id: e.target.value})}>
                                            {countries.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                        </select>
                                    </div>
                                    <div className="form-group">
                                        <label className="form-label">Primary Timezone</label>
                                        <div style={{ position: 'relative' }}>
                                            <Clock size={14} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: '#94a3b8' }} />
                                            <input type="text" className="form-input" style={{ paddingLeft: '36px' }} value={company.timezone || ''} onChange={e => setCompany({...company, timezone: e.target.value})} />
                                        </div>
                                    </div>
                                </div>

                                <div style={{ marginTop: '12px', display: 'flex', justifyContent: 'flex-end' }}>
                                    <button type="submit" className="btn btn-primary" disabled={saving} style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 24px' }}>
                                        {saving ? <Loader size={16} className="spin" /> : <Save size={18} />}
                                        Commit Changes
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div>
                            <div className="card" style={{ padding: '24px', marginBottom: '20px', background: 'linear-gradient(135deg, var(--color-charcoal) 0%, #2a2a2a 100%)', color: 'white' }}>
                                <h4 style={{ margin: '0 0 16px 0', fontSize: '16px', color: 'var(--color-rose-gold)' }}>Configuration Status</h4>
                                <div style={{ display: 'grid', gap: '16px' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px' }}>
                                        <span style={{ opacity: 0.6 }}>Active Employees</span>
                                        <span style={{ fontWeight: '700' }}>{company.employee_count || '—'}</span>
                                    </div>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px' }}>
                                        <span style={{ opacity: 0.6 }}>Custom Parameters</span>
                                        <span style={{ fontWeight: '700' }}>{fields.length} Active</span>
                                    </div>
                                    <div style={{ height: '1px', background: 'rgba(255,255,255,0.1)' }} />
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '12px' }}>
                                        <Activity size={14} color="var(--color-rose-gold)" />
                                        <span>Last synced: {formatDate(new Date())}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="card" style={{ padding: '24px', border: '1px solid #fee2e2', backgroundColor: '#fffcfc' }}>
                                <h4 style={{ margin: '0 0 12px 0', fontSize: '14px', color: '#991b1b', fontWeight: '700' }}>Danger Zone</h4>
                                <p style={{ fontSize: '12px', color: '#7f1d1d', marginBottom: '16px', lineHeight: '1.5' }}>
                                    Deleting this company will remove all associated employees, payroll records, and configurations.
                                </p>
                                <button 
                                    onClick={handleDeleteCompany}
                                    style={{ 
                                        width: '100%', 
                                        padding: '10px', 
                                        borderRadius: '8px', 
                                        border: '1px solid #fecaca', 
                                        backgroundColor: '#fef2f2', 
                                        color: '#dc2626', 
                                        fontSize: '13px', 
                                        fontWeight: '600',
                                        cursor: 'pointer',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        gap: '8px',
                                        transition: 'all 0.2s'
                                    }}
                                    onMouseOver={e => { e.currentTarget.style.backgroundColor = '#fee2e2'; e.currentTarget.style.borderColor = '#ef4444'; }}
                                    onMouseOut={e => { e.currentTarget.style.backgroundColor = '#fef2f2'; e.currentTarget.style.borderColor = '#fecaca'; }}
                                >
                                    <Trash2 size={14} /> Delete Company
                                </button>
                            </div>
                        </div>
                    </>
                ) : (
                    <div className="card" style={{ padding: '24px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
                            <h3 style={{ fontSize: '18px', fontWeight: '700', margin: 0, display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <List size={20} style={{ color: 'var(--color-rose-gold)' }} /> Regional Compliance Fields
                            </h3>
                            <span style={{ fontSize: '12px', color: 'var(--color-text-muted)', background: '#f1f5f9', padding: '4px 10px', borderRadius: '20px' }}>
                                {fields.length} Defined Fields
                            </span>
                        </div>

                        <div style={{ overflowX: 'auto', marginBottom: '32px' }}>
                            <table className="data-table">
                                <thead>
                                    <tr>
                                        <th>Field Identifier</th>
                                        <th>Label Name</th>
                                        <th>Data Type</th>
                                        <th style={{ textAlign: 'center' }}>Mandatory</th>
                                        <th style={{ textAlign: 'right' }}>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {fields.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} style={{ textAlign: 'center', padding: '40px', color: 'var(--color-text-muted)' }}>
                                                No regional fields configured for this jurisdiction.
                                            </td>
                                        </tr>
                                    ) : fields.map(f => (
                                        <tr key={f.id}>
                                            {editingField?.id === f.id ? (
                                                <>
                                                    <td><code>{f.field_key}</code></td>
                                                    <td><input className="form-input" style={{ fontSize: '13px', padding: '4px 8px' }} value={editingField.field_name} onChange={e => setEditingField({...editingField, field_name: e.target.value})} /></td>
                                                    <td>
                                                        <select className="form-input" style={{ fontSize: '13px', padding: '4px 8px' }} value={editingField.field_type} onChange={e => setEditingField({...editingField, field_type: e.target.value})}>
                                                            <option value="text">Text</option>
                                                            <option value="number">Number</option>
                                                            <option value="date">Date</option>
                                                            <option value="dropdown">Dropdown</option>
                                                        </select>
                                                    </td>
                                                    <td style={{ textAlign: 'center' }}>
                                                        <input type="checkbox" checked={editingField.is_required} onChange={e => setEditingField({...editingField, is_required: e.target.checked})} />
                                                    </td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                                            <button onClick={saveFieldEdit} className="btn btn-primary" style={{ padding: '4px 12px', fontSize: '12px' }}>Save</button>
                                                            <button onClick={() => setEditingField(null)} className="btn btn-secondary" style={{ padding: '4px 12px', fontSize: '12px' }}>Cancel</button>
                                                        </div>
                                                    </td>
                                                </>
                                            ) : (
                                                <>
                                                    <td><code style={{ fontSize: '11px', padding: '2px 6px', background: '#f1f5f9', borderRadius: '4px' }}>{f.field_key}</code></td>
                                                    <td style={{ fontWeight: '600' }}>{f.field_name}</td>
                                                    <td style={{ textTransform: 'capitalize' }}>{f.field_type}</td>
                                                    <td style={{ textAlign: 'center' }}>{f.is_required ? <span style={{ color: '#ef4444' }}>Required</span> : <span style={{ color: '#94a3b8' }}>Optional</span>}</td>
                                                    <td style={{ textAlign: 'right' }}>
                                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                                            <button onClick={() => setEditingField({...f})} style={{ background: 'none', border: 'none', color: '#2563eb', cursor: 'pointer', padding: '4px' }}>
                                                                <Settings size={16} />
                                                            </button>
                                                            <button onClick={() => handleDeleteField(f.id, f.field_name)} style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', padding: '4px' }}>
                                                                <Trash2 size={16} />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Add New Field UI */}
                        <div style={{ background: '#f8fafc', padding: '24px', borderRadius: '12px', border: '1px solid #e2e8f0' }}>
                            <h4 style={{ margin: '0 0 20px 0', fontSize: '15px', fontWeight: '700', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <Plus size={16} /> Initialize New Compliance Parameter
                            </h4>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto auto', gap: '16px', alignItems: 'end' }}>
                                <div className="form-group" style={{ marginBottom: 0 }}>
                                    <label className="form-label" style={{ fontSize: '12px' }}>Technical Key</label>
                                    <input className="form-input" value={newField.field_key} readOnly style={{ background: '#f1f5f9', cursor: 'not-allowed' }} />
                                </div>
                                <div className="form-group" style={{ marginBottom: 0 }}>
                                    <label className="form-label" style={{ fontSize: '12px' }}>Display Label</label>
                                    <input className="form-input" placeholder="e.g., Pension ID" value={newField.field_name} 
                                        onChange={e => {
                                            const val = e.target.value;
                                            const slug = val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
                                            setNewField({...newField, field_name: val, field_key: slug});
                                        }} />
                                </div>
                                <div className="form-group" style={{ marginBottom: 0 }}>
                                    <label className="form-label" style={{ fontSize: '12px' }}>Value Type</label>
                                    <select className="form-input" value={newField.field_type} onChange={e => setNewField({...newField, field_type: e.target.value})}>
                                        <option value="text">Alphanumeric Text</option>
                                        <option value="number">Numeric Value</option>
                                        <option value="date">Calendar Date</option>
                                        <option value="dropdown">Selectable Options</option>
                                    </select>
                                </div>
                                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px', paddingBottom: '8px' }}>
                                    <label style={{ fontSize: '11px', fontWeight: '600' }}>Strict</label>
                                    <input type="checkbox" checked={newField.is_required} onChange={e => setNewField({...newField, is_required: e.target.checked})} />
                                </div>
                                <button className="btn btn-primary" onClick={handleAddField} disabled={submittingField} style={{ height: '42px', padding: '0 24px' }}>
                                    {submittingField ? <Loader size={16} className="spin" /> : 'Append Field'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <style>{`
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .form-group {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                    margin-bottom: 0;
                }
                .form-label {
                    font-size: 13px;
                    font-weight: 600;
                    color: #475569;
                }
            `}</style>
        </div>
    );
};

export default CompanyDetail;
