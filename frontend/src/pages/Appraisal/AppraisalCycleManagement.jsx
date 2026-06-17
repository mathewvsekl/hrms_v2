import React, { useState, useEffect } from 'react';
import api from '../../services/api';
import { Ban, Trash2, PlayCircle } from 'lucide-react';
import useNotificationStore from '../../store/useNotificationStore';
import useLayoutStore from '../../store/useLayoutStore';
import DateInput from '../../components/ui/DateInput';

const AppraisalCycleManagement = () => {
    const [cycles, setCycles] = useState([]);
    const [templates, setTemplates] = useState([]);
    const [offices, setOffices] = useState([]);
    const [loading, setLoading] = useState(false);
    const showAlert = useNotificationStore(state => state.showAlert);
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();

    const [formData, setFormData] = useState({
        year: new Date().getFullYear(),
        frequency: 'Yearly',
        period: '',
        template_id: '',
        office_ids: 'ALL',
        employee_deadline: '',
        manager_deadline: '',
        hr_deadline: '',
        management_deadline: ''
    });

    const currentYear = new Date().getFullYear();
    const years = [currentYear - 1, currentYear, currentYear + 1, currentYear + 2];

    useEffect(() => {
        setPageTitle("Cycle Management");
        setPageSubtitle("Initiate and monitor performance appraisal cycles");
        setBackPath('/appraisals');
        return () => resetPageHeader();
    }, []);

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        try {
            setLoading(true);
            const [cyclesRes, templatesRes, officesRes] = await Promise.all([
                api.get('/appraisal-cycles').catch(() => ({ data: [] })),
                api.get('/appraisal-templates').catch(() => ({ data: [] })),
                api.get('/organization/companies').catch(() => ({ data: { data: [] } }))
            ]);
            setCycles(cyclesRes.data.data || cyclesRes.data || []);
            setTemplates(templatesRes.data.data || templatesRes.data || []);
            setOffices(officesRes.data.data || officesRes.data || []);
        } catch (error) {
            showAlert('Error', 'Failed to load cycles', 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleCreateCycle = async (e) => {
        e.preventDefault();
        try {
            const payload = { ...formData };
            if (payload.office_ids === 'ALL') {
                payload.office_ids = offices.map(o => o.id.toString());
            } else {
                payload.office_ids = [payload.office_ids];
            }
            
            if (payload.frequency !== 'Yearly' && !payload.period) {
                showAlert('Error', 'Please select a period for the chosen frequency.', 'error');
                return;
            }

            await api.post('/appraisal-cycles/generate', payload);
            showAlert('Success', 'Cycle Generated Successfully', 'success');
            fetchData();
        } catch (error) {
            showAlert('Error', 'Failed to generate cycle', 'error');
        }
    };

    const handleCancel = async (id) => {
        if (!window.confirm('Cancel and lock this cycle?')) return;
        try {
            await api.post(`/appraisal-cycles/${id}/cancel`);
            showAlert('Success', 'Cycle cancelled and locked', 'success');
            fetchData();
        } catch (error) {
            showAlert('Error', 'Failed to cancel cycle', 'error');
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this cycle? (Only works if no data entered)')) return;
        try {
            await api.delete(`/appraisal-cycles/${id}`);
            showAlert('Success', 'Cycle deleted', 'success');
            fetchData();
        } catch (error) {
            showAlert('Error', error.response?.data?.message || 'Failed to delete. Appraisals might already be active.', 'error');
        }
    };

    const [editingCycle, setEditingCycle] = useState(null);

    const handleSaveEdit = async (id) => {
        try {
            await api.put(`/appraisal-cycles/${id}`, editingCycle);
            showAlert('Success', 'Cycle deadlines updated', 'success');
            setEditingCycle(null);
            fetchData();
        } catch (error) {
            showAlert('Error', 'Failed to update cycle', 'error');
        }
    };

    return (
        <div className="appraisals-container">
            <div className="card" style={{ marginBottom: '24px' }}>
                <div className="card-header">
                    <h2 style={{ margin: 0, fontSize: '1.2rem' }}>Initiate New Appraisal Cycle</h2>
                </div>
                <div className="card-body">
                    <form onSubmit={handleCreateCycle}>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px', marginBottom: '24px' }}>
                            <div>
                                <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.9rem', fontWeight: 500 }}>Appraisal Year</label>
                                <select 
                                    required className="form-input" style={{ width: '100%' }}
                                    value={formData.year}
                                    onChange={e => setFormData({...formData, year: parseInt(e.target.value)})}
                                >
                                    {years.map(y => <option key={y} value={y}>{y}</option>)}
                                </select>
                            </div>
                            <div>
                                <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.9rem', fontWeight: 500 }}>Frequency</label>
                                <select 
                                    required className="form-input" style={{ width: '100%' }}
                                    value={formData.frequency}
                                    onChange={e => setFormData({...formData, frequency: e.target.value, period: ''})}
                                >
                                    <option value="Yearly">Yearly</option>
                                    <option value="Half-yearly">Half-yearly</option>
                                    <option value="Quarterly">Quarterly</option>
                                </select>
                            </div>
                            
                            {formData.frequency !== 'Yearly' && (
                                <div>
                                    <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.9rem', fontWeight: 500 }}>Period</label>
                                    <select 
                                        required className="form-input" style={{ width: '100%' }}
                                        value={formData.period}
                                        onChange={e => setFormData({...formData, period: e.target.value})}
                                    >
                                        <option value="" disabled>Select Period</option>
                                        {formData.frequency === 'Half-yearly' && (
                                            <>
                                                <option value="H1">H1 (Jan - Jun)</option>
                                                <option value="H2">H2 (Jul - Dec)</option>
                                            </>
                                        )}
                                        {formData.frequency === 'Quarterly' && (
                                            <>
                                                <option value="Q1">Q1 (Jan - Mar)</option>
                                                <option value="Q2">Q2 (Apr - Jun)</option>
                                                <option value="Q3">Q3 (Jul - Sep)</option>
                                                <option value="Q4">Q4 (Oct - Dec)</option>
                                            </>
                                        )}
                                    </select>
                                </div>
                            )}

                            <div>
                                <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.9rem', fontWeight: 500 }}>Target Company</label>
                                <select 
                                    required className="form-input" style={{ width: '100%' }}
                                    value={formData.office_ids}
                                    onChange={e => setFormData({...formData, office_ids: e.target.value})}
                                >
                                    <option value="ALL">Global (All Companies)</option>
                                    {offices.map(o => (
                                        <option key={o.id} value={o.id}>{o.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.9rem', fontWeight: 500 }}>Appraisal Template</label>
                                <select 
                                    required className="form-input" style={{ width: '100%' }}
                                    value={formData.template_id}
                                    onChange={e => setFormData({...formData, template_id: e.target.value})}
                                >
                                    <option value="" disabled>Select a Template</option>
                                    {templates.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                                </select>
                            </div>
                        </div>
                        
                        <div style={{ padding: '16px', background: 'var(--color-bg-light)', borderRadius: '6px', marginBottom: '24px' }}>
                            <h3 style={{ margin: '0 0 16px 0', fontSize: '1rem', color: 'var(--color-rose-gold)' }}>Landmark Deadlines</h3>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '20px' }}>
                                <div>
                                    <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.85rem', fontWeight: 500 }}>Employee Submission</label>
                                    <DateInput value={formData.employee_deadline} onChange={val => setFormData({...formData, employee_deadline: val})} />
                                </div>
                                <div>
                                    <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.85rem', fontWeight: 500 }}>Manager Review</label>
                                    <DateInput value={formData.manager_deadline} onChange={val => setFormData({...formData, manager_deadline: val})} />
                                </div>
                                <div>
                                    <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.85rem', fontWeight: 500 }}>HR Review</label>
                                    <DateInput value={formData.hr_deadline} onChange={val => setFormData({...formData, hr_deadline: val})} />
                                </div>
                                <div>
                                    <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.85rem', fontWeight: 500 }}>Management Approval</label>
                                    <DateInput value={formData.management_deadline} onChange={val => setFormData({...formData, management_deadline: val})} />
                                </div>
                            </div>
                        </div>

                        <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                            <button type="submit" className="btn btn-primary" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <PlayCircle size={18} /> Generate Cycle
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div className="card">
                <div className="card-header">
                    <h2 style={{ margin: 0, fontSize: '1.2rem' }}>Active & Past Cycles</h2>
                </div>
                <div className="card-body" style={{ padding: 0 }}>
                    <div className="table-container">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Cycle</th>
                                    <th>Frequency / Period</th>
                                    <th>Target Company</th>
                                    <th>Status</th>
                                    <th>Landmark Deadlines</th>
                                    <th style={{ textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loading ? (
                                    <tr><td colSpan="6" style={{ textAlign: 'center', padding: '20px' }}>Loading...</td></tr>
                                ) : cycles.length === 0 ? (
                                    <tr><td colSpan="6" style={{ textAlign: 'center', padding: '20px' }}>No cycles found</td></tr>
                                ) : (
                                    cycles.map(c => {
                                        // Parse selected offices
                                        let selectedCompanyNames = "Global";
                                        try {
                                            const selectedIds = JSON.parse(c.selected_offices || '[]');
                                            if (selectedIds && selectedIds.length > 0 && selectedIds.length < offices.length) {
                                                selectedCompanyNames = selectedIds.map(id => {
                                                    const office = offices.find(o => String(o.id) === String(id));
                                                    return office ? office.name : id;
                                                }).join(', ');
                                            }
                                        } catch(e) {}
                                        
                                        const isEditing = editingCycle?.id === c.id;

                                        return (
                                        <tr key={c.id}>
                                            <td style={{ fontWeight: 500 }}>{c.name}</td>
                                            <td>
                                                <div>{c.frequency}</div>
                                                {c.period && <div style={{ fontSize: '0.85rem', color: '#64748b' }}>{c.period}</div>}
                                            </td>
                                            <td>{selectedCompanyNames}</td>
                                            <td>
                                                <span style={{
                                                    display: 'inline-flex',
                                                    padding: '4px 10px',
                                                    borderRadius: '20px',
                                                    fontSize: '0.8rem',
                                                    fontWeight: 600,
                                                    textTransform: 'uppercase',
                                                    background: c.status === 'active' ? '#dcfce7' : c.status === 'cancelled' ? '#fee2e2' : '#f3f4f6',
                                                    color: c.status === 'active' ? '#166534' : c.status === 'cancelled' ? '#991b1b' : '#374151'
                                                }}>
                                                    {c.status}
                                                </span>
                                            </td>
                                            <td style={{ fontSize: '0.85rem', color: '#475569' }}>
                                                {isEditing ? (
                                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                                        <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}><strong style={{ width: '40px' }}>Emp:</strong> <DateInput value={editingCycle.employee_deadline || ''} onChange={v => setEditingCycle({...editingCycle, employee_deadline: v})} /></div>
                                                        <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}><strong style={{ width: '40px' }}>Mgr:</strong> <DateInput value={editingCycle.manager_deadline || ''} onChange={v => setEditingCycle({...editingCycle, manager_deadline: v})} /></div>
                                                        <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}><strong style={{ width: '40px' }}>HR:</strong> <DateInput value={editingCycle.hr_deadline || ''} onChange={v => setEditingCycle({...editingCycle, hr_deadline: v})} /></div>
                                                        <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}><strong style={{ width: '40px' }}>Mgmt:</strong> <DateInput value={editingCycle.management_deadline || ''} onChange={v => setEditingCycle({...editingCycle, management_deadline: v})} /></div>
                                                    </div>
                                                ) : (
                                                    <>
                                                        {c.employee_deadline && <div><strong>Emp:</strong> {c.employee_deadline}</div>}
                                                        {c.manager_deadline && <div><strong>Mgr:</strong> {c.manager_deadline}</div>}
                                                        {c.hr_deadline && <div><strong>HR:</strong> {c.hr_deadline}</div>}
                                                        {c.management_deadline && <div><strong>Mgmt:</strong> {c.management_deadline}</div>}
                                                    </>
                                                )}
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                {isEditing ? (
                                                    <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                                        <button onClick={() => setEditingCycle(null)} className="btn btn-secondary" style={{ padding: '4px 10px', fontSize: '0.85rem' }}>Cancel</button>
                                                        <button onClick={() => handleSaveEdit(c.id)} className="btn btn-primary" style={{ padding: '4px 10px', fontSize: '0.85rem' }}>Save</button>
                                                    </div>
                                                ) : (
                                                    <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end', flexWrap: 'wrap', width: '160px', marginLeft: 'auto' }}>
                                                        <button onClick={() => setEditingCycle(c)} disabled={c.status !== 'active'} className="btn btn-secondary" style={{ padding: '4px 10px', fontSize: '0.85rem', display: 'flex', alignItems: 'center', gap: '4px', opacity: c.status !== 'active' ? 0.5 : 1 }}>
                                                            Edit
                                                        </button>
                                                        <button onClick={() => handleCancel(c.id)} disabled={c.status !== 'active'} className="btn btn-secondary" style={{ padding: '4px 10px', fontSize: '0.85rem', display: 'flex', alignItems: 'center', gap: '4px', opacity: c.status !== 'active' ? 0.5 : 1 }}>
                                                            <Ban size={14} /> Cancel
                                                        </button>
                                                        <button onClick={() => handleDelete(c.id)} className="btn btn-secondary" style={{ padding: '4px 10px', fontSize: '0.85rem', display: 'flex', alignItems: 'center', gap: '4px', color: '#dc2626', borderColor: '#fca5a5' }}>
                                                            <Trash2 size={14} /> Delete
                                                        </button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    )})
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AppraisalCycleManagement;
