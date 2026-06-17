import React, { useState, useEffect } from 'react';
import { X, History, User, Calendar, Clock } from 'lucide-react';
import api from '../services/api';
import { formatDateTime } from '../utils/dateUtils';

const AttendanceHistoryModal = ({ isOpen, onClose, logId }) => {
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen && logId) {
            fetchHistory();
        }
    }, [isOpen, logId]);

    const fetchHistory = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/attendance/history?id=${logId}`);
            const data = res.data?.data || res.data;
            setHistory(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Failed to fetch attendance history', error);
        } finally {
            setLoading(false);
        }
    };

    if (!isOpen) return null;

    const renderChanges = (oldVal, newVal) => {
        const oldObj = oldVal ? (typeof oldVal === 'string' ? JSON.parse(oldVal) : oldVal) : null;
        const newObj = newVal ? (typeof newVal === 'string' ? JSON.parse(newVal) : newVal) : null;

        return (
            <div style={{ fontSize: '13px', marginTop: '8px' }}>
                {newObj && Object.keys(newObj).map(key => {
                    if (key === 'id' || key === 'employee_id' || key === 'attendance_date') return null;
                    const oldV = oldObj ? oldObj[key] : '';
                    const newV = newObj[key];
                    if (oldV === newV) return null;
                    
                    return (
                        <div key={key} style={{ marginBottom: '4px' }}>
                            <span style={{ fontWeight: '600', textTransform: 'capitalize' }}>{key.replace(/_/g, ' ')}:</span> 
                            {oldObj && <span style={{ color: 'var(--accent-red)', textDecoration: 'line-through', margin: '0 4px' }}>{oldV || '(empty)'}</span>}
                            <span style={{ color: 'var(--accent-green)' }}>{newV || '(empty)'}</span>
                        </div>
                    );
                })}

            </div>
        );
    };

    return (
        <div className="modal-overlay" style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex',
            justifyContent: 'center', alignItems: 'center', zIndex: 1000
        }}>
            <div className="card" style={{ width: '600px', maxWidth: '90%', maxHeight: '80vh', display: 'flex', flexDirection: 'column', padding: '0', overflow: 'hidden' }}>
                <div style={{ padding: '20px', borderBottom: '1px solid var(--border-gray)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                        <History size={20} color="var(--primary-brand)" />
                        <h2 style={{ fontSize: '1.25rem', fontWeight: 'bold' }}>Audit Trail</h2>
                    </div>
                    <button onClick={onClose} className="btn-icon"><X size={20} /></button>
                </div>

                <div style={{ flex: 1, overflowY: 'auto', padding: '20px' }}>
                    {loading ? (
                        <div style={{ textAlign: 'center', padding: '40px' }}>Loading history...</div>
                    ) : history.length === 0 ? (
                        <div style={{ textAlign: 'center', padding: '40px', color: 'var(--text-secondary)' }}>No audit logs found for this entry.</div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            {history.map((item, index) => (
                                <div key={item.id} style={{ position: 'relative', paddingLeft: '24px', borderLeft: '2px solid var(--border-gray)' }}>
                                    <div style={{ 
                                        position: 'absolute', left: '-9px', top: '0', 
                                        width: '16px', height: '16px', borderRadius: '50%', 
                                        backgroundColor: index === 0 ? 'var(--primary-brand)' : 'var(--border-gray)' 
                                    }} />
                                    
                                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                        <div>
                                            <div style={{ fontWeight: '600', fontSize: '15px' }}>{item.change_reason}</div>
                                            <div style={{ fontSize: '12px', color: 'var(--text-secondary)', display: 'flex', gap: '12px', marginTop: '4px' }}>
                                                <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}><User size={12}/>{item.changed_by_name}</span>
                                                <span style={{ display: 'flex', alignItems: 'center', gap: '4px' }}><Clock size={12}/>{formatDateTime(item.created_at_utc)}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {renderChanges(item.old_values, item.new_values)}
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div style={{ padding: '20px', borderTop: '1px solid var(--border-gray)', textAlign: 'right' }}>
                    <button onClick={onClose} className="btn btn-secondary">Close</button>
                </div>
            </div>
        </div>
    );
};

export default AttendanceHistoryModal;
