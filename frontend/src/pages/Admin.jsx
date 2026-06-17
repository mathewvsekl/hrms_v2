import { getSecureMediaUrl } from '../utils/mediaHelper';
import { useState, useEffect, Fragment } from 'react';
import { useNavigate } from 'react-router-dom';
import { Shield, List, DollarSign, Building2, Settings, Plus, Save, RefreshCw, Loader, ChevronDown, ExternalLink, FileText, Trash2, Upload } from 'lucide-react';
import api from '../services/api';
import { formatDate } from '../utils/dateUtils';
import useLayoutStore from '../store/useLayoutStore';
import COUNTRY_DATA from '../data/countryData';
import DateInput from '../components/ui/DateInput';
import useNotificationStore from '../store/useNotificationStore';
import useAuthStore from '../store/useAuthStore';

/* ── Tab definitions ────────────────────────────────────── */
const TABS = [
    { id: 'org', label: 'Organization', icon: <Building2 size={16} /> },
    { id: 'documents', label: 'Documents', icon: <FileText size={16} /> },
    { id: 'global', label: 'Global Settings', icon: <Settings size={16} /> },
    { id: 'rbac', label: 'RBAC Matrix', icon: <Shield size={16} /> },
];

/* ── RBAC Panel ─────────────────────────────────────────── */
const MODULES = ['Dashboard', 'Directory', 'Employees', 'Documents', 'Attendance', 'Leave', 'Payroll', 'Appraisals', 'Offboarding', 'Assets', 'Reports', 'Configuration'];
const ACTIONS = ['view', 'create', 'edit', 'delete', 'approve'];
const ROLES = [
    { value: 'Super Admin', label: 'Super Admin' },
    { value: 'HR Manager', label: 'HR Manager' },
    { value: 'CountryManager', label: 'Country Manager' },
    { value: 'Employee', label: 'Employee' },
    { value: 'HRAssistant', label: 'HR Assistant' },
    { value: 'Admin', label: 'Admin' },
];

const defaultPerms = {
    SuperAdmin: MODULES.reduce((a, m) => { a[m] = ACTIONS.reduce((b, act) => { b[act] = true; return b; }, {}); return a; }, {}),
    HRManager: MODULES.reduce((a, m) => { a[m] = { view: true, create: ['Directory', 'Employees', 'Leave', 'Attendance'].includes(m), edit: ['Directory', 'Employees', 'Leave', 'Attendance'].includes(m), delete: false, approve: ['Leave', 'Attendance'].includes(m) }; return a; }, {}),
    CountryManager: MODULES.reduce((a, m) => { a[m] = { view: ['Dashboard', 'Directory', 'Attendance', 'Leave', 'Appraisals'].includes(m), create: false, edit: false, delete: false, approve: ['Leave', 'Attendance'].includes(m) }; return a; }, {}),
    Employee: MODULES.reduce((a, m) => { a[m] = { view: ['Dashboard', 'Directory', 'Attendance', 'Leave', 'Payroll', 'Appraisals'].includes(m), create: ['Attendance', 'Leave'].includes(m), edit: false, delete: false, approve: false }; return a; }, {}),
    Admin: MODULES.reduce((a, m) => { a[m] = { view: ['Dashboard', 'Directory', 'Payroll', 'Reports'].includes(m), create: m === 'Payroll', edit: m === 'Payroll', delete: false, approve: m === 'Payroll' }; return a; }, {}),
};

