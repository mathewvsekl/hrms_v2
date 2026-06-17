import React, { useState, useEffect } from 'react';
import { X, Download, Calendar, Building, CheckSquare, Square } from 'lucide-react';
import api from '../services/api';
import DateInput from './ui/DateInput';

const DataExportModal = ({ isOpen, onClose }) => {
    const [companies, setCompanies] = useState([]);
    const [formData, setFormData] = useState({
        company_id: '',
        start_date: new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().split('T')[0],
        end_date: new Date().toISOString().split('T')[0],
        modules: ['employees', 'attendance', 'leave', 'payroll', 'appraisal']
    });
    const [loading, setLoading] = useState(false);
    const [fetchingCompanies, setFetchingCompanies] = useState(false);
    const [error, setError] = useState(null);

    const availableModules = [
        { id: 'employees', label: 'Employees' },
        { id: 'attendance', label: 'Attendance Logs' },
        { id: 'leave', label: 'Leave Requests' },
        { id: 'payroll', label: 'Payroll Records' },
        { id: 'appraisal', label: 'Performance Appraisals' }
    ];

    useEffect(() => {
        if (isOpen) {
            fetchCompanies();
        }
    }, [isOpen]);

    const fetchCompanies = async () => {
        setFetchingCompanies(true);
        try {
            const res = await api.get('/organization/companies');
            const data = res.data?.data || res.data || [];
            setCompanies(data);
            if (data.length > 0 && !formData.company_id) {
                setFormData(prev => ({ ...prev, company_id: data[0].id }));
            }
        } catch (err) {
            console.error("Failed to fetch companies", err);
            setError("Could not load companies list.");
        } finally {
            setFetchingCompanies(false);
        }
    };

    const toggleModule = (id) => {
        setFormData(prev => ({
            ...prev,
            modules: prev.modules.includes(id)
                ? prev.modules.filter(m => m !== id)
                : [...prev.modules, id]
        }));
    };

    const handleSelectAll = () => {
        setFormData(prev => ({ ...prev, modules: availableModules.map(m => m.id) }));
    };

    const handleClearAll = () => {
        setFormData(prev => ({ ...prev, modules: [] }));
    };

    const handleDownload = async (e) => {
        e.preventDefault();
        if (!formData.company_id) {
            setError("Please select a company.");
            return;
        }
        if (formData.modules.length === 0) {
            setError("Please select at least one module to export.");
            return;
        }

        setLoading(true);
        setError(null);

        try {
            // Build query string
            const params = new URLSearchParams();
            params.append('company_id', formData.company_id);
            params.append('start_date', formData.start_date);
            params.append('end_date', formData.end_date);
            formData.modules.forEach(m => params.append('modules[]', m));

            // Use window.location or a temporary link to trigger download since it's a file stream
            const downloadUrl = `${api.defaults.baseURL}/export/data?${params.toString()}`;
            
            // To handle credentials/auth, we can't just use window.location.href directly if we need headers
            // But since this is a GET request for a file, we can either:
            // 1. Fetch as blob (better for auth)
            // 2. Open in new tab (simple, but might miss auth headers if not handled by cookies)
            
            const response = await api.get(`/export/data?${params.toString()}`, {
                responseType: 'blob'
            });

            // Create a link and trigger download
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            
            // Extract filename and extension from headers if available
            // Extract filename from headers if available
            const contentDisposition = response.headers['content-disposition'];
            let filename = null;

            if (contentDisposition) {
                const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                if (filenameMatch != null && filenameMatch[1]) { 
                    filename = filenameMatch[1].replace(/['"]/g, '');
                }
            }

            // Fallback filename and extension deduction from Content-Type
            if (!filename) {
                const contentType = response.headers['content-type'] || '';
                let extension = '.zip'; // Default
                if (contentType.includes('csv')) extension = '.csv';
                else if (contentType.includes('gzip')) extension = '.tar.gz';
                
                filename = `HRMS_Export_${new Date().toISOString().split('T')[0]}${extension}`;
            }

            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
            
            onClose();
        } catch (err) {
            console.error("Export failed", err);
            
            // Try to extract the error message from the blob if it's a server error
            if (err.response && err.response.data instanceof Blob) {
                const reader = new FileReader();
                reader.onload = () => {
                    try {
                        const errorData = JSON.parse(reader.result);
                        setError(errorData.message || "Export failed. Please check your data and try again.");
                    } catch (e) {
                        setError("Export failed with a server error.");
                    }
                };
                reader.readAsText(err.response.data);
            } else {
                setError(err.response?.data?.message || err.message || "Export failed. Please check your data and try again.");
            }
        } finally {
            setLoading(false);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="modal-overlay" style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex',
            justifyContent: 'center', alignItems: 'center', zIndex: 1000
        }}>
            <div className="card" style={{ width: '500px', maxWidth: '95%', padding: '24px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
                    <h2 style={{ fontSize: '1.25rem', fontWeight: 'bold', display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <Download size={22} color="var(--primary-brand)" /> Data Export Manager
                    </h2>
                    <button onClick={onClose} className="btn-icon"><X size={20} /></button>
                </div>

                <p style={{ color: 'var(--text-secondary)', fontSize: '14px', marginBottom: '20px' }}>
                    Select the data segments and time period you wish to include in your export ZIP file.
                </p>

                {error && <div style={{ color: 'var(--accent-red)', marginBottom: '16px', fontSize: '14px', backgroundColor: '#fef2f2', padding: '10px', borderRadius: '6px' }}>{error}</div>}

                <form onSubmit={handleDownload}>
                    <div style={{ marginBottom: '20px' }}>
                        <label className="form-label">
                            <Building size={16} style={{ marginRight: '6px', verticalAlign: 'text-bottom' }} /> Target Company
                        </label>
                        <select 
                            className="form-input"
                            value={formData.company_id}
                            onChange={(e) => setFormData({...formData, company_id: e.target.value})}
                            required
                            disabled={fetchingCompanies}
                        >
                            <option value="">Select Company...</option>
                            {companies.map(c => (
                                <option key={c.id} value={c.id}>{c.name}</option>
                            ))}
                        </select>
                    </div>

                    <div style={{ marginBottom: '20px' }}>
                        <label className="form-label">Select Modules to Export</label>
                        <div style={{ 
                            display: 'grid', 
                            gridTemplateColumns: '1fr 1fr', 
                            gap: '10px',
                            backgroundColor: '#fafafa',
                            padding: '12px',
                            borderRadius: '8px',
                            border: '1px solid var(--border-gray)'
                        }}>
                            {availableModules.map(m => (
                                <label key={m.id} style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer', fontSize: '14px' }}>
                                    <input 
                                        type="checkbox" 
                                        checked={formData.modules.includes(m.id)}
                                        onChange={() => toggleModule(m.id)}
                                    />
                                    {m.label}
                                </label>
                            ))}
                        </div>
                        <div style={{ marginTop: '8px', display: 'flex', gap: '12px' }}>
                            <button type="button" className="btn-link" style={{ fontSize: '12px' }} onClick={handleSelectAll}>Select All</button>
                            <button type="button" className="btn-link" style={{ fontSize: '12px' }} onClick={handleClearAll}>Clear All</button>
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: '16px', marginBottom: '24px' }}>
                        <div style={{ flex: 1 }}>
                            <label className="form-label">
                                <Calendar size={16} style={{ marginRight: '6px', verticalAlign: 'text-bottom' }} /> Start Date
                            </label>
                            <DateInput 
                                value={formData.start_date}
                                onChange={(val) => setFormData({...formData, start_date: val})}
                                required
                            />
                        </div>
                        <div style={{ flex: 1 }}>
                            <label className="form-label">
                                <Calendar size={16} style={{ marginRight: '6px', verticalAlign: 'text-bottom' }} /> End Date
                            </label>
                            <DateInput 
                                value={formData.end_date}
                                onChange={(val) => setFormData({...formData, end_date: val})}
                                required
                            />
                        </div>
                    </div>

                    <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                        <button type="button" onClick={onClose} className="btn btn-secondary">Cancel</button>
                        <button type="submit" className="btn btn-primary" disabled={loading}>
                            {loading ? 'Preparing ZIP...' : <><Download size={18} style={{marginRight: '8px'}}/> Download ZIP</>}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default DataExportModal;
