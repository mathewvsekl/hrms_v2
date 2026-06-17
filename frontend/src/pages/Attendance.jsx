import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { Search, Filter, Calendar, History, Save, Globe, Settings, Users, ClipboardList, Info, Loader, Clock, PlusCircle, FileText } from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useLayoutStore from '../store/useLayoutStore';
import AttendanceHistoryModal from '../components/AttendanceHistoryModal';
import DateInput from '../components/ui/DateInput';
import useNotificationStore from '../store/useNotificationStore';
const renderFlag = (country) => {
    if (!country) return <span>🌐</span>;
    const name = country.name?.toLowerCase() || '';
    const iso = country.iso_code?.toUpperCase() || '';

    // Mapping 3-letter ISO or Name to 2-letter for FlagCDN
    let code = '';
    if (iso === 'ARE' || name.includes('emirates')) code = 'ae';
    else if (iso === 'IND' || name.includes('india')) code = 'in';
    else if (iso === 'UGA' || name.includes('uganda')) code = 'ug';
    else if (iso === 'KEN' || name.includes('kenya')) code = 'ke';
    else if (iso === 'TZA' || name.includes('tanzania')) code = 'tz';
    else if (iso === 'GBR' || name.includes('united kingdom')) code = 'gb';
    else if (iso === 'USA' || name.includes('united states')) code = 'us';
    else if (iso === 'BGD' || name.includes('bangladesh')) code = 'bd';
    else if (iso === 'PAK' || name.includes('pakistan')) code = 'pk';
    else if (iso === 'PHL' || name.includes('philippines')) code = 'ph';
    else if (iso.length === 2) code = iso.toLowerCase();
    else if (iso.length === 3) code = iso.toLowerCase().slice(0, 2); // Fallback attempt

    if (!code) return <span>🏳️</span>;

    return (
        <img
            src={`https://flagcdn.com/w40/${code}.png`}
            srcSet={`https://flagcdn.com/w80/${code}.png 2x`}
            width="20"
            style={{
                borderRadius: '3px',
                boxShadow: '0 1px 2px rgba(0,0,0,0.1)',
                display: 'block'
            }}
            alt={country.name}
            onError={(e) => { e.target.style.display = 'none'; e.target.nextSibling.style.display = 'inline'; }}
        />
    );
};