const RbacPanel = () => {
    const [role, setRole] = useState('');
    const [roles, setRoles] = useState([]);
    const [permissions, setPermissions] = useState([]);
    const [rolePerms, setRolePerms] = useState([]); // List of permission IDs for active role
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const { showAlert, showConfirm } = useNotificationStore();

    const getModuleGroup = (mod) => {
        if (['Directory', 'Employees', 'Onboarding', 'Offboarding', 'Documents'].includes(mod)) return 'Core HR';
        if (['Attendance', 'Leave', 'Appraisals', 'Payroll', 'Assets'].includes(mod)) return 'Operations';
        return 'System Administration';
    };

    const fetchData = async () => {
        setLoading(true);
        try {
            const [rolesRes, permsRes] = await Promise.all([
                api.get('/rbac/roles'),
                api.get('/rbac/permissions')
            ]);
            let rolesData = rolesRes.data?.data || rolesRes.data;
            const permsData = permsRes.data?.data || permsRes.data;

            // Phase 1: Filter out SuperAdmin from dropdown lists entirely
            rolesData = (Array.isArray(rolesData) ? rolesData : []).filter(r => r.name !== 'Super Admin' && r.name !== 'Super Admin');

            setRoles(rolesData);
            setPermissions(Array.isArray(permsData) ? permsData : []);

            if (rolesData.length > 0) {
                setRole(rolesData[0].id);
            }
        } catch (e) {
            const msg = e.response?.data?.message || 'Failed to fetch RBAC data';
            console.error(msg, e);
            showAlert('Error', msg, 'error');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchData(); }, []);

    useEffect(() => {
        if (!role) return;
        api.get(`/rbac/roles/${role}/permissions`).then(res => {
            const data = res.data?.data || res.data;
            setRolePerms(Array.isArray(data) ? data : []);
        }).catch(() => setRolePerms([]));
    }, [role]);

    const togglePerm = (permId) => {
        if (rolePerms.includes(permId)) {
            setRolePerms(rolePerms.filter(id => id !== permId));
        } else {
            setRolePerms([...rolePerms, permId]);
        }
    };

    const toggleRow = (mod, isChecked) => {
        const modPermIds = permissions.filter(p => p.module === mod).map(p => p.id);
        if (isChecked) {
            setRolePerms([...new Set([...rolePerms, ...modPermIds])]);
        } else {
            setRolePerms(rolePerms.filter(id => !modPermIds.includes(id)));
        }
    };

    const toggleColumn = (act, isChecked) => {
        const actPermIds = permissions.filter(p => p.action === act).map(p => p.id);
        if (isChecked) {
            setRolePerms([...new Set([...rolePerms, ...actPermIds])]);
        } else {
            setRolePerms(rolePerms.filter(id => !actPermIds.includes(id)));
        }
    };

    const savePermissions = async () => {
        setSaving(true);
        try {
            await api.put(`/rbac/roles/${role}/permissions`, { permission_ids: rolePerms });
            showAlert('Success', 'Permissions saved successfully.', 'success');
        } catch (e) {
            const msg = e.response?.data?.message || 'Failed to save permissions.';
            showAlert('Error', msg, 'error');
        } finally {
            setSaving(false);
        }
    };

    const [newRoleName, setNewRoleName] = useState('');
    const [copyFromRoleId, setCopyFromRoleId] = useState('');
    const [isCreatingRole, setIsCreatingRole] = useState(false);

    const createRole = async () => {
        if (!newRoleName.trim()) return;
        if (!copyFromRoleId) {
            return showAlert('Required', 'Please select a base role blueprint to clone from.', 'warning');
        }
        try {
            await api.post('/rbac/roles', { 
                name: newRoleName,
                base_role_id: copyFromRoleId
            });
            setNewRoleName('');
            setCopyFromRoleId('');
            setIsCreatingRole(false);
            fetchData();
            showAlert('Success', 'Role created successfully.', 'success');
        } catch (e) {
            showAlert('Error', 'Failed to create role.', 'error');
        }
    };

    const deleteRole = async (roleId, name) => {
        // IDs 1-6 are protected system roles
        if (roleId >= 1 && roleId <= 6) {
            return showAlert('Denied', 'Protected system roles cannot be deleted.', 'error');
        }

        showConfirm('Delete Role', `Are you sure you want to delete the role "${name}"?`, async () => {
            try {
                await api.delete(`/rbac/roles/${roleId}`);
                fetchData();
                showAlert('Success', 'Role deleted.', 'success');
            } catch (e) {
                showAlert('Error', 'Failed to delete role.', 'error');
            }
        });
    };

    if (loading) return <div style={{ textAlign: 'center', padding: '40px' }}><Loader size={24} className="spin" /> Loading RBAC...</div>;

    const modules = Array.from(new Set(permissions.map(p => p.module)));
    const actions = ['view', 'create', 'edit', 'delete', 'approve', 'configuration'];

    const groupedModules = modules.reduce((acc, mod) => {
        const group = getModuleGroup(mod);
        if (!acc[group]) acc[group] = [];
        acc[group].push(mod);
        return acc;
    }, {});

    return (
        <div className="card">
            {isCreatingRole && (
                <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999 }}>
                    <div style={{ background: '#fff', padding: '24px', borderRadius: '12px', width: '400px', boxShadow: '0 20px 25px -5px rgba(0,0,0,0.1)' }}>
                        <h3 style={{ marginBottom: '16px', fontSize: '18px', fontWeight: '600', color: '#1e293b' }}>Create Custom Role</h3>
                        <p style={{ fontSize: '13px', color: '#64748b', marginBottom: '20px', lineHeight: '1.5' }}>
                            Custom roles must inherit their baseline permissions from a core system role. Select a blueprint below to clone.
                        </p>
                        <div style={{ marginBottom: '16px' }}>
                            <label style={{ display: 'block', fontSize: '12px', fontWeight: '600', marginBottom: '8px', color: '#334155' }}>Role Name *</label>
                            <input
                                type="text"
                                className="form-input"
                                placeholder="e.g. Regional Auditor"
                                value={newRoleName}
                                onChange={e => setNewRoleName(e.target.value)}
                                style={{ width: '100%', padding: '10px 12px', borderRadius: '6px' }}
                                autoFocus
                            />
                        </div>
                        <div style={{ marginBottom: '24px' }}>
                            <label style={{ display: 'block', fontSize: '12px', fontWeight: '600', marginBottom: '8px', color: '#334155' }}>Clone from Base Role *</label>
                            <select 
                                className="form-input" 
                                value={copyFromRoleId} 
                                onChange={e => setCopyFromRoleId(e.target.value)}
                                style={{ width: '100%', padding: '10px 12px', borderRadius: '6px' }}
                            >
                                <option value="" disabled>Select Base Role Blueprint...</option>
                                {roles.filter(r => r.id >= 2 && r.id <= 6).map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                            </select>
                        </div>
                        <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                            <button className="btn btn-secondary" onClick={() => setIsCreatingRole(false)}>Cancel</button>
                            <button className="btn btn-primary" onClick={createRole}>Create Role</button>
                        </div>
                    </div>
                </div>
            )}

            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <Shield size={20} style={{ color: 'var(--primary-brand)' }} /> Role Permission Architect
                </h3>
                <button className="btn btn-primary" style={{ padding: '8px 16px', fontSize: '14px', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '6px', fontWeight: '500' }} onClick={() => setIsCreatingRole(true)}>
                    <Plus size={16} /> New Role
                </button>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '24px', background: '#f8fafc', padding: '16px', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                <label style={{ fontSize: '14px', fontWeight: '600', color: 'var(--text-secondary)' }}>Select Role to Edit:</label>
                <div style={{ display: 'flex', gap: '12px', alignItems: 'center', flex: 1 }}>
                    <select value={role} onChange={e => setRole(e.target.value)} className="form-input" style={{ width: '300px', fontWeight: '500' }}>
                        {roles.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                    </select>
                    {role && !(role >= 1 && role <= 6) && (
                        <button onClick={() => deleteRole(role, roles.find(r => r.id == role)?.name)}
                            style={{ background: 'none', border: 'none', color: '#e74c3c', cursor: 'pointer', padding: '4px', display: 'flex', alignItems: 'center', gap: '4px', fontSize: '13px', fontWeight: '500' }}>
                            <Trash2 size={16} /> Delete Custom Role
                        </button>
                    )}
                </div>
            </div>

            <div style={{ overflowX: 'auto', border: '1px solid #e2e8f0', borderRadius: '8px' }}>
                <table className="data-table" style={{ border: 'none' }}>
                    <thead>
                        <tr style={{ background: '#f1f5f9' }}>
                            <th style={{ width: '200px' }}>Module Group</th>
                            {actions.map(a => (
                                <th key={a} style={{ textAlign: 'center', textTransform: 'capitalize' }}>
                                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '6px' }}>
                                        {a}
                                        <input 
                                            type="checkbox" 
                                            title={`Select all ${a}`}
                                            onChange={(e) => toggleColumn(a, e.target.checked)}
                                            checked={permissions.filter(p => p.action === a).every(p => rolePerms.includes(p.id))}
                                            style={{ cursor: 'pointer' }}
                                        />
                                    </div>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {Object.entries(groupedModules).map(([groupName, groupModules]) => (
                            <Fragment key={groupName}>
                                <tr style={{ background: '#f8fafc' }}>
                                    <td colSpan={actions.length + 1} style={{ fontWeight: '600', color: '#334155', padding: '12px 16px', borderTop: '2px solid #e2e8f0' }}>
                                        {groupName}
                                    </td>
                                </tr>
                                {groupModules.map(mod => {
                                    const isRowAllChecked = actions.every(act => {
                                        const p = permissions.find(per => per.module === mod && per.action === act);
                                        return p ? rolePerms.includes(p.id) : true;
                                    });

                                    return (
                                        <tr key={mod}>
                                            <td style={{ fontWeight: '500', paddingLeft: '32px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                <input 
                                                    type="checkbox" 
                                                    title={`Select all for ${mod}`}
                                                    onChange={(e) => toggleRow(mod, e.target.checked)}
                                                    checked={isRowAllChecked}
                                                    style={{ cursor: 'pointer' }}
                                                />
                                                {mod}
                                            </td>
                                            {actions.map(act => {
                                                const p = permissions.find(per => per.module === mod && per.action === act);
                                                return (
                                                    <td key={act} style={{ textAlign: 'center' }}>
                                                        {p ? (
                                                            <input type="checkbox"
                                                                checked={rolePerms.includes(p.id)}
                                                                onChange={() => togglePerm(p.id)}
                                                                style={{ width: '16px', height: '16px', accentColor: 'var(--primary-brand)', cursor: 'pointer' }} />
                                                        ) : <span style={{ color: '#cbd5e1' }}>—</span>}
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    );
                                })}
                            </Fragment>
                        ))}
                    </tbody>
                </table>
            </div>

            <div style={{ marginTop: '24px', display: 'flex', justifyContent: 'flex-end', paddingTop: '16px', borderTop: '1px solid #e2e8f0' }}>
                <button className="btn btn-primary" style={{ padding: '10px 24px', fontSize: '14px', fontWeight: '600', borderRadius: '6px' }} onClick={savePermissions} disabled={saving}>
                    {saving ? <Loader size={16} className="spin" /> : <Save size={16} />} Save Configurations
                </button>
            </div>
        </div>
    );
};



/* ── Currency Configuration (Exchange Rates) ────────────────────────────── */
const CurrencyConfiguration = () => {
    const [rates, setRates] = useState([]);
    const [loading, setLoading] = useState(true);
    const [form, setForm] = useState({ from_currency: 'USD', to_currency: 'KES', rate: '', effective_date: new Date().toISOString().split('T')[0] });
    const [editingRate, setEditingRate] = useState(null);
    const { showAlert, showConfirm } = useNotificationStore();

    const currencies = ['USD', 'KES', 'UGX', 'AED', 'INR', 'EUR', 'GBP', 'TZS', 'RWF'];

    const fetchRates = async () => {
        setLoading(true);
        try {
            const ratesRes = await api.get('/organization/exchange-rates');
            setRates(ratesRes.data?.data || ratesRes.data || []);
        } catch (e) {
            console.error("Failed to fetch exchange rates", e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchRates(); }, []);

    const addRate = async () => {
        if (!form.rate) return showAlert('Required', 'Please enter a rate', 'warning');
        try {
            await api.post('/organization/exchange-rates', form);
            setForm({ ...form, rate: '' });
            fetchRates();
            showAlert('Success', 'Exchange rate added.', 'success');
        } catch (e) {
            showAlert('Error', 'Error adding rate: ' + (e.response?.data?.message || e.message), 'error');
        }
    };

    const deleteRate = async (id) => {
        showConfirm('Delete Rate', 'Are you sure you want to delete this exchange rate?', async () => {
            try {
                await api.delete(`/organization/exchange-rates/${id}`);
                fetchRates();
                showAlert('Success', 'Rate deleted.', 'success');
            } catch (e) {
                showAlert('Error', 'Failed to delete rate.', 'error');
            }
        });
    };

    const handleEditSave = async () => {
        if (!editingRate?.rate) return showAlert('Required', 'Please enter a rate', 'warning');
        try {
            await api.put(`/organization/exchange-rates/${editingRate.id}`, editingRate);
            setEditingRate(null);
            fetchRates();
            showAlert('Success', 'Exchange rate updated.', 'success');
        } catch (e) {
            showAlert('Error', 'Update failed: ' + (e.response?.data?.message || e.message), 'error');
        }
    };

    return (
        <div style={{ marginTop: '32px' }}>
            <h4 style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '16px', fontSize: '15px', color: 'var(--text-main)' }}>
                <DollarSign size={18} style={{ color: 'var(--primary-brand)' }} /> Currency Configuration (Exchange Rates)
            </h4>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1.5fr auto', gap: '12px', alignItems: 'end', marginBottom: '24px', background: '#f8fafc', padding: '16px', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                <div>
                    <label className="form-label" style={{ fontSize: '13px' }}>From</label>
                    <select className="form-input" value={form.from_currency}
                        onChange={e => setForm({ ...form, from_currency: e.target.value })}>
                        {currencies.map(c => <option key={c} value={c}>{c}</option>)}
                    </select>
                </div>
                <div>
                    <label className="form-label" style={{ fontSize: '13px' }}>To</label>
                    <select className="form-input" value={form.to_currency}
                        onChange={e => setForm({ ...form, to_currency: e.target.value })}>
                        {currencies.map(c => <option key={c} value={c}>{c}</option>)}
                    </select>
                </div>
                <div>
                    <label className="form-label" style={{ fontSize: '13px' }}>Rate</label>
                    <input className="form-input" type="number" step="any" placeholder="1.0000"
                        value={form.rate} onChange={e => setForm({ ...form, rate: e.target.value })} />
                </div>
                <div>
                    <label className="form-label" style={{ fontSize: '13px' }}>Effective From Date</label>
                    <DateInput
                        value={form.effective_date} onChange={val => setForm({ ...form, effective_date: val })} />
                </div>
                <button className="btn btn-primary" style={{ height: '40px', padding: '0 16px' }} onClick={addRate}><Plus size={16} /> Add Rate</button>
            </div>

            {loading ? <div style={{ textAlign: 'center', padding: '20px' }}><Loader size={20} className="spin" /> Loading rates...</div> : (
                <div style={{ overflowX: 'auto' }}>
                    <table className="data-table">
                        <thead>
                            <tr><th>Pair</th><th>Rate</th><th>Effective From</th><th>Status</th><th style={{ textAlign: 'center' }}>Actions</th></tr>
                        </thead>
                        <tbody>
                            {rates.length === 0 ? (
                                <tr><td colSpan={5} style={{ textAlign: 'center', color: 'var(--text-secondary)' }}>No exchange rates configured</td></tr>
                            ) : rates.map(r => {
                                const isEditing = editingRate?.id === r.id;
                                return (
                                <tr key={r.id}>
                                    <td style={{ fontWeight: '500' }}>
                                        {isEditing ? (
                                            <div style={{ display: 'flex', gap: '4px' }}>
                                                <input className="form-input" style={{ width: '60px', padding: '4px' }} value={editingRate.from_currency} 
                                                    onChange={e => setEditingRate({...editingRate, from_currency: e.target.value})} />
                                                /
                                                <input className="form-input" style={{ width: '60px', padding: '4px' }} value={editingRate.to_currency} 
                                                    onChange={e => setEditingRate({...editingRate, to_currency: e.target.value})} />
                                            </div>
                                        ) : `${r.from_currency} / ${r.to_currency}`}
                                    </td>
                                    <td>
                                        {isEditing ? (
                                            <input type="number" step="any" className="form-input" style={{ width: '100px', padding: '4px' }} value={editingRate.rate} 
                                                onChange={e => setEditingRate({...editingRate, rate: e.target.value})} />
                                        ) : Number(r.rate).toFixed(6)}
                                    </td>
                                    <td>
                                        {isEditing ? (
                                            <DateInput style={{ width: '150px' }} value={editingRate.effective_date} 
                                                onChange={val => setEditingRate({...editingRate, effective_date: val})} />
                                        ) : r.effective_date}
                                    </td>
                                    <td>
                                        <span style={{
                                            padding: '4px 8px', borderRadius: '12px', fontSize: '11px', fontWeight: '600',
                                            backgroundColor: r.is_active ? '#ecfdf5' : '#f3f4f6', color: r.is_active ? '#065f46' : '#6b7280'
                                        }}>
                                            {r.is_active ? 'Active' : 'Archived'}
                                        </span>
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                            {isEditing ? (
                                                <>
                                                    <button onClick={handleEditSave} style={{ color: '#059669', background: 'none', border: 'none', cursor: 'pointer', fontWeight: '600' }}>Save</button>
                                                    <button onClick={() => setEditingRate(null)} style={{ color: '#6b7280', background: 'none', border: 'none', cursor: 'pointer' }}>Cancel</button>
                                                </>
                                            ) : (
                                                <>
                                                    <button onClick={() => setEditingRate({...r})} style={{ background: 'none', border: 'none', color: '#2563eb', cursor: 'pointer', fontSize: '13px' }}>Edit</button>
                                                    <button onClick={() => deleteRate(r.id)} style={{ background: 'none', border: 'none', color: '#dc2626', cursor: 'pointer', fontSize: '13px' }}>Delete</button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            )})}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};

/* ── Organization Panel (Live CRUD) ─────────────────────── */
const OrgPanel = () => {
    const navigate = useNavigate();
    const [countries, setCountries] = useState([]);
    const [companies, setCompanies] = useState([]);
    const [departments, setDepartments] = useState([]);
    const [designations, setDesignations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [activeForm, setActiveForm] = useState(null);
    const [openSection, setOpenSection] = useState(null);
    const { showAlert, showConfirm } = useNotificationStore();

    const fetchAll = async () => {
        setLoading(true);
        const [cRes, coRes, dRes, dgRes] = await Promise.allSettled([
            api.get('/organization/countries'),
            api.get('/organization/companies'),
            api.get('/organization/departments'),
            api.get('/organization/designations'),
        ]);
        if (cRes.status === 'fulfilled') setCountries(cRes.value.data?.data || cRes.value.data || []);
        if (coRes.status === 'fulfilled') setCompanies(coRes.value.data?.data || coRes.value.data || []);
        if (dRes.status === 'fulfilled') setDepartments(dRes.value.data?.data || dRes.value.data || []);
        if (dgRes.status === 'fulfilled') setDesignations(dgRes.value.data?.data || dgRes.value.data || []);
        setLoading(false);
    };

    useEffect(() => { fetchAll(); }, []);

    /* ── Company Form ── */
    const CompanyForm = () => {
        const [f, setF] = useState({ name: '', timezone: '', address: '', contact_phone: '', contact_email: '', country_name: '', country_iso: '', country_currency: '', country_timezone: '', attendance_mode: 'time_based' });
        const [searchCountry, setSearchCountry] = useState('');
        const [showCountryDropdown, setShowCountryDropdown] = useState(false);

        const filteredCountries = COUNTRY_DATA.filter(c => c.name.toLowerCase().includes(searchCountry.toLowerCase()));

        const selectCountry = (c) => {
            setF({ ...f, country_name: c.name, country_iso: c.iso_code, country_currency: c.currency_code, country_timezone: c.default_timezone, timezone: c.default_timezone || '' });
            setSearchCountry(c.name);
            setShowCountryDropdown(false);
        };

        const submit = async () => {
            if (!f.name || !f.country_name || !f.timezone) return showAlert('Required', 'Name, Country and Timezone are required', 'warning');
            try {
                let countryId = countries.find(c => c.iso_code === f.country_iso)?.id;
                if (!countryId) {
                    const countryRes = await api.post('/organization/countries', {
                        name: f.country_name, iso_code: f.country_iso, currency_code: f.country_currency, default_timezone: f.country_timezone
                    });
                    countryId = countryRes.data?.data?.id || countryRes.data?.id;
                }
                await api.post('/organization/companies', { ...f, country_id: countryId });
                setActiveForm(null);
                fetchAll();
                showAlert('Success', 'Legal entity created.', 'success');
            }
            catch (e) { showAlert('Error', 'Error: ' + (e.response?.data?.message || e.message), 'error'); }
        };

        return (
            <div style={{
                display: 'grid', gridTemplateColumns: '1.5fr 1fr 1fr auto', gap: '15px', alignItems: 'end', marginTop: '16px',
                background: 'var(--bg-light-gray)', padding: '20px', borderRadius: '12px', border: '1px solid var(--border-gray)'
            }}>
                <div style={{ position: 'relative' }}>
                    <label className="form-label" style={{ fontSize: '13px', fontWeight: '500' }}>Operating Country</label>
                    <input className="form-input" style={{ fontSize: '14px', padding: '8px 12px', borderRadius: '6px', width: '100%' }} placeholder="Search country..."
                        value={searchCountry} onChange={e => { setSearchCountry(e.target.value); setShowCountryDropdown(true); setF({ ...f, country_id: '' }); }}
                        onFocus={() => setShowCountryDropdown(true)} />
                    {showCountryDropdown && searchCountry.length > 0 && (
                        <div style={{
                            position: 'absolute', top: 'calc(100% + 4px)', left: 0, right: 0, maxHeight: '250px', overflowY: 'auto',
                            background: '#ffffff', border: '1px solid #d1d5db', borderRadius: '6px', zIndex: 9999, boxShadow: '0 10px 25px rgba(0,0,0,0.2)'
                        }}>
                            {filteredCountries.length > 0 ? filteredCountries.map(c => (
                                <div key={c.id} style={{ padding: '10px 12px', cursor: 'pointer', fontSize: '13px', borderBottom: '1px solid #e5e7eb', display: 'flex', flexDirection: 'column', gap: '2px' }}
                                    onMouseDown={() => selectCountry(c)}
                                    onMouseEnter={e => e.currentTarget.style.backgroundColor = '#f3f4f6'}
                                    onMouseLeave={e => e.currentTarget.style.backgroundColor = 'transparent'}>
                                    <strong style={{ color: '#111827' }}>{c.name}</strong>
                                    <span style={{ color: '#6b7280', fontSize: '11px' }}>ISO: {c.iso_code} • Curr: {c.currency_code} • TZ: {c.default_timezone}</span>
                                </div>
                            )) : <div style={{ padding: '10px 12px', fontSize: '13px', color: '#6b7280' }}>No matches found</div>}
                        </div>
                    )}
                </div>
                <div><label className="form-label" style={{ fontSize: '13px', fontWeight: '500' }}>Legal Entity Name</label>
                    <input className="form-input" style={{ fontSize: '14px', padding: '8px 12px', borderRadius: '6px' }} value={f.name} onChange={e => setF({ ...f, name: e.target.value })} /></div>
                <div><label className="form-label" style={{ fontSize: '13px', fontWeight: '500' }}>Default Timezone</label>
                    <input className="form-input" style={{ fontSize: '14px', padding: '8px 12px', borderRadius: '6px' }} placeholder="e.g., Africa/Kampala" value={f.timezone} onChange={e => setF({ ...f, timezone: e.target.value })} /></div>
                <button className="btn btn-primary" style={{ padding: '8px 20px', fontSize: '14px', borderRadius: '6px', height: '40px', display: 'flex', alignItems: 'center', gap: '6px', fontWeight: '600', justifyContent: 'center' }} onClick={submit}><Plus size={16} /> Create Entity</button>
            </div>
        );
    };

    /* ── Department Form ── */
    const DeptForm = () => {
        const [f, setF] = useState({ name: '' });
        const submit = async () => {
            if (!f.name) return showAlert('Required', 'Department Name is required', 'warning');
            try { 
                await api.post('/organization/departments', f); 
                setActiveForm(null); 
                fetchAll(); 
                showAlert('Success', 'Department added.', 'success');
            }
            catch (e) { showAlert('Error', 'Error: ' + (e.response?.data?.message || e.message), 'error'); }
        };
        return (
            <div style={{
                display: 'grid', gridTemplateColumns: '1fr auto', gap: '10px', alignItems: 'end', marginTop: '16px',
                background: 'var(--bg-light-gray)', padding: '16px', borderRadius: '8px', border: '1px solid var(--border-gray)'
            }}>
                <div><label className="form-label" style={{ fontSize: '13px', fontWeight: '500' }}>Department Name</label>
                    <input className="form-input" style={{ fontSize: '14px', padding: '8px 12px', borderRadius: '6px' }} value={f.name} onChange={e => setF({ ...f, name: e.target.value })} /></div>
                <button className="btn btn-primary" style={{ padding: '8px 16px', fontSize: '14px', borderRadius: '6px', height: '40px', display: 'flex', alignItems: 'center', gap: '6px', fontWeight: '500', justifyContent: 'center' }} onClick={submit}><Plus size={16} /> Add</button>
            </div>
        );
    };

    /* ── Designation Form ── */
    const DesigForm = () => {
        const [f, setF] = useState({ department_id: departments[0]?.id || '', title: '', level: 0 });
        const submit = async () => {
            if (!f.title || !f.department_id) return showAlert('Required', 'Department and Title are required', 'warning');
            try { 
                await api.post('/organization/designations', f); 
                setActiveForm(null); 
                fetchAll(); 
                showAlert('Success', 'Designation added.', 'success');
            }
            catch (e) { showAlert('Error', 'Error: ' + (e.response?.data?.message || e.message), 'error'); }
        };
        return (
            <div style={{
                display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: '10px', alignItems: 'end', marginTop: '16px',
                background: 'var(--bg-light-gray)', padding: '16px', borderRadius: '8px', border: '1px solid var(--border-gray)'
            }}>
                <div><label className="form-label" style={{ fontSize: '13px', fontWeight: '500' }}>Department</label>
                    <select className="form-input" style={{ fontSize: '14px', padding: '8px 12px', borderRadius: '6px' }} value={f.department_id} onChange={e => setF({ ...f, department_id: e.target.value })}>
                        {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                    </select></div>
                <div><label className="form-label" style={{ fontSize: '13px', fontWeight: '500' }}>Title</label>
                    <input className="form-input" style={{ fontSize: '14px', padding: '8px 12px', borderRadius: '6px' }} value={f.title} onChange={e => setF({ ...f, title: e.target.value })} /></div>
                <div><label className="form-label" style={{ fontSize: '13px', fontWeight: '500' }}>Level</label>
                    <input className="form-input" type="number" style={{ fontSize: '14px', padding: '8px 12px', borderRadius: '6px' }} value={f.level} onChange={e => setF({ ...f, level: e.target.value })} /></div>
                <button className="btn btn-primary" style={{ padding: '8px 16px', fontSize: '14px', borderRadius: '6px', height: '40px', display: 'flex', alignItems: 'center', gap: '6px', fontWeight: '500', justifyContent: 'center' }} onClick={submit}><Plus size={16} /> Add</button>
            </div>
        );
    };

    const apiMap = {
        countries: '/organization/countries',
        companies: '/organization/companies',
        departments: '/organization/departments',
        designations: '/organization/designations',
    };

    const sections = [
        { key: 'countries', title: 'Countries', singular: 'Country', data: countries, columns: ['name', 'iso_code', 'currency_code', 'default_timezone'], headers: ['Name', 'ISO', 'Currency', 'Timezone'], Form: null },
        { key: 'companies', title: 'Companies', singular: 'Company', data: companies, columns: ['name', 'country_name'], headers: ['Company Name', 'Primary Region'], Form: CompanyForm },
        { key: 'departments', title: 'Departments', singular: 'Department', data: departments, columns: ['name'], headers: ['Name'], Form: DeptForm },
        { key: 'designations', title: 'Designations', singular: 'Designation', data: designations, columns: ['title', 'department_name', 'level'], headers: ['Title', 'Department', 'Level'], Form: DesigForm },
    ];

    const [editingRow, setEditingRow] = useState(null); // { section, id, data }

    const handleDelete = async (sectionKey, id, name) => {
        showConfirm('Delete Record', `Are you sure you want to delete "${name}"? This action cannot be undone.`, async () => {
            try {
                await api.delete(`${apiMap[sectionKey]}/${id}`);
                fetchAll();
                showAlert('Success', 'Record deleted successfully', 'success');
            } catch (e) {
                showAlert('Error', 'Delete failed: ' + (e.response?.data?.message || e.message), 'error');
            }
        });
    };

    const handleEditSave = async (sectionKey) => {
        if (!editingRow) return;
        try {
            await api.put(`${apiMap[sectionKey]}/${editingRow.id}`, editingRow.data);
            setEditingRow(null);
            fetchAll();
            showAlert('Success', 'Record updated successfully', 'success');
        } catch (e) {
            showAlert('Error', 'Update failed: ' + (e.response?.data?.message || e.message), 'error');
        }
    };

    const startEdit = (sectionKey, row) => {
        setEditingRow({ section: sectionKey, id: row.id, data: { ...row } });
    };

    const updateEditField = (field, value) => {
        setEditingRow(prev => ({ ...prev, data: { ...prev.data, [field]: value } }));
    };

    if (loading) return <div className="card" style={{ textAlign: 'center', padding: '40px' }}><Loader size={24} className="spin" /> Loading organization data...</div>;

    return (
        <div style={{ display: 'grid', gap: '20px' }}>
            {sections.map(s => {
                const isOpen = openSection === s.key;
                return (
                    <div className="card" key={s.key}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer', userSelect: 'none' }} onClick={() => setOpenSection(isOpen ? null : s.key)}>
                            <div>
                                <h3 className="card-title">{s.title}</h3>
                                <p style={{ color: 'var(--text-secondary)', fontSize: '13px', marginTop: '4px' }}>{s.data.length} records</p>
                            </div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                                {s.Form && isOpen && (
                                    <button className="btn btn-primary" style={{ padding: '8px 16px', fontSize: '14px', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '6px', fontWeight: '500' }}
                                        onClick={(e) => { e.stopPropagation(); setActiveForm(activeForm === s.key ? null : s.key); }}>
                                        <Plus size={16} /> Add {s.singular}
                                    </button>
                                )}
                                <ChevronDown size={20} style={{ color: '#6b7280', transform: isOpen ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s' }} />
                            </div>
                        </div>

                        {isOpen && (
                            <div style={{ marginTop: '16px', borderTop: '1px solid #e5e7eb', paddingTop: '16px' }}>
                                {s.Form && activeForm === s.key && <s.Form />}

                                {s.data.length > 0 && (
                                    <div style={{ marginTop: '12px', overflowX: 'auto', paddingBottom: '30px' }}>
                                        <table className="data-table">
                                            <thead>
                                                <tr>
                                                    {s.headers.map(h => <th key={h}>{h}</th>)}
                                                    <th style={{ width: '120px', textAlign: 'center', position: 'sticky', right: 0, background: '#f9fafb', zIndex: 10, boxShadow: '-5px 0 10px -5px rgba(0,0,0,0.05)' }}>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {s.data.map((row, i) => {
                                                    const isEditing = editingRow?.section === s.key && editingRow?.id === row.id;
                                                    return (
                                                        <tr key={row.id || i}>
                                                            {isEditing ? (
                                                                <>
                                                                    {s.columns.map(col => {
                                                                        if (s.key === 'companies' && col === 'country_name') {
                                                                            return (
                                                                                <td key={col}>
                                                                                    <select className="form-input" style={{ fontSize: '13px', padding: '6px 8px', margin: 0, borderRadius: '6px', maxWidth: '160px' }} value={editingRow.data.country_id || ''} onChange={e => {
                                                                                        const cId = parseInt(e.target.value);
                                                                                        const cName = countries.find(c => c.id === cId)?.name || '';
                                                                                        setEditingRow(prev => ({ ...prev, data: { ...prev.data, country_id: cId, country_name: cName } }));
                                                                                    }}>
                                                                                        {countries.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                                                                    </select>
                                                                                </td>
                                                                            );
                                                                        }
                                                                        if (s.key === 'companies' && col === 'attendance_mode') {
                                                                            return (
                                                                                <td key={col}>
                                                                                    <select className="form-input" style={{ fontSize: '13px', padding: '6px 8px', margin: 0, borderRadius: '6px' }} value={editingRow.data.attendance_mode || 'time_based'} onChange={e => updateEditField('attendance_mode', e.target.value)}>
                                                                                        <option value="time_based">Time Based</option>
                                                                                        <option value="status_based">Status Based</option>
                                                                                    </select>
                                                                                </td>
                                                                            );
                                                                        }
                                                                        if (s.key === 'designations' && col === 'department_name') {
                                                                            return (
                                                                                <td key={col}>
                                                                                    <select className="form-input" style={{ fontSize: '13px', padding: '6px 8px', margin: 0, borderRadius: '6px' }} value={editingRow.data.department_id || ''} onChange={e => {
                                                                                        const dId = parseInt(e.target.value);
                                                                                        const dName = departments.find(d => d.id === dId)?.name || '';
                                                                                        setEditingRow(prev => ({ ...prev, data: { ...prev.data, department_id: dId, department_name: dName } }));
                                                                                    }}>
                                                                                        {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                                                                    </select>
                                                                                </td>
                                                                            );
                                                                        }
                                                                        return (
                                                                            <td key={col}>
                                                                                <input className="form-input" style={{ fontSize: '13px', padding: '6px 8px', margin: 0, borderRadius: '6px', minWidth: '100px', maxWidth: '160px', backgroundColor: (s.key === 'countries' && col !== 'default_timezone') ? '#f3f4f6' : '' }}
                                                                                    type={col === 'level' ? 'number' : 'text'}
                                                                                    readOnly={s.key === 'countries' && col !== 'default_timezone'}
                                                                                    value={editingRow.data[col] || ''} onChange={e => updateEditField(col, e.target.value)} />
                                                                            </td>
                                                                        );
                                                                    })}
                                                                    <td style={{ textAlign: 'center', position: 'sticky', right: 0, background: '#ffffff', zIndex: 10, borderLeft: '1px solid #e5e7eb', boxShadow: '-5px 0 10px -5px rgba(0,0,0,0.05)' }}>
                                                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                                                            <button onClick={() => handleEditSave(s.key)} title="Save"
                                                                                style={{ background: '#286B3E', color: '#ffffff', border: '1px solid #286B3E', borderRadius: '6px', padding: '6px 12px', cursor: 'pointer', fontSize: '13px', fontWeight: '500', transition: 'all 0.2s', display: 'inline-block', visibility: 'visible' }}>
                                                                                Save
                                                                            </button>
                                                                            <button onClick={() => setEditingRow(null)} title="Cancel"
                                                                                style={{ background: '#f3f4f6', color: '#4b5563', border: '1px solid #d1d5db', borderRadius: '6px', padding: '6px 12px', cursor: 'pointer', fontSize: '13px', fontWeight: '500', transition: 'all 0.2s' }}>
                                                                                Cancel
                                                                            </button>
                                                                        </div>
                                                                    </td>
                                                                </>
                                                            ) : (
                                                                <>
                                                                    {s.columns.map(col => <td key={col}>{row[col] || '—'}</td>)}
                                                                    <td style={{ textAlign: 'center', position: 'sticky', right: 0, background: '#ffffff', zIndex: 10, borderLeft: '1px solid #e5e7eb', boxShadow: '-5px 0 10px -5px rgba(0,0,0,0.05)' }}>
                                                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                                                            {s.key === 'companies' ? (
                                                                                <button onClick={() => navigate(`/admin/company/${row.id}`)} title="Configure"
                                                                                    style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '6px', padding: '6px 12px', cursor: 'pointer', fontSize: '12px', color: '#64748b', fontWeight: '600', display: 'flex', alignItems: 'center', gap: '5px', transition: 'all 0.2s' }}>
                                                                                    <Settings size={13} /> Configure
                                                                                </button>
                                                                            ) : (
                                                                                <button onClick={() => startEdit(s.key, row)} title="Edit"
                                                                                    style={{ background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: '6px', padding: '6px 12px', cursor: 'pointer', fontSize: '13px', color: '#2563eb', fontWeight: '500', transition: 'all 0.2s' }}>
                                                                                    Edit
                                                                                </button>
                                                                            )}
                                                                            {s.key !== 'companies' && (
                                                                                <button onClick={async () => {
                                                                                    showConfirm('Confirm Delete', `Are you sure you want to delete ${row.name || row.title}?`, async () => {
                                                                                        try {
                                                                                            await api.delete(`${apiMap[s.key]}/${row.id}`);
                                                                                            fetchAll();
                                                                                            showAlert('Success', 'Deleted successfully!', 'success');
                                                                                        } catch (e) {
                                                                                            showAlert('Error', 'Delete failed.', 'error');
                                                                                        }
                                                                                    });
                                                                                }} title="Delete"
                                                                                    style={{ background: '#fef2f2', border: '1px solid #fecaca', borderRadius: '6px', padding: '6px 12px', cursor: 'pointer', fontSize: '13px', color: '#dc2626', fontWeight: '500', transition: 'all 0.2s' }}>
                                                                                    Delete
                                                                                </button>
                                                                            )}
                                                                        </div>
                                                                    </td>
                                                                </>
                                                            )}
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {s.key === 'countries' && <CurrencyConfiguration />}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
};

/* ── Documents Panel ─────────────────────────────────────── */
const DocumentsPanel = () => {
    const [docs, setDocs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [countries, setCountries] = useState([]);
    const [companies, setCompanies] = useState([]);
    const [form, setForm] = useState({ document_name: '', category: 'Policy', company_id: '', country_id: '' });
    const [file, setFile] = useState(null);
    const [editingId, setEditingId] = useState(null);
    const [editForm, setEditForm] = useState({ document_name: '', category: 'Policy', company_id: '', country_id: '' });
    const { showAlert, showConfirm } = useNotificationStore();

    const fetchDocs = async () => {
        setLoading(true);
        try {
            const res = await api.get('/company-documents/all');
            setDocs(res.data?.data || []);
        } catch (e) {
            console.error("Failed to fetch documents", e);
        } finally {
            setLoading(false);
        }
    };

    const fetchMeta = async () => {
        try {
            const [cRes, coRes] = await Promise.all([
                api.get('/organization/countries'),
                api.get('/organization/companies')
            ]);
            setCountries(cRes.data?.data || []);
            setCompanies(coRes.data?.data || []);
        } catch (e) {}
    };

    useEffect(() => { 
        fetchDocs(); 
        fetchMeta();
    }, []);

    const handleUpload = async () => {
        if (!file || !form.document_name) {
            return showAlert('Required', 'Please provide a document name and select a file.', 'warning');
        }

        setUploading(true);
        const formData = new FormData();
        formData.append('document', file);
        formData.append('document_name', form.document_name);
        formData.append('category', form.category);
        if (form.company_id) formData.append('company_id', form.company_id);
        if (form.country_id) formData.append('country_id', form.country_id);

        try {
            await api.post('/company-documents', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            showAlert('Success', 'Document uploaded successfully.', 'success');
            setForm({ document_name: '', category: 'Policy', company_id: '', country_id: '' });
            setFile(null);
            fetchDocs();
        } catch (e) {
            showAlert('Error', e.response?.data?.message || 'Upload failed.', 'error');
        } finally {
            setUploading(false);
        }
    };

    const handleDelete = (id) => {
        showConfirm('Delete Document', 'Are you sure you want to remove this reference document?', async () => {
            try {
                await api.delete(`/company-documents/${id}`);
                fetchDocs();
                showAlert('Success', 'Document deleted.', 'success');
            } catch (e) {
                showAlert('Error', 'Failed to delete document.', 'error');
            }
        });
    };

    const handleEditSave = async (id) => {
        if (!editForm.document_name) return showAlert('Required', 'Document name is required', 'warning');
        try {
            await api.put(`/company-documents/${id}`, editForm);
            setEditingId(null);
            fetchDocs();
            showAlert('Success', 'Document updated successfully.', 'success');
        } catch (e) {
            showAlert('Error', e.response?.data?.message || 'Update failed.', 'error');
        }
    };

    const startEditing = (doc) => {
        setEditingId(doc.id);
        setEditForm({
            document_name: doc.document_name,
            category: doc.category,
            company_id: doc.company_id || '',
            country_id: doc.country_id || ''
        });
    };

    return (
        <div style={{ display: 'grid', gap: '24px' }}>
            <div className="card">
                <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '20px' }}>
                    <Upload size={20} style={{ color: 'var(--primary-brand)' }} /> Upload Reference Document
                </h3>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr auto', gap: '12px', alignItems: 'end', background: '#f8fafc', padding: '20px', borderRadius: '12px' }}>
                    <div>
                        <label className="form-label">Document Name</label>
                        <input className="form-input" value={form.document_name} onChange={e => setForm({...form, document_name: e.target.value})} placeholder="e.g. Employment Act 2026" />
                    </div>
                    <div>
                        <label className="form-label">Category</label>
                        <select className="form-input" value={form.category} onChange={e => setForm({...form, category: e.target.value})}>
                            <option value="Policy">Policy</option>
                            <option value="Law">Law</option>
                            <option value="Manual">Manual</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label className="form-label">Company (Optional)</label>
                        <select className="form-input" value={form.company_id} onChange={e => setForm({...form, company_id: e.target.value, country_id: ''})}>
                            <option value="">Global / All Offices</option>
                            {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="form-label">Country (Optional)</label>
                        <select className="form-input" value={form.country_id} onChange={e => setForm({...form, country_id: e.target.value, company_id: ''})}>
                            <option value="">Global / All Regions</option>
                            {countries.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                    <div>
                         <label className="form-label" style={{ visibility: 'hidden' }}>File</label>
                         <div style={{ display: 'flex', gap: '8px' }}>
                            <input type="file" id="admin-doc-upload" hidden onChange={e => setFile(e.target.files[0])} />
                            <button className="btn btn-secondary" onClick={() => document.getElementById('admin-doc-upload').click()} style={{ padding: '8px 12px', fontSize: '13px' }}>
                                {file ? 'Change File' : 'Select File'}
                            </button>
                            <button className="btn btn-primary" onClick={handleUpload} disabled={uploading}>
                                {uploading ? <Loader size={14} className="spin" /> : <Plus size={14} />} Upload
                            </button>
                         </div>
                    </div>
                </div>
                {file && <div style={{ marginTop: '8px', fontSize: '12px', color: 'var(--primary-brand)', fontWeight: '600' }}>Selected: {file.name}</div>}
            </div>

            <div className="card">
                <h3 className="card-title">Document Registry</h3>
                {loading ? <div style={{ textAlign: 'center', padding: '20px' }}><Loader className="spin" /> Loading docs...</div> : (
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Scope</th>
                                <th>Uploaded At</th>
                                <th style={{ textAlign: 'center' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {docs.length === 0 ? <tr><td colSpan={5} style={{ textAlign: 'center' }}>No documents registered.</td></tr> : docs.map(d => {
                                const isEditing = editingId === d.id;
                                return (
                                    <tr key={d.id}>
                                        <td>
                                            {isEditing ? (
                                                <input className="form-input" style={{ fontSize: '13px', padding: '4px 8px' }} value={editForm.document_name} onChange={e => setEditForm({...editForm, document_name: e.target.value})} />
                                            ) : (
                                                <span style={{ fontWeight: '600' }}>{d.document_name}</span>
                                            )}
                                        </td>
                                        <td>
                                            {isEditing ? (
                                                <select className="form-input" style={{ fontSize: '13px', padding: '4px 8px' }} value={editForm.category} onChange={e => setEditForm({...editForm, category: e.target.value})}>
                                                    <option value="Policy">Policy</option>
                                                    <option value="Law">Law</option>
                                                    <option value="Manual">Manual</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            ) : (
                                                <span style={{ 
                                                    padding: '4px 8px', borderRadius: '6px', fontSize: '11px', fontWeight: '700',
                                                    background: '#f1f5f9', color: '#475569'
                                                }}>{d.category}</span>
                                            )}
                                        </td>
                                        <td>
                                            {isEditing ? (
                                                <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                                    <select className="form-input" style={{ fontSize: '12px', padding: '4px' }} value={editForm.company_id} onChange={e => setEditForm({...editForm, company_id: e.target.value, country_id: ''})}>
                                                        <option value="">Global / All Offices</option>
                                                        {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                                    </select>
                                                    <select className="form-input" style={{ fontSize: '12px', padding: '4px' }} value={editForm.country_id} onChange={e => setEditForm({...editForm, country_id: e.target.value, company_id: ''})}>
                                                        <option value="">Global / All Regions</option>
                                                        {countries.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                                    </select>
                                                </div>
                                            ) : (
                                                d.company_name ? `🏢 ${d.company_name}` : d.country_name ? `🌍 ${d.country_name}` : '🌐 Global'
                                            )}
                                        </td>
                                        <td>{formatDate(d.created_at_utc)}</td>
                                        <td>
                                            <div style={{ display: 'flex', justifyContent: 'center', gap: '12px', alignItems: 'center' }}>
                                                {isEditing ? (
                                                    <>
                                                        <button onClick={() => handleEditSave(d.id)} style={{ background: 'none', border: 'none', color: '#10b981', cursor: 'pointer', fontSize: '13px', fontWeight: '700' }}>Save</button>
                                                        <button onClick={() => setEditingId(null)} style={{ background: 'none', border: 'none', color: '#64748b', cursor: 'pointer', fontSize: '13px', fontWeight: '600' }}>Cancel</button>
                                                    </>
                                                ) : (
                                                    <>
                                                        <a href={getSecureMediaUrl(d.file_path)} target="_blank" rel="noreferrer" style={{ color: 'var(--primary-brand)', fontSize: '13px', fontWeight: '600' }}>View</a>
                                                        <button onClick={() => startEditing(d)} style={{ background: 'none', border: 'none', color: '#2563eb', cursor: 'pointer', fontSize: '13px', fontWeight: '600' }}>Edit</button>
                                                        <button onClick={() => handleDelete(d.id)} style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '13px', fontWeight: '600' }}>Delete</button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
};

/* ── Global Settings Panel (Live) ───────────────────────── */
const GlobalPanel = () => {
    const [settings, setSettings] = useState({});
    const [loading, setLoading] = useState(true);

    const toggles = [
        { key: 'payroll_attendance_linkage', title: 'Link Attendance to Payroll Calculation', desc: 'When enabled, unpaid absences from attendance logs will be automatically deducted during payroll generation.', category: 'payroll' },
        { key: 'auto_leave_deduct', title: 'Auto-Deduct Leave on Absence', desc: 'Automatically file leave for unexplained absences if leave balance is available.', category: 'leave' },
        { key: 'enforce_clock_in', title: 'Enforce Clock-in/Clock-out for Time-Tracking Offices', desc: 'Mandate biometric or manual clock entries before marking attendance as present.', category: 'attendance' },
    ];

    useEffect(() => {
        api.get('/organization/settings').then(res => {
            const list = res.data?.data || res.data || [];
            const map = {};
            list.forEach(s => { map[s.setting_key] = s.setting_value; });
            setSettings(map);
            setLoading(false);
        }).catch(() => setLoading(false));
    }, []);

    const toggleSetting = async (key, category) => {
        const current = settings[key] === 'on';
        const newVal = current ? 'off' : 'on';
        setSettings({ ...settings, [key]: newVal });
        try {
            await api.post('/organization/settings', { setting_key: key, setting_value: newVal, category });
        } catch (e) {
            setSettings({ ...settings, [key]: current ? 'on' : 'off' });
            alert('Failed to update setting');
        }
    };

    const saveSetting = async (key, category) => {
        const value = settings[key];
        try {
            await api.post('/organization/settings', { setting_key: key, setting_value: value, category });
            alert('Setting updated successfully');
        } catch (e) {
            alert('Failed to update setting');
        }
    };

    if (loading) return (
        <div className="card" style={{ textAlign: 'center', padding: '60px' }}>
            <div className="loader-content">
                <div className="loader-spinner"></div>
                <div className="loader-text">PREPARING SYSTEM...</div>
            </div>
        </div>
    );

    return (
        <div className="card">
            <h3 className="card-title" style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '24px' }}>
                <Settings size={20} style={{ color: 'var(--primary-brand)' }} /> Global System Settings
            </h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                {toggles.map(t => (
                    <div key={t.key} style={{
                        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                        padding: '16px', border: '1px solid var(--border-gray)', borderRadius: '8px'
                    }}>
                        <div style={{ maxWidth: '80%' }}>
                            <h4 style={{ fontSize: '14px', marginBottom: '4px' }}>{t.title}</h4>
                            <p style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>{t.desc}</p>
                        </div>
                        <div onClick={() => toggleSetting(t.key, t.category)} style={{
                            width: '44px', height: '24px', borderRadius: '24px', cursor: 'pointer', position: 'relative',
                            backgroundColor: settings[t.key] === 'on' ? 'var(--primary-brand)' : '#ccc', transition: 'background-color 0.3s', flexShrink: 0
                        }}>
                            <div style={{
                                width: '20px', height: '20px', borderRadius: '50%', backgroundColor: '#fff',
                                position: 'absolute', top: '2px',
                                left: settings[t.key] === 'on' ? '22px' : '2px',
                                transition: 'left 0.3s', boxShadow: '0 1px 3px rgba(0,0,0,0.2)'
                            }} />
                        </div>
                    </div>
                ))}
            </div>
        </div >
    );
};

/* ── Main Admin Page ────────────────────────────────────── */
const Admin = () => {
    const navigate = useNavigate();
    const { user } = useAuthStore();
    const normalizedRole = user?.role || '';
    
    const isSuperAdmin = user?.role === 'Super Admin';
    const hasConfigView = useAuthStore.getState().hasPermission('configuration', 'view');
    const hasConfigEdit = useAuthStore.getState().hasPermission('configuration', 'edit');

    const filteredTabs = TABS.filter(tab => {
        if (tab.id === 'rbac') return isSuperAdmin || hasConfigEdit;
        return hasConfigView;
    });

    const isEmployeeView = localStorage.getItem('adminViewMode') === 'employee';
    const [activeTab, setActiveTab] = useState(filteredTabs[0]?.id || 'org');
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        if (isEmployeeView) {
            navigate('/employee-profile');
        }
    }, [isEmployeeView, navigate]);

    useEffect(() => {
        setPageTitle("System Configurations");
        setPageSubtitle("Manage Organization, RBAC, Form Templates, and Global Settings");
        setBackPath('/dashboard');
        return () => resetPageHeader();
    }, []);

    const renderPanel = () => {
        switch (activeTab) {
            case 'rbac': return <RbacPanel />;
            case 'org': return <OrgPanel />;
            case 'documents': return <DocumentsPanel />;
            case 'global': return <GlobalPanel />;
            default: return null;
        }
    };

    return (
        <div>

            {/* Tab Bar */}
            <div style={{
                display: 'flex', gap: '4px', marginBottom: '24px',
                borderBottom: '1px solid #e5e7eb', paddingBottom: '0'
            }}>
                {filteredTabs.map(tab => (
                    <button
                        key={tab.id}
                        onClick={() => setActiveTab(tab.id)}
                        style={{
                            display: 'flex', alignItems: 'center', gap: '6px',
                            padding: '10px 16px', fontSize: '13px', fontWeight: '500',
                            background: 'none', border: 'none', cursor: 'pointer',
                            color: activeTab === tab.id ? 'var(--primary-brand)' : 'var(--text-secondary)',
                            borderBottom: activeTab === tab.id ? '2px solid var(--primary-brand)' : '2px solid transparent',
                            transition: 'all 0.2s',
                            marginBottom: '-1px'
                        }}
                    >
                        {tab.icon} {tab.label}
                    </button>
                ))}
            </div>

            {/* Active Panel */}
            {renderPanel()}
        </div>
    );
};

export default Admin;
