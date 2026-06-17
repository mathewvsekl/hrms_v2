import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Timer, Calendar, Award, CheckCircle, ChevronRight } from 'lucide-react';
import api from '../services/api';
import useLayoutStore from '../store/useLayoutStore';
import { formatDate } from '../utils/dateUtils';

const ActionRequired = () => {
    const [pending, setPending] = useState([]);
    const [loading, setLoading] = useState(true);
    const navigate = useNavigate();
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        setPageTitle("Action Required");
        setPageSubtitle("All pending authorization requests");
        setBackPath('/dashboard');
        return () => resetPageHeader();
    }, []);

    useEffect(() => {
        const fetchPending = async () => {
            try {
                const res = await api.get('/dashboard/summary');
                const data = res.data?.data || {};
                setPending(data.pending_approvals || []);
            } catch (err) {
                console.error("Failed to fetch pending approvals", err);
            } finally {
                setLoading(false);
            }
        };
        fetchPending();
    }, []);

    return (
        <div className="card" style={{ padding: '24px' }}>
            {loading ? (
                <div style={{ textAlign: 'center', padding: '40px', color: 'var(--color-text-muted)' }}>
                    Loading...
                </div>
            ) : pending.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '40px', color: 'var(--color-text-muted)' }}>
                    <CheckCircle size={48} style={{ opacity: 0.5, marginBottom: '16px' }} />
                    <p>No authorization requests pending.</p>
                </div>
            ) : (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {pending.map((req, idx) => (
                        <div 
                            key={`${req.type}-${req.id}-${idx}`} 
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                padding: '16px',
                                border: '1px solid var(--border-gray)',
                                borderRadius: 'var(--radius-md)',
                                cursor: 'pointer',
                                transition: 'all 0.2s',
                                backgroundColor: 'var(--bg-white)'
                            }}
                            onMouseOver={(e) => e.currentTarget.style.borderColor = 'var(--color-rose-gold)'}
                            onMouseOut={(e) => e.currentTarget.style.borderColor = 'var(--border-gray)'}
                            onClick={() => navigate(req.link)}
                        >
                            <div style={{
                                width: '40px',
                                height: '40px',
                                borderRadius: '10px',
                                background: 'var(--color-ivory)',
                                color: 'var(--color-text-muted)',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                marginRight: '16px'
                            }}>
                                {req.type === 'leave' && <Calendar size={20} />}
                                {req.type === 'appraisal' && <Award size={20} />}
                                {req.type === 'attendance' && <CheckCircle size={20} />}
                            </div>
                            <div style={{ flex: 1 }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px' }}>
                                    <span style={{ fontWeight: 700, color: 'var(--color-charcoal)' }}>{req.title}</span>
                                    <span style={{ fontSize: '12px', color: 'var(--color-rose-gold)', fontWeight: 600 }}>
                                        {formatDate(req.date)}
                                        {req.end_date && ` - ${formatDate(req.end_date)}`}
                                    </span>
                                </div>
                                <div style={{ fontSize: '13px', color: 'var(--color-text-muted)' }}>{req.subtitle}</div>
                            </div>
                            <ChevronRight size={18} style={{ color: 'var(--color-border)', marginLeft: '16px' }} />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
};

export default ActionRequired;
