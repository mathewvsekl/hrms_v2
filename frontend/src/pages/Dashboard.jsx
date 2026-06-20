import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    Users, Calendar, CreditCard, Award,
    Timer, Cake, Medal, UserPlus, UserMinus,
    RefreshCw, CheckCircle, Globe, ChevronRight,
    Search, Filter, TrendingUp, Activity
} from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useLayoutStore from '../store/useLayoutStore';
import { formatDate } from '../utils/dateUtils';
import { isAdmin as checkIsAdmin } from '../utils/roleConstants';

const Dashboard = () => {
    const [stats, setStats] = useState({
        total: 0,
        active: 0,
        other: 0,
        attendancePerc: 0,
        staffOnSite: 0,
        payrollPerc: 0,
        appraisalsCount: 0,
        onboarding: 0,
        separation: 0,
        countries: [],
        milestones: [],
        pendingApprovals: []
    });
    const [loading, setLoading] = useState(true);
    const [companyName, setCompanyName] = useState('Avantgarde HRMS');
    const [allStatuses, setAllStatuses] = useState({ attendance: [], leave: [] });
    const user = useAuthStore(state => state.user);
    const navigate = useNavigate();

    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();
    
    useEffect(() => {
        const hasAdminAccess = [
            ['admin portal', 'view'],
            ['configuration', 'view'],
            ['employees', 'view'],
            ['offboarding', 'view'],
            ['reports', 'view'],
            ['assets', 'view'],
            ['payroll', 'edit']
        ].some(([mod, act]) => useAuthStore.getState().hasPermission(mod, act));
        
        const isAdmin = checkIsAdmin(user?.role_id ?? 0) || hasAdminAccess;
        if (!isAdmin) {
            navigate('/employee-profile');
        } else {
            setPageTitle("HR Administration Centre");
            // loadDashboardData will be called, which will eventually set the company name
            loadDashboardData();
        }
        return () => resetPageHeader();
    }, [user, navigate]);

    useEffect(() => {
        setPageSubtitle(`${companyName.toUpperCase()} Operational Overview`);
    }, [companyName]);

    const fetchStatuses = async (companyId = null) => {
        try {
            const url = `/attendance/statuses${companyId ? `?company_id=${companyId}` : ''}`;
            const res = await api.get(url);
            const data = res.data?.data || res.data || {};
            setAllStatuses({
                attendance: data.attendance || [],
                leave: data.leave || []
            });
        } catch (error) {
            console.error('Failed to fetch attendance statuses', error);
        }
    };

    const fetchSettings = async () => {
        try {
            const res = await api.get('/organization/settings');
            const list = res.data?.data || res.data || [];
            const companySetting = list.find(s => s.setting_key === 'company_name');
            if (companySetting?.setting_value) {
                setCompanyName(companySetting.setting_value);
            }
        } catch (err) {
            console.error("Failed to fetch settings", err);
        }
    };

    const loadDashboardData = async (force = false) => {
        try {
            setLoading(true);
            
            let totalCount = 0;
            let activeCount = 0;
            let presentCount = 0;
            let otherCount = 0;
            let onboardingCount = 0;
            let separationCount = 0;
            let countryStatsMap = {};
            let milestones = [];
            let employees = [];
            let payrollData = {};
            let appraisalData = {};
            let logs = [];

            const iso3to2 = {
                'AFG': 'af', 'ALB': 'al', 'DZA': 'dz', 'AGO': 'ao', 'ARG': 'ar', 'AUS': 'au', 'AUT': 'at', 'BHR': 'bh', 'BGD': 'bd', 'BEL': 'be', 'BWA': 'bw', 'BRA': 'br', 'CMR': 'cm', 'CAN': 'ca', 'CHL': 'cl', 'CHN': 'cn', 'COL': 'co', 'COD': 'cd', 'CIV': 'ci', 'CZE': 'cz', 'DNK': 'dk', 'EGY': 'eg', 'ETH': 'et', 'FIN': 'fi', 'FRA': 'fr', 'DEU': 'de', 'GHA': 'gh', 'GRC': 'gr', 'HUN': 'hu', 'IND': 'in', 'IDN': 'id', 'IRQ': 'iq', 'IRL': 'ie', 'ISR': 'il', 'ITA': 'it', 'JPN': 'jp', 'JOR': 'jo', 'KEN': 'ke', 'KWT': 'kw', 'LBN': 'lb', 'LBY': 'ly', 'MWI': 'mw', 'MYS': 'my', 'MEX': 'mx', 'MAR': 'ma', 'MOZ': 'mz', 'NAM': 'na', 'NLD': 'nl', 'NZL': 'nz', 'NGA': 'ng', 'NOR': 'no', 'OMN': 'om', 'PAK': 'pk', 'PHL': 'ph', 'POL': 'pl', 'PRT': 'pt', 'QAT': 'qa', 'ROU': 'ro', 'RUS': 'ru', 'RWA': 'rw', 'SAU': 'sa', 'SEN': 'sn', 'SGP': 'sg', 'ZAF': 'za', 'KOR': 'kr', 'SSD': 'ss', 'ESP': 'es', 'LKA': 'lk', 'SDN': 'sd', 'SWE': 'se', 'CHE': 'ch', 'TZA': 'tz', 'THA': 'th', 'TUN': 'tn', 'TUR': 'tr', 'UGA': 'ug', 'UKR': 'ua', 'ARE': 'ae', 'GBR': 'gb', 'USA': 'us', 'VNM': 'vn', 'ZMB': 'zm', 'ZWE': 'zw'
            };

            let summary = {};
            try {
                // Fetch consolidated dashboard summary to overcome sequential SSH tunnel latency
                const url = force ? '/dashboard/summary?force=1' : '/dashboard/summary';
                const summaryRes = await api.get(url);
                summary = summaryRes.data?.data || {};

                // Map statuses and settings from summary
                if (summary.attendance_statuses) {
                    setAllStatuses({
                        attendance: summary.attendance_statuses.attendance || summary.attendance_statuses || [],
                        leave: summary.attendance_statuses.leave || []
                    });
                }
                
                if (summary.organization_settings) {
                    const companySetting = summary.organization_settings.find(s => s.setting_key === 'company_name');
                    if (companySetting?.setting_value) {
                        setCompanyName(companySetting.setting_value);
                    }
                }

                const empData = summary.employee_stats || {};
                const statusStats = empData.status_stats || [];
                const countryStats = empData.country_stats || [];
                const backendMilestones = empData.milestones || [];
                
                payrollData = summary.payroll_summary || {};
                appraisalData = summary.appraisal_stats || {};

                totalCount = statusStats.reduce((acc, s) => acc + parseInt(s.count), 0);
                activeCount = parseInt(statusStats.find(s => s.status === 'active')?.count || 0);
                onboardingCount = parseInt(statusStats.find(s => s.status === 'onboarding')?.count || 0);
                separationCount = parseInt(statusStats.find(s => s.status === 'offboarding')?.count || 0);
                otherCount = totalCount - activeCount;

                // Use attendance logs from consolidated summary
                logs = summary.today_attendance || [];
                
                // Initialize countries from backend country_stats
                countryStats.forEach(c => {
                    const countryName = c.country_name || 'Other';
                    countryStatsMap[countryName] = { 
                        id: c.country_id,
                        name: countryName, 
                        total: parseInt(c.count), 
                        present: 0, 
                        iso: c.country_iso ? (iso3to2[c.country_iso.toUpperCase()] || c.country_iso.toLowerCase()) : '',
                        statusCounts: {} 
                    };
                });

                // Map today's attendance into country counts
                logs.forEach(log => {
                    const countryName = log.country_name || 'Other';
                    const c = countryStatsMap[countryName];
                    if (c) {
                        const statusKey = log.status || 'not_updated';
                        c.statusCounts[statusKey] = (c.statusCounts[statusKey] || 0) + 1;

                        // Check if At Work
                        const sKey = statusKey.toLowerCase();
                        if (['present', 'on_site', 'remote', 'work_from_home', 'late'].includes(sKey)) {
                            c.present++;
                            presentCount++;
                        }
                    }
                });

                milestones = backendMilestones.map(m => {
                    let type = m.type;
                    let rawDateStr = type === 'birthday' ? m.dob : (type === 'holiday' ? (m.actual_date || m.date) : m.joining_date);
                    
                    const d = new Date(rawDateStr);
                    const today = new Date();
                    
                    let celeb = new Date(today.getFullYear(), d.getMonth(), d.getDate());
                    if (type === 'holiday') {
                         celeb = new Date(rawDateStr);
                    } else if (celeb < new Date(today.setHours(0,0,0,0))) {
                        celeb.setFullYear(celeb.getFullYear() + 1);
                    }
                    
                    let dayOfWeek = celeb.getDay();
                    let isHighlighted = false;
                    
                    if (dayOfWeek === 0) { // Sunday
                        celeb.setDate(celeb.getDate() - 2);
                        isHighlighted = true;
                    } else if (dayOfWeek === 6) { // Saturday
                        celeb.setDate(celeb.getDate() - 1);
                        isHighlighted = true;
                    }
                    
                    return {
                        ...m,
                        id: m.id || m.employee_id,
                        employee_id: m.id || m.employee_id,
                        name: m.name || `${m.first_name || ''} ${m.last_name || ''}`.trim(),
                        type: type,
                        photo: m.photo,
                        date: m.date,
                        celebDate: celeb,
                        isHighlighted: isHighlighted,
                        years: m.anniversary_years
                    };
                });
                milestones.sort((a, b) => a.celebDate - b.celebDate);

            } catch (e) { 
                console.warn('Dashboard API failed, falling back:', e);
            }

            const finalCountries = Object.values(countryStatsMap)
                .filter(c => c.total > 0)
                .map(c => {
                    const sortedSegments = Object.entries(c.statusCounts)
                        .filter(([key]) => key !== 'not_updated') // Don't show "not updated" as a colored segment
                        .map(([key, count]) => ({
                            key,
                            count,
                            perc: (count / c.total) * 100
                        })).sort((a, b) => {
                            const getPriority = (k) => {
                                if (k === 'present') return 1;
                                if (k === 'on_site') return 2;
                                if (k === 'remote') return 3;
                                if (k === 'work_from_home') return 4;
                                if (k === 'late') return 5;
                                if (k === 'absent') return 90;
                                if (k === 'weekend') return 91;
                                if (k === 'public_holiday') return 92;
                                return 50;
                            };
                            return getPriority(a.key) - getPriority(b.key);
                        });

                    return {
                        ...c,
                        perc: c.total > 0 ? Math.round((c.present / c.total) * 100) : 0,
                        segments: sortedSegments
                    };
                })
                .sort((a, b) => b.total - a.total);

            setStats({
                total: totalCount,
                active: activeCount,
                other: otherCount,
                staffOnSite: presentCount,
                attendancePerc: activeCount > 0 ? Math.round((presentCount / activeCount) * 100) : 0,
                payrollPerc: payrollData.integrity_perc || 0,
                appraisalsCount: appraisalData.appraisalsCount || 0,
                onboarding: onboardingCount,
                separation: separationCount,
                countries: finalCountries,
                milestones: milestones.slice(0, 5), // Top 5 upcoming
                pendingApprovals: summary.pending_approvals || []
            });
        } catch (error) {
            console.error('Critical failure in dashboard data loader:', error);
        } finally {
            setLoading(false);
        }
    };

    const getStatusColor = (statusKey) => {
        if (statusKey === 'not_updated') return 'transparent';
        
        const list = [...allStatuses.attendance, ...allStatuses.leave];
        // Match by string status_key or numeric ID
        const status = list.find(s => String(s.id) === String(statusKey) || s.status_key === statusKey);
        if (status?.color_code) return status.color_code;
        
        // System defaults fallback
        if (statusKey === 'present') return '#10b981';
        if (statusKey === 'absent') return '#ef4444';
        if (statusKey === 'late') return '#f59e0b';
        if (statusKey === 'work_from_home') return '#6366f1';
        if (statusKey === 'on_site') return '#06b6d4';
        
        return '#e2e8f0';
    };

    const val = (v) => loading ? '-' : v;

    return (
        <div className="dg-container" style={{ padding: '0.75rem 0' }}>
            <style>{`
                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .summary-card {
                    animation: slideUp 0.4s ease forwards;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .summary-card:hover {
                    transform: translateY(-2px);
                    box-shadow: var(--shadow-md);
                }
                .geo-row {
                    transition: background 0.2s;
                    border-radius: 8px;
                    padding: 6px 10px !important;
                }
                .geo-row:hover {
                    background: var(--color-ivory);
                }
                .country-link:hover {
                    color: var(--color-rose-gold);
                }
                .milestone-name:hover {
                    color: var(--color-rose-gold);
                }
                .dg-container {
                    font-family: var(--font-body);
                    line-height: 1.25;
                }
                .dashboard-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 0.75rem;
                }
                .header-left h1 {
                    font-family: var(--font-heading);
                    font-size: 28px;
                    margin: 0;
                    color: var(--color-charcoal);
                    letter-spacing: -0.01em;
                }
                .header-left p {
                    color: var(--color-text-muted);
                    margin: 4px 0 0 0;
                    font-size: 14px;
                    font-weight: 500;
                }
                .refresh-btn {
                    padding: 8px 16px;
                    border: 1px solid var(--color-border);
                    border-radius: var(--radius-md);
                    background: var(--color-white);
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    font-size: 13px;
                    font-weight: 600;
                    color: var(--color-charcoal);
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .refresh-btn:hover { 
                    background: var(--color-ivory); 
                    border-color: var(--color-rose-gold); 
                }

                .top-cards {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 0.75rem;
                    margin-bottom: 1rem;
                }
                .summary-card {
                    background: var(--color-white);
                    border-radius: var(--radius-lg);
                    padding: 1rem;
                    box-shadow: var(--shadow-sm);
                    border: 1px solid var(--color-border);
                    position: relative;
                    overflow: hidden;
                }
                .card-accent {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 4px;
                    height: 100%;
                }
                .blue-accent { background: #3b82f6; }
                .green-accent { background: var(--color-rose-gold); }
                .purple-accent { background: #8b5cf6; }
                .orange-accent { background: #f59e0b; }

                .card-head {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 0.25rem;
                }
                .card-label {
                    font-size: 11px;
                    font-weight: 700;
                    color: var(--color-text-muted);
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                }
                .card-icon-box {
                    width: 32px;
                    height: 32px;
                    background: var(--color-ivory);
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: var(--color-charcoal);
                }
                .card-value {
                    font-family: var(--font-heading);
                    font-size: 28px;
                    font-weight: 700;
                    color: var(--color-charcoal);
                    margin-bottom: 0.25rem;
                }
                .card-footer-badges {
                    display: flex;
                    gap: 8px;
                }
                .f-badge {
                    font-size: 11px;
                    font-weight: 600;
                    padding: 4px 10px;
                    border-radius: 6px;
                }
                .badge-bg-green { background: #ecfdf5; color: #059669; }
                .badge-bg-gray { background: #f3f4f6; color: #6b7280; }
                .badge-bg-orange { background: #fff7ed; color: #ea580c; }
                .badge-text-only { color: var(--color-text-muted); font-weight: 600; }

                .main-grid {
                    display: grid;
                    grid-template-columns: 1.8fr 1fr;
                    gap: 0.75rem;
                    margin-bottom: 0.75rem;
                }
                .sec-card {
                    background: var(--color-white);
                    border-radius: var(--radius-lg);
                    padding: 1rem;
                    box-shadow: var(--shadow-sm);
                    border: 1px solid var(--color-border);
                }
                .sec-title-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 0.75rem;
                }
                .sec-title {
                    font-family: var(--font-heading);
                    font-size: 17px;
                    font-weight: 700;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    color: var(--color-charcoal);
                }
                .detailed-logs-link {
                    font-size: 12px;
                    font-weight: 700;
                    color: var(--color-rose-gold);
                    display: flex;
                    align-items: center;
                    gap: 4px;
                    cursor: pointer;
                    transition: opacity 0.2s;
                }
                .detailed-logs-link:hover { opacity: 0.7; }

                /* Geographic Table */
                .geo-list { width: 100%; border-collapse: collapse; }
                .geo-row { border-bottom: none; display: flex; align-items: center; padding: 10px 0; margin-bottom: 4px; }
                .geo-country { flex: 1; display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 600; color: var(--color-charcoal); }
                .geo-flag-box { 
                    width: 24px; 
                    height: 18px; 
                    overflow: hidden; 
                    border-radius: 3px; 
                    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
                    background: var(--color-ivory);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .geo-stats-box { flex: 1.5; display: flex; align-items: center; gap: 16px; }
                .geo-perc { width: 42px; font-size: 13px; font-weight: 800; text-align: right; color: var(--color-charcoal); }
                .geo-bar-cont { flex: 1; height: 10px; background: var(--color-ivory); border-radius: 100px; overflow: hidden; display: flex; }
                .geo-segment { height: 100%; transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1); }
                .geo-count { width: 95px; font-size: 12px; font-weight: 600; color: var(--color-text-muted); text-align: right; }

                /* Team Culture / Requests */
                .culture-list { display: flex; flex-direction: column; gap: 0.5rem; }
                .culture-sub { font-size: 10px; font-weight: 800; color: var(--color-text-muted); letter-spacing: 0.12em; margin-bottom: 0.5rem; }
                .event-item { 
                    display: flex; 
                    justify-content: space-between; 
                    align-items: center; 
                    padding: 10px;
                    border-radius: var(--radius-md);
                    transition: background 0.2s;
                }
                .event-item:hover { background: var(--color-ivory); }
                .event-left { display: flex; align-items: center; gap: 12px; }
                .event-icon { 
                    width: 36px; 
                    height: 36px; 
                    border-radius: 10px; 
                    background: var(--color-white); 
                    border: 1px solid var(--color-border);
                    display: flex; 
                    align-items: center; 
                    justify-content: center; 
                    color: var(--color-text-muted); 
                }
                .event-info h4 { font-size: 14px; margin: 0; font-weight: 700; color: var(--color-charcoal); }
                .event-info p { font-size: 12px; color: var(--color-text-muted); margin: 2px 0 0 0; font-weight: 500; }
                .event-date { font-size: 13px; font-weight: 700; color: var(--color-rose-gold); }

                .empty-approval {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    padding: 40px 0;
                    color: var(--color-text-muted);
                    opacity: 0.5;
                }
                .empty-approval p { font-size: 14px; margin: 12px 0 0 0; font-weight: 600; }
                .process-btn {
                    padding: 8px 16px;
                    background: var(--color-charcoal);
                    color: var(--color-white);
                    border: none;
                    border-radius: var(--radius-md);
                    font-size: 12px;
                    font-weight: 700;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    cursor: pointer;
                    transition: opacity 0.2s;
                }
                .process-btn:hover { opacity: 0.9; }

                .pipeline-box { display: flex; flex-direction: column; gap: 1rem; }
                .pipe-label { font-size: 10px; font-weight: 800; color: var(--color-text-muted); letter-spacing: 0.1em; margin-bottom: 6px; display: block; }
                .pipe-val-row { display: flex; align-items: center; justify-content: space-between; }
                .pipe-num { font-family: var(--font-heading); font-size: 28px; font-weight: 800; color: var(--color-charcoal); }
                .pipe-sub { font-size: 12px; color: #3b82f6; font-weight: 700; margin-top: 4px; display: block; }
                .pipe-sub-red { color: var(--color-error); }

                @media (max-width: 1200px) {
                    .top-cards { grid-template-columns: repeat(2, 1fr); }
                    .main-grid { grid-template-columns: 1fr; }
                }
            `}</style>

            {/* Dashboard Actions */}
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '1rem' }}>
                <button className="refresh-btn" onClick={() => loadDashboardData(true)} disabled={loading}>
                    <RefreshCw size={14} className={loading ? 'animate-spin' : ''} />
                    <span>Refresh Analytics</span>
                </button>
            </div>

            <div className="top-cards">
                {/* Headcount */}
                <div className="summary-card" style={{ animationDelay: '0.1s' }}>
                    <div className="card-accent blue-accent"></div>
                    <div className="card-head">
                        <span className="card-label">Total Headcount</span>
                        <div className="card-icon-box"><Users size={18} /></div>
                    </div>
                    <div className="card-value">{val(stats.total)}</div>
                    <div className="card-footer-badges">
                        <span className="f-badge badge-bg-green" style={{ background: 'rgba(16, 185, 129, 0.1)' }}>{val(stats.active)} ACTIVE</span>
                        <span className="f-badge badge-bg-gray">{val(stats.other)} PENDING</span>
                    </div>
                </div>

                {/* Attendance */}
                <div className="summary-card" style={{ animationDelay: '0.2s' }}>
                    <div className="card-accent green-accent"></div>
                    <div className="card-head">
                        <span className="card-label">Operational Availability</span>
                        <div className="card-icon-box"><Activity size={18} /></div>
                    </div>
                    <div className="card-value">{val(stats.attendancePerc)}%</div>
                    <div className="card-footer-badges">
                        <span className="f-badge badge-text-only" style={{ color: 'var(--color-rose-gold)' }}>
                            {val(stats.staffOnSite)}/{val(stats.active)} Personnel Active
                        </span>
                    </div>
                </div>

                {/* Payroll */}
                <div className="summary-card" style={{ animationDelay: '0.3s' }}>
                    <div className="card-accent purple-accent"></div>
                    <div className="card-head">
                        <span className="card-label">Payroll Compliance</span>
                        <div className="card-icon-box"><TrendingUp size={18} /></div>
                    </div>
                    <div className="card-value">{val(stats.payrollPerc)}%</div>
                    <div className="card-footer-badges">
                        <span className="f-badge badge-text-only" style={{ color: '#8b5cf6' }}>
                            Validation Pending
                        </span>
                    </div>
                </div>

                {/* Reviews */}
                <div className="summary-card" style={{ animationDelay: '0.4s' }}>
                    <div className="card-accent orange-accent"></div>
                    <div className="card-head">
                        <span className="card-label">Performance Insights</span>
                        <div className="card-icon-box"><Award size={18} /></div>
                    </div>
                    <div className="card-value">{val(stats.appraisalsCount)}</div>
                    <div className="card-footer-badges">
                        <span className="f-badge badge-bg-orange" style={{ background: 'rgba(245, 158, 11, 0.1)' }}>ACTIVE AUDITS</span>
                    </div>
                </div>
            </div>

            <div className="main-grid">
                {/* Geographic Attendance Health */}
                <div className="sec-card">
                    <div className="sec-title-row">
                        <div className="sec-title">
                            <Globe size={20} style={{ color: 'var(--color-rose-gold)' }} />
                            Regional Distribution
                        </div>
                        <div className="detailed-logs-link" onClick={() => navigate('/attendance')}>
                            Detailed Analysis <ChevronRight size={14} />
                        </div>
                    </div>

                    <div className="geo-container">
                        {stats.countries.map((c, idx) => (
                            <div key={idx} className="geo-row">
                                <div className="geo-country">
                                    <div className="geo-flag-box">
                                        {c.iso ? (
                                            <img 
                                                src={`https://flagcdn.com/w20/${c.iso.toLowerCase()}.png`} 
                                                width="20" 
                                                alt={c.iso.toLowerCase()}
                                            />
                                        ) : '🏳️'}
                                    </div>
                                    <span 
                                        className="country-link" 
                                        style={{ cursor: 'pointer' }}
                                        onClick={() => navigate(`/attendance?country_id=${c.id}`)}
                                    >
                                        {c.name}
                                    </span>
                                </div>
                                <div className="geo-stats-box">
                                    <div className="geo-perc">{c.perc}%</div>
                                    <div className="geo-bar-cont">
                                        {(c.segments || []).map((seg, sIdx) => (
                                            <div 
                                                key={sIdx} 
                                                className="geo-segment" 
                                                style={{ 
                                                    width: `${seg.perc}%`,
                                                    backgroundColor: getStatusColor(seg.key) 
                                                }}
                                                title={`${seg.key}: ${seg.count}`}
                                            ></div>
                                        ))}
                                        {/* Fill remaining space only if there's actual unaccounted headcount */}
                                        {(() => {
                                            const accountedPerc = (c.segments || []).reduce((sum, seg) => sum + seg.perc, 0);
                                            if (accountedPerc < 99.5) { // Account for float rounding
                                                return (
                                                    <div 
                                                        className="geo-segment" 
                                                        style={{ 
                                                            width: `${100 - accountedPerc}%`,
                                                            backgroundColor: 'rgba(0,0,0,0.05)',
                                                            borderLeft: accountedPerc > 0 ? '1px solid white' : 'none'
                                                        }}
                                                    />
                                                );
                                            }
                                            return null;
                                        })()}
                                    </div>
                                    <div className="geo-count">{c.present}/{c.total} PRESENT</div>
                                </div>
                            </div>
                        ))}
                        {stats.countries.length === 0 && !loading && (
                            <p style={{ textAlign: 'center', color: 'var(--color-text-muted)', padding: '20px' }}>No geographic data available.</p>
                        )}
                    </div>
                </div>

                {/* Team Culture */}
                <div className="sec-card">
                    <div className="sec-title-row">
                        <div className="sec-title">
                            <Medal size={20} style={{ color: 'var(--color-text-muted)' }} />
                            Milestones & Anniversaries
                        </div>
                    </div>
                    <div className="culture-sub">CELEBRATIONS • NEXT 7 DAYS</div>
                    <div className="culture-list">
                        {stats.milestones.map((m, idx) => {
                            const getOrdinal = (n) => {
                                const s = ["th", "st", "nd", "rd"];
                                const v = n % 100;
                                return n + (s[(v - 20) % 10] || s[v] || s[0]);
                            };
                            const celebrationDate = m.celebDate;
                            const formattedDate = `${String(celebrationDate.getDate()).padStart(2, '0')}/${String(celebrationDate.getMonth() + 1).padStart(2, '0')}/${celebrationDate.getFullYear()}`;
                            
                            return (
                                <div key={idx} className="event-item" onClick={() => m.type !== 'holiday' && navigate(`/employee-profile?id=${m.id}`)} style={{ cursor: m.type !== 'holiday' ? 'pointer' : 'default', background: m.isHighlighted ? 'var(--color-ivory)' : '' }}>
                                    <div className="event-left">
                                        <div className="event-icon" style={{ overflow: 'hidden', background: m.photo ? 'transparent' : 'var(--color-ivory)' }}>
                                            {m.photo && !m.photoError ? (
                                                <img 
                                                    src={m.photo} 
                                                    alt="" 
                                                    style={{ width: '100%', height: '100%', objectFit: 'cover' }} 
                                                    onError={() => {
                                                        m.photoError = true;
                                                        // Trigger a re-render by updating local state if needed, 
                                                        // but for milestones we can just use a local property check
                                                        setStats(prev => ({
                                                            ...prev,
                                                            milestones: prev.milestones.map(mile => 
                                                                mile.employee_id === m.employee_id ? { ...mile, photoError: true } : mile
                                                            )
                                                        }));
                                                    }}
                                                />
                                            ) : (
                                                m.type === 'birthday' ? <Cake size={16} /> : (m.type === 'holiday' ? <Calendar size={16} /> : <Award size={16} />)
                                            )}
                                        </div>
                                        <div className="event-info">
                                        <h4 
                                            style={{ transition: 'color 0.2s', color: m.isHighlighted ? 'var(--color-rose-gold)' : '' }} 
                                            className="milestone-name"
                                        >
                                            {m.name}
                                        </h4>
                                            <p>
                                                {m.type === 'birthday' 
                                                    ? 'Employee Birthday' 
                                                    : m.type === 'holiday' ? 'Public Holiday'
                                                    : `${m.years ? getOrdinal(m.years) + ' ' : ''}Work Anniversary`}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="event-date" style={{ color: m.isHighlighted ? 'var(--color-error)' : 'var(--color-rose-gold)' }}>
                                        {formattedDate}
                                        {m.isHighlighted && <span style={{fontSize: '10px', display: 'block', color: 'var(--color-text-muted)', textAlign: 'right'}}>(Observed)</span>}
                                    </div>
                                </div>
                            );
                        })}
                        {stats.milestones.length === 0 && (
                            <div style={{ padding: '20px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: '13px' }}>
                                No upcoming milestones.
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <div className="main-grid" style={{ marginBottom: 0 }}>
                {/* Requests Awaiting Approval */}
                <div className="sec-card">
                    <div className="sec-title-row">
                        <div className="sec-title">
                            <Timer size={20} style={{ color: 'var(--color-text-muted)' }} />
                            Action Required
                        </div>
                        {stats.pendingApprovals?.length > 0 && (
                            <button className="process-btn" onClick={() => navigate('/action-required')}>
                                View All Pending <ChevronRight size={14} />
                            </button>
                        )}
                    </div>
                    
                    {!stats.pendingApprovals || stats.pendingApprovals.length === 0 ? (
                        <div className="empty-approval">
                            <CheckCircle size={48} style={{ color: 'var(--color-border)', opacity: 0.5 }} />
                            <p>No authorization requests pending.</p>
                        </div>
                    ) : (
                        <div className="approval-list" style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                            {stats.pendingApprovals.slice(0, 4).map((req, idx) => (
                                <div 
                                    key={`${req.type}-${req.id}-${idx}`} 
                                    className="approval-item clickable"
                                    onClick={() => navigate(req.link)}
                                >
                                    <div className={`approval-icon-box ${req.type}`}>
                                        {req.type === 'leave' && <Calendar size={18} />}
                                        {req.type === 'appraisal' && <Award size={18} />}
                                        {req.type === 'attendance' && <CheckCircle size={18} />}
                                    </div>
                                    <div className="approval-info">
                                        <div className="approval-main">
                                            <span className="approval-title">{req.title}</span>
                                            <span className="approval-date">
                                                {formatDate(req.date)}
                                                {req.end_date && ` - ${formatDate(req.end_date)}`}
                                            </span>
                                        </div>
                                        <div className="approval-sub">{req.subtitle}</div>
                                    </div>
                                    <ChevronRight size={16} className="approval-arrow" />
                                </div>
                            ))}
                            {stats.pendingApprovals.length > 4 && (
                                <div 
                                    className="approval-item clickable"
                                    style={{ justifyContent: 'center', background: 'var(--color-ivory)', fontWeight: 600, fontSize: '12px', color: 'var(--color-text-muted)', border: 'none', padding: '10px' }}
                                    onClick={() => navigate('/action-required')}
                                >
                                    + {stats.pendingApprovals.length - 4} more line items below
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Hiring Pipeline */}
                <div className="sec-card">
                    <div className="sec-title-row">
                        <div className="sec-title">
                            <UserPlus size={20} style={{ color: '#3b82f6' }} />
                            Recruitment Pipeline
                        </div>
                    </div>
                    <div className="pipeline-box">
                        <div 
                            className="pipe-item"
                            style={{ cursor: 'pointer' }}
                            onClick={() => navigate('/onboarding')}
                        >
                            <span className="pipe-label">TALENT ACQUISITION</span>
                            <div className="pipe-val-row">
                                <span className="pipe-num">{val(stats.onboarding)}</span>
                                <UserPlus size={24} style={{ color: '#60a5fa', opacity: 0.5 }} />
                            </div>
                            <span className="pipe-sub">PERSONNEL IN ONBOARDING</span>
                        </div>
                        <div 
                            className="pipe-item"
                            style={{ cursor: 'pointer' }}
                            onClick={() => navigate('/offboarding')}
                        >
                            <span className="pipe-label">TALENT RETENTION RISK</span>
                            <div className="pipe-val-row">
                                <span className="pipe-num">{val(stats.separation)}</span>
                                <UserMinus size={24} style={{ color: 'var(--color-error)', opacity: 0.5 }} />
                            </div>
                            <span className="pipe-sub pipe-sub-red">PERSONNEL IN SEPARATION</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Dashboard;
