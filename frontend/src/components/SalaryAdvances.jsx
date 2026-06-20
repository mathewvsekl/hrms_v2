import { useState, useEffect } from 'react';
import { Plus, Check, X, Search, FileText } from 'lucide-react';
import api from '../services/api';
import useNotificationStore from '../store/useNotificationStore';
import useAuthStore from '../store/useAuthStore';
import Modal from './ui/Modal';
import { ROLE_IDS } from '../utils/roleConstants';

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount || 0);
};

const SalaryAdvances = ({ companies }) => {
    const { showAlert } = useNotificationStore();
    const { user } = useAuthStore();
    const isSuperAdmin = user?.role_id === ROLE_IDS.SUPER_ADMIN || user?.role_id === ROLE_IDS.ADMIN;
    const isAdmin = isSuperAdmin || useAuthStore.getState().hasPermission('payroll', 'edit');

    const [advances, setAdvances] = useState([]);
    const [employees, setEmployees] = useState([]);
    const [loading, setLoading] = useState(false);
    const [companyId, setCompanyId] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [formData, setFormData] = useState({
        employee_id: '',
        amount: '',
        installment_amount: '',
        deduction_start_date: '',
        currency_code: 'UGX',
        date_requested: new Date().toISOString().split('T')[0],
        attachment: null
    });
    const [selectedAdvance, setSelectedAdvance] = useState(null);
    const [showDetailModal, setShowDetailModal] = useState(false);
    const [managerComment, setManagerComment] = useState('');
    const [previewDoc, setPreviewDoc] = useState(null);
    const [installments, setInstallments] = useState([]);
    const [loadingInstallments, setLoadingInstallments] = useState(false);
    const [isEditingTerms, setIsEditingTerms] = useState(false);
    const [editTermsData, setEditTermsData] = useState({ installment_amount: '', deduction_start_date: '' });

    useEffect(() => {
        if (companies.length > 0 && !companyId) {
            setCompanyId(companies[0].id);
        }
    }, [companies]);

    useEffect(() => {
        if (companyId) {
            fetchAdvances();
            fetchEmployees();
        }
    }, [companyId]);

    const fetchAdvances = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/salary-advances?company_id=${companyId}`);
            if (res.data.success) {
                setAdvances(res.data.data);
            }
        } catch (error) {
            showAlert('error', 'Failed to fetch salary advances');
        } finally {
            setLoading(false);
        }
    };

    const fetchEmployees = async () => {
        try {
            const res = await api.get(`/employees?company_id=${companyId}&limit=1000`);
            if (res.data.success || res.data.status === 'success' || Array.isArray(res.data.data)) {
                setEmployees(res.data.data || res.data);
            }
        } catch (error) {
            console.error('Error fetching employees:', error);
        }
    };

    const handleCreate = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        try {
            const data = new FormData();
            data.append('employee_id', formData.employee_id);
            data.append('amount', formData.amount);
            if (formData.installment_amount) data.append('installment_amount', formData.installment_amount);
            if (formData.deduction_start_date) data.append('deduction_start_date', formData.deduction_start_date);
            data.append('currency_code', formData.currency_code);
            data.append('date_requested', formData.date_requested);
            data.append('company_id', companyId);
            if (formData.attachment) {
                data.append('attachment', formData.attachment);
            }

            const res = await api.post('/salary-advances', data, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            if (res.data.success) {
                showAlert('success', 'Salary advance recorded successfully');
                setShowModal(false);
                setFormData({
                    employee_id: '',
                    amount: '',
                    installment_amount: '',
                    deduction_start_date: '',
                    currency_code: 'UGX',
                    date_requested: new Date().toISOString().split('T')[0],
                    attachment: null
                });
                fetchAdvances();
            } else {
                showAlert('error', res.data.message || 'Failed to record advance');
            }
        } catch (error) {
            showAlert('error', error.response?.data?.message || 'Error occurred');
        } finally {
            setSubmitting(false);
        }
    };

    const handleStatusUpdate = async (id, status) => {
        if (!confirm(`Are you sure you want to mark this advance as ${status}?`)) return;
        try {
            const res = await api.put(`/salary-advances/${id}/status`, { status });
            if (res.data.success) {
                showAlert('success', `Advance marked as ${status}`);
                fetchAdvances();
            } else {
                showAlert('error', res.data.message || 'Failed to update status');
            }
        } catch (error) {
            showAlert('error', 'Error updating status');
        }
    };

    const handleActionChange = async (e, id) => {
        e.stopPropagation();
        const status = e.target.value;
        if (!status) return;
        
        try {
            const res = await api.put(`/salary-advances/${id}/status`, { status });
            if (res.data.success) {
                showAlert('success', `Advance marked as ${status}`);
                fetchAdvances();
            } else {
                showAlert('error', res.data.message || 'Failed to update status');
            }
        } catch (error) {
            showAlert('error', 'Error updating status');
        }
    };

    const handleRowClick = async (adv) => {
        setSelectedAdvance(adv);
        setManagerComment(adv.manager_comment || '');
        setIsEditingTerms(false);
        setEditTermsData({
            installment_amount: adv.installment_amount || '',
            deduction_start_date: adv.deduction_start_date || ''
        });
        setShowDetailModal(true);
        
        // Fetch installments history
        setLoadingInstallments(true);
        try {
            const res = await api.get(`/salary-advances/${adv.id}/installments`);
            if (res.data.success) {
                setInstallments(res.data.data);
            }
        } catch (error) {
            console.error('Error fetching installments', error);
        } finally {
            setLoadingInstallments(false);
        }
    };

    const handleUpdateTerms = async () => {
        setSubmitting(true);
        try {
            const res = await api.put(`/salary-advances/${selectedAdvance.id}/terms`, editTermsData);
            if (res.data.success) {
                showAlert('success', 'Advance terms updated successfully');
                setIsEditingTerms(false);
                fetchAdvances();
                setSelectedAdvance({
                    ...selectedAdvance,
                    installment_amount: editTermsData.installment_amount,
                    deduction_start_date: editTermsData.deduction_start_date
                });
            } else {
                showAlert('error', res.data.message || 'Failed to update terms');
            }
        } catch (error) {
            showAlert('error', error.response?.data?.message || 'Error updating terms');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDetailAction = async (status) => {
        try {
            const res = await api.put(`/salary-advances/${selectedAdvance.id}/status`, { status, manager_comment: managerComment });
            if (res.data.success) {
                showAlert('success', `Advance marked as ${status}`);
                setShowDetailModal(false);
                fetchAdvances();
            } else {
                showAlert('error', res.data.message || 'Failed to update status');
            }
        } catch (error) {
            showAlert('error', 'Error updating status');
        }
    };


    return (
        <div style={{ animation: 'fadeIn 0.4s ease-out' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px', flexWrap: 'wrap', gap: '16px' }}>
                <div style={{ display: 'flex', gap: '12px', alignItems: 'center', background: '#fff', padding: '8px 16px', borderRadius: '12px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span style={{ fontSize: '14px', fontWeight: '500', color: '#64748b' }}>Company:</span>
                        <select 
                            value={companyId} 
                            onChange={(e) => setCompanyId(e.target.value)}
                            style={{ padding: '6px 12px', borderRadius: '6px', border: '1px solid #e2e8f0', fontSize: '14px', outline: 'none', background: '#f8fafc' }}
                        >
                            {companies.map(c => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <button 
                    onClick={() => setShowModal(true)}
                    className="btn btn-primary"
                    style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 16px', borderRadius: '8px', fontWeight: '500', fontSize: '14px' }}
                >
                    <Plus size={16} />
                    Record Advance
                </button>
            </div>

            <div style={{ background: '#fff', borderRadius: '16px', boxShadow: '0 4px 20px rgba(0,0,0,0.03)', overflow: 'hidden' }}>
                <div style={{ padding: '20px 24px', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h3 style={{ margin: 0, fontSize: '16px', fontWeight: '600', color: '#0f172a' }}>Salary Advances</h3>
                </div>
                
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc', borderBottom: '1px solid #e2e8f0' }}>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Employee</th>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Date Requested</th>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Amount</th>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Reason</th>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Status</th>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b', textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan="6" style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>
                                        Loading advances...
                                    </td>
                                </tr>
                            ) : advances.length === 0 ? (
                                <tr>
                                    <td colSpan="6" style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>
                                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '12px' }}>
                                            <div style={{ width: '48px', height: '48px', background: '#f1f5f9', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                                <FileText size={24} color="#94a3b8" />
                                            </div>
                                            <div>No salary advances found for this company</div>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                advances.map((adv) => (
                                    <tr key={adv.id} onClick={() => handleRowClick(adv)} style={{ borderBottom: '1px solid #f1f5f9', cursor: 'pointer' }} onMouseOver={e => e.currentTarget.style.background = '#f8fafc'} onMouseOut={e => e.currentTarget.style.background = 'transparent'}>
                                        <td style={{ padding: '16px 24px' }}>
                                            <div style={{ fontWeight: '500', color: '#0f172a' }}>{adv.first_name} {adv.last_name}</div>
                                            <div style={{ fontSize: '12px', color: '#64748b' }}>{adv.employee_code}</div>
                                        </td>
                                        <td style={{ padding: '16px 24px', color: '#475569', fontSize: '14px' }}>
                                            {new Date(adv.date_requested).toLocaleDateString()}
                                        </td>
                                        <td style={{ padding: '16px 24px' }}>
                                            <div style={{ fontWeight: '600', color: '#0f172a' }}>{formatCurrency(adv.amount)} {adv.currency_code}</div>
                                            {adv.installment_amount && parseFloat(adv.installment_amount) > 0 && (
                                                <div style={{ fontSize: '12px', color: '#64748b', marginTop: '4px' }}>
                                                    Installment: {formatCurrency(adv.installment_amount)} /mo
                                                </div>
                                            )}
                                            {parseFloat(adv.deducted_amount) > 0 && (
                                                <div style={{ fontSize: '12px', color: '#10b981', marginTop: '2px' }}>
                                                    Paid: {formatCurrency(adv.deducted_amount)}
                                                </div>
                                            )}
                                        </td>
                                        <td style={{ padding: '16px 24px', color: '#475569', fontSize: '13px', maxWidth: '200px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }} title={adv.reason}>
                                            {adv.reason || '-'}
                                        </td>
                                        <td style={{ padding: '16px 24px' }}>
                                            <span style={{ 
                                                display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '4px 10px', borderRadius: '20px', fontSize: '12px', fontWeight: '500',
                                                background: adv.status === 'Pending' ? '#fef3c7' : adv.status === 'Reviewed' ? '#e0f2fe' : adv.status === 'Approved' ? '#dcfce7' : adv.status === 'Deducted' ? '#e0e7ff' : '#fee2e2',
                                                color: adv.status === 'Pending' ? '#b45309' : adv.status === 'Reviewed' ? '#0369a1' : adv.status === 'Approved' ? '#166534' : adv.status === 'Deducted' ? '#3730a3' : '#991b1b'
                                            }}>
                                                {adv.status === 'Pending' && <Search size={12} />}
                                                {adv.status === 'Reviewed' && <Check size={12} />}
                                                {adv.status === 'Approved' && <Check size={12} />}
                                                {adv.status === 'Deducted' && <Check size={12} />}
                                                {adv.status}
                                            </span>
                                        </td>
                                        <td style={{ padding: '16px 24px', textAlign: 'right' }}>
                                            {(adv.status === 'Pending' || adv.status === 'Reviewed') && (
                                                <select
                                                    value=""
                                                    onClick={e => e.stopPropagation()}
                                                    onChange={(e) => handleActionChange(e, adv.id)}
                                                    style={{ padding: '6px 12px', borderRadius: '6px', border: '1px solid #e2e8f0', fontSize: '13px', outline: 'none', background: '#fff', cursor: 'pointer' }}
                                                >
                                                    <option value="" disabled>Actions...</option>
                                                    {adv.status === 'Pending' && <option value="Reviewed">Mark Reviewed</option>}
                                                    <option value="Approved">Approve</option>
                                                    <option value="Rejected">Reject</option>
                                                </select>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modal */}
            <Modal isOpen={showModal} onClose={() => setShowModal(false)} title="Record Salary Advance">
                <form onSubmit={handleCreate} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Employee</label>
                        <select 
                            value={formData.employee_id}
                            onChange={e => setFormData({...formData, employee_id: e.target.value})}
                            required
                            className="form-control"
                        >
                            <option value="">Select an employee...</option>
                            {employees.map(emp => (
                                <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name} ({emp.employee_code})</option>
                            ))}
                        </select>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 100px', gap: '16px' }}>
                        <div className="form-group">
                            <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Amount</label>
                            <input 
                                type="number" 
                                step="0.01"
                                required
                                value={formData.amount}
                                onChange={e => setFormData({...formData, amount: e.target.value})}
                                className="form-control"
                                placeholder="e.g. 500.00"
                            />
                        </div>
                        <div className="form-group">
                            <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Installment /mo</label>
                            <input 
                                type="number" 
                                step="0.01"
                                value={formData.installment_amount}
                                onChange={e => setFormData({...formData, installment_amount: e.target.value})}
                                className="form-control"
                                placeholder="Optional"
                            />
                        </div>
                        <div className="form-group">
                            <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Currency</label>
                            <select 
                                value={formData.currency_code}
                                onChange={e => setFormData({...formData, currency_code: e.target.value})}
                                className="form-control"
                            >
                                <option value="UGX">UGX</option>
                                <option value="USD">USD</option>
                                <option value="AED">AED</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </div>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                        <div className="form-group">
                            <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Date Requested</label>
                            <input 
                                type="date" 
                                required
                                value={formData.date_requested}
                                onChange={e => setFormData({...formData, date_requested: e.target.value})}
                                className="form-control"
                            />
                        </div>
                        <div className="form-group">
                            <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Deduction Start Date (Optional)</label>
                            <input 
                                type="date" 
                                value={formData.deduction_start_date}
                                onChange={e => setFormData({...formData, deduction_start_date: e.target.value})}
                                className="form-control"
                            />
                        </div>
                    </div>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Attachment (Optional)</label>
                        <input 
                            type="file" 
                            className="form-control"
                            onChange={e => setFormData({...formData, attachment: e.target.files[0]})}
                            accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
                        />
                    </div>
                    <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end', marginTop: '8px' }}>
                        <button type="button" className="btn btn-secondary" onClick={() => setShowModal(false)} disabled={submitting}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={submitting}>
                            {submitting ? 'Recording...' : 'Record Advance'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Detail Modal */}
            <Modal isOpen={showDetailModal && selectedAdvance} onClose={() => setShowDetailModal(false)} title="Advance Request Details">
                {selectedAdvance && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <div style={{ marginBottom: '8px' }}>
                            <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '4px' }}>Employee</div>
                            <div style={{ fontWeight: '500' }}>{selectedAdvance.first_name} {selectedAdvance.last_name} ({selectedAdvance.employee_code})</div>
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '8px' }}>
                            <div>
                                <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '4px' }}>Amount</div>
                                <div style={{ fontWeight: '500' }}>
                                    {formatCurrency(selectedAdvance.amount)} {selectedAdvance.currency_code}
                                    {selectedAdvance.installment_amount > 0 && ` (${formatCurrency(selectedAdvance.installment_amount)}/mo)`}
                                </div>
                            </div>
                            <div>
                                <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '4px' }}>Date Requested</div>
                                <div style={{ fontWeight: '500' }}>{new Date(selectedAdvance.date_requested).toLocaleDateString()}</div>
                            </div>
                            {selectedAdvance.deduction_start_date && (
                                <div>
                                    <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '4px' }}>Deduction Starts</div>
                                    <div style={{ fontWeight: '500' }}>{new Date(selectedAdvance.deduction_start_date).toLocaleDateString()}</div>
                                </div>
                            )}
                        </div>
                        <div style={{ marginBottom: '8px' }}>
                            <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '4px' }}>Reason</div>
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '8px', fontSize: '14px' }}>{selectedAdvance.reason || 'No reason provided.'}</div>
                        </div>
                        {selectedAdvance.attachment && (
                            <div style={{ marginBottom: '8px' }}>
                                <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '4px' }}>Attachment</div>
                                <button 
                                    onClick={(e) => {
                                        e.preventDefault();
                                        setPreviewDoc(`http://localhost:8000${selectedAdvance.attachment}#toolbar=0&navpanes=0&scrollbar=0`);
                                    }}
                                    style={{ background: 'none', border: 'none', padding: 0, color: '#3b82f6', textDecoration: 'underline', fontSize: '14px', cursor: 'pointer' }}
                                >
                                    View Attachment
                                </button>
                            </div>
                        )}

                        {(selectedAdvance.status === 'Pending' || selectedAdvance.status === 'Reviewed') ? (
                            <div style={{ marginTop: '16px', borderTop: '1px solid #f1f5f9', paddingTop: '16px' }}>
                                <div className="form-group" style={{ marginBottom: '16px' }}>
                                    <label className="form-label" style={{ display: 'block', fontSize: '13px', fontWeight: '500', color: '#475569', marginBottom: '8px' }}>Manager Comment (Optional)</label>
                                    <textarea 
                                        value={managerComment}
                                        onChange={e => setManagerComment(e.target.value)}
                                        className="form-control"
                                        style={{ minHeight: '80px', resize: 'vertical' }}
                                        placeholder="Add a comment before approving/rejecting..."
                                    />
                                </div>
                                <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                                    {selectedAdvance.status === 'Pending' && (
                                        <button onClick={() => handleDetailAction('Reviewed')} className="btn btn-secondary">
                                            Mark Reviewed
                                        </button>
                                    )}
                                    <button onClick={() => handleDetailAction('Rejected')} style={{ padding: '8px 16px', borderRadius: '8px', border: '1px solid #fca5a5', background: '#fee2e2', color: '#991b1b', fontWeight: '500', cursor: 'pointer' }}>
                                        Reject
                                    </button>
                                    <button onClick={() => handleDetailAction('Approved')} className="btn btn-primary">
                                        Approve
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <div style={{ marginTop: '16px' }}>
                                <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '4px' }}>Manager Comment</div>
                                <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '8px', fontSize: '14px' }}>
                                    {selectedAdvance.manager_comment || 'No comment provided.'}
                                </div>
                                
                                {['Approved', 'Partially Deducted', 'Deducted'].includes(selectedAdvance.status) && (
                                    <div style={{ marginTop: '24px', borderTop: '1px solid #e2e8f0', paddingTop: '16px' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                                            <h4 style={{ margin: 0, fontSize: '14px', fontWeight: '600' }}>Repayment Terms</h4>
                                            {selectedAdvance.status !== 'Deducted' && (
                                                !isEditingTerms ? (
                                                    <button onClick={() => setIsEditingTerms(true)} style={{ background: 'none', border: '1px solid #e2e8f0', padding: '4px 12px', borderRadius: '6px', fontSize: '13px', color: '#3b82f6', cursor: 'pointer' }}>Edit Terms</button>
                                                ) : (
                                                    <div style={{ display: 'flex', gap: '8px' }}>
                                                        <button onClick={() => setIsEditingTerms(false)} style={{ background: 'none', border: 'none', padding: '4px 12px', fontSize: '13px', color: '#64748b', cursor: 'pointer' }}>Cancel</button>
                                                        <button onClick={handleUpdateTerms} disabled={submitting} style={{ background: '#3b82f6', border: 'none', padding: '4px 12px', borderRadius: '6px', fontSize: '13px', color: '#fff', cursor: 'pointer' }}>Save</button>
                                                    </div>
                                                )
                                            )}
                                        </div>
                                        {isEditingTerms && (
                                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', background: '#f8fafc', padding: '16px', borderRadius: '8px', marginBottom: '16px' }}>
                                                <div className="form-group">
                                                    <label className="form-label" style={{ fontSize: '12px' }}>Installment /mo</label>
                                                    <input type="number" step="0.01" value={editTermsData.installment_amount} onChange={e => setEditTermsData({...editTermsData, installment_amount: e.target.value})} className="form-control" placeholder="Optional" />
                                                </div>
                                                <div className="form-group">
                                                    <label className="form-label" style={{ fontSize: '12px' }}>Deduction Start Date</label>
                                                    <input type="date" value={editTermsData.deduction_start_date} onChange={e => setEditTermsData({...editTermsData, deduction_start_date: e.target.value})} className="form-control" />
                                                </div>
                                            </div>
                                        )}
                                        <div style={{ marginBottom: '16px' }}>
                                            <div style={{ fontSize: '13px', color: '#64748b', marginBottom: '4px' }}>Amount Paid</div>
                                            <div style={{ fontWeight: '600', color: '#10b981' }}>{formatCurrency(selectedAdvance.deducted_amount || 0)} {selectedAdvance.currency_code}</div>
                                        </div>
                                        
                                        <h4 style={{ margin: '0 0 12px 0', fontSize: '14px', fontWeight: '600' }}>Installment History</h4>
                                        {loadingInstallments ? (
                                            <div style={{ fontSize: '13px', color: '#64748b' }}>Loading history...</div>
                                        ) : installments.length === 0 ? (
                                            <div style={{ fontSize: '13px', color: '#64748b', background: '#f8fafc', padding: '12px', borderRadius: '8px' }}>No deductions have been made yet.</div>
                                        ) : (
                                            <table style={{ width: '100%', fontSize: '13px', borderCollapse: 'collapse' }}>
                                                <thead>
                                                    <tr style={{ background: '#f1f5f9' }}>
                                                        <th style={{ padding: '8px', textAlign: 'left', fontWeight: '500' }}>Payroll</th>
                                                        <th style={{ padding: '8px', textAlign: 'left', fontWeight: '500' }}>Date of Deduction</th>
                                                        <th style={{ padding: '8px', textAlign: 'right', fontWeight: '500' }}>Amount Deducted</th>
                                                        <th style={{ padding: '8px', textAlign: 'right', fontWeight: '500' }}>Remaining Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {installments.map(inst => (
                                                        <tr key={inst.id} style={{ borderBottom: '1px solid #f1f5f9' }}>
                                                            <td style={{ padding: '8px' }}>{inst.payroll_name}</td>
                                                            <td style={{ padding: '8px' }}>{inst.deduction_date ? new Date(inst.deduction_date).toLocaleDateString() : new Date(inst.payroll_date).toLocaleDateString()}</td>
                                                            <td style={{ padding: '8px', textAlign: 'right', fontWeight: '500' }}>{formatCurrency(inst.amount)} {selectedAdvance.currency_code}</td>
                                                            <td style={{ padding: '8px', textAlign: 'right', fontWeight: '500' }}>{inst.remaining_balance !== null && inst.remaining_balance !== undefined ? formatCurrency(inst.remaining_balance) + ' ' + selectedAdvance.currency_code : '-'}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </Modal>

            {/* Document Preview Modal */}
            {previewDoc && (
                <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, background: 'rgba(15,23,42,0.75)', backdropFilter: 'blur(4px)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1100, padding: '20px' }}>
                    <div style={{ background: '#fff', borderRadius: '16px', width: '100%', maxWidth: '900px', height: '85vh', display: 'flex', flexDirection: 'column', boxShadow: '0 20px 25px -5px rgba(0,0,0,0.1)' }}>
                        <div style={{ padding: '20px 24px', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <h3 style={{ margin: 0, fontSize: '18px', fontWeight: '600', color: '#0f172a', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <FileText size={20} style={{ color: 'var(--color-primary)' }}/> Document Preview
                            </h3>
                            <button onClick={() => setPreviewDoc(null)} style={{ background: '#f1f5f9', border: 'none', cursor: 'pointer', color: '#64748b', width: '32px', height: '32px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <X size={18} />
                            </button>
                        </div>
                        <div style={{ flex: 1, padding: '24px', background: '#f8fafc', overflow: 'hidden' }}>
                            <iframe 
                                src={previewDoc} 
                                style={{ width: '100%', height: '100%', border: 'none', borderRadius: '8px', background: '#fff', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }} 
                                title="Document Preview" 
                            />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default SalaryAdvances;
