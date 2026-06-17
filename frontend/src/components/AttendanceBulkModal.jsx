import React, { useState } from 'react';
import { X, Users, Save } from 'lucide-react';
import api from '../services/api';
import DateInput from './ui/DateInput';
import useAuthStore from '../store/authStore';

const AttendanceBulkModal = ({ isOpen, onClose, onSave, employees }) => {
    const user = useAuthStore(state => state.user);
    const userRole = user?.role;
    const isSuperAdmin = user?.role === 'Super Admin';
    const [formData, setFormData] = useState({
        employee_ids: [],
        attendance_date: new Date().toISOString().split('T')[0],
        status: 'present',
        check_in: '',
        check_out: '',
        remarks: ''
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    if (!isOpen) return null;

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (formData.employee_ids.length === 0) {
            setError('Please select at least one employee');
            return;
        }

        const today = new Date().toISOString().split('T')[0];
        if (!isSuperAdmin && formData.attendance_date > today) {
            setError('Attendance cannot be logged for future dates');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            await api.post('/attendance/bulk', formData);
            onSave();
            onClose();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to save bulk attendance');
        } finally {
            setLoading(false);
        }
    };

    const toggleEmployee = (id) => {
        setFormData(prev => ({
            ...prev,
            employee_ids: prev.employee_ids.includes(id)
                ? prev.employee_ids.filter(eid => eid !== id)
                : [...prev.employee_ids, id]
        }));
    };

    return (
        <div className="modal-overlay" style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex',
            justifyContent: 'center', alignItems: 'center', zIndex: 1000
        }}>
            <div className="card" style={{ width: '600px', maxWidth: '90%', padding: '24px', maxHeight: '90vh', overflowY: 'auto' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
                    <h2 style={{ fontSize: '1.25rem', fontWeight: 'bold', display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <Users size={22} color="var(--primary-brand)" /> Bulk Attendance Entry
                    </h2>
                    <button onClick={onClose} className="btn-icon"><X size={20} /></button>
                </div>

                {error && <div style={{ color: 'var(--accent-red)', marginBottom: '16px', fontSize: '14px' }}>{error}</div>}

                <form onSubmit={handleSubmit}>
                    <div style={{ marginBottom: '16px' }}>
                        <label className="form-label">Select Employees ({formData.employee_ids.length} selected)</label>
                        <div style={{ 
                            border: '1px solid var(--border-gray)', 
                            borderRadius: '8px', 
                            maxHeight: '150px', 
                            overflowY: 'auto',
                            padding: '8px'
                        }}>
                            {employees.map(emp => (
                                <label key={emp.id} style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '6px 0', cursor: 'pointer', borderBottom: '1px solid var(--bg-main)' }}>
                                    <input 
                                        type="checkbox" 
                                        checked={formData.employee_ids.includes(emp.id)}
                                        onChange={() => toggleEmployee(emp.id)}
                                    />
                                    <span style={{ fontSize: '14px' }}>{emp.first_name} {emp.last_name} ({emp.employee_code})</span>
                                </label>
                            ))}
                        </div>
                        <div style={{ marginTop: '8px', display: 'flex', gap: '8px' }}>
                            <button type="button" className="btn-link" onClick={() => setFormData({...formData, employee_ids: employees.map(e => e.id)})}>Select All</button>
                            <button type="button" className="btn-link" onClick={() => setFormData({...formData, employee_ids: []})}>Clear All</button>
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: '16px', marginBottom: '16px' }}>
                        <div style={{ flex: 1 }}>
                            <label className="form-label">Date</label>
                            <DateInput 
                                value={formData.attendance_date}
                                onChange={(val) => setFormData({...formData, attendance_date: val})}
                                max={isSuperAdmin ? undefined : new Date().toISOString().split('T')[0]}
                                required
                            />
                        </div>
                        <div style={{ flex: 1 }}>
                            <label className="form-label">Status</label>
                            <select 
                                className="form-input"
                                value={formData.status}
                                onChange={(e) => setFormData({...formData, status: e.target.value})}
                            >
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="half_day">Half Day</option>
                                <option value="late">Late</option>
                                <option value="on_leave">On Leave</option>
                                <option value="training">Training</option>
                                <option value="on_site">On Site</option>
                                <option value="work_from_home">Work From Home</option>
                            </select>
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: '16px', marginBottom: '16px' }}>
                        <div style={{ flex: 1 }}>
                            <label className="form-label">Check-in (Optional)</label>
                            <input 
                                type="time" 
                                className="form-input"
                                value={formData.check_in}
                                onChange={(e) => setFormData({...formData, check_in: e.target.value})}
                            />
                        </div>
                        <div style={{ flex: 1 }}>
                            <label className="form-label">Check-out (Optional)</label>
                            <input 
                                type="time" 
                                className="form-input"
                                value={formData.check_out}
                                onChange={(e) => setFormData({...formData, check_out: e.target.value})}
                            />
                        </div>
                    </div>

                    <div style={{ marginBottom: '24px' }}>
                        <label className="form-label">Remarks</label>
                        <textarea 
                            className="form-input"
                            rows="2"
                            value={formData.remarks}
                            onChange={(e) => setFormData({...formData, remarks: e.target.value})}
                            placeholder="Reason for bulk entry..."
                        />
                    </div>

                    <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                        <button type="button" onClick={onClose} className="btn btn-secondary">Cancel</button>
                        <button type="submit" className="btn btn-primary" disabled={loading}>
                            {loading ? 'Saving...' : <><Save size={18} style={{marginRight: '8px'}}/> Save Bulk Entry</>}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default AttendanceBulkModal;
