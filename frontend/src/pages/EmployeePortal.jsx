import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    User, Calendar, Clock, CreditCard, 
    Award, Bell, ChevronRight, RefreshCw,
    CheckCircle, Timer, FileText, ExternalLink, DollarSign
} from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import { formatDate, formatTime } from '../utils/dateUtils';
import Modal from '../components/ui/Modal';
import useNotificationStore from '../store/useNotificationStore';

const EmployeePortal = () => {
    const { user } = useAuthStore();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(true);
    const [employeeData, setEmployeeData] = useState(null);
    const [leaveBalances, setLeaveBalances] = useState([]);
    const [leaveRequests, setLeaveRequests] = useState([]);
    const [attendanceStats, setAttendanceStats] = useState([]);
    const [attendanceLogs, setAttendanceLogs] = useState([]);
    const [salaryAdvances, setSalaryAdvances] = useState([]);
    const [showAdvanceModal, setShowAdvanceModal] = useState(false);
    const [submittingAdvance, setSubmittingAdvance] = useState(false);
    const [advanceForm, setAdvanceForm] = useState({ amount: '', reason: '', date_requested: new Date().toISOString().split('T')[0] });
    const { showAlert } = useNotificationStore();

    useEffect(() => {
        if (user?.employee_id) {
            loadPortalData();
        }
    }, [user]);

    const loadPortalData = async () => {
        try {
            setLoading(true);
            const empId = user.employee_id;

            const [empRes, balRes, reqRes, attRes, advanceRes] = await Promise.all([
                api.get(`/employees/${empId}`),
                api.get(`/leave/balances?employee_id=${empId}`),
                api.get(`/leave?employee_id=${empId}`),
                api.get(`/attendance/summary?employee_id=${empId}`),
                api.get(`/salary-advances?employee_id=${empId}`)
            ]);

            setEmployeeData(empRes.data?.data || null);
            setLeaveBalances(balRes.data?.data || []);
            setLeaveRequests((reqRes.data?.data || []).slice(0, 5));
            setAttendanceStats(attRes.data?.data?.stats || []);
            setAttendanceLogs((attRes.data?.data?.logs || []).reverse().slice(0, 5));
            setSalaryAdvances((advanceRes.data?.data || []).slice(0, 5));

        } catch (error) {
            console.error("Failed to load portal data", error);
        } finally {
            setLoading(false);
        }
    };

    const handleRequestAdvance = async (e) => {
        e.preventDefault();
        if (!advanceForm.amount) return showAlert('Required', 'Please enter amount', 'warning');
        setSubmittingAdvance(true);
        try {
            await api.post('/salary-advances', {
                employee_id: user.employee_id,
                amount: advanceForm.amount,
                reason: advanceForm.reason,
                date_requested: advanceForm.date_requested
            });
            showAlert('Success', 'Salary advance requested successfully', 'success');
            setShowAdvanceModal(false);
            setAdvanceForm({ amount: '', reason: '', date_requested: new Date().toISOString().split('T')[0] });
            loadPortalData();
        } catch (error) {
            showAlert('Error', 'Failed to submit request', 'error');
        } finally {
            setSubmittingAdvance(false);
        }
    };

    const getStatusColor = (statusKey) => {
        const sk = String(statusKey);
        
        switch (sk.toLowerCase()) {
            case 'approved': case 'present': return '#10b981';
            case 'pending': case 'late': case 'on_leave': return '#f59e0b';
            case 'rejected': case 'absent': return '#ef4444';
            default: return '#6b7280';
        }
    };

    if (loading && !employeeData) {
        return (
            <div className="portal-loading" style={{ 
                display: 'flex', justifyContent: 'center', alignItems: 'center', height: '80vh' 
            }}>
                <RefreshCw className="animate-spin" size={32} color="var(--color-rose-gold)" />
            </div>
        );
    }

    return (
        <div className="portal-container" style={{ padding: '1.5rem 0' }}>
            <style>{`
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .portal-card {
                    animation: fadeIn 0.4s ease forwards;
                    background: var(--color-white);
                    border-radius: var(--radius-lg);
                    padding: 1.5rem;
                    box-shadow: var(--shadow-sm);
                    border: 1px solid var(--color-border);
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .portal-card:hover {
                    box-shadow: var(--shadow-md);
                }
                .portal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 2rem;
                }
                .welcome-text h1 {
                    font-family: var(--font-heading);
                    font-size: 28px;
                    margin: 0;
                    color: var(--color-charcoal);
                }
                .welcome-text p {
                    color: var(--color-text-muted);
                    margin: 4px 0 0 0;
                    font-size: 14px;
                    font-weight: 500;
                }
                .grid-main {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 1.5rem;
                }
                .stats-strip {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 1.25rem;
                    margin-bottom: 1.5rem;
                }
                .mini-stat {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .stat-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 12px;
                    background: var(--color-ivory);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: var(--color-charcoal);
                }
                .stat-info h4 {
                    font-size: 11px;
                    color: var(--color-text-muted);
                    text-transform: uppercase;
                    margin: 0;
                    letter-spacing: 0.05em;
                }
                .stat-info p {
                    font-size: 18px;
                    font-weight: 700;
                    margin: 2px 0 0 0;
                    color: var(--color-charcoal);
                }
                .section-title {
                    font-family: var(--font-heading);
                    font-size: 16px;
                    font-weight: 700;
                    margin-bottom: 1.25rem;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    color: var(--color-charcoal);
                }
                .request-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 12px;
                    border-radius: var(--radius-md);
                    border: 1px solid var(--color-border);
                    margin-bottom: 8px;
                }
                .req-left h5 { margin: 0; font-size: 14px; font-weight: 600; }
                .req-left span { font-size: 12px; color: var(--color-text-muted); }
                .status-badge {
                    font-size: 10px;
                    font-weight: 700;
                    padding: 4px 8px;
                    border-radius: 6px;
                    text-transform: uppercase;
                }
                .attendance-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .attendance-table th {
                    text-align: left;
                    font-size: 11px;
                    color: var(--color-text-muted);
                    padding: 8px;
                    border-bottom: 1px solid var(--color-border);
                }
                .attendance-table td {
                    padding: 12px 8px;
                    font-size: 13px;
                    border-bottom: 1px solid var(--color-ivory);
                }
                .balance-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 0.75rem;
                }
                .balance-card {
                    padding: 12px;
                    background: var(--color-ivory);
                    border-radius: var(--radius-md);
                    text-align: center;
                }
                .balance-card h6 { margin: 0; font-size: 10px; color: var(--color-text-muted); text-transform: uppercase; }
                .balance-card p { margin: 4px 0 0 0; font-size: 20px; font-weight: 800; color: var(--color-charcoal); }

                @media (max-width: 1024px) {
                    .grid-main { grid-template-columns: 1fr; }
                }
            `}</style>

            <div className="portal-header">
                <div className="welcome-text">
                    <h1>Welcome, {employeeData?.first_name || 'Employee'}!</h1>
                    <p>Here's your professional overview for today.</p>
                </div>
                <button className="refresh-btn" onClick={loadPortalData} style={{
                    padding: '8px 16px',
                    border: '1px solid var(--color-border)',
                    borderRadius: 'var(--radius-md)',
                    background: 'var(--color-white)',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '8px',
                    fontSize: '13px',
                    fontWeight: '600',
                    cursor: 'pointer'
                }}>
                    <RefreshCw size={14} className={loading ? 'animate-spin' : ''} />
                    <span>Refresh</span>
                </button>
            </div>

            <div className="grid-main">
                <div className="column-left">
                    {/* Top Stats */}
                    <div className="portal-card stats-strip">
                        <div className="mini-stat">
                            <div className="stat-icon" style={{ background: 'rgba(16, 185, 129, 0.1)', color: '#10b981' }}>
                                <Clock size={20} />
                            </div>
                            <div className="stat-info">
                                <h4>Today's Status</h4>
                                <p>Clocked In</p>
                            </div>
                        </div>
                        <div className="mini-stat">
                            <div className="stat-icon" style={{ background: 'rgba(59, 130, 246, 0.1)', color: '#3b82f6' }}>
                                <Calendar size={20} />
                            </div>
                            <div className="stat-info">
                                <h4>Leave Balance</h4>
                                <p>{leaveBalances.reduce((acc, b) => acc + (parseFloat(b.allocated_days) - parseFloat(b.used_days)), 0)} Days</p>
                            </div>
                        </div>
                        <div className="mini-stat">
                            <div className="stat-icon" style={{ background: 'rgba(139, 92, 246, 0.1)', color: '#8b5cf6' }}>
                                <Award size={20} />
                            </div>
                            <div className="stat-info">
                                <h4>Next Review</h4>
                                <p>Pending</p>
                            </div>
                        </div>
                    </div>

                    {/* Attendance History */}
                    <div className="portal-card" style={{ marginBottom: '1.5rem' }}>
                        <div className="section-title">
                            <Timer size={18} color="var(--color-rose-gold)" />
                            Recent Attendance
                        </div>
                        <table className="attendance-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                </tr>
                            </thead>
                            <tbody>
                                {attendanceLogs.length > 0 ? attendanceLogs.map((log, idx) => (
                                    <tr key={idx}>
                                        <td style={{ fontWeight: '600' }}>{formatDate(log.attendance_date)}</td>
                                        <td>
                                            <span style={{ 
                                                color: getStatusColor(log.status), 
                                                fontWeight: '700',
                                                textTransform: 'capitalize'
                                            }}>
                                                {log.status?.replace('_', ' ')}
                                            </span>
                                        </td>
                                        <td>{log.check_in_utc ? formatTime(log.check_in_utc) : '-'}</td>
                                        <td>{log.check_out_utc ? formatTime(log.check_out_utc) : '-'}</td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="4" style={{ textAlign: 'center', padding: '2rem', color: 'var(--color-text-muted)' }}>
                                            No recent attendance logs found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Recent Leave Requests */}
                    <div className="portal-card">
                        <div className="section-title">
                            <Calendar size={18} color="#3b82f6" />
                            Leave Applications
                        </div>
                        {leaveRequests.length > 0 ? leaveRequests.map((req, idx) => (
                            <div key={idx} className="request-item">
                                <div className="req-left">
                                    <h5>{req.leave_type_name}</h5>
                                    <span>{formatDate(req.start_date)} to {formatDate(req.end_date)} ({req.total_days} days)</span>
                                </div>
                                <span className="status-badge" style={{ 
                                    background: getStatusColor(req.status) + '15', 
                                    color: getStatusColor(req.status) 
                                }}>
                                    {req.status}
                                </span>
                            </div>
                        )) : (
                            <div style={{ textAlign: 'center', padding: '1rem', color: 'var(--color-text-muted)' }}>
                                No recent leave applications.
                            </div>
                        )}
                        <button 
                            onClick={() => navigate('/leave')}
                            style={{
                                width: '100%',
                                padding: '10px',
                                marginTop: '1rem',
                                border: '1px dashed var(--color-border)',
                                borderRadius: 'var(--radius-md)',
                                background: 'transparent',
                                color: 'var(--color-rose-gold)',
                                fontWeight: '700',
                                fontSize: '13px',
                                cursor: 'pointer'
                            }}
                        >
                            Apply for Leave
                        </button>
                    </div>

                    {/* Salary Advances */}
                    <div className="portal-card">
                        <div className="section-title">
                            <DollarSign size={18} color="#10b981" />
                            Salary Advances
                        </div>
                        {salaryAdvances.length > 0 ? salaryAdvances.map((adv, idx) => (
                            <div key={idx} className="request-item">
                                <div className="req-left">
                                    <h5>{adv.currency_code} {parseFloat(adv.amount).toLocaleString()}</h5>
                                    <span>Requested: {formatDate(adv.date_requested)}</span>
                                </div>
                                <span className="status-badge" style={{ 
                                    background: getStatusColor(adv.status) + '15', 
                                    color: getStatusColor(adv.status) 
                                }}>
                                    {adv.status}
                                </span>
                            </div>
                        )) : (
                            <div style={{ textAlign: 'center', padding: '1rem', color: 'var(--color-text-muted)' }}>
                                No recent salary advance requests.
                            </div>
                        )}
                        <button 
                            onClick={() => setShowAdvanceModal(true)}
                            style={{
                                width: '100%',
                                padding: '10px',
                                marginTop: '1rem',
                                border: '1px dashed var(--color-border)',
                                borderRadius: 'var(--radius-md)',
                                background: 'transparent',
                                color: 'var(--color-rose-gold)',
                                fontWeight: '700',
                                fontSize: '13px',
                                cursor: 'pointer'
                            }}
                        >
                            Request Salary Advance
                        </button>
                    </div>
                </div>

                <div className="column-right">
                    {/* Personal Profile Summary */}
                    <div className="portal-card" style={{ marginBottom: '1.5rem', textAlign: 'center' }}>
                        <div style={{
                            width: '80px',
                            height: '80px',
                            borderRadius: '24px',
                            background: 'var(--color-charcoal)',
                            margin: '0 auto 1rem auto',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            color: 'var(--color-white)',
                            fontSize: '32px',
                            fontWeight: '700',
                            boxShadow: 'var(--shadow-md)'
                        }}>
                            {employeeData?.first_name?.[0]}{employeeData?.last_name?.[0]}
                        </div>
                        <h2 style={{ margin: '0', fontSize: '20px', fontFamily: 'var(--font-heading)' }}>
                            {employeeData?.first_name} {employeeData?.last_name}
                        </h2>
                        <p style={{ margin: '4px 0 0 0', fontSize: '13px', color: 'var(--color-text-muted)', fontWeight: '600' }}>
                            {employeeData?.designation_title}
                        </p>
                        <hr style={{ margin: '1.5rem 0', opacity: 0.1 }} />
                        <div style={{ textAlign: 'left', display: 'flex', flexDirection: 'column', gap: '12px' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '13px' }}>
                                <User size={14} color="var(--color-text-muted)" />
                                <span>{employeeData?.employee_code}</span>
                            </div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '13px' }}>
                                <Bell size={14} color="var(--color-text-muted)" />
                                <span>{employeeData?.email}</span>
                            </div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '10px', fontSize: '13px' }}>
                                <Award size={14} color="var(--color-text-muted)" />
                                <span>{employeeData?.department_name}</span>
                            </div>
                        </div>
                        <button 
                            onClick={() => navigate('/employee-profile')}
                            style={{
                                width: '100%',
                                padding: '12px',
                                marginTop: '1.5rem',
                                background: 'var(--color-charcoal)',
                                color: 'white',
                                border: 'none',
                                borderRadius: 'var(--radius-md)',
                                fontWeight: '700',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                gap: '8px',
                                cursor: 'pointer'
                            }}
                        >
                            View Full Profile <ExternalLink size={14} />
                        </button>
                    </div>

                    {/* Quick Access */}
                    <div className="portal-card">
                        <div className="section-title">
                            <CreditCard size={18} color="#8b5cf6" />
                            Financial Brief
                        </div>
                        <div className="balance-grid">
                            <div className="balance-card">
                                <h6>Annual Leave</h6>
                                <p>{leaveBalances.find(b => b.leave_type_name?.toLowerCase().includes('annual'))?.allocated_days || '0'}</p>
                            </div>
                            <div className="balance-card">
                                <h6>Sick Leave</h6>
                                <p>{leaveBalances.find(b => b.leave_type_name?.toLowerCase().includes('sick'))?.allocated_days || '0'}</p>
                            </div>
                        </div>
                        <div style={{ marginTop: '1.5rem' }}>
                            <div style={{ 
                                display: 'flex', 
                                alignItems: 'center', 
                                justifyContent: 'space-between',
                                padding: '12px',
                                borderRadius: 'var(--radius-md)',
                                background: 'var(--color-ivory)',
                                fontSize: '13px',
                                fontWeight: '600'
                            }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    <FileText size={16} color="var(--color-text-muted)" />
                                    Latest Payslip
                                </div>
                                <ChevronRight size={16} color="var(--color-text-muted)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Salary Advance Modal */}
            <Modal isOpen={showAdvanceModal} onClose={() => setShowAdvanceModal(false)} title="Request Salary Advance">
                <form onSubmit={handleRequestAdvance} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Amount</label>
                        <input 
                            type="number" 
                            className="form-control" 
                            required 
                            value={advanceForm.amount} 
                            onChange={e => setAdvanceForm({...advanceForm, amount: e.target.value})}
                        />
                    </div>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Reason (Optional)</label>
                        <textarea 
                            className="form-control" 
                            rows="3" 
                            value={advanceForm.reason}
                            onChange={e => setAdvanceForm({...advanceForm, reason: e.target.value})}
                        ></textarea>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px', marginTop: '8px' }}>
                        <button type="button" className="btn btn-secondary" onClick={() => setShowAdvanceModal(false)} disabled={submittingAdvance}>Cancel</button>
                        <button type="submit" className="btn btn-primary" disabled={submittingAdvance}>
                            {submittingAdvance ? 'Submitting...' : 'Submit Request'}
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
};

export default EmployeePortal;
