import React, { useState, useEffect } from 'react';
import { X, Save, Clock } from 'lucide-react';
import api from '../services/api';
import DateInput from './ui/DateInput';
import { formatTime } from '../utils/dateUtils';

const AttendanceEntryModal = ({ isOpen, onClose, onSave, employees, initialData = null }) => {
    const [formData, setFormData] = useState({
        employee_id: '',
        attendance_date: new Date().toISOString().split('T')[0],
        status: 'present',
        check_in: '',
        check_out: '',
        remarks: ''
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (initialData) {
            setFormData({
                ...formData,
                ...initialData,
                check_in: initialData.check_in_utc ? formatTime(initialData.check_in_utc) : '',
                check_out: initialData.check_out_utc ? formatTime(initialData.check_out_utc) : ''
            });
        }
    }, [initialData]);

    if (!isOpen) return null;

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);
        setError(null);

        try {
            // Prepare data: convert time to full UTC timestamps if needed
            const payload = {
                ...formData,
                check_in: formData.check_in ? `${formData.attendance_date} ${formData.check_in}:00` : null,
                check_out: formData.check_out ? `${formData.attendance_date} ${formData.check_out}:00` : null
            };

            const res = await api.post('/attendance', payload);
            onSave(res.data);
            onClose();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to save attendance');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="modal-overlay" style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex',
            justifyContent: 'center', alignItems: 'center', zIndex: 1000
        }}>
            <div className="card" style={{ width: '500px', maxWidth: '90%', padding: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
                    <h2 style={{ fontSize: '1.25rem', fontWeight: 'bold' }}>
                        {initialData ? 'Edit Attendance' : 'Log Manual Entry'}
                    </h2>
                    <button onClick={onClose} className="btn-icon"><X size={20} /></button>
                </div>

                {error && <div style={{ color: 'var(--accent-red)', marginBottom: '16px', fontSize: '14px' }}>{error}</div>}

                <form onSubmit={handleSubmit}>
                    <div style={{ marginBottom: '16px' }}>
                        <label className="form-label">Employee</label>
                        <select 
                            className="form-input"
                            value={formData.employee_id}
                            onChange={(e) => setFormData({...formData, employee_id: e.target.value})}
                            required
                            disabled={!!initialData}
                        >
                            <option value="">Select Employee</option>
                            {employees.map(emp => (
                                <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name} ({emp.employee_code})</option>
                            ))}
                        </select>
                    </div>

                    <div style={{ display: 'flex', gap: '16px', marginBottom: '16px' }}>
                        <div style={{ flex: 1 }}>
                            <label className="form-label">Date</label>
                            <DateInput 
                                value={formData.attendance_date}
                                onChange={(val) => setFormData({...formData, attendance_date: val})}
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
                            rows="3"
                            value={formData.remarks}
                            onChange={(e) => setFormData({...formData, remarks: e.target.value})}
                            placeholder="Add notes or reason for manual entry..."
                        />
                    </div>

                    <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                        <button type="button" onClick={onClose} className="btn btn-secondary">Cancel</button>
                        <button type="submit" className="btn btn-primary" disabled={loading}>
                            {loading ? 'Saving...' : <><Save size={18} style={{marginRight: '8px'}}/> Save Entry</>}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default AttendanceEntryModal;