const Attendance = () => {
    const navigate = useNavigate();
    const user = useAuthStore(state => state.user);
    const userRole = user?.role;
    const isSuperAdmin = user?.role === 'Super Admin';
    const canConfigure = useAuthStore.getState().hasPermission('configuration', 'view');
    const isAdmin = canConfigure;

    const [activeMainTab, setActiveMainTab] = useState('log');
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();
    const { showAlert, showConfirm } = useNotificationStore();

    useEffect(() => {
        setPageTitle("Attendance Management");
        setPageSubtitle(activeMainTab === 'log' ? "Live Attendance Log" : "Policy & Schedule Config");
        setBackPath('/dashboard');
        return () => resetPageHeader();
    }, [activeMainTab]);

    const [gridData, setGridData] = useState([]);
    const [countries, setCountries] = useState([]);
    const [searchParams] = useSearchParams();
    const [activeTab, setActiveTab] = useState(searchParams.get('country_id') || 'global');
    const [selectedDate, setSelectedDate] = useState(new Date().toLocaleDateString('en-CA'));
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [officeConfigs, setOfficeConfigs] = useState([]);
    const [attendanceStatuses, setAttendanceStatuses] = useState({ attendance: [], leave: [], all: [] });

    // History Modal
    const [isHistoryModalOpen, setIsHistoryModalOpen] = useState(false);
    const [selectedLogId, setSelectedLogId] = useState(null);

    // Track local changes in the grid
    const [localChanges, setLocalChanges] = useState({}); // {employeeId: status}

    useEffect(() => {
        fetchCountries();
        fetchStatuses();
    }, []);

    const fetchStatuses = async (countryId = null) => {
        try {
            const url = `/attendance/statuses${countryId ? `?country_id=${countryId}` : ''}`;
            const res = await api.get(url);
            const data = res.data?.data || res.data || {};

            // If backend provides 'all' (unified list), use it; otherwise merge attendance and leave
            const all = data.all || [...(data.attendance || []), ...(data.leave || [])];

            setAttendanceStatuses({
                attendance: data.attendance || [],
                leave: data.leave || [],
                all: all,
                companies: data.companies || {}
            });
        } catch (error) {
            console.error('Failed to fetch attendance statuses', error);
        }
    };

    useEffect(() => {
        fetchGridData();
        fetchStatuses(activeTab === 'global' ? null : activeTab);

        // Auto-switch date to country's 'Today' when switching tabs
        if (activeTab !== 'global' && countries.length > 0) {
            const country = countries.find(c => c.id == activeTab);
            if (country && country.current_date_local) {
                setSelectedDate(country.current_date_local);
            }
        } else if (activeTab === 'global') {
            // Default global to browser's today
            setSelectedDate(new Date().toLocaleDateString('en-CA'));
        }
    }, [activeTab, countries]);

    useEffect(() => {
        fetchGridData();
    }, [selectedDate]);



    const fetchCountries = async () => {
        try {
            const res = await api.get('/attendance/countries');
            const data = res.data?.data || res.data;
            const countriesList = Array.isArray(data) ? data : [];
            setCountries(countriesList);

            // If we just loaded and no date is set, or if we switch tabs, 
            // we might want to default to the country's local 'today'
            if (activeTab !== 'global') {
                const currentCountry = countriesList.find(c => c.id == activeTab);
                if (currentCountry && currentCountry.current_date_local) {
                    // Only auto-update if the user hasn't interacted with the date picker yet
                    // For now, let's just make it available for display
                }
            }
        } catch (error) {
            console.error('Failed to fetch countries', error);
        }
    };

    const fetchGridData = async () => {
        try {
            setLoading(true);
            const url = `/attendance/grid?date=${selectedDate}${activeTab !== 'global' ? `&country_id=${activeTab}` : ''}`;
            const res = await api.get(url);
            const data = res.data?.data || res.data;
            setGridData(Array.isArray(data) ? data : []);
            setLocalChanges({}); // Reset changes on new fetch
        } catch (error) {
            console.error('Failed to fetch grid data', error);
            showAlert('Error', 'Failed to load attendance log: ' + (error.response?.data?.message || error.message), 'error');
        } finally {
            setLoading(false);
        }
    };


    const handleStatusChange = (employeeId, status) => {
        setLocalChanges(prev => ({
            ...prev,
            [employeeId]: status
        }));
    };

    const handleSaveAll = async (confirm_unpaid = false) => {
        // Collect all entries that have a status, whether from localChanges or from calculated defaults in the grid
        const entries = filteredData.map(row => {
            const status = localChanges[row.employee_id] || row.status;
            // Only include if status is NOT "Select Status" (empty)
            if (status) {
                return {
                    employee_id: row.employee_id,
                    status: status,
                    remarks: row.remarks // Carry over existing remarks if any
                };
            }
            return null;
        }).filter(Boolean);

        if (entries.length === 0) {
            showAlert('Empty', 'No attendance statuses to save.', 'warning');
            return;
        }

        try {
            setSaving(true);
            await api.post('/attendance/grid-save', {
                attendance_date: selectedDate,
                entries: entries,
                confirm_unpaid: confirm_unpaid === true
            });
            fetchGridData();
            showAlert('Success', `Successfully saved ${entries.length} attendance records.`, 'success');
        } catch (error) {
            if (error.response?.status === 409 && error.response?.data?.message === 'UnpaidLeaveWarning') {
                showConfirm(
                    'Unpaid Leave Warning', 
                    'One or more of the selected leave types will exceed the employee\'s available balance and will be automatically converted to Unpaid Leave (Loss of Pay). Do you want to proceed and save?', 
                    () => { handleSaveAll(true); }
                );
            } else {
                showAlert('Error', 'Failed to save attendance: ' + (error.response?.data?.message || error.message), 'error');
            }
        } finally {
            setSaving(false);
        }
    };

    const handleSaveSingle = async (employeeId, confirm_unpaid = false) => {
        const row = gridData.find(r => r.employee_id === employeeId);
        if (!row) return;

        const status = localChanges[employeeId] || row.status;
        if (!status) return showAlert('Required', 'Please select a status first.', 'warning');

        try {
            setSaving(true);
            const entry = {
                employee_id: employeeId,
                status: status,
                remarks: row.remarks || '',
                is_manually_modified: localChanges[employeeId] ? 1 : 0
            };

            await api.post('/attendance/grid-save', {
                attendance_date: selectedDate,
                entries: [entry],
                confirm_unpaid: confirm_unpaid === true
            });

            // Clear local change for this row
            setLocalChanges(prev => {
                const next = { ...prev };
                delete next[employeeId];
                return next;
            });

            fetchGridData();
            showAlert('Success', 'Record saved successfully.', 'success');
        } catch (error) {
            if (error.response?.status === 409 && error.response?.data?.message === 'UnpaidLeaveWarning') {
                showConfirm(
                    'Unpaid Leave Warning', 
                    'The selected leave type exceeds the employee\'s available balance and will be automatically converted to Unpaid Leave (Loss of Pay). Do you want to proceed and save?', 
                    () => { handleSaveSingle(employeeId, true); }
                );
            } else {
                showAlert('Error', 'Failed to save record: ' + (error.response?.data?.message || error.message), 'error');
            }
        } finally {
            setSaving(false);
        }
    };

    const filteredData = gridData.filter(row => {
        const fullSearch = `${row.first_name} ${row.last_name} ${row.employee_code} ${row.role_name}`.toLowerCase();
        return fullSearch.includes(searchTerm.toLowerCase());
    });

    const getStatusStyle = (status, isSaved) => {
        const baseStyle = {
            border: isSaved ? '2px solid var(--border-gray)' : '2px dashed var(--border-gray)',
            backgroundColor: isSaved ? 'white' : 'var(--bg-light)',
            transition: 'all 0.2s'
        };

        if (!status) return baseStyle;

        // Find status in the dynamic list from backend by id (semantic mapping)
        const found = attendanceStatuses.all?.find(s => s.id === status);
        const color = found?.color_code || '#3b82f6';

        return {
            ...baseStyle,
            border: isSaved ? `2px solid ${color}` : `2px dashed ${color}`,
            boxShadow: isSaved ? `0 0 0 1px ${color}20` : 'none'
        };
    };

    return (
        <div style={{ maxWidth: '1200px', margin: '0 auto', padding: '20px' }}>
            {/* Main Tabs Navigation */}
            <div style={{ display: 'flex', gap: '32px', borderBottom: '1px solid var(--border-gray)', marginBottom: '24px' }}>
                <button
                    onClick={() => setActiveMainTab('log')}
                    style={{
                        padding: '12px 0',
                        fontSize: '14px',
                        fontWeight: '600',
                        color: activeMainTab === 'log' ? '#065f46' : 'var(--text-secondary)',
                        borderBottom: activeMainTab === 'log' ? '2px solid #065f46' : '2px solid transparent',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        background: 'none',
                        cursor: 'pointer',
                        transition: 'all 0.2s'
                    }}
                >
                    <ClipboardList size={18} /> Attendance Log
                </button>
                {isAdmin && (
                    <button
                        onClick={() => setActiveMainTab('config')}
                        style={{
                            padding: '12px 0',
                            fontSize: '14px',
                            fontWeight: '600',
                            color: activeMainTab === 'config' ? '#065f46' : 'var(--text-secondary)',
                            borderBottom: activeMainTab === 'config' ? '2px solid #065f46' : '2px solid transparent',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            background: 'none',
                            cursor: 'pointer',
                            transition: 'all 0.2s'
                        }}
                    >
                        <Settings size={18} /> Configuration
                    </button>
                )}
                <button
                    onClick={() => navigate('/attendance-report')}
                    style={{
                        padding: '12px 0',
                        fontSize: '14px',
                        fontWeight: '600',
                        color: 'var(--text-secondary)',
                        borderBottom: '2px solid transparent',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        background: 'none',
                        cursor: 'pointer',
                        transition: 'all 0.2s'
                    }}
                >
                    <FileText size={18} /> Attendance Report
                </button>
            </div>

            {activeMainTab === 'log' ? (
                <>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '32px', gap: '20px', flexWrap: 'wrap' }}>
                        <div style={{ display: 'flex', gap: '12px', flexWrap: 'wrap', flex: '1', minWidth: '300px' }}>
                            <button
                                onClick={() => setActiveTab('global')}
                                style={{
                                    padding: '8px 20px',
                                    borderRadius: '20px',
                                    border: 'none',
                                    background: activeTab === 'global' ? 'var(--color-rose-gold)' : 'white', // Using official theme brand color
                                    color: activeTab === 'global' ? 'white' : 'var(--color-charcoal)',
                                    fontWeight: '600',
                                    fontSize: '14px',
                                    cursor: 'pointer',
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '8px',
                                    boxShadow: activeTab === 'global' ? 'none' : '0 1px 3px rgba(0,0,0,0.1)'
                                }}
                            >
                                <Globe size={16} /> Global
                            </button>
                            {countries.map(country => (
                                <button
                                    key={country.id}
                                    onClick={() => setActiveTab(country.id)}
                                    style={{
                                        padding: '8px 20px',
                                        borderRadius: '20px',
                                        border: 'none',
                                        background: activeTab == country.id ? 'var(--color-rose-gold)' : 'white',
                                        color: activeTab == country.id ? 'white' : 'var(--color-charcoal)',
                                        fontWeight: '600',
                                        fontSize: '14px',
                                        cursor: 'pointer',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '8px',
                                        boxShadow: activeTab == country.id ? 'none' : '0 1px 3px rgba(0,0,0,0.1)',
                                        position: 'relative'
                                    }}
                                >
                                    {renderFlag(country)}
                                    <span>{country.name}</span>
                                </button>
                            ))}
                        </div>

                        <div style={{
                            background: 'white',
                            border: '1px solid var(--border-gray, var(--color-border))',
                            borderRadius: '12px',
                            padding: '6px 16px',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '12px',
                            minWidth: '200px',
                            boxShadow: '0 1px 3px rgba(0,0,0,0.05)',
                            marginBottom: '8px'
                        }}>
                            <Calendar size={16} style={{ color: 'var(--color-rose-gold)' }} />
                            <DateInput
                                value={selectedDate}
                                onChange={(val) => {
                                    const browserToday = new Date().toLocaleDateString('en-CA');
                                    const countryToday = countries.find(c => c.id == activeTab)?.current_date_local || browserToday;
                                    const maxDate = countryToday > browserToday ? countryToday : browserToday;

                                    if (!isSuperAdmin && val > maxDate) {
                                        showAlert('Invalid Date', "Attendance cannot be logged for future dates.", 'warning');
                                        return;
                                    }
                                    setSelectedDate(val);
                                }}
                                max={(() => {
                                    if (isSuperAdmin) return undefined;
                                    const browserToday = new Date().toLocaleDateString('en-CA');
                                    const countryToday = countries.find(c => c.id == activeTab)?.current_date_local || browserToday;
                                    return countryToday > browserToday ? countryToday : browserToday;
                                })()}
                                style={{ border: 'none', outline: 'none', fontSize: '14.5px', fontWeight: '600', color: 'var(--text-main)' }}
                            />
                        </div>
                    </div>

                    {/* Main Content Card */}
                    <div className="card" style={{ padding: '0', overflow: 'hidden', border: '1px solid var(--border-gray)', background: 'white' }}>
                        <div style={{ padding: '20px 24px', borderBottom: '1px solid var(--border-gray)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '20px' }}>
                                <h2 style={{ fontSize: '18px', fontWeight: '700', margin: '0' }}>Mark Attendance</h2>
                                <div style={{ position: 'relative' }}>
                                    <Search size={16} style={{ position: 'absolute', left: '12px', top: '10px', color: 'var(--text-secondary)' }} />
                                    <input
                                        type="text"
                                        className="form-input"
                                        placeholder="Search by name or Staff ID..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        style={{ paddingLeft: '36px', width: '250px', height: '36px', fontSize: '14px' }}
                                    />
                                </div>
                            </div>
                            <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
                                <div style={{ fontSize: '13px', color: 'var(--text-secondary)', marginRight: '8px' }}>
                                    {filteredData.filter(r => r.is_saved || localChanges[r.employee_id]).length} / {filteredData.length} set
                                </div>
                                <button className="btn btn-primary" onClick={handleSaveAll} disabled={saving}>
                                    <Save size={18} style={{ marginRight: '8px' }} /> {saving ? 'Saving...' : `Save All Entries`}
                                </button>
                            </div>
                        </div>

                        <div style={{ overflowX: 'auto' }}>
                            <table className="data-table" style={{ width: '100%' }}>
                                <thead style={{ background: '#f8fafc' }}>
                                    <tr>
                                        <th style={{ color: 'var(--text-secondary)', fontWeight: '600', textTransform: 'uppercase', fontSize: '12px', padding: '10px 16px' }}>Employee Name</th>
                                        <th style={{ color: 'var(--text-secondary)', fontWeight: '600', textTransform: 'uppercase', fontSize: '12px', padding: '10px 16px' }}>Staff ID</th>
                                        <th style={{ color: 'var(--text-secondary)', fontWeight: '600', textTransform: 'uppercase', fontSize: '12px', padding: '10px 16px' }}>Role</th>
                                        <th style={{ color: 'var(--text-secondary)', fontWeight: '600', textTransform: 'uppercase', fontSize: '12px', padding: '10px 16px', textAlign: 'right' }}>Status / Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {loading ? (
                                        <tr>
                                            <td colSpan="4" style={{ textAlign: 'center', padding: '60px' }}>
                                                <div className="loader-content">
                                                    <div className="loader-spinner"></div>
                                                    <div className="loader-text">LOADING EMPLOYEES...</div>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : filteredData.length === 0 ? (
                                        <tr><td colSpan="4" style={{ textAlign: 'center', padding: '40px', color: 'var(--text-secondary)' }}>No employees found for this selection.</td></tr>
                                    ) : (
                                        filteredData.map((row) => (
                                            <tr key={row.employee_id} style={{ transition: 'background 0.2s' }}>
                                                <td style={{ padding: '6px 16px' }}>
                                                    <div style={{ fontWeight: '600', color: 'var(--text-main)', fontSize: '14px' }}>{row.first_name} {row.last_name}</div>
                                                </td>
                                                <td style={{ padding: '6px 16px', color: 'var(--text-secondary)', fontSize: '13px' }}>
                                                    {row.employee_code || 'N/A'}
                                                </td>
                                                <td style={{ padding: '6px 16px', color: 'var(--text-secondary)', fontSize: '13px' }}>
                                                    {row.role_name || 'N/A'}
                                                </td>
                                                <td style={{ padding: '6px 16px', textAlign: 'right' }}>
                                                    <div style={{ display: 'flex', justifyContent: 'flex-end', alignItems: 'center', gap: '8px' }}>


                                                        {row.log_id && (
                                                            <button
                                                                className="btn-icon"
                                                                title="History"
                                                                onClick={() => { setSelectedLogId(row.log_id); setIsHistoryModalOpen(true); }}
                                                                style={{ color: 'var(--text-secondary)', padding: '4px' }}
                                                            >
                                                                <History size={14} />
                                                            </button>
                                                        )}

                                                        <select
                                                            value={localChanges[row.employee_id] || row.status || 'present'}
                                                            onChange={(e) => handleStatusChange(row.employee_id, e.target.value)}
                                                            className="form-input"
                                                            style={{
                                                                width: '160px',
                                                                borderRadius: '6px',
                                                                padding: '4px 8px',
                                                                fontSize: '13px',
                                                                fontWeight: '500',
                                                                height: '32px',
                                                                background: 'white',
                                                                ...getStatusStyle(
                                                                    localChanges[row.employee_id] || row.status,
                                                                    (row.is_saved && !localChanges[row.employee_id])
                                                                )
                                                            }}
                                                        >
                                                            <option value="">Select Status</option>
                                                            {(() => {
                                                                const cId = row.company_id;
                                                                const defaultStatuses = { attendance: attendanceStatuses.attendance, leave: attendanceStatuses.leave };
                                                                const specificStatuses = attendanceStatuses.companies && cId && attendanceStatuses.companies[cId]
                                                                    ? attendanceStatuses.companies[cId]
                                                                    : defaultStatuses;

                                                                return (
                                                                    <>
                                                                        <optgroup label="Attendance Types">
                                                                            {specificStatuses.attendance.map(s => (
                                                                                <option key={s.id} value={s.id}>{s.name}</option>
                                                                            ))}
                                                                        </optgroup>
                                                                        <optgroup label="Leave Types">
                                                                            {specificStatuses.leave.filter(s => {
                                                                                const restriction = s.gender_restriction?.toLowerCase() || 'none';
                                                                                const empGender = row.gender?.toLowerCase() || 'none';
                                                                                if (restriction === 'none') return true;
                                                                                return restriction === empGender;
                                                                            }).map(s => (
                                                                                <option key={s.id} value={s.id}>{s.name}</option>
                                                                            ))}
                                                                        </optgroup>
                                                                    </>
                                                                );
                                                            })()}
                                                        </select>

                                                        {(localChanges[row.employee_id] || !row.is_saved) && (
                                                            <button
                                                                className="btn-icon"
                                                                title="Save this record"
                                                                onClick={() => handleSaveSingle(row.employee_id)}
                                                                disabled={saving}
                                                                style={{
                                                                    color: '#065f46',
                                                                    background: '#ecfdf5',
                                                                    borderRadius: '6px',
                                                                    border: '1px solid #059669',
                                                                    width: '32px',
                                                                    height: '32px',
                                                                    display: 'flex',
                                                                    alignItems: 'center',
                                                                    justifyContent: 'center',
                                                                    flexShrink: 0
                                                                }}
                                                            >
                                                                {saving ? <Loader className="spin" size={14} /> : <Save size={14} />}
                                                            </button>
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

                    <AttendanceHistoryModal
                        isOpen={isHistoryModalOpen}
                        onClose={() => setIsHistoryModalOpen(false)}
                        logId={selectedLogId}
                    />

                </>
            ) : (
                <AttendanceConfiguration />
            )}

        </div>
    );
};

/* ── Configuration Sub-component ────────────────────────── */

const AttendanceConfiguration = () => {
    const [companies, setCompanies] = useState([]);
    const [selectedCompany, setSelectedCompany] = useState('');
    const [attendanceStatuses, setAttendanceStatuses] = useState({ attendance: [], leave: [] });
    const [weeklySchedules, setWeeklySchedules] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const { showAlert, showConfirm } = useNotificationStore();

    const [isAdding, setIsAdding] = useState(false);
    const [formData, setFormData] = useState({ status_key: '', status_label: '', color_code: '#3b82f6', is_default: false });
    const [configTab, setConfigTab] = useState('schedule');

    useEffect(() => {
        fetchCompanies();
    }, []);

    useEffect(() => {
        if (selectedCompany) {
            fetchStatuses();
            fetchWeeklySchedules();
        }
    }, [selectedCompany]);

    const fetchWeeklySchedules = async () => {
        try {
            const res = await api.get(`/attendance/weekly-schedules?company_id=${selectedCompany}`);
            setWeeklySchedules(res.data?.data || res.data || []);
        } catch (error) { console.error(error); }
    };

    const fetchStatuses = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/attendance/statuses?company_id=${selectedCompany}&all=1`);
            const data = res.data?.data || res.data || {};
            setAttendanceStatuses({
                attendance: data.attendance || [],
                leave: data.leave || [],
                all_templates: data.system_templates || []
            });
        } catch (error) { console.error(error); }
        finally { setLoading(false); }
    };

    const handleWeeklyToggle = async (day, currentStatus) => {
        const newStatus = currentStatus === 'Workday' ? 'Weekend' : 'Workday';
        try {
            await api.post('/attendance/weekly-schedule', {
                company_id: selectedCompany,
                day_of_week: day,
                status: newStatus
            });
            fetchWeeklySchedules();
        } catch (error) {
            console.error(error);
            showAlert('Error', 'Failed to update schedule.', 'error');
        }
    };

    const user = useAuthStore(state => state.user);
    const userCompanyId = user?.company_id || user?.primary_company_id;

    const fetchCompanies = async () => {
        try {
            const res = await api.get('/organization/companies');
            const list = res.data?.data || res.data || [];
            setCompanies(list);
            
            if (list.length > 0) {
                // Priority: User's primary company -> First company in list
                const defaultCompany = list.find(c => c.id == userCompanyId) || list[0];
                setSelectedCompany(defaultCompany.id);
            }
        } catch (e) { console.error(e); }
    };

    return (
        <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
            <div style={{ padding: '24px', borderBottom: '1px solid var(--border-gray)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#fcfcfc' }}>
                <div>
                    <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '8px', fontSize: '18px', fontWeight: '700' }}>
                        <Settings size={20} style={{ color: '#065f46' }} /> Attendance Architect
                    </h3>
                    <p style={{ margin: '4px 0 0', fontSize: '13px', color: 'var(--text-secondary)' }}>Manage office-level attendance defaults and special days</p>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <label style={{ fontSize: '14px', fontWeight: '600' }}>Office Scope:</label>
                    <select className="form-input" style={{ width: '220px', height: '38px' }} value={selectedCompany} onChange={e => setSelectedCompany(e.target.value)}>
                        {companies.map(c => <option key={c.id} value={c.id}>{c.name} ({c.country_name})</option>)}
                    </select>
                </div>
            </div>

            <div style={{ display: 'flex', background: '#fff', minHeight: '60vh' }}>
                {/* Left Sidebar */}
                <div style={{ width: '200px', borderRight: '1px solid var(--border-gray)', padding: '10px' }}>
                    <button 
                        className={`nav-item ${configTab === 'schedule' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'schedule' ? 'var(--color-rose-gold)' : 'inherit', background: configTab === 'schedule' ? '#1e293b' : 'transparent',  borderRadius: '6px', padding: '8px 12px', display: 'flex', alignItems: 'center', gap: '8px', border: 'none', cursor: 'pointer', fontWeight: configTab === 'schedule' ? '600' : '400' }}
                        onClick={() => setConfigTab('schedule')}
                    >
                        <Calendar size={16} /> Weekly Schedule
                    </button>
                    <button 
                        className={`nav-item ${configTab === 'status' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'status' ? 'var(--color-rose-gold)' : 'inherit', background: configTab === 'status' ? '#1e293b' : 'transparent',  borderRadius: '6px', padding: '8px 12px', display: 'flex', alignItems: 'center', gap: '8px', border: 'none', cursor: 'pointer', fontWeight: configTab === 'status' ? '600' : '400' }}
                        onClick={() => setConfigTab('status')}
                    >
                        <ClipboardList size={16} /> Status Definitions
                    </button>
                </div>

                {/* Main Content */}
                <div style={{ flex: 1, padding: '20px' }}>
                    {configTab === 'schedule' && (
                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                                <h4 style={{ margin: 0, fontSize: '15px' }}>Office Weekly Schedule</h4>
                                <p style={{ margin: 0, fontSize: '13px', color: 'var(--text-secondary)' }}>Define standard workdays and weekends for this office scope.</p>
                            </div>

                            <div style={{ padding: '20px', display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(140px, 1fr))', gap: '12px' }}>
                            {['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].map(day => {
                                const config = weeklySchedules.find(s => s.day_of_week === day);
                                const isWeekend = config ? (config.status === 'Weekend' || config.status === 'Off') : (day === 'Saturday' || day === 'Sunday');

                                return (
                                    <div
                                        key={day}
                                        style={{
                                            padding: '16px',
                                            background: 'white',
                                            borderRadius: '12px',
                                            border: '1px solid var(--border-gray)',
                                            display: 'flex',
                                            flexDirection: 'column',
                                            alignItems: 'center',
                                            gap: '12px',
                                            boxShadow: '0 1px 2px rgba(0,0,0,0.05)'
                                        }}
                                    >
                                        <span style={{ fontWeight: '600', fontSize: '14px' }}>{day}</span>
                                        <button
                                            onClick={() => handleWeeklyToggle(day, isWeekend ? 'Weekend' : 'Workday')}
                                            style={{
                                                padding: '6px 12px',
                                                borderRadius: '20px',
                                                border: 'none',
                                                background: isWeekend ? '#fef2f2' : '#ecfdf5',
                                                color: isWeekend ? '#ef4444' : '#059669',
                                                fontSize: '12px',
                                                fontWeight: '700',
                                                cursor: 'pointer',
                                                width: '100%',
                                                textAlign: 'center',
                                                transition: 'all 0.2s'
                                            }}
                                        >
                                            {isWeekend ? 'WEEKEND' : 'WORKDAY'}
                                        </button>
                                    </div>
                                );
                            })}
                            </div>
                        </div>
                    )}

                    {configTab === 'status' && (
                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                                <div>
                                    <h4 style={{ margin: 0, fontSize: '15px' }}>Attendance Status Definitions</h4>
                                    <p style={{ margin: '4px 0 0', fontSize: '13px', color: 'var(--text-secondary)' }}>Define the valid attendance types for this office.</p>
                                </div>
                                <button className="btn btn-secondary" style={{ padding: '6px 12px', fontSize: '13px' }} onClick={() => {
                                    if (isAdding) {
                                        setIsAdding(false);
                                        setFormData({ status_key: '', status_label: '', color_code: '#3b82f6', is_default: false, is_edit: false, copy_from_id: '' });
                                    } else {
                                        setIsAdding(true);
                                    }
                                }}>
                                    <PlusCircle size={14} style={{ marginRight: '8px' }} /> {isAdding ? 'Cancel' : 'Add Status Type'}
                                </button>
                            </div>

                            {isAdding && (
                                <div style={{ background: '#f8fafc', padding: '20px', borderRadius: '12px', border: '1px solid var(--border-gray)', marginBottom: '24px' }}>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.5fr 1fr auto auto', gap: '16px', alignItems: 'end' }}>
                                        <div>
                                            <label className="form-label" style={{ fontWeight: '600' }}>
                                                {formData.is_edit ? 'Internal Key' : 'Copy From Template (Optional)'}
                                            </label>
                                            <input
                                                className="form-input"
                                                value={formData.status_key}
                                                onChange={e => setFormData({ ...formData, status_key: e.target.value.toUpperCase() })}
                                                placeholder="e.g. SL, PR"
                                                disabled={formData.is_edit}
                                                style={formData.is_edit ? { backgroundColor: '#f1f5f9', cursor: 'not-allowed' } : {}}
                                            />
                                        </div>
                                        <div>
                                            <label className="form-label" style={{ fontWeight: '600' }}>Label</label>
                                            <input className="form-input" placeholder="e.g. On Site" value={formData.status_label} onChange={e => setFormData({ ...formData, status_label: e.target.value, copy_from_id: '' })} />
                                        </div>
                                        <div>
                                            <label className="form-label" style={{ fontWeight: '600' }}>Color</label>
                                            <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                                <input type="color" className="form-input" style={{ width: '40px', padding: '2px', height: '38px' }} value={formData.color_code} onChange={e => setFormData({ ...formData, color_code: e.target.value })} />
                                                <input className="form-input" style={{ flex: 1 }} value={formData.color_code} onChange={e => setFormData({ ...formData, color_code: e.target.value })} />
                                            </div>
                                        </div>
                                        <div style={{ paddingBottom: '10px' }}>
                                            <label className="form-label" style={{ fontWeight: '600', display: 'block', marginBottom: '8px' }}>Default Status</label>
                                            <div style={{ display: 'flex', gap: '16px', alignItems: 'center' }}>
                                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontSize: '14px' }}>
                                                    <input type="radio" name="is_default" checked={formData.is_default === true} onChange={() => setFormData({ ...formData, is_default: true })} /> Yes
                                                </label>
                                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', cursor: 'pointer', fontSize: '14px' }}>
                                                    <input type="radio" name="is_default" checked={formData.is_default === false} onChange={() => setFormData({ ...formData, is_default: false })} /> No
                                                </label>
                                            </div>
                                        </div>
                                        <button
                                            className="btn btn-primary"
                                            style={{ height: '38px', padding: '0 24px' }}
                                            onClick={async () => {
                                                if (!selectedCompany) return showAlert('Wait', 'Please select an office context first.', 'warning');
                                                if (!formData.status_label) return showAlert('Required', 'Label is required', 'warning');
                                                setSaving(true);
                                                try {
                                                    await api.post('/attendance/status-definitions', { ...formData, company_id: selectedCompany });
                                                    setIsAdding(false);
                                                    fetchStatuses();
                                                    showAlert('Success', 'Status definition saved.', 'success');
                                                } catch (e) {
                                                    console.error('Save failed:', e.response?.data || e);
                                                    showAlert('Error', 'Failed to save: ' + (e.response?.data?.message || e.message), 'error');
                                                }
                                                finally { setSaving(false); }
                                            }}
                                            disabled={saving}
                                        >
                                            {saving ? <Loader className="spin" size={18} /> : 'Save'}
                                        </button>
                                    </div>
                                </div>
                            )}

                            <div className="table-container" style={{ border: '1px solid var(--border-gray)', borderRadius: '12px' }}>
                                <table className="data-table">
                                    <thead style={{ background: '#f8fafc' }}>
                                        <tr>
                                            <th>Key</th>
                                            <th>Display</th>
                                            <th>Label</th>
                                            <th>Default</th>
                                            <th>Indication</th>
                                            <th style={{ textAlign: 'center' }}>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {loading ? (
                                            <tr>
                                                <td colSpan="6" style={{ textAlign: 'center', padding: '60px' }}>
                                                    <div className="loader-content">
                                                        <div className="loader-spinner"></div>
                                                        <div className="loader-text">REFRESHING LOGS...</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        ) : attendanceStatuses.attendance.length === 0 ? (
                                            <tr><td colSpan="6" style={{ textAlign: 'center', padding: '40px', color: 'var(--text-secondary)' }}>No custom statuses defined. Using system defaults.</td></tr>
                                        ) : (
                                            attendanceStatuses.attendance.map(status => (
                                                <tr key={status.id}>
                                                    <td style={{ fontFamily: 'monospace', color: 'var(--text-secondary)', fontSize: '11px' }}>{status.id}</td>
                                                    <td><span style={{ fontWeight: '600', color: 'var(--color-rose-gold)' }}>{status.display_code || status.id?.substring(0, 2)?.toUpperCase()}</span></td>
                                                    <td style={{ fontWeight: '600' }}>{status.name}</td>
                                                    <td>{status.is_default ? <span style={{ color: '#059669', fontWeight: '700' }}>YES</span> : 'No'}</td>
                                                    <td>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                            <div style={{ width: '16px', height: '16px', borderRadius: '4px', background: status.color_code }}></div>
                                                            <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>{status.color_code}</span>
                                                        </div>
                                                    </td>
                                                    <td style={{ textAlign: 'center' }}>
                                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                                            <button className="btn btn-secondary" style={{ padding: '4px 8px' }} onClick={() => {
                                                                setFormData({
                                                                    status_key: status.id,
                                                                    status_label: status.name,
                                                                    color_code: status.color_code || '#3b82f6',
                                                                    is_default: !!status.is_default,
                                                                    is_edit: true
                                                                });
                                                                setIsAdding(true);
                                                            }}>
                                                                Edit
                                                            </button>
                                                            <button className="btn btn-secondary" style={{ padding: '4px 8px', color: '#ef4444' }} onClick={async () => {
                                                                showConfirm('Delete Status', `Are you sure you want to delete status "${status.name}"?`, async () => {
                                                                    try {
                                                                        const targetId = status.status_id || status.id;
                                                                        await api.delete(`/attendance/status-definitions/${targetId}?company_id=${selectedCompany}`);
                                                                        fetchStatuses();
                                                                        showAlert('Success', 'Deleted.', 'success');
                                                                    } catch (e) {
                                                                        showAlert('Error', 'Failed to delete status.', 'error');
                                                                    }
                                                                });
                                                            }}>
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default Attendance;
