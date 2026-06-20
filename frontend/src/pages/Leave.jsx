import { getSecureMediaUrl } from '../utils/mediaHelper';
import { useState, useEffect, useMemo } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { 
    Calendar, Plus, Settings, Users, ClipboardList, Info, 
    Loader, Save, PlusCircle, Trash2, Edit2, ChevronDown, Clock, Globe, Paperclip,
    Eye, X, Image, Download, Lock, Search, Filter
} from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useLayoutStore from '../store/useLayoutStore';
import DateInput from '../components/ui/DateInput';
import Modal from '../components/ui/Modal';
import { formatDate } from '../utils/dateUtils';
import useNotificationStore from '../store/useNotificationStore';
import { ROLE_IDS } from '../utils/roleConstants';

/* ── Sub-components for Configuration ──────────────────── */

const LeaveTypesConfig = ({ companyId }) => {
    const [leaveTypes, setLeaveTypes] = useState([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [isAdding, setIsAdding] = useState(false);
    const [newType, setNewType] = useState({ name: '', is_paid: true, gender_restriction: 'none', color_code: '#6b7280' });
    const { showAlert, showConfirm } = useNotificationStore();

    const [editingType, setEditingType] = useState(null);

    const fetchLeaveTypes = async () => {
        if (!companyId) {
            setLoading(false);
            return;
        }
        setLoading(true);
        try {
            const res = await api.get(`/leave/types?company_id=${companyId}`);
            const data = res.data?.data || res.data;
            setLeaveTypes(Array.isArray(data) ? data : []);
        } catch (e) {
            console.error('Failed to fetch leave types', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchLeaveTypes(); }, [companyId]);

    const addLeaveType = async () => {
        if (!newType.name) return showAlert('Required', 'Name is required', 'warning');
        setSaving(true);
        try {
            await api.post('/leave/types', { ...newType, company_id: companyId });
            setIsAdding(false);
            setNewType({ name: '', is_paid: true, gender_restriction: 'none', color_code: '#6b7280' });
            fetchLeaveTypes();
            showAlert('Success', 'Leave type added.', 'success');
        } catch (e) {
            showAlert('Error', 'Failed to add leave type.', 'error');
        } finally {
            setSaving(false);
        }
    };

    const updateLeaveType = async (type) => {
        setSaving(true);
        try {
            await api.put('/leave/types', type);
            setEditingType(null);
            fetchLeaveTypes();
            showAlert('Success', 'Leave type updated.', 'success');
        } catch (e) {
            showAlert('Error', 'Failed to update leave type.', 'error');
        } finally {
            setSaving(false);
        }
    };

    const deleteLeaveType = async (id) => {
        showConfirm('Delete Leave Type', 'Are you sure you want to delete this leave type?', async () => {
            try {
                await api.delete(`/leave/types/${id}`);
                fetchLeaveTypes();
                showAlert('Success', 'Leave type deleted.', 'success');
            } catch (e) {
                showAlert('Error', 'Failed to delete leave type.', 'error');
            }
        });
    };

    if (loading) return (
        <div style={{ textAlign: 'center', padding: '60px' }}>
            <div className="loader-content">
                <div className="loader-spinner"></div>
                <div className="loader-text">SYNCING CONFIG...</div>
            </div>
        </div>
    );

    return (
        <div style={{ marginTop: '20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                <h4 style={{ margin: 0, fontSize: '15px' }}>Leave Categories</h4>
                <button className="btn btn-secondary" style={{ padding: '6px 12px', fontSize: '13px' }} onClick={() => setIsAdding(!isAdding)}>
                    <PlusCircle size={14} /> {isAdding ? 'Cancel' : 'Add Category'}
                </button>
            </div>

            {isAdding && (
                <div style={{ background: 'var(--color-ivory)', padding: '16px', borderRadius: '8px', border: '1px solid var(--color-border)', marginBottom: '16px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr auto auto auto', gap: '12px', alignItems: 'end' }}>
                        <div>
                            <label className="form-label" style={{ fontSize: '11px' }}>Name</label>
                            <input className="form-input" value={newType.name} onChange={e => setNewType({...newType, name: e.target.value})} placeholder="e.g., Annual Leave" />
                        </div>
                        <div style={{ paddingBottom: '10px' }}>
                            <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                                <input type="checkbox" checked={newType.is_paid} onChange={e => setNewType({...newType, is_paid: e.target.checked})} />
                                <span style={{ fontSize: '13px' }}>Is Paid</span>
                            </label>
                        </div>
                        <div>
                            <label className="form-label" style={{ fontSize: '11px' }}>Color & Gender</label>
                            <div style={{ display: 'flex', gap: '8px' }}>
                                <input type="color" className="form-input" style={{ padding: '2px', height: '38px', width: '40px' }} value={newType.color_code} onChange={e => setNewType({...newType, color_code: e.target.value})} />
                                <select className="form-input" value={newType.gender_restriction} onChange={e => setNewType({...newType, gender_restriction: e.target.value})}>
                                    <option value="none">Anyone</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        <button className="btn btn-primary" style={{ height: '38px', minWidth: '80px' }} onClick={addLeaveType} disabled={saving}>
                            {saving ? <Loader size={14} className="spin" /> : 'Save'}
                        </button>
                    </div>
                </div>
            )}

            <div className="table-container">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Status Code</th>
                            <th>Display</th>
                            <th>Color</th>
                            <th>Payment</th>
                            <th>Gender</th>
                            <th style={{ textAlign: 'center' }}>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {leaveTypes.map(lt => {
                            const isEditing = editingType?.id === lt.id;
                            
                            return (
                                <tr key={lt.id}>
                                    <td>
                                        {isEditing ? (
                                            <input className="form-input" value={editingType.name} style={{ padding: '4px 8px' }}
                                                onChange={e => setEditingType({...editingType, name: e.target.value})} />
                                        ) : lt.name}
                                    </td>
                                    <td>
                                        <code style={{ background: '#f1f5f9', padding: '2px 6px', borderRadius: '4px', fontSize: '11px', color: 'var(--color-primary)' }}>{lt.code}</code>
                                    </td>
                                    <td>
                                        <span style={{ fontWeight: '600', color: 'var(--color-rose-gold)' }}>{lt.code}</span>
                                    </td>
                                    <td>
                                        {isEditing ? (
                                            <input type="color" className="form-input" style={{ padding: '2px', height: '30px', width: '40px' }}
                                                value={editingType.color_code || '#6b7280'}
                                                onChange={e => setEditingType({...editingType, color_code: e.target.value})} />
                                        ) : (
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                <div style={{ width: '16px', height: '16px', borderRadius: '4px', background: lt.color_code || '#6b7280' }} />
                                            </div>
                                        )}
                                    </td>
                                    <td>
                                        {isEditing ? (
                                            <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                                                <input type="checkbox" checked={editingType.is_paid} onChange={e => setEditingType({...editingType, is_paid: e.target.checked})} />
                                                <span style={{ fontSize: '13px' }}>Paid</span>
                                            </label>
                                        ) : (
                                            <span className={`badge ${lt.is_paid ? 'badge-success' : 'badge-neutral'}`}>
                                                {lt.is_paid ? 'Paid' : 'Unpaid'}
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        {isEditing ? (
                                            <select className="form-input" style={{ padding: '4px 8px' }} value={editingType.gender_restriction} 
                                                onChange={e => setEditingType({...editingType, gender_restriction: e.target.value})}>
                                                <option value="none">None</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                            </select>
                                        ) : (
                                            <span style={{ fontSize: '12px', textTransform: 'capitalize' }}>{lt.gender_restriction || 'none'}</span>
                                        )}
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                            {isEditing ? (
                                                <>
                                                    <button className="btn-icon text-success" onClick={() => updateLeaveType(editingType)} disabled={saving}>
                                                        {saving ? <Loader size={14} className="spin" /> : <Save size={14} />}
                                                    </button>
                                                    <button className="btn-icon" onClick={() => setEditingType(null)}><Edit2 size={14} style={{ transform: 'rotate(180deg)' }} /></button>
                                                </>
                                            ) : (
                                                <>
                                                    <button className="btn-icon" title="Edit" onClick={() => setEditingType({...lt})}><Edit2 size={14} /></button>
                                                    <button className="btn-icon text-danger" title="Delete" onClick={() => deleteLeaveType(lt.id)}><Trash2 size={14} /></button>
                                                </>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

const HolidayConfig = ({ companyId }) => {
    const [holidays, setHolidays] = useState([]);
    const [loading, setLoading] = useState(false);
    const [isAdding, setIsAdding] = useState(false);
    const [newHoliday, setNewHoliday] = useState({ name: '', holiday_date: '', is_recurring: false });
    const [holidayYear, setHolidayYear] = useState(new Date().getFullYear());
    const { showAlert, showConfirm } = useNotificationStore();

    const fetchHolidays = async () => {
        if (!companyId) {
            setLoading(false);
            return;
        }
        setLoading(true);
        try {
            const res = await api.get(`/leave/holidays?company_id=${companyId}&year=${holidayYear}`);
            const data = res.data?.data || res.data;
            setHolidays(Array.isArray(data) ? data : []);
        } catch (e) {
            console.error('Failed to fetch holidays', e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchHolidays(); }, [companyId, holidayYear]);

    const copyFromPreviousYear = async () => {
        if (!window.confirm(`Copy non-recurring holidays from ${holidayYear - 1} to ${holidayYear}?`)) return;
        try {
            await api.post('/leave/holidays/copy', {
                company_id: companyId,
                from_year: holidayYear - 1,
                to_year: holidayYear
            });
            fetchHolidays();
            showAlert('Success', 'Holidays copied from previous year.', 'success');
        } catch (e) {
            showAlert('Error', e.response?.data?.message || 'Failed to copy holidays.', 'error');
        }
    };

    const addHoliday = async () => {
        if (!newHoliday.name || !newHoliday.holiday_date) return showAlert('Required', 'Name and Date are required', 'warning');
        try {
            await api.post('/leave/holidays', { ...newHoliday, company_id: companyId });
            setIsAdding(false);
            setNewHoliday({ name: '', holiday_date: '', is_recurring: false });
            fetchHolidays();
            showAlert('Success', 'Holiday added.', 'success');
        } catch (e) {
            showAlert('Error', 'Failed to add holiday.', 'error');
        }
    };

    const deleteHoliday = async (id) => {
        showConfirm('Delete Holiday', 'Are you sure you want to delete this holiday?', async () => {
            try {
                await api.delete(`/leave/holidays/${id}`);
                fetchHolidays();
                showAlert('Success', 'Holiday deleted.', 'success');
            } catch (e) {
                showAlert('Error', 'Failed to delete holiday.', 'error');
            }
        });
    };

    if (loading) return (
        <div style={{ textAlign: 'center', padding: '60px' }}>
            <div className="loader-content">
                <div className="loader-spinner"></div>
                <div className="loader-text">FETCHING HOLIDAYS...</div>
            </div>
        </div>
    );

    return (
        <div style={{ marginTop: '20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                <h4 style={{ margin: 0, fontSize: '15px' }}>Public Holidays</h4>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <button 
                        onClick={copyFromPreviousYear} 
                        className="btn btn-secondary" 
                        style={{ padding: '6px 12px', fontSize: '13px' }}
                        title={`Copy holidays from ${holidayYear - 1}`}
                    >
                        Copy from Previous
                    </button>
                    <label style={{ fontSize: '13px', fontWeight: '500' }}>Holiday Year:</label>
                    <select 
                        value={holidayYear} 
                        onChange={e => setHolidayYear(Number(e.target.value))}
                        className="form-input" 
                        style={{ padding: '6px 12px', fontSize: '13px', width: 'auto' }}
                    >
                        {[...Array(5)].map((_, i) => {
                            const y = new Date().getFullYear() - 2 + i;
                            return <option key={y} value={y}>{y}</option>;
                        })}
                    </select>
                    <button className="btn btn-secondary" style={{ padding: '6px 12px', fontSize: '13px' }} onClick={() => setIsAdding(!isAdding)}>
                        <PlusCircle size={14} /> {isAdding ? 'Cancel' : 'Add Holiday'}
                    </button>
                </div>
            </div>

            {isAdding && (
                <div style={{ background: 'var(--color-ivory)', padding: '16px', borderRadius: '8px', border: '1px solid var(--color-border)', marginBottom: '16px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr auto auto', gap: '12px', alignItems: 'end' }}>
                        <div>
                            <label className="form-label">Holiday Name</label>
                            <input className="form-input" value={newHoliday.name} onChange={e => setNewHoliday({...newHoliday, name: e.target.value})} placeholder="Independence Day" />
                        </div>
                        <div>
                            <label className="form-label">Date</label>
                            <DateInput value={newHoliday.holiday_date} onChange={val => setNewHoliday({...newHoliday, holiday_date: val})} />
                        </div>
                        <div style={{ paddingBottom: '10px' }}>
                            <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                                <input type="checkbox" checked={newHoliday.is_recurring} onChange={e => setNewHoliday({...newHoliday, is_recurring: e.target.checked})} />
                                <span style={{ fontSize: '13px' }}>Recurring</span>
                            </label>
                        </div>
                        <button className="btn btn-primary" style={{ height: '38px' }} onClick={addHoliday}>Save</button>
                    </div>
                </div>
            )}

            <div className="table-container">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Holiday</th>
                            <th>Date</th>
                            <th>Recurring</th>
                            <th style={{ textAlign: 'center' }}>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {holidays.length === 0 ? (
                            <tr><td colSpan={4} style={{ textAlign: 'center', padding: '20px', color: 'var(--color-text-muted)' }}>No holidays configured.</td></tr>
                        ) : holidays.map(h => (
                            <tr key={h.id}>
                                <td style={{ fontWeight: '500' }}>{h.name}</td>
                                <td>{formatDate(h.holiday_date)}</td>
                                <td>{h.is_recurring ? 'Yes' : 'No'}</td>
                                <td>
                                    <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                        <button className="btn-icon text-danger" title="Delete" onClick={() => deleteHoliday(h.id)}><Trash2 size={14} /></button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

const PolicyConfig = ({ companyId }) => {
    const [policies, setPolicies] = useState([]);
    const [leaveTypes, setLeaveTypes] = useState([]);
    const [editingId, setEditingId] = useState(null);
    const [editingState, setEditingState] = useState(null);
    const [loading, setLoading] = useState(false);
    const [policyYear, setPolicyYear] = useState(new Date().getFullYear());
    const { showAlert } = useNotificationStore();

    useEffect(() => {
        const fetchData = async () => {
            if (!companyId) {
                setLoading(false);
                return;
            }
            setLoading(true);
            try {
                const [pRes, ltRes] = await Promise.all([
                    api.get(`/leave/policies?company_id=${companyId}&year=${policyYear}`),
                    api.get(`/leave/types?company_id=${companyId}`)
                ]);
                const pData = pRes.data?.data || pRes.data;
                const ltData = ltRes.data?.data || ltRes.data;
                setPolicies(Array.isArray(pData) ? pData : []);
                setLeaveTypes(Array.isArray(ltData) ? ltData : []);
            } catch (e) {
                console.error('Failed to fetch policy data', e);
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, [companyId, policyYear]);

    const savePolicy = async () => {
        try {
            const payload = { 
                ...editingState, 
                company_id: companyId,
                year: policyYear
            };
            await api.post('/leave/policies', payload);
            setEditingId(null);
            setEditingState(null);
            const res = await api.get(`/leave/policies?company_id=${companyId}&year=${policyYear}`);
            setPolicies(res.data?.data || res.data || []);
            showAlert('Success', 'Policy saved.', 'success');
        } catch (e) {
            showAlert('Error', 'Failed to save policy.', 'error');
        }
    };

    const copyFromPreviousYear = async () => {
        if (!window.confirm(`Copy policies from ${policyYear - 1} to ${policyYear}? This will overwrite existing policies for ${policyYear}.`)) return;
        try {
            await api.post('/leave/policies/copy', {
                company_id: companyId,
                from_year: policyYear - 1,
                to_year: policyYear
            });
            const res = await api.get(`/leave/policies?company_id=${companyId}&year=${policyYear}`);
            setPolicies(res.data?.data || res.data || []);
            showAlert('Success', 'Policies copied from previous year.', 'success');
        } catch (e) {
            showAlert('Error', e.response?.data?.message || 'Failed to copy policies.', 'error');
        }
    };

    const startEditing = (ltId, policy) => {
        setEditingId(ltId);
        setEditingState({
            ...policy
        });
    };

    if (loading) return (
        <div style={{ textAlign: 'center', padding: '60px' }}>
            <div className="loader-content">
                <div className="loader-spinner"></div>
                <div className="loader-text">LOADING POLICIES...</div>
            </div>
        </div>
    );

    return (
        <div style={{ marginTop: '20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                <h4 style={{ margin: 0, fontSize: '15px' }}>Leave Accrual & Validation Rules</h4>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <button 
                        onClick={copyFromPreviousYear} 
                        className="btn btn-secondary" 
                        style={{ padding: '4px 8px', fontSize: '12px' }}
                        title={`Copy policies from ${policyYear - 1}`}
                    >
                        Copy from Previous
                    </button>
                    <label style={{ fontSize: '12px', fontWeight: '500' }}>Policy Year:</label>
                    <select 
                        value={policyYear} 
                        onChange={e => setPolicyYear(Number(e.target.value))}
                        className="form-input" 
                        style={{ padding: '4px 8px', fontSize: '12px', width: 'auto' }}
                    >
                        {[...Array(5)].map((_, i) => {
                            const y = new Date().getFullYear() - 2 + i;
                            return <option key={y} value={y}>{y}</option>;
                        })}
                    </select>
                </div>
            </div>
            <div className="table-container">
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th>Days / Year</th>
                            <th>Carry Forward</th>
                            <th>Policy Type</th>
                            <th style={{ textAlign: 'center' }}>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {leaveTypes.map(lt => {
                            const policy = policies.find(p => p.leave_type_id === lt.id) || {
                                leave_type_id: lt.id,
                                default_days_per_year: 0,
                                carry_forward_allowed: false,
                                is_calendar_days: false
                            };

                            const isEditing = editingId === lt.id;
                            const current = isEditing ? editingState : policy;

                            return (
                                <tr key={lt.id}>
                                    <td style={{ fontWeight: '600' }}>{lt.name}</td>
                                    <td>
                                        {isEditing ? (
                                            <input type="number" step="0.5" className="form-input" style={{ width: '80px', padding: '4px 8px' }} 
                                                value={editingState.default_days_per_year} 
                                                onChange={e => setEditingState({...editingState, default_days_per_year: e.target.value})} />
                                        ) : (
                                            `${policy.default_days_per_year} Days`
                                        )}
                                    </td>
                                    <td>
                                        {isEditing ? (
                                            <select className="form-input" style={{ padding: '4px 8px' }} value={editingState.carry_forward_allowed ? '1' : '0'} 
                                                onChange={e => setEditingState({...editingState, carry_forward_allowed: e.target.value === '1'})}>
                                                <option value="1">Allowed</option>
                                                <option value="0">Not Allowed</option>
                                            </select>
                                        ) : (
                                            policy.carry_forward_allowed ? 'Allowed' : 'No'
                                        )}
                                    </td>
                                    <td>
                                        {isEditing ? (
                                            <select className="form-input" style={{ padding: '4px 8px' }} value={editingState.is_calendar_days ? '1' : '0'} 
                                                onChange={e => setEditingState({...editingState, is_calendar_days: e.target.value === '1'})}>
                                                <option value="1">Calendar Days</option>
                                                <option value="0">Working Days</option>
                                            </select>
                                        ) : (
                                            policy.is_calendar_days ? 'Calendar Days' : 'Working Days'
                                        )}
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                            {isEditing ? (
                                                <>
                                                    <button className="btn btn-primary" style={{ padding: '4px 10px', fontSize: '12px' }} onClick={savePolicy}>Save</button>
                                                    <button className="btn btn-secondary" style={{ padding: '4px 10px', fontSize: '12px' }} onClick={() => { setEditingId(null); setEditingState(null); }}>Cancel</button>
                                                </>
                                            ) : (
                                                <button className="btn-icon" onClick={() => startEditing(lt.id, policy)}><Edit2 size={14} /></button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

const LeaveConfiguration = () => {
    const [companies, setCompanies] = useState([]);
    const [selectedCompany, setSelectedCompany] = useState('');
    const [configTab, setConfigTab] = useState('types');
    const { showAlert, showConfirm } = useNotificationStore();

    useEffect(() => {
        api.get('/organization/companies').then(res => {
            const list = res.data?.data || res.data || [];
            setCompanies(list);
            if (list.length > 0) setSelectedCompany(list[0].id);
        });
    }, []);

    return (
        <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
            <div style={{ padding: '20px', borderBottom: '1px solid var(--color-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#fcfcfc' }}>
                <div>
                    <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <Settings size={18} style={{ color: 'var(--color-rose-gold)' }} /> Policy Architect
                    </h3>
                    <p style={{ margin: '4px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>Configure leave rules, types, and holiday calendars</p>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <label style={{ fontSize: '13px', fontWeight: '600' }}>Office Scope:</label>
                        <select className="form-input" style={{ width: 'auto', minWidth: '180px' }} value={selectedCompany} onChange={e => setSelectedCompany(e.target.value)}>
                            {companies.map(c => <option key={c.id} value={c.id}>{c.name} ({c.country_name})</option>)}
                        </select>
                    </div>
                    <button 
                        className="btn btn-secondary" 
                        style={{ padding: '8px 16px', fontSize: '13px', display: 'flex', alignItems: 'center', gap: '8px', background: 'var(--color-ivory)' }}
                        onClick={async () => {
                            showConfirm('Recalculate Balances', 'Recalculate leaf balances for ALL employees in this office based on attendance history?', async () => {
                                try {
                                    await api.post('/leave/recalculate', { company_id: selectedCompany });
                                    showAlert('Success', 'Balances synchronized successfully.', 'success');
                                } catch (e) {
                                    showAlert('Error', 'Failed to sync balances.', 'error');
                                }
                            });
                        }}
                    >
                        <Clock size={16} /> Sync All Balances
                    </button>
                </div>

            </div>

            <div style={{ display: 'flex', background: '#fff' }}>
                <div style={{ width: '200px', borderRight: '1px solid var(--color-border)', padding: '10px' }}>
                    <button 
                        className={`nav-item ${configTab === 'types' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'types' ? 'var(--color-rose-gold)' : 'inherit' }}
                        onClick={() => setConfigTab('types')}
                    >
                        <ClipboardList size={16} /> Leave Types
                    </button>
                    <button 
                        className={`nav-item ${configTab === 'holidays' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'holidays' ? 'var(--color-rose-gold)' : 'inherit' }}
                        onClick={() => setConfigTab('holidays')}
                    >
                        <Globe size={16} /> Public Holidays
                    </button>
                    <button 
                        className={`nav-item ${configTab === 'policies' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', color: configTab === 'policies' ? 'var(--color-rose-gold)' : 'inherit' }}
                        onClick={() => setConfigTab('policies')}
                    >
                        <Clock size={16} /> Leave Policies
                    </button>
                </div>
                <div style={{ flex: 1, padding: '20px' }}>
                    {configTab === 'types' && <LeaveTypesConfig companyId={selectedCompany} />}
                    {configTab === 'holidays' && <HolidayConfig companyId={selectedCompany} />}
                    {configTab === 'policies' && <PolicyConfig companyId={selectedCompany} />}
                </div>
            </div>
        </div>
    );
};

const FilePreviewModal = ({ doc, onClose }) => {
    if (!doc) return null;

    const isPdf = doc.file_path?.toLowerCase().endsWith('.pdf') || doc.file?.type === 'application/pdf';
    const isImage = (doc.file_path && ['jpg', 'jpeg', 'png', 'gif', 'webp'].some(ext => doc.file_path.toLowerCase().endsWith(ext))) || (doc.file?.type?.startsWith('image/'));
    const src = doc.file ? URL.createObjectURL(doc.file) : getSecureMediaUrl(doc.file_path);

    return (
        <Modal
            isOpen={!!doc}
            onClose={onClose}
            title={doc.name || doc.file?.name || 'Attachment Preview'}
            maxWidth="1200px"
        >
            <div style={{ height: '70vh', display: 'flex', flexDirection: 'column' }}>
                <div style={{ 
                    padding: '12px 0', 
                    borderBottom: '1px solid var(--color-border)', 
                    display: 'flex', 
                    justifyContent: 'space-between', 
                    alignItems: 'center',
                    marginBottom: '16px'
                }}>
                    <div style={{ fontSize: '13px', color: 'var(--color-text-muted)' }}>
                        {doc.type || doc.file?.type || 'Document'} {doc.date ? `• ${doc.date}` : ''}
                    </div>
                    {src && !doc.file && (
                        <a href={src} download className="btn btn-primary">
                            Download File
                        </a>
                    )}
                </div>
                <div style={{ flex: 1, backgroundColor: '#f1f5f9', borderRadius: '12px', overflow: 'hidden' }}>
                    {isPdf ? (
                        <iframe src={src} style={{ width: '100%', height: '100%', border: 'none' }} title="PDF Preview" />
                    ) : isImage ? (
                        <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                            <img src={src} style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} alt="Preview" />
                        </div>
                    ) : (
                        <div style={{ width: '100%', height: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', background: '#fff' }}>
                            <Paperclip size={48} style={{ color: '#cbd5e1', marginBottom: '16px' }} />
                            <p>Preview not available for this file type.</p>
                            {src && !doc.file && <a href={src} download className="btn btn-primary" style={{ marginTop: '16px' }}>Download to View</a>}
                        </div>
                    )}
                </div>
            </div>
        </Modal>
    );
};

/* ── Main Leave Page ────────────────────────────────────── */

const MyLeavesTab = ({ user, onPreview }) => {
    const location = useLocation();
    const navigate = useNavigate();
    const isSuperAdmin = user?.role_id === ROLE_IDS.SUPER_ADMIN || user?.role_id === ROLE_IDS.ADMIN;
    const canCreate = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'create');
    const canEdit = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'edit');
    const canApprove = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'approve');
    const canDelete = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'delete');
    const [balances, setBalances] = useState([]);
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showApplyModal, setShowApplyModal] = useState(false);
    const [autoOpened, setAutoOpened] = useState(false);
    const [leaveTypes, setLeaveTypes] = useState([]);
    const [form, setForm] = useState({ 
        segments: [{ leave_type_id: '', start_date: '', end_date: '' }],
        remarks: '',
        attachment: null,
        draft_id: null,
        origin: null
    });
    const [isPreview, setIsPreview] = useState(false);
    const [previewData, setPreviewData] = useState([]);
    const [submitting, setSubmitting] = useState(false);
    const [showDetailsModal, setShowDetailsModal] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState(null);
    const { showAlert, showConfirm, showPrompt } = useNotificationStore();

    // Filter, Search, Sort States
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [sortBy, setSortBy] = useState('date_desc');

    const filteredHistory = useMemo(() => {
        let filtered = [...history];

        if (searchTerm) {
            const term = searchTerm.toLowerCase();
            filtered = filtered.filter(r => 
                (r.leave_type_name || '').toLowerCase().includes(term) ||
                (r.origin || '').toLowerCase().includes(term) ||
                (r.remarks || '').toLowerCase().includes(term)
            );
        }

        if (statusFilter !== 'all') {
            filtered = filtered.filter(r => r.status === statusFilter);
        }

        filtered.sort((a, b) => {
            if (sortBy === 'date_desc') return new Date(b.created_at_utc) - new Date(a.created_at_utc);
            if (sortBy === 'date_asc') return new Date(a.created_at_utc) - new Date(b.created_at_utc);
            if (sortBy === 'days_desc') return b.total_days - a.total_days;
            if (sortBy === 'days_asc') return a.total_days - b.total_days;
            return 0;
        });

        return filtered;
    }, [history, searchTerm, statusFilter, sortBy]);

    const fetchData = async () => {
        setLoading(true);
        try {
            const [balRes, histRes, typeRes] = await Promise.all([
                api.get(`/leave/balances?employee_id=${user?.employee_id || user?.id}`),
                api.get(`/leave?employee_id=${user?.employee_id || user?.id}`),
                api.get(`/leave/types?employee_id=${user?.employee_id || user?.id}`)
            ]);
            const balData = balRes.data?.data || balRes.data;
            const histData = histRes.data?.data || histRes.data;
            const typeData = typeRes.data?.data || typeRes.data;
            setBalances(Array.isArray(balData) ? balData : []);
            setHistory(Array.isArray(histData) ? histData : []);
            const types = Array.isArray(typeData) ? typeData : [];
            setLeaveTypes(types);
            
            // Fix: Populate default value if not set
            if (types.length > 0 && !form.segments[0].leave_type_id) {
                const next = [...form.segments];
                next[0].leave_type_id = types[0].id;
                setForm(prev => ({ ...prev, segments: next }));
            }
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (user?.employee_id || user?.id) {
            fetchData();
        } else {
            setLoading(false);
        }
    }, [user]);

    useEffect(() => {
        if (!autoOpened && history.length > 0) {
            const searchParams = new URLSearchParams(location.search);
            const reqId = searchParams.get('id');
            if (reqId) {
                const req = history.find(r => r.id.toString() === reqId);
                if (req) {
                    openRequestDetails(req);
                    setAutoOpened(true);
                    
                    const newParams = new URLSearchParams(location.search);
                    newParams.delete('id');
                    navigate(`${location.pathname}${newParams.toString() ? '?' + newParams.toString() : ''}`, { replace: true });
                }
            }
        }
    }, [history, location.search, autoOpened]);

    // Cleanup: preview logic for multi-segment is tricky, we'll just show for the last one or skip for now
    // For v1.8.7 we'll just let the backend handle the total.

    const handlePreview = async () => {
        const isValid = form.segments.every(s => s.leave_type_id && s.leave_type_id !== 'null' && s.leave_type_id !== '0' && s.start_date && s.end_date);
        if (!isValid) return showAlert('Required', 'Please select a leave category and fill all dates.', 'warning');
        
        setLoading(true);
        try {
            const eid = user?.employee_id || user?.id;
            const segmentsWithDays = await Promise.all(form.segments.map(async (seg) => {
                const res = await api.get(`/leave/preview?employee_id=${eid}&leave_type_id=${seg.leave_type_id}&start_date=${seg.start_date}&end_date=${seg.end_date}`);
                const lt = leaveTypes.find(t => t.id.toString() === seg.leave_type_id.toString());
                return { ...seg, days: res.data.data.total_days, typeName: lt?.name || 'Leave' };
            }));
            setPreviewData(segmentsWithDays);
            setIsPreview(true);
        } catch (e) {
            showAlert('Error', 'Failed to generate preview.', 'error');
        } finally {
            setLoading(false);
        }
    };

    const submitRequest = async (isDraft = false, skipWarning = false) => {
        if (!isDraft && !skipWarning) {
            let willBeUnpaid = false;
            for (let seg of previewData) {
                const ltid = seg.leave_type_id;
                const b = balances.find(x => x.leave_type_id.toString() === ltid.toString());
                const lt = leaveTypes.find(t => t.id.toString() === ltid.toString());
                
                const pending = b?.pending_days ? parseInt(b.pending_days) : 0;
                const remaining = b ? (b.allocated_days - b.used_days - pending) : 0;
                
                if (lt?.is_paid && seg.days > remaining) {
                    willBeUnpaid = true;
                    break;
                }
            }

            if (willBeUnpaid) {
                showConfirm(
                    'Insufficient Balance Warning',
                    'You do not have enough balance for this leave type. The excess days will be automatically converted to Unpaid Leave (Loss of Pay). Do you want to proceed?',
                    () => {
                        submitRequest(isDraft, true);
                    }
                );
                return;
            }
        }
        
        setSubmitting(true);
        try {
            const formData = new FormData();
            formData.append('employee_id', (user?.employee_id || user?.id || '').toString());
            if (isDraft === true) formData.append('is_draft', '1');
            formData.append('segments', JSON.stringify(form.segments));
            let finalRemarks = form.remarks;
            if (form.draft_id && form.origin === 'system') {
                finalRemarks = form.remarks ? "System-Generated Leave Request\n\n" + form.remarks : "System-Generated Leave Request";
            }
            formData.append('remarks', finalRemarks);
            if (form.draft_id) formData.append('draft_id', form.draft_id);
            if (form.attachment) {
                formData.append('attachment', form.attachment);
            }

            await api.post('/leave', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            setShowApplyModal(false);
            setIsPreview(false);
            setForm({ 
                segments: [{ leave_type_id: '', start_date: '', end_date: '' }],
                remarks: '',
                attachment: null,
                draft_id: null,
                origin: null
            });
            fetchData();
            showAlert('Success', 'Leave request submitted successfully.', 'success');
        } catch (e) {
            showAlert('Error', e.response?.data?.message || 'Failed to submit leave request', 'error');
        } finally {
            setSubmitting(false);
        }
    };

    const handleCancelRequest = async (id, status = 'approved') => {
        if (status === 'approved') {
            showPrompt('Cancel Approved Leave', 'Please provide a reason for cancellation:', async (reason) => {
                if (!reason) return showAlert('Required', 'Cancellation reason is required.', 'warning');
                try {
                    await api.post('/leave/request-cancel', { id, reason });
                    fetchData();
                    showAlert('Success', 'Cancellation request submitted.', 'success');
                } catch (e) {
                    const msg = e.response?.data?.message || 'Failed to process cancellation.';
                    showAlert('Error', msg, 'error');
                }
            });
        } else {
            showConfirm('Withdraw Request', 'Are you sure you want to withdraw this pending leave request?', async () => {
                try {
                    await api.post('/leave/request-cancel', { id, reason: 'Withdrawn by employee' });
                    fetchData();
                    showAlert('Success', 'Request withdrawn.', 'success');
                } catch (e) {
                    const msg = e.response?.data?.message || 'Failed to process withdrawal.';
                    showAlert('Error', msg, 'error');
                }
            });
        }
    };

    const openRequestDetails = (request) => {
        setSelectedRequest(request);
        setShowDetailsModal(true);
    };

    if (loading) return (
        <div style={{ textAlign: 'center', padding: '60px' }}>
            <div className="loader-content">
                <div className="loader-spinner"></div>
                <div className="loader-text">PREPARING BALANCES...</div>
            </div>
        </div>
    );

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                <h3 style={{ margin: 0 }}>My Leave History</h3>
                <button className="btn btn-primary" onClick={() => setShowApplyModal(true)}>
                    <Plus size={16} /> Apply Leave
                </button>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: '16px', marginBottom: '24px' }}>
                {balances.map(b => {
                    const pending = parseFloat(b.pending_days || 0);
                    const available = b.allocated_days - b.used_days - pending;
                    
                    return (
                        <div key={b.id} className="card" style={{ padding: '16px' }}>
                            <div style={{ fontSize: '13px', color: 'var(--color-text-muted)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <span>{b.leave_type_name}</span>
                                {pending > 0 && (
                                    <span style={{ 
                                        background: '#fef3c7', 
                                        color: '#92400e', 
                                        fontSize: '10px', 
                                        padding: '2px 6px', 
                                        borderRadius: '4px',
                                        fontWeight: '700'
                                    }}>
                                        {pending} PENDING
                                    </span>
                                )}
                            </div>
                            <div style={{ fontSize: '24px', fontWeight: '600', color: 'var(--color-rose-gold)' }}>
                                {available} <span style={{ fontSize: '14px', color: 'var(--color-text-muted)', fontWeight: 'normal' }}>Days Available</span>
                            </div>
                            <div style={{ fontSize: '11px', color: 'var(--color-text-muted)', marginTop: '4px' }}>
                                {b.used_days} used • {b.allocated_days} total
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="card table-container">
                <div style={{ padding: '20px 20px 16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
                    <h3 style={{ margin: 0 }}>Leave History</h3>
                    <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                        <div className="search-box" style={{ position: 'relative' }}>
                            <Search size={16} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: '#64748b' }} />
                            <input 
                                type="text" 
                                className="form-input" 
                                placeholder="Search type, remark..." 
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                style={{ paddingLeft: '36px', width: '220px' }}
                            />
                        </div>
                        <select 
                            className="form-input" 
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            style={{ width: '160px' }}
                        >
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="draft">Draft</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="cancel_requested">Cancel Requested</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <select 
                            className="form-input" 
                            value={sortBy}
                            onChange={(e) => setSortBy(e.target.value)}
                            style={{ width: '180px' }}
                        >
                            <option value="date_desc">Newest First</option>
                            <option value="date_asc">Oldest First</option>
                            <option value="days_desc">Duration (High-Low)</option>
                            <option value="days_asc">Duration (Low-High)</option>
                        </select>
                    </div>
                </div>
                <table className="data-table">
                    <thead>
                        <tr>
                            <th>Origin</th>
                            <th>Leave Type</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {filteredHistory.length === 0 ? (
                            <tr><td colSpan={6} style={{ textAlign: 'center', padding: '20px' }}>No leave history found matching your filters.</td></tr>
                        ) : filteredHistory.map(h => (
                            <tr key={h.id} onClick={() => openRequestDetails(h)} style={{ cursor: 'pointer' }}>
                                <td>
                                    {h.origin === 'system' ? (
                                        <span style={{ fontSize: '11px', fontWeight: '600', color: '#64748b' }}>SYS-{h.id}</span>
                                    ) : (
                                        <span style={{ fontSize: '11px', fontWeight: '600', color: '#0ea5e9' }}>EMP-{h.id}</span>
                                    )}
                                </td>
                                <td>{h.leave_type_name}</td>
                                <td>{formatDate(h.start_date)}</td>
                                <td>{formatDate(h.end_date)}</td>
                                <td style={{ fontWeight: '500' }}>{h.total_days}</td>
                                <td>
                                    <span style={{ textTransform: 'capitalize' }} className={`badge badge-${
                                        h.status === 'approved' ? 'success' : 
                                        (['rejected', 'cancelled'].includes(h.status) ? 'danger' : 'warning')
                                    }`}>
                                        {h.status.replace('_', ' ')}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {showApplyModal && (
                <div className="modal-overlay">
                    <div className="modal-content" style={{ width: '600px', maxWidth: '95%' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
                            <h3 style={{ margin: 0 }}>{isPreview ? 'Review Your Request' : 'Apply for Leave'}</h3>
                        </div>

                        {!isPreview ? (
                            <>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '16px', marginBottom: '24px' }}>
                                    {form.segments.map((seg, idx) => (
                                        <div key={idx} style={{ padding: '12px', border: '1px solid var(--color-border)', borderRadius: '8px', background: '#fcfcfc', position: 'relative' }}>
                                            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1.5fr 1.5fr auto', gap: '12px', alignItems: 'end' }}>
                                                <div>
                                                    <label className="form-label" style={{ fontSize: '11px' }}>Category</label>
                                                    <select 
                                                        className="form-input" 
                                                        value={seg.leave_type_id} 
                                                        onChange={e => {
                                                        const next = [...form.segments];
                                                        next[idx].leave_type_id = e.target.value;
                                                        setForm({ ...form, segments: next });
                                                    }}>
                                                        <option value="">Select...</option>
                                                        {leaveTypes.map(lt => <option key={lt.id} value={lt.id.toString()}>{lt.name}</option>)}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="form-label" style={{ fontSize: '11px' }}>From</label>
                                                    <DateInput 
                                                        value={seg.start_date} 
                                                        min={isSuperAdmin ? undefined : new Date().toISOString().split('T')[0]}
                                                        onChange={val => {
                                                            const next = [...form.segments];
                                                            next[idx].start_date = val;
                                                            setForm({ ...form, segments: next });
                                                        }} 
                                                    />
                                                </div>
                                                <div>
                                                    <label className="form-label" style={{ fontSize: '11px' }}>To</label>
                                                    <DateInput value={seg.end_date} min={seg.start_date} onChange={val => {
                                                        const next = [...form.segments];
                                                        next[idx].end_date = val;
                                                        setForm({ ...form, segments: next });
                                                    }} />
                                                </div>
                                                {form.segments.length > 1 && (
                                                    <button className="btn-icon text-danger" onClick={() => {
                                                        const next = form.segments.filter((_, i) => i !== idx);
                                                        setForm({ ...form, segments: next });
                                                    }}><Trash2 size={16} /></button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    <button className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: '6px', alignSelf: 'flex-start' }}
                                        onClick={() => setForm({ ...form, segments: [...form.segments, { leave_type_id: '', start_date: '', end_date: '' }] })}>
                                        <Plus size={16} /> Add Another Leave
                                    </button>
                                </div>

                                <div style={{ marginBottom: '24px' }}>
                                    <label className="form-label">{(form.draft_id && form.origin === 'system') ? 'Additional Remarks (Optional)' : 'Remarks (Optional)'}</label>
                                    {(form.draft_id && form.origin === 'system') && (
                                        <div style={{ padding: '10px 12px', background: '#f8fafc', marginBottom: '8px', fontSize: '13px', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '8px', border: '1px solid #e2e8f0', color: '#64748b' }}>
                                            <Lock size={14} /> System-Generated Leave Request
                                        </div>
                                    )}
                                    <textarea 
                                        className="form-input" 
                                        rows={3} 
                                        placeholder="Add any additional comments or reasons..."
                                        value={form.remarks}
                                        onChange={e => setForm({...form, remarks: e.target.value})}
                                    />
                                </div>

                                <div style={{ marginBottom: '32px' }}>
                                    <label className="form-label">Supporting Document (PDF/Images)</label>
                                    <input 
                                        type="file" 
                                        className="form-input" 
                                        onChange={e => setForm({...form, attachment: e.target.files[0]})}
                                        accept=".pdf,.jpg,.jpeg,.png"
                                    />
                                    <p style={{ fontSize: '11px', color: 'var(--color-text-muted)', marginTop: '4px' }}>Max size 5MB. Supported: PDF, JPG, PNG.</p>
                                </div>

                                <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                                    <button className="btn btn-secondary" onClick={() => setShowApplyModal(false)}>Cancel</button>
                                    <button className="btn btn-primary" onClick={handlePreview}>Continue to Preview</button>
                                </div>
                            </>
                        ) : (
                            <>
                                <div style={{ background: '#f8fafc', padding: '20px', borderRadius: '12px', marginBottom: '24px', border: '1px solid #e2e8f0' }}>
                                    <h4 style={{ margin: '0 0 16px', fontSize: '14px', color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Request Summary</h4>
                                    
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                        {previewData.map((seg, idx) => {
                                            const b = balances.find(x => x.leave_type_id.toString() === seg.leave_type_id.toString());
                                            const lt = leaveTypes.find(t => t.id.toString() === seg.leave_type_id.toString());
                                            const pending = b?.pending_days ? parseInt(b.pending_days) : 0;
                                            const remaining = b ? (b.allocated_days - b.used_days - pending) : 0;
                                            const isExceeded = lt?.is_paid && seg.days > remaining;

                                            return (
                                                <div key={idx} style={{ paddingBottom: '12px', borderBottom: idx === previewData.length - 1 ? 'none' : '1px dashed #cbd5e1' }}>
                                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                                        <div>
                                                            <div style={{ fontWeight: '600', color: 'var(--color-slate-900)' }}>{seg.typeName}</div>
                                                            <div style={{ fontSize: '13px', color: '#64748b' }}>{formatDate(seg.start_date)} to {formatDate(seg.end_date)}</div>
                                                        </div>
                                                        <div style={{ textAlign: 'right' }}>
                                                            <div style={{ fontWeight: '700', color: 'var(--color-rose-gold)' }}>{seg.days} Days</div>
                                                            {lt?.is_paid && (
                                                                <div style={{ fontSize: '12px', color: '#64748b' }}>
                                                                    Avail: {remaining} {remaining === 1 ? 'Day' : 'Days'}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                    {isExceeded && (
                                                        <div style={{ marginTop: '8px', padding: '8px 12px', background: '#fef2f2', border: '1px solid #fca5a5', borderRadius: '6px', color: '#b91c1c', fontSize: '12px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                            <span>⚠️</span> Exceeds available balance. {seg.days - remaining} {seg.days - remaining === 1 ? 'day' : 'days'} will be automatically converted to Unpaid Leave (Loss of Pay).
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>

                                    <div style={{ marginTop: '20px', paddingTop: '16px', borderTop: '2px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <span style={{ fontWeight: '700', fontSize: '16px' }}>Total Leave Duration</span>
                                        <span style={{ fontWeight: '800', fontSize: '20px', color: 'var(--color-rose-gold)' }}>
                                            {previewData.reduce((sum, s) => sum + s.days, 0)} Days
                                        </span>
                                    </div>
                                </div>

                                {form.remarks && (
                                    <div style={{ marginBottom: '20px' }}>
                                        <label className="form-label" style={{ color: '#64748b' }}>Remarks</label>
                                        <div style={{ padding: '12px', background: 'var(--color-ivory)', borderRadius: '8px', fontSize: '14px', border: '1px solid var(--color-border)' }}>
                                            {form.remarks}
                                        </div>
                                    </div>
                                )}

                                {form.attachment && (
                                    <div style={{ marginBottom: '32px' }}>
                                        <label className="form-label" style={{ color: '#64748b' }}>Attachment</label>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 12px', background: '#f1f5f9', borderRadius: '8px', fontSize: '13px', width: 'fit-content' }}>
                                                <Info size={14} /> {form.attachment.name} ({(form.attachment.size / 1024).toFixed(1)} KB)
                                            </div>
                                            <button 
                                                className="btn btn-secondary" 
                                                style={{ padding: '6px 12px', fontSize: '12px', display: 'flex', alignItems: 'center', gap: '6px' }}
                                                onClick={() => onPreview({ file: form.attachment })}
                                            >
                                                <Eye size={14} /> Preview
                                            </button>
                                        </div>
                                    </div>
                                )}

                                <div style={{ display: 'flex', gap: '12px', justifyContent: 'space-between' }}>
                                    <button className="btn btn-secondary" onClick={() => setIsPreview(false)}>Back to Edit</button>
                                    <div style={{ display: 'flex', gap: '12px' }}>
                                        <button className="btn btn-secondary" onClick={() => submitRequest(true)} disabled={submitting}>
                                            Save as Draft
                                        </button>
                                        <button className="btn btn-primary" onClick={() => submitRequest(false)} disabled={submitting}>
                                            {submitting ? <Loader size={14} className="spin" /> : 'Confirm & Submit Request'}
                                        </button>
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}

            {showDetailsModal && selectedRequest && (
                <div className="modal-overlay">
                    <div className="modal-content" style={{ width: '500px' }}>
                        <div style={{ marginBottom: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                            <div>
                                <h3 style={{ margin: 0 }}>Leave Application Details</h3>
                                <p style={{ margin: '4px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>
                                    Summary of your leave request.
                                </p>
                            </div>
                            <span className={`badge badge-${
                                selectedRequest.status === 'approved' ? 'success' : 
                                (['rejected', 'cancelled'].includes(selectedRequest.status) ? 'danger' : 'warning')
                            }`} style={{ textTransform: 'uppercase' }}>
                                {selectedRequest.status.replace('_', ' ')}
                            </span>
                        </div>

                        <div style={{ background: '#f8fafc', padding: '20px', borderRadius: '12px', marginBottom: '24px', border: '1px solid #e2e8f0' }}>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <span style={{ fontSize: '13px', color: '#64748b' }}>Category</span>
                                    <span style={{ fontWeight: '600' }}>{selectedRequest.leave_type_name}</span>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <span style={{ fontSize: '13px', color: '#64748b' }}>Period</span>
                                    <span style={{ fontWeight: '600' }}>{formatDate(selectedRequest.start_date)} - {formatDate(selectedRequest.end_date)}</span>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: '12px', borderTop: '1px dashed #cbd5e1' }}>
                                    <span style={{ fontSize: '14px', fontWeight: '700' }}>Total Duration</span>
                                    <span style={{ fontWeight: '800', fontSize: '18px', color: 'var(--color-rose-gold)' }}>{selectedRequest.total_days} Days</span>
                                </div>
                            </div>
                        </div>

                        {selectedRequest.remarks && (
                            <div style={{ marginBottom: '20px' }}>
                                <label className="form-label" style={{ color: '#64748b' }}>Your Remarks</label>
                                <div style={{ padding: '12px', background: 'var(--color-ivory)', borderRadius: '8px', fontSize: '13px', border: '1px solid var(--color-border)' }}>
                                    {selectedRequest.remarks}
                                </div>
                            </div>
                        )}

                        {selectedRequest.manager_comment && (
                            <div style={{ marginBottom: '20px' }}>
                                <label className="form-label" style={{ color: 'var(--color-rose-gold)' }}>Manager Feedback</label>
                                <div style={{ padding: '12px', background: '#fff1f2', borderRadius: '8px', fontSize: '13px', border: '1px solid #fecdd3' }}>
                                    {selectedRequest.manager_comment}
                                </div>
                            </div>
                        )}

                        {selectedRequest.attachment_path && (
                            <div style={{ marginBottom: '32px' }}>
                                <label className="form-label" style={{ color: '#64748b' }}>Your Attachment</label>
                                <div style={{ display: 'flex', gap: '12px' }}>
                                    <a 
                                        href={getSecureMediaUrl(selectedRequest.attachment_path)} 
                                        target="_blank" 
                                        rel="noreferrer" 
                                        className="btn btn-secondary" 
                                        style={{ padding: '8px 16px', display: 'flex', alignItems: 'center', gap: '8px' }}
                                    >
                                        <Eye size={16} /> View in New Tab
                                    </a>
                                    <a 
                                        href={getSecureMediaUrl(selectedRequest.attachment_path)} 
                                        download
                                        className="btn btn-secondary" 
                                        style={{ padding: '8px 16px', display: 'flex', alignItems: 'center', gap: '8px' }}
                                    >
                                        <Download size={16} /> Download
                                    </a>
                                </div>
                            </div>
                        )}

                        <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                            <button className="btn btn-secondary" onClick={() => setShowDetailsModal(false)}>Close</button>
                            {selectedRequest.status === 'approved' && (
                                <button className="btn btn-primary text-danger" onClick={() => { setShowDetailsModal(false); handleCancelRequest(selectedRequest.id, 'approved'); }}>
                                    Request Cancellation
                                </button>
                            )}
                            {selectedRequest.status === 'pending' && (
                                <button className="btn btn-secondary text-danger" onClick={() => { setShowDetailsModal(false); handleCancelRequest(selectedRequest.id, 'pending'); }}>
                                    Withdraw Request
                                </button>
                            )}
                            {selectedRequest.status === 'draft' && (
                                <button className="btn btn-primary" onClick={() => { 
                                    setShowDetailsModal(false);
                                    let baseRemarks = selectedRequest.remarks || '';
                                    baseRemarks = baseRemarks.replace(/System-Generated Leave Request/gi, '').trim();
                                    
                                    setForm({
                                        segments: [{
                                            leave_type_id: selectedRequest.leave_type_id ? selectedRequest.leave_type_id.toString() : '',
                                            start_date: selectedRequest.start_date,
                                            end_date: selectedRequest.end_date
                                        }],
                                        remarks: baseRemarks,
                                        attachment: null,
                                        draft_id: selectedRequest.id,
                                        origin: selectedRequest.origin
                                    });
                                    setShowApplyModal(true);
                                }}>
                                    Edit & Submit Draft
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

const RequestsTab = ({ user, onPreview }) => {
    const location = useLocation();
    const navigate = useNavigate();
    const isSuperAdmin = user?.role_id === ROLE_IDS.SUPER_ADMIN || user?.role_id === ROLE_IDS.ADMIN;
    const canCreate = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'create');
    const canEdit = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'edit');
    const canApprove = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'approve');
    const canDelete = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'delete');
    const canApproveLeave = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'approve');
    const canEditLeave = isSuperAdmin || useAuthStore.getState().hasPermission('leave', 'edit');
    const [requests, setRequests] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showPreviewModal, setShowPreviewModal] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [autoOpened, setAutoOpened] = useState(false);
    const [editMode, setEditMode] = useState(false);
    const [leaveTypes, setLeaveTypes] = useState([]);
    const [editForm, setEditForm] = useState({ leave_type_id: '', start_date: '', end_date: '' });
    
    // Exact same edit UI state as MyLeavesTab
    const [showApplyModal, setShowApplyModal] = useState(false);
    const [form, setForm] = useState({ 
        segments: [{ leave_type_id: '', start_date: '', end_date: '' }],
        remarks: '',
        attachment: null,
        draft_id: null
    });
    const [isPreview, setIsPreview] = useState(false);
    const [previewData, setPreviewData] = useState([]);
    const [submitting, setSubmitting] = useState(false);
    const { showAlert } = useNotificationStore();

    // Filter, Search, Sort States
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    const [countryFilter, setCountryFilter] = useState('all');
    const [sortBy, setSortBy] = useState('date_desc');

    const [configCountries, setConfigCountries] = useState([]);

    const filteredRequests = useMemo(() => {
        let filtered = [...requests];

        if (searchTerm) {
            const term = searchTerm.toLowerCase();
            filtered = filtered.filter(r => 
                `${r.first_name || ''} ${r.last_name || ''}`.toLowerCase().includes(term) ||
                (r.leave_type_name || '').toLowerCase().includes(term) ||
                (r.origin || '').toLowerCase().includes(term)
            );
        }

        if (statusFilter !== 'all') {
            filtered = filtered.filter(r => r.status === statusFilter);
        }

        if (countryFilter !== 'all') {
            filtered = filtered.filter(r => r.country_name === countryFilter);
        }

        filtered.sort((a, b) => {
            if (sortBy === 'date_desc') return new Date(b.created_at_utc) - new Date(a.created_at_utc);
            if (sortBy === 'date_asc') return new Date(a.created_at_utc) - new Date(b.created_at_utc);
            if (sortBy === 'days_desc') return b.total_days - a.total_days;
            if (sortBy === 'days_asc') return a.total_days - b.total_days;
            return 0;
        });

        return filtered;
    }, [requests, searchTerm, statusFilter, countryFilter, sortBy]);

    const fetchRequests = async () => {
        setLoading(true);
        try {
            const [res, countryRes] = await Promise.all([
                api.get('/leave?status=pending,approved,cancel_requested,draft,rejected,cancelled'),
                api.get('/organization/countries')
            ]);
            setRequests(res.data?.data || res.data || []);
            setConfigCountries(countryRes.data?.data || countryRes.data || []);
        } catch (e) {
            console.error(e);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchRequests(); }, []);

    useEffect(() => {
        if (!autoOpened && requests.length > 0) {
            const searchParams = new URLSearchParams(location.search);
            const reqId = searchParams.get('id');
            if (reqId) {
                const req = requests.find(r => r.id.toString() === reqId);
                if (req) {
                    openRequestDetails(req);
                    setAutoOpened(true);
                    
                    const newParams = new URLSearchParams(location.search);
                    newParams.delete('id');
                    navigate(`${location.pathname}${newParams.toString() ? '?' + newParams.toString() : ''}`, { replace: true });
                }
            }
        }
    }, [requests, location.search, autoOpened]);

    const handleAction = async (id, action, comment = '') => {
        setProcessing(true);
        try {
            await api.post(`/leave/${action}`, { id, comment });
            setShowPreviewModal(false);
            setSelectedRequest(null);
            fetchRequests();
        } catch (e) {
            alert(`Failed to ${action} request: ${e.response?.data?.message || e.message}`);
        } finally {
            setProcessing(false);
        }
    };

    const openRequestDetails = async (request) => {
        setSelectedRequest(request);
        setEditMode(false);
        setShowPreviewModal(true);
        if (request.status === 'draft') {
            try {
                const res = await api.get(`/leave/types?employee_id=${request.employee_id}`);
                setLeaveTypes(res.data?.data || res.data || []);
                setEditForm({
                    leave_type_id: request.leave_type_id?.toString() || '',
                    start_date: request.start_date.substring(0, 10),
                    end_date: request.end_date.substring(0, 10)
                });
            } catch (e) { console.error(e); }
        }
    };

    const handlePreview = async () => {
        const isValid = form.segments.every(s => s.leave_type_id && s.leave_type_id !== 'null' && s.leave_type_id !== '0' && s.start_date && s.end_date);
        if (!isValid) return showAlert('Required', 'Please select a leave category and fill all dates.', 'warning');
        
        setLoading(true);
        try {
            const eid = selectedRequest.employee_id;
            const segmentsWithDays = await Promise.all(form.segments.map(async (seg) => {
                const res = await api.get(`/leave/preview?employee_id=${eid}&leave_type_id=${seg.leave_type_id}&start_date=${seg.start_date}&end_date=${seg.end_date}`);
                const lt = leaveTypes.find(t => t.id.toString() === seg.leave_type_id.toString());
                return { ...seg, days: res.data.data.total_days, typeName: lt?.name || 'Leave' };
            }));
            setPreviewData(segmentsWithDays);
            setIsPreview(true);
        } catch (e) {
            showAlert('Error', 'Failed to generate preview.', 'error');
        } finally {
            setLoading(false);
        }
    };

    const submitRequest = async (isDraft = false) => {
        setSubmitting(true);
        try {
            const formData = new FormData();
            formData.append('employee_id', selectedRequest.employee_id.toString());
            if (isDraft === true) formData.append('is_draft', '1');
            formData.append('segments', JSON.stringify(form.segments));
            let finalRemarks = form.remarks;
            
            const adminComment = prompt('Add an optional comment for this draft submission:');
            if (adminComment !== null && adminComment.trim()) {
                finalRemarks = finalRemarks ? finalRemarks + '\n[Admin]: ' + adminComment.trim() : adminComment.trim();
            }
            
            if (form.draft_id && form.origin === 'system') {
                finalRemarks = finalRemarks ? "System-Generated Leave Request\n\n" + finalRemarks : "System-Generated Leave Request";
            }
            formData.append('remarks', finalRemarks);
            if (form.draft_id) formData.append('draft_id', form.draft_id);
            if (form.attachment) {
                formData.append('attachment', form.attachment);
            }

            await api.post('/leave', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            setShowApplyModal(false);
            setIsPreview(false);
            fetchRequests();
            showAlert('Success', 'Leave request submitted successfully.', 'success');
        } catch (e) {
            showAlert('Error', e.response?.data?.message || 'Failed to submit leave request', 'error');
        } finally {
            setSubmitting(false);
        }
    };

    const handleReject = (id) => {
        const comment = prompt('Please enter a reason for rejection:');
        if (comment === null) return;
        handleAction(id, 'reject', comment);
    };

    const handleAdminCancel = async (id) => {
        const comment = prompt('Enter cancellation reason (Leave will be removed from logs):');
        if (comment === null) return;
        try {
            await api.post('/leave/admin-cancel', { id, comment });
            fetchRequests();
        } catch (e) {
            alert('Failed to cancel leave.');
        }
    };

    if (loading) return (
        <div style={{ textAlign: 'center', padding: '60px' }}>
            <div className="loader-content">
                <div className="loader-spinner"></div>
                <div className="loader-text">FETCHING REQUESTS...</div>
            </div>
        </div>
    );

    return (
        <div className="card table-container">
            <div style={{ padding: '20px 20px 16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' }}>
                <h3 style={{ margin: 0 }}>Leave Requests Management</h3>
                <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap' }}>
                    <div className="search-box" style={{ position: 'relative' }}>
                        <Search size={16} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: '#64748b' }} />
                        <input 
                            type="text" 
                            className="form-input" 
                            placeholder="Search employee, type..." 
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            style={{ paddingLeft: '36px', width: '220px' }}
                        />
                    </div>
                    {configCountries.length > 0 && (
                        <select 
                            className="form-input" 
                            value={countryFilter}
                            onChange={(e) => setCountryFilter(e.target.value)}
                            style={{ width: '140px' }}
                        >
                            <option value="all">All Countries</option>
                            {configCountries.map(c => <option key={c.id} value={c.name}>{c.name}</option>)}
                        </select>
                    )}
                    <select 
                        className="form-input" 
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        style={{ width: '160px' }}
                    >
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="draft">Draft</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancel_requested">Cancel Requested</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select 
                        className="form-input" 
                        value={sortBy}
                        onChange={(e) => setSortBy(e.target.value)}
                        style={{ width: '180px' }}
                    >
                        <option value="date_desc">Newest First</option>
                        <option value="date_asc">Oldest First</option>
                        <option value="days_desc">Duration (High-Low)</option>
                        <option value="days_asc">Duration (Low-High)</option>
                    </select>
                </div>
            </div>
            <table className="data-table">
                <thead>
                    <tr>
                        <th>Origin</th>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Period</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Reason / Comment</th>
                    </tr>
                </thead>
                <tbody>
                    {filteredRequests.length === 0 ? (
                        <tr><td colSpan={7} style={{ textAlign: 'center', padding: '20px' }}>No requests found matching your filters.</td></tr>
                    ) : filteredRequests.map(r => (
                        <tr key={r.id} onClick={() => openRequestDetails(r)} style={{ cursor: 'pointer' }}>
                            <td>
                                {r.origin === 'system' ? (
                                    <span style={{ fontSize: '11px', fontWeight: '600', color: '#64748b' }}>SYS-{r.id}</span>
                                ) : (
                                    <span style={{ fontSize: '11px', fontWeight: '600', color: '#0ea5e9' }}>EMP-{r.id}</span>
                                )}
                            </td>
                            <td style={{ fontWeight: '500' }}>{r.first_name} {r.last_name}</td>
                            <td>{r.leave_type_name}</td>
                            <td style={{ fontSize: '12px' }}>
                                {formatDate(r.start_date)} - {formatDate(r.end_date)}
                            </td>
                            <td style={{ fontWeight: '500' }}>{r.total_days}</td>
                            <td>
                                <span style={{ textTransform: 'capitalize' }} className={`badge badge-${
                                    r.status === 'pending' ? 'warning' : 
                                    (r.status === 'approved' ? 'success' : 'danger')
                                }`}>
                                    {r.status.replace('_', ' ')}
                                </span>
                            </td>
                            <td style={{ fontSize: '11px', maxWidth: '300px' }}>
                                {r.status === 'cancel_requested' ? (
                                    <div style={{ color: 'var(--color-rose-gold)', marginBottom: '4px' }}>
                                        <strong>Cancellation:</strong> {r.cancellation_reason}
                                    </div>
                                ) : r.remarks ? (
                                    <div style={{ color: 'var(--color-slate-600)', marginBottom: '4px' }}>
                                        <strong>Emp Remarks:</strong> {r.remarks}
                                    </div>
                                ) : null}
                                
                                {r.manager_comment && r.status !== 'pending' && (
                                    <div style={{ color: 'var(--color-text-muted)' }}>
                                        <strong>Admin:</strong> {r.manager_comment}
                                    </div>
                                )}

                                {r.attachment_path && (
                                    <div style={{ marginTop: '6px' }}>
                                        <button 
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                onPreview({
                                                    name: `Attachment: ${r.first_name} ${r.last_name}`,
                                                    file_path: r.attachment_path,
                                                    type: r.leave_type_name,
                                                    date: formatDate(r.start_date)
                                                });
                                            }}
                                            style={{ 
                                                display: 'inline-flex', 
                                                alignItems: 'center', 
                                                gap: '4px', 
                                                color: 'var(--color-rose-gold)', 
                                                textDecoration: 'none', 
                                                fontWeight: '600',
                                                padding: '4px 8px',
                                                background: '#fff1f2',
                                                borderRadius: '6px',
                                                fontSize: '10px',
                                                border: '1px solid #fecdd3',
                                                cursor: 'pointer'
                                            }}
                                        >
                                            <Eye size={10} /> PREVIEW ATTACHMENT
                                        </button>
                                    </div>
                                )}
                                
                                {!r.remarks && !r.manager_comment && !r.cancellation_reason && !r.attachment_path && '-'}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {showPreviewModal && selectedRequest && (
                <div className="modal-overlay">
                    <div className="modal-content" style={{ width: '500px' }}>
                        <div style={{ marginBottom: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                            <div>
                                <h3 style={{ margin: 0 }}>Leave Request Details</h3>
                                <p style={{ margin: '4px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>
                                    {selectedRequest.status === 'pending' ? 'Review this application before approval.' : 'Summary of this leave application.'}
                                </p>
                            </div>
                            <span className={`badge badge-${
                                selectedRequest.status === 'approved' ? 'success' : 
                                (['rejected', 'cancelled'].includes(selectedRequest.status) ? 'danger' : 'warning')
                            }`} style={{ textTransform: 'uppercase' }}>
                                {selectedRequest.status.replace('_', ' ')}
                            </span>
                        </div>

                        <div style={{ background: '#f8fafc', padding: '20px', borderRadius: '12px', marginBottom: '24px', border: '1px solid #e2e8f0' }}>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <span style={{ fontSize: '13px', color: '#64748b' }}>Employee</span>
                                    <span style={{ fontWeight: '600' }}>{selectedRequest.first_name} {selectedRequest.last_name}</span>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <span style={{ fontSize: '13px', color: '#64748b' }}>Category</span>
                                    <span style={{ fontWeight: '600' }}>{selectedRequest.leave_type_name}</span>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <span style={{ fontSize: '13px', color: '#64748b' }}>Period</span>
                                    <span style={{ fontWeight: '600' }}>{formatDate(selectedRequest.start_date)} - {formatDate(selectedRequest.end_date)}</span>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: '12px', borderTop: '1px dashed #cbd5e1' }}>
                                    <span style={{ fontSize: '14px', fontWeight: '700' }}>Total Duration</span>
                                    <span style={{ fontWeight: '800', fontSize: '18px', color: 'var(--color-rose-gold)' }}>{selectedRequest.total_days} Days</span>
                                </div>
                            </div>
                        </div>

                        {selectedRequest.remarks && (
                            <div style={{ marginBottom: '20px' }}>
                                <label className="form-label" style={{ color: '#64748b' }}>Employee Remarks</label>
                                <div style={{ padding: '12px', background: 'var(--color-ivory)', borderRadius: '8px', fontSize: '13px', border: '1px solid var(--color-border)' }}>
                                    {selectedRequest.remarks}
                                </div>
                            </div>
                        )}

                        {selectedRequest.attachment_path && (
                            <div style={{ marginBottom: '32px' }}>
                                <label className="form-label" style={{ color: '#64748b' }}>Supporting Document</label>
                                <div style={{ display: 'flex', gap: '12px' }}>
                                    <a 
                                        href={getSecureMediaUrl(selectedRequest.attachment_path)} 
                                        target="_blank" 
                                        rel="noreferrer" 
                                        className="btn btn-secondary" 
                                        style={{ padding: '8px 16px', display: 'flex', alignItems: 'center', gap: '8px' }}
                                    >
                                        <Eye size={16} /> View in New Tab
                                    </a>
                                    <a 
                                        href={getSecureMediaUrl(selectedRequest.attachment_path)} 
                                        download
                                        className="btn btn-secondary" 
                                        style={{ padding: '8px 16px', display: 'flex', alignItems: 'center', gap: '8px' }}
                                    >
                                        <Download size={16} /> Download
                                    </a>
                                </div>
                            </div>
                        )}

                        <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                            <button className="btn btn-secondary" onClick={() => setShowPreviewModal(false)} disabled={processing}>Close</button>
                            
                            {selectedRequest.status === 'pending' && canApproveLeave && (
                                <>
                                    <button className="btn btn-secondary text-danger" onClick={() => handleReject(selectedRequest.id)} disabled={processing}>
                                        Reject
                                    </button>
                                    <button className="btn btn-primary" onClick={() => {
                                        const comment = prompt('Add an optional remark for this approval:');
                                        if (comment !== null) {
                                            handleAction(selectedRequest.id, 'approve', comment);
                                        }
                                    }} disabled={processing}>
                                        {processing ? <Loader size={14} className="spin" /> : 'Confirm Approval'}
                                    </button>
                                </>
                            )}

                            {selectedRequest.status === 'cancel_requested' && canApproveLeave && (
                                <button className="btn btn-primary text-danger" onClick={() => handleAdminCancel(selectedRequest.id)} disabled={processing}>
                                    Confirm Cancellation
                                </button>
                            )}

                            {selectedRequest.status === 'approved' && canApproveLeave && (
                                <button className="btn btn-secondary text-danger" onClick={() => handleAdminCancel(selectedRequest.id)} disabled={processing}>
                                    Cancel Leave
                                </button>
                            )}

                            {canEditLeave && selectedRequest.status !== 'draft' && (
                                <button className="btn btn-primary" onClick={() => { 
                                    setShowPreviewModal(false);
                                    let baseRemarks = selectedRequest.remarks || '';
                                    baseRemarks = baseRemarks.replace(/System-Generated Leave Request/gi, '').trim();
                                    
                                    setForm({
                                        segments: [{
                                            leave_type_id: selectedRequest.leave_type_id ? selectedRequest.leave_type_id.toString() : '',
                                            start_date: selectedRequest.start_date.substring(0, 10),
                                            end_date: selectedRequest.end_date.substring(0, 10)
                                        }],
                                        remarks: baseRemarks,
                                        attachment: null,
                                        draft_id: selectedRequest.id,
                                        origin: selectedRequest.origin
                                    });
                                    setShowApplyModal(true);
                                }}>
                                    Edit Leave
                                </button>
                            )}
                            {selectedRequest.status === 'draft' && (
                                <button className="btn btn-primary" onClick={() => { 
                                    setShowPreviewModal(false);
                                    let baseRemarks = selectedRequest.remarks || '';
                                    baseRemarks = baseRemarks.replace(/System-Generated Leave Request/gi, '').trim();
                                    
                                    setForm({
                                        segments: [{
                                            leave_type_id: selectedRequest.leave_type_id ? selectedRequest.leave_type_id.toString() : '',
                                            start_date: selectedRequest.start_date,
                                            end_date: selectedRequest.end_date
                                        }],
                                        remarks: baseRemarks,
                                        attachment: null,
                                        draft_id: selectedRequest.id,
                                        origin: selectedRequest.origin
                                    });
                                    setShowApplyModal(true);
                                }}>
                                    Edit & Submit Draft
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {showApplyModal && (
                <div className="modal-overlay">
                    <div className="modal-content" style={{ width: '600px', maxWidth: '95%' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
                            <h3 style={{ margin: 0 }}>{isPreview ? 'Review Request Details' : 'Edit Leave Draft'}</h3>
                        </div>

                        {!isPreview ? (
                            <>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '16px', marginBottom: '24px' }}>
                                    {form.segments.map((seg, idx) => (
                                        <div key={idx} style={{ padding: '12px', border: '1px solid var(--color-border)', borderRadius: '8px', background: '#fcfcfc', position: 'relative' }}>
                                            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1.5fr 1.5fr auto', gap: '12px', alignItems: 'end' }}>
                                                <div>
                                                    <label className="form-label" style={{ fontSize: '11px' }}>Category</label>
                                                    <select 
                                                        className="form-input" 
                                                        value={seg.leave_type_id} 
                                                        onChange={e => {
                                                        const next = [...form.segments];
                                                        next[idx].leave_type_id = e.target.value;
                                                        setForm({ ...form, segments: next });
                                                    }}>
                                                        <option value="">Select...</option>
                                                        {leaveTypes.map(lt => <option key={lt.id} value={lt.id.toString()}>{lt.name}</option>)}
                                                    </select>
                                                </div>
                                                <div>
                                                    <label className="form-label" style={{ fontSize: '11px' }}>From</label>
                                                    <DateInput 
                                                        value={seg.start_date} 
                                                        onChange={val => {
                                                            const next = [...form.segments];
                                                            next[idx].start_date = val;
                                                            setForm({ ...form, segments: next });
                                                        }} 
                                                    />
                                                </div>
                                                <div>
                                                    <label className="form-label" style={{ fontSize: '11px' }}>To</label>
                                                    <DateInput value={seg.end_date} min={seg.start_date} onChange={val => {
                                                        const next = [...form.segments];
                                                        next[idx].end_date = val;
                                                        setForm({ ...form, segments: next });
                                                    }} />
                                                </div>
                                                {form.segments.length > 1 && (
                                                    <button className="btn-icon text-danger" onClick={() => {
                                                        const next = form.segments.filter((_, i) => i !== idx);
                                                        setForm({ ...form, segments: next });
                                                    }}><Trash2 size={16} /></button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                <div style={{ marginBottom: '24px' }}>
                                    <label className="form-label">{(form.draft_id && form.origin === 'system') ? 'Additional Remarks (Optional)' : 'Remarks (Optional)'}</label>
                                    {(form.draft_id && form.origin === 'system') && (
                                        <div style={{ padding: '10px 12px', background: '#f8fafc', marginBottom: '8px', fontSize: '13px', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '8px', border: '1px solid #e2e8f0', color: '#64748b' }}>
                                            <Lock size={14} /> System-Generated Leave Request
                                        </div>
                                    )}
                                    <textarea 
                                        className="form-input" 
                                        rows={3} 
                                        placeholder="Add any additional comments or reasons..."
                                        value={form.remarks}
                                        onChange={e => setForm({...form, remarks: e.target.value})}
                                    />
                                </div>

                                <div style={{ marginBottom: '32px' }}>
                                    <label className="form-label">Supporting Document (PDF/Images)</label>
                                    <input 
                                        type="file" 
                                        className="form-input" 
                                        onChange={e => setForm({...form, attachment: e.target.files[0]})}
                                        accept=".pdf,.jpg,.jpeg,.png"
                                    />
                                    <p style={{ fontSize: '11px', color: 'var(--color-text-muted)', marginTop: '4px' }}>Max size 5MB. Supported: PDF, JPG, PNG.</p>
                                </div>

                                <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                                    <button className="btn btn-secondary" onClick={() => setShowApplyModal(false)}>Cancel</button>
                                    <button className="btn btn-primary" onClick={handlePreview}>Continue to Preview</button>
                                </div>
                            </>
                        ) : (
                            <>
                                <div style={{ background: '#f8fafc', padding: '20px', borderRadius: '12px', marginBottom: '24px', border: '1px solid #e2e8f0' }}>
                                    <h4 style={{ margin: '0 0 16px', fontSize: '14px', color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Request Summary</h4>
                                    
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                        {previewData.map((seg, idx) => (
                                            <div key={idx} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', paddingBottom: '12px', borderBottom: idx === previewData.length - 1 ? 'none' : '1px dashed #cbd5e1' }}>
                                                <div>
                                                    <div style={{ fontWeight: '600', color: 'var(--color-slate-900)' }}>{seg.typeName}</div>
                                                    <div style={{ fontSize: '13px', color: '#64748b' }}>{formatDate(seg.start_date)} to {formatDate(seg.end_date)}</div>
                                                </div>
                                                <div style={{ fontWeight: '700', color: 'var(--color-rose-gold)' }}>{seg.days} Days</div>
                                            </div>
                                        ))}
                                    </div>

                                    <div style={{ marginTop: '20px', paddingTop: '16px', borderTop: '2px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                        <span style={{ fontWeight: '700', fontSize: '16px' }}>Total Leave Duration</span>
                                        <span style={{ fontWeight: '800', fontSize: '20px', color: 'var(--color-rose-gold)' }}>
                                            {previewData.reduce((sum, s) => sum + s.days, 0)} Days
                                        </span>
                                    </div>
                                </div>

                                {form.remarks && (
                                    <div style={{ marginBottom: '20px' }}>
                                        <label className="form-label" style={{ color: '#64748b' }}>Remarks</label>
                                        <div style={{ padding: '12px', background: 'var(--color-ivory)', borderRadius: '8px', fontSize: '14px', border: '1px solid var(--color-border)' }}>
                                            {form.remarks}
                                        </div>
                                    </div>
                                )}

                                {form.attachment && (
                                    <div style={{ marginBottom: '32px' }}>
                                        <label className="form-label" style={{ color: '#64748b' }}>Attachment</label>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 12px', background: '#f1f5f9', borderRadius: '8px', fontSize: '13px', width: 'fit-content' }}>
                                                <Info size={14} /> {form.attachment.name} ({(form.attachment.size / 1024).toFixed(1)} KB)
                                            </div>
                                            <button 
                                                className="btn btn-secondary" 
                                                style={{ padding: '6px 12px', fontSize: '12px', display: 'flex', alignItems: 'center', gap: '6px' }}
                                                onClick={() => onPreview({ file: form.attachment })}
                                            >
                                                <Eye size={14} /> Preview
                                            </button>
                                        </div>
                                    </div>
                                )}

                                <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-between' }}>
                                    <button className="btn btn-secondary" onClick={() => setIsPreview(false)}>Back to Edit</button>
                                    <div style={{ display: 'flex', gap: '12px' }}>
                                        <button className="btn btn-secondary" onClick={() => submitRequest(true)} disabled={submitting}>
                                            Save as Draft
                                        </button>
                                        <button className="btn btn-primary" onClick={() => submitRequest(false)} disabled={submitting}>
                                            {submitting ? <Loader size={14} className="spin" /> : 'Confirm & Submit Request'}
                                        </button>
                                    </div>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

/* ── Main Leave Page ────────────────────────────────────── */

const Leave = () => {
    const location = useLocation();
    const searchParams = new URLSearchParams(location.search);
    const user = useAuthStore(state => state.user);
    
    const canViewRequests = useAuthStore.getState().hasPermission('leave', 'view') || useAuthStore.getState().hasPermission('leave', 'approve');
    const canConfigureLeave = useAuthStore.getState().hasPermission('configuration', 'view');
    
    const isEmployeeView = localStorage.getItem('adminViewMode') === 'employee';
    const showRequests = canViewRequests && !isEmployeeView;
    const showConfig = canConfigureLeave && !isEmployeeView;
    
    const initialTab = searchParams.get('tab') || (showRequests ? 'requests' : 'my_leaves');
    const [activeTab, setActiveTab] = useState(initialTab);
    const [previewDoc, setPreviewDoc] = useState(null);
    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        const tab = searchParams.get('tab');
        if (tab && tab !== activeTab) {
            setActiveTab(tab);
        }
    }, [location.search]);

    useEffect(() => {
        setPageTitle("Leave Management");
        setPageSubtitle("Annual Balance overview available in My Leaves.");
        return () => resetPageHeader();
    }, []);

    const tabs = [
        { id: 'my_leaves', label: 'My Leaves', icon: <Users size={16} />, hidden: showRequests },
        { id: 'requests', label: 'Requests', icon: <ClipboardList size={16} />, hidden: !showRequests },
        { id: 'configuration', label: 'Configuration', icon: <Settings size={16} />, hidden: !showConfig },
    ].filter(t => !t.hidden);

    return (
        <div style={{ paddingBottom: '40px' }}>
            {/* Tab Navigation */}
            <div style={{ display: 'flex', gap: '32px', borderBottom: '1px solid var(--color-border)', marginBottom: '24px' }}>
                {tabs.map(tab => (
                    <button
                        key={tab.id}
                        onClick={() => setActiveTab(tab.id)}
                        style={{
                            padding: '12px 0',
                            fontSize: '14px',
                            fontWeight: '500',
                            color: activeTab === tab.id ? 'var(--color-rose-gold)' : 'var(--color-text-muted)',
                            borderBottom: activeTab === tab.id ? '2px solid var(--color-rose-gold)' : '2px solid transparent',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            transition: 'all 0.2s',
                            background: 'none',
                            cursor: 'pointer'
                        }}
                    >
                        {tab.icon} {tab.label}
                    </button>
                ))}
            </div>

            {/* Tab Content */}
            <div className="tab-content">
                {activeTab === 'my_leaves' && <MyLeavesTab user={user} onPreview={setPreviewDoc} />}
                {activeTab === 'requests' && <RequestsTab user={user} onPreview={setPreviewDoc} />}
                {activeTab === 'configuration' && <LeaveConfiguration />}
            </div>

            {/* Global Preview Modal */}
            <FilePreviewModal doc={previewDoc} onClose={() => setPreviewDoc(null)} />
        </div>
    );
};

export default Leave;
