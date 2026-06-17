import React, { useState, useEffect } from 'react';
import { BarChart, UserCheck, Search, Filter, ChevronRight, Clock, CheckCircle, AlertCircle, Users, User as UserIcon, Settings, PlayCircle, Trash2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '../../services/api';
import useAuthStore from '../../store/useAuthStore';
import useLayoutStore from '../../store/useLayoutStore';
import DateInput from '../../components/ui/DateInput';

const AppraisalCycleList = () => {
    const [appraisals, setAppraisals] = useState([]);
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState({ pending: 0, completed: 0, total: 0 });
    const [activeTab, setActiveTab] = useState('self'); // 'self', 'team', 'all'
    const navigate = useNavigate();
    const { user } = useAuthStore();
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        setPageTitle("Performance Appraisals");
        setPageSubtitle("Track and manage performance review cycles");
        setBackPath('/dashboard');
        return () => resetPageHeader();
    }, []);

    useEffect(() => {
        fetchAppraisals();
    }, []);



    const fetchAppraisals = async () => {
        try {
            setLoading(true);
            const response = await api.get('/appraisals');
            const res = response.data;
            if (res.status === 'success') {
                // Defensive check: sometimes data might be double-nested or direct
                const data = res.data?.data || res.data;
                const appraisalsArray = Array.isArray(data) ? data : [];
                setAppraisals(appraisalsArray);

                // Calculate stats
                const pending = appraisalsArray.filter(a => a.status !== 'finalized').length;
                const completed = appraisalsArray.filter(a => a.status === 'finalized').length;
                setStats({ pending, completed, total: appraisalsArray.length });
            }
        } catch (error) {
            console.error('Error fetching appraisals:', error);
        } finally {
            setLoading(false);
        }
    };

    const getStatusColor = (status) => {
        switch (status) {
            case 'draft': return 'var(--color-rose-gold)';
            case 'l1_review': return 'var(--color-rose-gold)';
            case 'l2_review': return 'var(--color-rose-gold)';
            case 'l3_review': return 'var(--color-rose-gold)';
            case 'hr_calibration': return 'var(--color-rose-gold)';
            case 'finalized': return 'var(--color-charcoal)';
            default: return '#6b7280';
        }
    };

    const getStatusIcon = (status) => {
        switch (status) {
            case 'draft': return <Clock size={14} />;
            case 'l1_review': 
            case 'l2_review': 
            case 'l3_review': return <AlertCircle size={14} />;
            case 'hr_calibration': return <UserCheck size={14} />;
            case 'finalized': return <CheckCircle size={14} />;
            default: return null;
        }
    };

    const normalizedRole = user?.role || '';
    const isAdmin = normalizedRole && normalizedRole !== 'EMPLOYEE';
    
    // Derived collections
    const selfAppraisals = appraisals.filter(a => a.employee_id === user?.employee_id);
    const teamAppraisals = appraisals.filter(a => a.manager_id === user?.employee_id && a.employee_id !== user?.employee_id);
    
    const displayAppraisals = () => {
        if (activeTab === 'self') return selfAppraisals;
        if (activeTab === 'team') return teamAppraisals;
        return appraisals; 
    };

    return (
        <div className="appraisals-container">
            <div className="header-actions" style={{ display: 'flex', gap: '12px', justifyContent: 'space-between', marginBottom: '24px' }}>
                {isAdmin ? (
                    <>
                        <button className="btn btn-primary" onClick={() => navigate('/appraisals/cycles')}>
                            <PlayCircle size={18} /> Initiate Appraisal
                        </button>
                        <button className="btn btn-secondary" onClick={() => navigate('/appraisals/settings')}>
                            <Settings size={18} /> System Configurations
                        </button>
                    </>
                ) : (
                    <div></div>
                )}
            </div>

            {/* Stats Overview */}
            <div className="stats-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '20px', marginBottom: '24px' }}>
                <div className="card stat-card" style={{ padding: '20px' }}>
                    <div style={{ color: 'var(--color-text-muted)', fontSize: '0.9rem' }}>Total Reviews</div>
                    <div style={{ fontSize: '1.8rem', fontWeight: 'bold', marginTop: '8px' }}>{stats.total}</div>
                </div>
                <div className="card stat-card" style={{ padding: '20px' }}>
                    <div style={{ color: 'var(--color-text-muted)', fontSize: '0.9rem' }}>Pending Action</div>
                    <div style={{ fontSize: '1.8rem', fontWeight: 'bold', marginTop: '8px', color: 'var(--color-rose-gold)' }}>{stats.pending}</div>
                </div>
                <div className="card stat-card" style={{ padding: '20px' }}>
                    <div style={{ color: 'var(--color-text-muted)', fontSize: '0.9rem' }}>Completed</div>
                    <div style={{ fontSize: '1.8rem', fontWeight: 'bold', marginTop: '8px', color: 'var(--color-charcoal)' }}>{stats.completed}</div>
                </div>
            </div>

            {/* Appraisal List */}
            <div className="card">
                <div className="card-header" style={{ padding: '0 20px', borderBottom: 'none', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    
                    <div className="tabs" style={{ display: 'flex', gap: '20px' }}>
                        <button 
                            className={`tab-btn ${activeTab === 'self' ? 'active' : ''}`}
                            style={{ padding: '16px 0', borderBottom: activeTab === 'self' ? '2px solid var(--color-rose-gold)' : '2px solid transparent', background: 'none', borderTop: 'none', borderLeft: 'none', borderRight: 'none', color: activeTab === 'self' ? 'var(--color-rose-gold)' : 'var(--color-text-muted)', fontWeight: activeTab === 'self' ? 600 : 500, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px' }}
                            onClick={() => setActiveTab('self')}
                        >
                            <UserIcon size={16} /> My Appraisals ({selfAppraisals.length})
                        </button>
                        
                        {(teamAppraisals.length > 0 || isAdmin) && (
                            <button 
                                className={`tab-btn ${activeTab === 'team' ? 'active' : ''}`}
                                style={{ padding: '16px 0', borderBottom: activeTab === 'team' ? '2px solid var(--color-rose-gold)' : '2px solid transparent', background: 'none', borderTop: 'none', borderLeft: 'none', borderRight: 'none', color: activeTab === 'team' ? 'var(--color-rose-gold)' : 'var(--color-text-muted)', fontWeight: activeTab === 'team' ? 600 : 500, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px' }}
                                onClick={() => setActiveTab('team')}
                            >
                                <Users size={16} /> Team Appraisals ({teamAppraisals.length})
                            </button>
                        )}

                        {isAdmin && (
                            <button 
                                className={`tab-btn ${activeTab === 'all' ? 'active' : ''}`}
                                style={{ padding: '16px 0', borderBottom: activeTab === 'all' ? '2px solid var(--color-rose-gold)' : '2px solid transparent', background: 'none', borderTop: 'none', borderLeft: 'none', borderRight: 'none', color: activeTab === 'all' ? 'var(--color-rose-gold)' : 'var(--color-text-muted)', fontWeight: activeTab === 'all' ? 600 : 500, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '8px' }}
                                onClick={() => setActiveTab('all')}
                            >
                                <BarChart size={16} /> All Appraisals ({appraisals.length})
                            </button>
                        )}
                    </div>

                    <div className="filter-group" style={{ display: 'flex', gap: '12px' }}>
                        <div className="search-box" style={{ position: 'relative' }}>
                            <Search size={16} style={{ position: 'absolute', left: '10px', top: '50%', transform: 'translateY(-50%)', color: 'var(--color-text-muted)' }} />
                            <input type="text" placeholder="Search employee..." style={{ padding: '8px 12px 8px 34px', border: '1px solid var(--color-border)', borderRadius: '6px' }} />
                        </div>
                        <button className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <Filter size={16} /> Filters
                        </button>
                    </div>
                </div>

                <div className="card-body" style={{ padding: 0 }}>
                    {loading ? (
                        <div style={{ padding: '40px', textAlign: 'center' }}>Loading appraisals...</div>
                    ) : (
                        <div className="table-container">
                            <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Review Type</th>
                                    <th>Status</th>
                                    <th>Score</th>
                                    <th style={{ textAlign: 'center' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {displayAppraisals().length === 0 ? (
                                    <tr>
                                        <td colSpan="5" style={{ textAlign: 'center', padding: '20px' }}>No active appraisals found in this category</td>
                                    </tr>
                                ) : (
                                    displayAppraisals().map((item) => (
                                        <tr key={item.id} style={{ cursor: 'pointer' }} onClick={() => navigate(`/appraisals/${item.id}`)}>
                                            <td>
                                                <div style={{ fontWeight: 500 }}>Emp ID: {item.employee_id}</div>
                                                <div style={{ color: 'var(--color-text-muted)', fontSize: '0.85rem' }}>Cycle ID: {item.cycle_id}</div>
                                            </td>
                                            <td>Annual Appraisal 2025</td>
                                            <td>
                                                <span style={{
                                                    display: 'inline-flex',
                                                    alignItems: 'center',
                                                    gap: '6px',
                                                    padding: '4px 10px',
                                                    borderRadius: '20px',
                                                    background: getStatusColor(item.status) + '15',
                                                    color: getStatusColor(item.status),
                                                    fontSize: '0.8rem',
                                                    fontWeight: 500,
                                                    textTransform: 'capitalize'
                                                }}>
                                                    {getStatusIcon(item.status)}
                                                    {item.status.replace('_', ' ')}
                                                </span>
                                            </td>
                                            <td>
                                                {item.final_rating ? (
                                                    <span style={{ fontWeight: 'bold', color: 'var(--color-rose-gold)' }}>{item.final_rating} / 5.0</span>
                                                ) : (
                                                    <span style={{ color: 'var(--color-text-muted)' }}>--</span>
                                                )}
                                            </td>
                                            <td style={{ textAlign: 'center' }}>
                                                <div style={{ display: 'flex', gap: '8px', justifyContent: 'center', alignItems: 'center' }}>
                                                    {isAdmin && item.status === 'draft' && (
                                                        <button 
                                                            className="btn btn-secondary" 
                                                            style={{ padding: '4px 8px', color: '#dc2626', borderColor: '#fca5a5' }}
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                if (window.confirm('Are you sure you want to delete this appraisal?')) {
                                                                    api.delete(`/appraisals/${item.id}`).then(() => {
                                                                        fetchAppraisals();
                                                                    }).catch(err => alert(err.response?.data?.message || 'Failed to delete'));
                                                                }
                                                            }}
                                                            title="Delete Appraisal"
                                                        >
                                                            <Trash2 size={16} />
                                                        </button>
                                                    )}
                                                    <button className="btn btn-secondary" style={{ padding: '4px' }}>
                                                        <ChevronRight size={20} />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                        </div>
                    )}
                </div>
            </div>

        </div>
    );
};

export default AppraisalCycleList;

