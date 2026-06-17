import { useState, useEffect } from 'react';
import { Plus, Banknote, FileText, Edit, Trash2, X } from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useNotificationStore from '../store/useNotificationStore';
import { formatDate } from '../utils/dateUtils';
import Modal from '../components/ui/Modal';
import useLayoutStore from '../store/useLayoutStore';

const EmployeeAdvances = () => {
    const { user } = useAuthStore();
    const [advances, setAdvances] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [editId, setEditId] = useState(null);
    const [showHistoryModal, setShowHistoryModal] = useState(false);
    const [history, setHistory] = useState([]);
    const [loadingHistory, setLoadingHistory] = useState(false);
    const [selectedHistoryAdv, setSelectedHistoryAdv] = useState(null);
    const [previewDoc, setPreviewDoc] = useState(null);
    const [formData, setFormData] = useState({
        amount: '',
        installment_amount: '',
        deduction_start_date: '',
        reason: '',
        date_requested: new Date().toISOString().split('T')[0],
        attachment: null
    });
    const { showAlert } = useNotificationStore();
    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        setPageTitle("My Salary Advances");
        setPageSubtitle("Request and track your salary advances");
        return () => resetPageHeader();
    }, []);

    useEffect(() => {
        if (user?.employee_id || user?.id) {
            fetchAdvances();
        }
    }, [user]);

    const fetchAdvances = async () => {
        setLoading(true);
        try {
            const empId = user?.employee_id || user?.id;
            const res = await api.get(`/salary-advances?employee_id=${empId}`);
            if (res.data?.success || res.data) {
                setAdvances(res.data.data || res.data);
            }
        } catch (error) {
            console.error("Failed to fetch advances", error);
        } finally {
            setLoading(false);
        }
    };

    const getStatusColor = (status) => {
        if (!status) return '#64748b';
        switch (status.toLowerCase()) {
            case 'approved': return '#10b981';
            case 'pending': return '#f59e0b';
            case 'reviewed': return '#3b82f6';
            case 'rejected': return '#ef4444';
            case 'deducted': return '#8b5cf6';
            default: return '#64748b';
        }
    };

    const handleRequest = async (e) => {
        e.preventDefault();
        if (!formData.amount) return showAlert('Required', 'Please enter amount', 'warning');
        setSubmitting(true);
        try {
            if (editId) {
                await api.put(`/salary-advances/${editId}`, {
                    amount: formData.amount,
                    installment_amount: formData.installment_amount,
                    deduction_start_date: formData.deduction_start_date,
                    reason: formData.reason
                });
                showAlert('Success', 'Salary advance updated successfully', 'success');
            } else {
                const data = new FormData();
                data.append('employee_id', user?.employee_id || user?.id);
                data.append('amount', formData.amount);
                if (formData.installment_amount) data.append('installment_amount', formData.installment_amount);
                if (formData.deduction_start_date) data.append('deduction_start_date', formData.deduction_start_date);
                data.append('reason', formData.reason);
                data.append('date_requested', formData.date_requested);
                if (formData.attachment) {
                    data.append('attachment', formData.attachment);
                }
                
                await api.post('/salary-advances', data, {
                    headers: { 'Content-Type': 'multipart/form-data' }
                });
                showAlert('Success', 'Salary advance requested successfully', 'success');
            }
            setShowModal(false);
            setEditId(null);
            setFormData({ amount: '', installment_amount: '', deduction_start_date: '', reason: '', date_requested: new Date().toISOString().split('T')[0], attachment: null });
            fetchAdvances();
        } catch (error) {
            showAlert('Error', error.response?.data?.message || 'Failed to submit request', 'error');
        } finally {
            setSubmitting(false);
        }
    };

    const handleEdit = (adv) => {
        setFormData({ amount: adv.amount, installment_amount: adv.installment_amount || '', reason: adv.reason || '', date_requested: adv.date_requested, attachment: null });
        setEditId(adv.id);
        setShowModal(true);
    };

    const handleCancelRequest = async (id) => {
        if (!confirm('Are you sure you want to cancel this request?')) return;
        try {
            await api.delete(`/salary-advances/${id}`);
            showAlert('Success', 'Request cancelled', 'success');
            fetchAdvances();
        } catch (error) {
            showAlert('Error', 'Failed to cancel request', 'error');
        }
    };

    const handleViewHistory = async (adv) => {
        setSelectedHistoryAdv(adv);
        setShowHistoryModal(true);
        setLoadingHistory(true);
        try {
            const res = await api.get(`/salary-advances/${adv.id}/installments`);
            if (res.data.success) {
                setHistory(res.data.data);
            }
        } catch (error) {
            console.error('Error fetching history:', error);
        } finally {
            setLoadingHistory(false);
        }
    };

    return (
        <div style={{ animation: 'fadeIn 0.4s ease-out' }}>
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '24px' }}>
                <button 
                    onClick={() => {
                        setEditId(null);
                        setFormData({ amount: '', reason: '', date_requested: new Date().toISOString().split('T')[0], attachment: null });
                        setShowModal(true);
                    }}
                    className="btn btn-primary"
                    style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 16px', borderRadius: '8px', fontWeight: '500', fontSize: '14px' }}
                >
                    <Plus size={16} />
                    Request Advance
                </button>
            </div>

            <div style={{ background: '#fff', borderRadius: '16px', boxShadow: '0 4px 20px rgba(0,0,0,0.03)', overflow: 'hidden' }}>
                <div style={{ padding: '20px 24px', borderBottom: '1px solid #f1f5f9' }}>
                    <h3 style={{ margin: 0, fontSize: '16px', fontWeight: '600', color: '#0f172a' }}>Advance History</h3>
                </div>
                
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc', borderBottom: '1px solid #e2e8f0' }}>
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
                                    <td colSpan="5" style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>
                                        Loading advances...
                                    </td>
                                </tr>
                            ) : advances.length === 0 ? (
                                <tr>
                                    <td colSpan="5" style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>
                                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '12px' }}>
                                            <div style={{ width: '48px', height: '48px', background: '#f1f5f9', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                                <FileText size={24} color="#94a3b8" />
                                            </div>
                                            <div>You have not requested any salary advances.</div>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                advances.map((adv) => (
                                    <tr key={adv.id} style={{ borderBottom: '1px solid #f1f5f9' }}>
                                        <td style={{ padding: '16px 24px', color: '#475569', fontSize: '14px' }}>
                                            {formatDate(adv.date_requested)}
                                        </td>
                                        <td style={{ padding: '16px 24px' }}>
                                            <span style={{ fontWeight: '600', color: '#0f172a' }}>
                                                {adv.currency_code} {parseFloat(adv.amount).toLocaleString()}
                                            </span>
                                            {adv.installment_amount && parseFloat(adv.installment_amount) > 0 && (
                                                <div style={{ fontSize: '12px', color: '#64748b', marginTop: '4px' }}>
                                                    Installment: {parseFloat(adv.installment_amount).toLocaleString()} /mo
                                                </div>
                                            )}
                                            {parseFloat(adv.deducted_amount) > 0 && (
                                                <div style={{ fontSize: '12px', color: '#10b981', marginTop: '2px' }}>
                                                    Paid: {parseFloat(adv.deducted_amount).toLocaleString()}
                                                </div>
                                            )}
                                        </td>
                                        <td style={{ padding: '16px 24px', color: '#475569', fontSize: '14px' }}>
                                            <div>{adv.reason || '-'}</div>
                                            {adv.attachment && (
                                                <button 
                                                    onClick={(e) => {
                                                        e.preventDefault();
                                                        setPreviewDoc(`http://localhost:8000${adv.attachment}#toolbar=0&navpanes=0&scrollbar=0`);
                                                    }}
                                                    style={{ background: 'none', border: 'none', padding: 0, marginTop: '4px', fontSize: '12px', color: '#3b82f6', textDecoration: 'underline', cursor: 'pointer', display: 'inline-block' }}
                                                >
                                                    View Attachment
                                                </button>
                                            )}
                                            {adv.manager_comment && (
                                                <div style={{ marginTop: '8px', fontSize: '12px', background: '#f1f5f9', padding: '6px 10px', borderRadius: '6px', color: '#475569' }}>
                                                    <strong>Manager:</strong> {adv.manager_comment}
                                                </div>
                                            )}
                                        </td>
                                        <td style={{ padding: '16px 24px' }}>
                                            <span style={{
                                                display: 'inline-block',
                                                padding: '4px 12px',
                                                borderRadius: '20px',
                                                fontSize: '12px',
                                                fontWeight: '600',
                                                backgroundColor: `${getStatusColor(adv.status)}15`,
                                                color: getStatusColor(adv.status)
                                            }}>
                                                {adv.status}
                                            </span>
                                        </td>
                                        <td style={{ padding: '16px 24px', textAlign: 'right' }}>
                                            <div style={{ display: 'flex', gap: '8px', justifyContent: 'flex-end' }}>
                                                {parseFloat(adv.deducted_amount) > 0 && (
                                                    <button 
                                                        onClick={() => handleViewHistory(adv)}
                                                        style={{ padding: '6px', borderRadius: '6px', border: '1px solid #e2e8f0', background: '#fff', color: '#10b981', cursor: 'pointer', transition: 'all 0.2s' }}
                                                        onMouseOver={e => { e.currentTarget.style.borderColor = '#10b981'; e.currentTarget.style.background = '#ecfdf5'; }}
                                                        onMouseOut={e => { e.currentTarget.style.borderColor = '#e2e8f0'; e.currentTarget.style.background = '#fff'; }}
                                                        title="View History"
                                                    >
                                                        <FileText size={14} />
                                                    </button>
                                                )}
                                                {adv.status.toLowerCase() === 'pending' && (
                                                    <>
                                                    <button 
                                                        onClick={() => handleEdit(adv)}
                                                        style={{ padding: '6px', borderRadius: '6px', border: '1px solid #e2e8f0', background: '#fff', color: '#3b82f6', cursor: 'pointer', transition: 'all 0.2s' }}
                                                        onMouseOver={e => { e.currentTarget.style.borderColor = '#3b82f6'; e.currentTarget.style.background = '#eff6ff'; }}
                                                        onMouseOut={e => { e.currentTarget.style.borderColor = '#e2e8f0'; e.currentTarget.style.background = '#fff'; }}
                                                        title="Edit Request"
                                                    >
                                                        <Edit size={14} />
                                                    </button>
                                                    <button 
                                                        onClick={() => handleCancelRequest(adv.id)}
                                                        style={{ padding: '6px', borderRadius: '6px', border: '1px solid #e2e8f0', background: '#fff', color: '#ef4444', cursor: 'pointer', transition: 'all 0.2s' }}
                                                        onMouseOver={e => { e.currentTarget.style.borderColor = '#ef4444'; e.currentTarget.style.background = '#fef2f2'; }}
                                                        onMouseOut={e => { e.currentTarget.style.borderColor = '#e2e8f0'; e.currentTarget.style.background = '#fff'; }}
                                                        title="Cancel Request"
                                                    >
                                                        <Trash2 size={14} />
                                                    </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Modal isOpen={showHistoryModal} onClose={() => { setShowHistoryModal(false); setSelectedHistoryAdv(null); }} title="Repayment History">
                {loadingHistory ? (
                    <div style={{ padding: '20px', textAlign: 'center', color: '#64748b' }}>Loading history...</div>
                ) : history.length === 0 ? (
                    <div style={{ padding: '20px', textAlign: 'center', color: '#64748b' }}>No deductions have been made yet.</div>
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
                            {history.map(inst => (
                                <tr key={inst.id} style={{ borderBottom: '1px solid #f1f5f9' }}>
                                    <td style={{ padding: '8px' }}>{inst.payroll_name}</td>
                                    <td style={{ padding: '8px' }}>{inst.deduction_date ? new Date(inst.deduction_date).toLocaleDateString() : new Date(inst.payroll_date).toLocaleDateString()}</td>
                                    <td style={{ padding: '8px', textAlign: 'right', fontWeight: '500' }}>{parseFloat(inst.amount).toLocaleString()}</td>
                                    <td style={{ padding: '8px', textAlign: 'right', fontWeight: '500' }}>{inst.remaining_balance !== null && inst.remaining_balance !== undefined ? parseFloat(inst.remaining_balance).toLocaleString() : '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Modal>

            <Modal isOpen={showModal} onClose={() => { setShowModal(false); setEditId(null); }} title={editId ? "Edit Salary Advance Request" : "Request Salary Advance"}>
                <form onSubmit={handleRequest} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Amount</label>
                        <input 
                            type="number" 
                            className="form-control" 
                            required 
                            value={formData.amount} 
                            onChange={e => setFormData({...formData, amount: e.target.value})}
                        />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                        <div className="form-group">
                            <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Monthly Installment Deduction (Optional)</label>
                            <input 
                                type="number" 
                                className="form-control" 
                                placeholder="Leave blank to deduct entirely in next payroll"
                                value={formData.installment_amount} 
                                onChange={e => setFormData({...formData, installment_amount: e.target.value})}
                            />
                        </div>
                        <div className="form-group">
                            <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Deduction Start Date (Optional)</label>
                            <input 
                                type="date" 
                                className="form-control" 
                                value={formData.deduction_start_date} 
                                onChange={e => setFormData({...formData, deduction_start_date: e.target.value})}
                            />
                        </div>
                    </div>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Reason (Optional)</label>
                        <textarea 
                            className="form-control" 
                            rows="3" 
                            value={formData.reason}
                            onChange={e => setFormData({...formData, reason: e.target.value})}
                        ></textarea>
                    </div>
                    {!editId && (
                        <div className="form-group">
                            <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Attachment (Optional)</label>
                            <input 
                                type="file" 
                                className="form-control" 
                                onChange={e => setFormData({...formData, attachment: e.target.files[0]})}
                                accept=".pdf,.png,.jpg,.jpeg,.doc,.docx"
                            />
                        </div>
                    )}
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px', marginTop: '8px' }}>
                        <button type="button" className="btn btn-secondary" onClick={() => { setShowModal(false); setEditId(null); }} disabled={submitting}>Cancel</button>
                        <button type="submit" className="btn btn-primary" disabled={submitting}>
                            {submitting ? 'Submitting...' : (editId ? 'Save Changes' : 'Submit Request')}
                        </button>
                    </div>
                </form>
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

export default EmployeeAdvances;
