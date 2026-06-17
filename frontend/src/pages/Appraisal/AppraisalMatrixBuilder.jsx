import React, { useState, useEffect } from 'react';
import api from '../../services/api';
import { Plus, Trash2, ArrowRight, Save, Building } from 'lucide-react';
import useNotificationStore from '../../store/useNotificationStore';
import useLayoutStore from '../../store/useLayoutStore';

const AppraisalMatrixBuilder = () => {
    const [companies, setCompanies] = useState([]);
    const [selectedCompanyId, setSelectedCompanyId] = useState('');
    const [matrices, setMatrices] = useState([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const showAlert = useNotificationStore(state => state.showAlert);



    const availableRoles = [
        { value: 'EMPLOYEE_SUBMIT', label: 'Employee Submission' },
        { value: 'L1_MANAGER', label: 'Reporting Manager (L1)' },
        { value: 'L2_MANAGER', label: 'Reporting Manager 2 (L2)' },
        { value: 'L3_MANAGER', label: 'Reporting Manager 3 (L3)' },
        { value: 'HR_CALIBRATION', label: 'HR Calibration Layer' },
        { value: 'HR_MANAGER', label: 'HR Manager / Management (Final)' }
    ];

    useEffect(() => {
        fetchCompanies();
    }, []);

    useEffect(() => {
        if (selectedCompanyId) {
            fetchMatrices(selectedCompanyId);
        } else {
            setMatrices([]);
        }
    }, [selectedCompanyId]);

    const fetchCompanies = async () => {
        try {
            const res = await api.get('/organization/companies');
            const data = res.data.data || res.data;
            setCompanies(data);
            if (data.length > 0) setSelectedCompanyId(data[0].id.toString());
        } catch (error) {
            showAlert('Error', 'Failed to load companies', 'error');
        }
    };

    const fetchMatrices = async (companyId) => {
        try {
            setLoading(true);
            const res = await api.get(`/appraisal-matrices?company_id=${companyId}`);
            const data = res.data.data || res.data;
            if (data && data.length > 0) {
                setMatrices(data);
            } else {
                setMatrices([
                    { id: Date.now(), role_required: 'EMPLOYEE_SUBMIT' },
                    { id: Date.now() + 1, role_required: 'L1_MANAGER' },
                    { id: Date.now() + 2, role_required: 'L2_MANAGER' },
                    { id: Date.now() + 3, role_required: 'L3_MANAGER' },
                    { id: Date.now() + 4, role_required: 'HR_CALIBRATION' },
                    { id: Date.now() + 5, role_required: 'HR_MANAGER' }
                ]);
            }
        } catch (error) {
            showAlert('Error', 'Failed to load matrices', 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleAddStep = () => {
        setMatrices([...matrices, { id: Date.now(), role_required: '' }]);
    };

    const handleRemoveStep = (index) => {
        const newMatrices = [...matrices];
        newMatrices.splice(index, 1);
        setMatrices(newMatrices);
    };

    const handleRoleChange = (index, value) => {
        const newMatrices = [...matrices];
        newMatrices[index].role_required = value;
        setMatrices(newMatrices);
    };

    const handleSave = async () => {
        try {
            setSaving(true);
            const payload = {
                company_id: parseInt(selectedCompanyId),
                matrices: matrices.map((m, index) => ({
                    step_order: index + 1,
                    role_required: m.role_required
                }))
            };
            await api.post('/appraisal-matrices', payload);
            showAlert('Success', 'Approval matrix saved successfully', 'success');
        } catch (error) {
            showAlert('Error', 'Failed to save approval matrix', 'error');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div>
            <div className="header-actions" style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end', marginBottom: '24px' }}>
                <button 
                    onClick={handleSave}
                    disabled={saving || !selectedCompanyId}
                    className="btn btn-primary"
                    style={{ display: 'flex', alignItems: 'center', gap: '8px' }}
                >
                    <Save size={18} /> {saving ? 'Saving...' : 'Save Matrix'}
                </button>
            </div>

            <div className="card" style={{ marginBottom: '24px' }}>
                <div className="card-header" style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <Building size={20} style={{ color: 'var(--color-rose-gold)' }} />
                    <h3 style={{ margin: 0, fontSize: '1.1rem' }}>Select Target Company</h3>
                </div>
                <div className="card-body">
                    <div style={{ maxWidth: '400px' }}>
                        <select 
                            className="form-input"
                            value={selectedCompanyId}
                            onChange={e => setSelectedCompanyId(e.target.value)}
                        >
                            <option value="" disabled>Select a company...</option>
                            {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                    </div>
                </div>
            </div>

            <div className="card">
                <div className="card-header">
                    <h3 style={{ margin: 0, fontSize: '1.1rem' }}>Workflow Sequence</h3>
                </div>
                <div className="card-body">
                    <div style={{ padding: '16px', backgroundColor: 'rgba(212, 175, 55, 0.05)', borderLeft: '4px solid var(--color-rose-gold)', borderRadius: '4px', marginBottom: '24px' }}>
                        <h4 style={{ margin: '0 0 8px 0', color: 'var(--color-charcoal)' }}>How it works</h4>
                        <p style={{ margin: 0, color: 'var(--color-text-muted)', fontSize: '0.9rem', lineHeight: '1.5' }}>
                            Define the sequence of approvals. The appraisal flows from left to right. It is considered "Finalized" only after the final step is completed.
                        </p>
                    </div>

                    {loading ? (
                        <div style={{ padding: '40px', textAlign: 'center', color: 'var(--color-text-muted)' }}>Loading matrix...</div>
                    ) : (
                        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: '16px' }}>
                            {matrices.map((step, index) => (
                                <React.Fragment key={step.id || index}>
                                    <div style={{ 
                                        display: 'flex', flexDirection: 'column', gap: '8px', 
                                        padding: '16px', background: 'var(--color-white)', 
                                        border: '1px solid var(--color-border)', borderRadius: '8px', 
                                        minWidth: '220px', boxShadow: '0 2px 4px rgba(0,0,0,0.02)' 
                                    }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                            <span style={{ fontSize: '0.8rem', fontWeight: 'bold', color: 'var(--color-text-muted)', textTransform: 'uppercase' }}>
                                                Step {index + 1}
                                            </span>
                                            {matrices.length > 1 && (
                                                <button 
                                                    onClick={() => handleRemoveStep(index)}
                                                    style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer' }}
                                                    title="Remove Step"
                                                >
                                                    <Trash2 size={16} />
                                                </button>
                                            )}
                                        </div>
                                        <select 
                                            className="form-input"
                                            style={{ padding: '6px 10px', fontSize: '0.9rem' }}
                                            value={step.role_required}
                                            onChange={(e) => handleRoleChange(index, e.target.value)}
                                        >
                                            <option value="" disabled>Select Role</option>
                                            {availableRoles.map(r => (
                                                <option key={r.value} value={r.value}>{r.label}</option>
                                            ))}
                                        </select>
                                    </div>
                                    
                                    {index < matrices.length - 1 && (
                                        <ArrowRight style={{ color: 'var(--color-border)' }} size={24} />
                                    )}
                                </React.Fragment>
                            ))}

                            <button 
                                onClick={handleAddStep}
                                style={{ 
                                    display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', 
                                    padding: '16px', border: '2px dashed var(--color-border)', borderRadius: '8px', 
                                    background: 'transparent', color: 'var(--color-text-muted)', cursor: 'pointer',
                                    minWidth: '120px', minHeight: '96px', transition: 'all 0.2s'
                                }}
                                onMouseOver={(e) => { e.currentTarget.style.borderColor = 'var(--color-rose-gold)'; e.currentTarget.style.color = 'var(--color-rose-gold)'; }}
                                onMouseOut={(e) => { e.currentTarget.style.borderColor = 'var(--color-border)'; e.currentTarget.style.color = 'var(--color-text-muted)'; }}
                            >
                                <Plus size={24} style={{ marginBottom: '4px' }} />
                                <span style={{ fontSize: '0.9rem', fontWeight: 500 }}>Add Step</span>
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default AppraisalMatrixBuilder;
