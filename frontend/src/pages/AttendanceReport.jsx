import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
    ChevronLeft, 
    Download, 
    Calendar, 
    Building, 
    Filter, 
    Search, 
    AlertCircle,
    User,
    ArrowUpDown,
    CheckCircle2,
    XCircle,
    Clock,
    FileSpreadsheet,
    MapPin
} from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useLayoutStore from '../store/useLayoutStore';

const AttendanceReport = () => {
    const navigate = useNavigate();
    const user = useAuthStore(state => state.user);
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();
    
    useEffect(() => {
        setPageTitle("Attendance Analysis");
        setPageSubtitle("Comprehensive review of organizational attendance patterns");
        setBackPath('/reports');
        return () => resetPageHeader();
    }, []);
    
    // Filters State
    const [selectedMonth, setSelectedMonth] = useState(new Date().getMonth() + 1);
    const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
    const [selectedCompany, setSelectedCompany] = useState('global');
    
    // Data State
    const [reportData, setReportData] = useState(null);
    const [companies, setCompanies] = useState([]);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [useCustomRange, setUseCustomRange] = useState(false);
    const [startDate, setStartDate] = useState(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
    const [endDate, setEndDate] = useState(new Date().toISOString().split('T')[0]);

    // Companies loaded once
    useEffect(() => {
        fetchCompanies();
    }, []);

    const fetchCompanies = async () => {
        try {
            const res = await api.get('/organization/companies');
            const data = res.data?.data || res.data || [];
            setCompanies(data);
            
            // Prioritize user's primary company for initial view
            const userCompId = user?.company_id || user?.primary_company_id;
            if (userCompId && data.some(c => c.id == userCompId)) {
                setSelectedCompany(userCompId);
            }
        } catch (err) {
            console.error("Failed to fetch companies", err);
        }
    };

    const fetchReport = async () => {
        setLoading(true);
        setError(null);
        setReportData(null); // Clear stale data
        try {
            const params = {
                company_id: selectedCompany
            };

            if (useCustomRange) {
                params.start_date = startDate;
                params.end_date = endDate;
            } else {
                params.month = selectedMonth;
                params.year = selectedYear;
            }

            const res = await api.get('/attendance/monthly-report', { params });
            const data = res.data?.data || res.data;
            setReportData(data);
        } catch (err) {
            console.error("Failed to fetch report", err);
            setError(err.response?.data?.message || "Failed to load report data.");
        } finally {
            setLoading(false);
        }
    };

    const handleExport = async () => {
        setExporting(true);
        try {
            const params = new URLSearchParams({
                company_id: selectedCompany
            });

            if (useCustomRange) {
                params.append('start_date', startDate);
                params.append('end_date', endDate);
            } else {
                params.append('month', selectedMonth);
                params.append('year', selectedYear);
            }
            
            const response = await api.get(`/attendance/export-monthly?${params.toString()}`, {
                responseType: 'blob'
            });

            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Attendance_Report_${selectedYear}_${String(selectedMonth).padStart(2, '0')}.csv`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error("Export failed", err);
            alert("Failed to export report.");
        } finally {
            setExporting(false);
        }
    };

    // Derived Data
    const daysMeta = reportData?.dates || [];
    const totalDays = reportData?.total_days || 0;
    const filteredGrid = useMemo(() => {
        if (!reportData?.grid) return [];
        return reportData.grid.filter(emp => 
            emp.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            emp.code.toLowerCase().includes(searchTerm.toLowerCase())
        );
    }, [reportData, searchTerm]);

    const statusConfig = reportData?.config || {};

    const getStatusStyle = (status) => {
        if (!status) return { backgroundColor: 'transparent', color: 'transparent' };
        
        const config = statusConfig[status];
        if (!config) return { backgroundColor: 'var(--color-ivory)', color: 'var(--color-text-muted)' };

        if (config.color_hex) {
            return {
                backgroundColor: `color-mix(in srgb, ${config.color_hex} 15%, transparent)`,
                color: config.color_hex,
                border: `1px solid color-mix(in srgb, ${config.color_hex} 30%, transparent)`
            };
        }

        if (config.color === 'custom' && config.color_hex) {
            return { backgroundColor: config.color_hex, color: '#ffffff', border: 'none' };
        }

        // Mapping for standard statuses
        const colorMap = {
            'bg-orange-100 text-orange-800': { backgroundColor: '#ffedd5', color: '#9a3412' },
            'bg-sky-100 text-sky-800': { backgroundColor: '#e0f2fe', color: '#075985' },
            'bg-emerald-100 text-emerald-800': { backgroundColor: '#d1fae5', color: '#065f46' },
            'bg-rose-100 text-rose-800': { backgroundColor: '#ffe4e6', color: '#9f1239' },
        };

        return colorMap[config.color] || { backgroundColor: 'var(--color-ivory)', color: 'var(--color-text-muted)' };
    };

    const getStatusDisplay = (status) => {
        if (!status) return '';
        return statusConfig[status]?.key || status.substring(0, 1).toUpperCase();
    };

    const months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

    return (
        <div style={{ paddingBottom: '40px' }}>
            {/* Actions Bar */}
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '24px' }}>
                <div style={{ display: 'flex', gap: '12px' }}>
                    <button 
                        className="btn btn-primary" 
                        onClick={handleExport}
                        disabled={exporting || !reportData}
                        style={{ display: 'flex', alignItems: 'center', gap: '8px' }}
                    >
                        {exporting ? <div className="spinner-inline" /> : <FileSpreadsheet size={18} />}
                        Export CSV
                    </button>
                </div>
            </div>

            {/* Filters Bar */}
            <div className="card" style={{ padding: '16px', marginBottom: '24px', display: 'flex', flexWrap: 'wrap', gap: '20px', alignItems: 'flex-end' }}>
                <div style={{ flex: 1, minWidth: '200px' }}>
                    <label className="form-label">
                        <Building size={14} style={{ marginRight: '6px' }} /> Company/Office
                    </label>
                    <select 
                        className="form-input" 
                        value={selectedCompany} 
                        onChange={(e) => setSelectedCompany(e.target.value)}
                        style={{ height: '40px' }}
                    >
                        <option value="global">All Offices (Global)</option>
                        {companies.map(c => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                </div>

                {!useCustomRange ? (
                    <>
                        <div style={{ width: '150px' }}>
                            <label className="form-label">
                                <Calendar size={14} style={{ marginRight: '6px' }} /> Month
                            </label>
                            <select 
                                className="form-input" 
                                value={selectedMonth} 
                                onChange={(e) => setSelectedMonth(parseInt(e.target.value))}
                                style={{ height: '40px' }}
                            >
                                {months.map((m, i) => (
                                    <option key={m} value={i + 1}>{m}</option>
                                ))}
                            </select>
                        </div>

                        <div style={{ width: '120px' }}>
                            <label className="form-label">
                                <Calendar size={14} style={{ marginRight: '6px' }} /> Year
                            </label>
                            <select 
                                className="form-input" 
                                value={selectedYear} 
                                onChange={(e) => setSelectedYear(parseInt(e.target.value))}
                                style={{ height: '40px' }}
                            >
                                {years.map(y => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>
                    </>
                ) : (
                    <>
                        <div style={{ width: '150px' }}>
                            <label className="form-label">Start Date</label>
                            <input 
                                type="date" 
                                className="form-input" 
                                value={startDate} 
                                onChange={(e) => setStartDate(e.target.value)}
                                style={{ height: '40px' }}
                            />
                        </div>
                        <div style={{ width: '150px' }}>
                            <label className="form-label">End Date</label>
                            <input 
                                type="date" 
                                className="form-input" 
                                value={endDate} 
                                onChange={(e) => setEndDate(e.target.value)}
                                style={{ height: '40px' }}
                            />
                        </div>
                    </>
                )}

                <div style={{ minWidth: '140px' }}>
                    <button 
                        className="btn" 
                        onClick={() => setUseCustomRange(!useCustomRange)}
                        style={{ 
                            height: '40px', width: '100%', 
                            border: '1px solid var(--color-border)', 
                            backgroundColor: useCustomRange ? 'var(--color-ivory)' : 'white', 
                            color: 'var(--color-charcoal)',
                            fontSize: '13px', fontWeight: '600'
                        }}
                    >
                        {useCustomRange ? "Switch to Monthly" : "Custom Range"}
                    </button>
                </div>

                <div style={{ flex: 2, minWidth: '250px', display: 'flex', gap: '12px', alignItems: 'flex-end' }}>
                    <div style={{ flex: 1 }}>
                        <label className="form-label">
                            <Search size={14} style={{ marginRight: '6px' }} /> Search Employee
                        </label>
                        <div style={{ position: 'relative' }}>
                            <input 
                                type="text" 
                                className="form-input" 
                                placeholder="Name or ID..." 
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                style={{ height: '40px', paddingLeft: '36px' }}
                            />
                            <Search size={16} style={{ position: 'absolute', left: '12px', top: '12px', color: 'var(--color-text-muted)' }} />
                        </div>
                    </div>
                    <button 
                        className="btn btn-primary" 
                        onClick={fetchReport}
                        disabled={loading}
                        style={{ height: '40px', display: 'flex', alignItems: 'center', gap: '8px', padding: '0 20px', whiteSpace: 'nowrap' }}
                    >
                        {loading ? <div className="spinner-inline" style={{ borderTopColor: 'white' }} /> : <Calendar size={18} />}
                        Generate
                    </button>
                </div>
            </div>

            {/* Error State */}
            {error && (
                <div className="card" style={{ padding: '40px', textAlign: 'center', border: '1px solid #fee2e2', backgroundColor: '#fef2f2' }}>
                    <AlertCircle size={48} color="#ef4444" style={{ marginBottom: '16px' }} />
                    <h3 style={{ color: '#991b1b', marginBottom: '8px' }}>Report Loading Failed</h3>
                    <p style={{ color: '#b91c1c' }}>{error}</p>
                    <button className="btn btn-primary" style={{ marginTop: '20px' }} onClick={fetchReport}>Retry</button>
                </div>
            )}

            {/* Report Analytics Summary */}
            {reportData && !loading && (
                <div style={{ 
                    display: 'grid', 
                    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', 
                    gap: '1rem', 
                    marginBottom: '1.5rem' 
                }}>
                    <div className="card" style={{ padding: '1.25rem', borderLeft: '4px solid var(--color-rose-gold)', position: 'relative' }}>
                        <div style={{ fontSize: '11px', fontWeight: '800', color: 'var(--color-text-muted)', textTransform: 'uppercase', marginBottom: '6px', letterSpacing: '0.05em' }}>Total Personnel</div>
                        <div style={{ fontSize: '28px', fontWeight: '700', color: 'var(--color-charcoal)', fontFamily: 'var(--font-heading)' }}>{reportData.grid.length}</div>
                        <div style={{ fontSize: '11px', color: 'var(--color-text-muted)', marginTop: '4px' }}>Employees in this selection</div>
                    </div>

                    {Object.entries(reportData.summary || {})
                        .sort((a, b) => b[1] - a[1])
                        .slice(0, 5)
                        .map(([key, count]) => {
                            const cfg = statusConfig[key];
                            if (!cfg) return null;
                            const style = getStatusStyle(key);
                            return (
                                <div key={key} className="card" style={{ padding: '1.25rem', borderLeft: `4px solid ${style.color || 'var(--color-border)'}`, position: 'relative' }}>
                                    <div style={{ fontSize: '11px', fontWeight: '800', color: 'var(--color-text-muted)', textTransform: 'uppercase', marginBottom: '6px', letterSpacing: '0.05em' }}>{cfg.label}</div>
                                    <div style={{ fontSize: '28px', fontWeight: '700', color: 'var(--color-charcoal)', fontFamily: 'var(--font-heading)' }}>{count}</div>
                                    <div style={{ fontSize: '11px', color: 'var(--color-text-muted)', marginTop: '4px' }}>Total instances this month</div>
                                </div>
                            );
                        })
                    }
                </div>
            )}

            {/* Empty State / Welcome */}
            {!reportData && !loading && !error && (
                <div className="card" style={{ padding: '80px 20px', textAlign: 'center', border: '1px dashed var(--color-border)', backgroundColor: 'var(--color-ivory)', opacity: 0.8 }}>
                    <div style={{ marginBottom: '20px', color: 'var(--color-rose-gold)' }}>
                        <FileSpreadsheet size={64} strokeWidth={1} />
                    </div>
                    <h2 style={{ fontSize: '24px', fontWeight: '600', color: 'var(--color-charcoal)', marginBottom: '12px' }}>Attendance Report Generator</h2>
                    <p style={{ color: 'var(--color-text-muted)', maxWidth: '500px', margin: '0 auto 24px' }}>
                        Select an office and period above, then click the <strong>Generate</strong> button to analyze attendance records and export reports.
                    </p>
                    <button className="btn btn-primary" onClick={fetchReport} style={{ padding: '0 32px' }}>
                        Get Started
                    </button>
                </div>
            )}

            {/* Main Report Table */}
            {!error && (reportData || loading) && (
                <div className="card" style={{ padding: 0, overflow: 'hidden', border: '1px solid var(--border-gray)' }}>
                    {loading && (
                        <div style={{ 
                            position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, 
                            zIndex: 10,
                            display: 'flex', justifyContent: 'center', alignItems: 'center',
                            pointerEvents: 'none'
                        }}>
                            <div className="loader-content">
                                <div className="loader-spinner"></div>
                                <div className="loader-text">Aggregating Report Data...</div>
                            </div>
                        </div>
                    )}

                    <div style={{ overflowX: 'auto', maxHeight: '70vh' }}>
                        <table style={{ width: '100%', borderCollapse: 'separate', borderSpacing: 0 }}>
                            <thead style={{ position: 'sticky', top: 0, zIndex: 5, backgroundColor: 'var(--color-ivory)' }}>
                                <tr>
                                    <th style={{ 
                                        position: 'sticky', left: 0, zIndex: 7, backgroundColor: 'var(--color-ivory)',
                                        padding: '12px 10px', textAlign: 'center', borderBottom: '2px solid var(--color-border)',
                                        width: '40px', fontWeight: '600', fontSize: '13px', borderRight: '1px solid var(--color-border)'
                                    }}>
                                        #
                                    </th>
                                    <th style={{ 
                                        position: 'sticky', left: '40px', zIndex: 6, backgroundColor: 'var(--color-ivory)',
                                        padding: '12px 20px', textAlign: 'left', borderBottom: '2px solid var(--color-border)',
                                        minWidth: '240px', fontWeight: '600', fontSize: '13px'
                                    }}>
                                        Employee Details
                                    </th>
                                    {daysMeta.map(dm => {
                                        const isToday = new Date().toISOString().split('T')[0] === dm.date;
                                        
                                        return (
                                            <th key={dm.date} style={{ 
                                                padding: '12px 4px', textAlign: 'center', borderBottom: '2px solid var(--color-border)',
                                                minWidth: '36px', fontWeight: '600', fontSize: '11px',
                                                backgroundColor: isToday ? 'rgba(40, 107, 62, 0.05)' : 'inherit'
                                            }}>
                                                <div style={{ color: isToday ? 'var(--color-rose-gold)' : 'var(--color-text-muted)', textTransform: 'uppercase' }}>{dm.day_name[0]}</div>
                                                <div style={{ fontSize: '13px', marginTop: '2px', color: isToday ? 'var(--color-rose-gold)' : 'inherit' }}>{dm.day}</div>
                                            </th>
                                        );
                                    })}
                                </tr>
                            </thead>
                            <tbody>
                                {filteredGrid.length === 0 ? (
                                    <tr>
                                        <td colSpan={totalDays + 2} style={{ padding: '60px', textAlign: 'center', color: 'var(--color-text-muted)' }}>
                                            {loading ? '' : 'No employees found matching your filters.'}
                                        </td>
                                    </tr>
                                ) : (
                                    filteredGrid.map((emp, idx) => (
                                        <tr key={emp.employee_id} style={{ backgroundColor: idx % 2 === 0 ? 'white' : 'rgba(0,0,0,0.01)' }}>
                                            <td style={{ 
                                                position: 'sticky', left: 0, zIndex: 4, 
                                                backgroundColor: idx % 2 === 0 ? 'white' : '#fafafa',
                                                padding: '10px', textAlign: 'center', borderBottom: '1px solid var(--color-border)',
                                                borderRight: '1px solid var(--color-border)', fontWeight: '600', color: 'var(--color-text-muted)'
                                            }}>
                                                {idx + 1}
                                            </td>
                                            <td style={{ 
                                                position: 'sticky', left: '40px', zIndex: 4, 
                                                backgroundColor: idx % 2 === 0 ? 'white' : '#fafafa',
                                                padding: '10px 20px', borderBottom: '1px solid var(--color-border)',
                                                borderRight: '1px solid var(--color-border)'
                                            }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                                    <div style={{ 
                                                        width: '32px', height: '32px', borderRadius: '50%', 
                                                        backgroundColor: 'var(--color-ivory)', display: 'flex', 
                                                        justifyContent: 'center', alignItems: 'center',
                                                        fontSize: '12px', fontWeight: 'bold', color: 'var(--color-rose-gold)'
                                                    }}>
                                                        {emp.name.charAt(0)}
                                                    </div>
                                                    <div>
                                                        <div style={{ fontWeight: '600', fontSize: '14px' }}>{emp.name}</div>
                                                        <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>{emp.code}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            {daysMeta.map(dm => {
                                                const status = emp.days[dm.date];
                                                const style = getStatusStyle(status);
                                                const display = getStatusDisplay(status);
                                                
                                                return (
                                                    <td key={dm.date} style={{ 
                                                        padding: '4px', borderBottom: '1px solid var(--border-gray)',
                                                        textAlign: 'center'
                                                    }}>
                                                        <div 
                                                            title={statusConfig[status]?.label || status}
                                                            style={{ 
                                                                width: '28px', height: '28px', margin: '0 auto',
                                                                borderRadius: '4px', display: 'flex', 
                                                                justifyContent: 'center', alignItems: 'center',
                                                                fontSize: '11px', fontWeight: '700',
                                                                cursor: status ? 'help' : 'default',
                                                                ...style
                                                            }}
                                                        >
                                                            {display}
                                                        </div>
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Legend */}
                    <div style={{ 
                        padding: '16px 20px', backgroundColor: 'var(--color-ivory)', borderTop: '1px solid var(--color-border)',
                        display: 'flex', flexWrap: 'wrap', gap: '20px'
                    }}>
                        <div style={{ fontSize: '12px', fontWeight: '600', color: 'var(--color-text-muted)', marginRight: '8px' }}>LEGEND & SUMMARY:</div>
                        {Object.entries(statusConfig)
                            .filter(([key]) => (reportData?.summary?.[key] || 0) > 0)
                            .map(([key, cfg]) => (
                            <div key={key} style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px' }}>
                                <div style={{ 
                                    width: '18px', height: '18px', borderRadius: '3px', 
                                    display: 'flex', justifyContent: 'center', alignItems: 'center',
                                    fontSize: '9px', fontWeight: 'bold',
                                    ...getStatusStyle(key)
                                }}>
                                    {getStatusDisplay(key)}
                                </div>
                                <span style={{ color: 'var(--color-text-muted)' }}>{cfg.label} ({reportData.summary[key]})</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default AttendanceReport;
