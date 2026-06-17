import { memo, useState, useEffect } from 'react';
import { NavLink, useNavigate, useLocation } from 'react-router-dom';
import {
    LayoutDashboard,
    Users,
    Clock,
    Calendar,
    Wallet,
    BarChart,
    FileText,
    Settings,
    UserPlus,
    UserMinus,
    LogOut,
    ChevronRight,
    User,
    Package,
    Banknote
} from 'lucide-react';
import useAuthStore from '../../store/useAuthStore';
import api from '../../services/api';
import packageJson from '../../../package.json';

const Sidebar = ({ isOpen }) => {
    const navigate = useNavigate();
    const location = useLocation();
    const [companyName, setCompanyName] = useState('Avantgarde HRMS');
    const [viewMode, setViewMode] = useState(localStorage.getItem('adminViewMode') || 'admin');



    useEffect(() => {
        api.get('/organization/settings').then(res => {
            const list = res.data?.data || res.data || [];
            const companySetting = list.find(s => s.setting_key === 'company_name');
            if (companySetting?.setting_value) {
                setCompanyName(companySetting.setting_value);
            }
        }).catch(() => { });
    }, [location.pathname]);

    const [expandedGroups, setExpandedGroups] = useState({});
    const { logout, user } = useAuthStore();

    const toggleGroup = (title) => {
        setExpandedGroups(prev => ({ [title]: !prev[title] }));
    };

    const normalizedRole = user?.role || '';
    const isAdmin = normalizedRole && normalizedRole !== 'EMPLOYEE';

    const handleSignOut = async () => {
        await logout();
        navigate('/login');
    };

    const menuGroups = [
        {
            title: 'Self Service',
            items: [
                { name: 'My Portal', path: '/portal', icon: <LayoutDashboard size={18} /> },
                { name: 'My Profile', path: '/employee-profile', icon: <User size={18} /> },
                { name: 'Directory', path: '/employees', icon: <Users size={18} /> },
            ]
        },
        {
            title: 'Operations',
            items: [
                { name: 'Attendance', path: '/attendance', icon: <Clock size={18} />, module: 'attendance' },
                { name: 'Leave', path: '/leave', icon: <Calendar size={18} />, module: 'leave' },
                { name: 'Appraisals', path: '/appraisals', icon: <BarChart size={18} />, module: 'appraisals' },
            ]
        },
        {
            title: 'Administration',
            items: [
                { name: 'My Advances', path: '/salary-advances', icon: <Banknote size={18} /> },
                { name: 'My Payslips', path: '/payslips', icon: <FileText size={18} /> },
                { name: 'My Assets', path: '/assets', icon: <Package size={18} /> },
            ]
        },
        {
            title: 'Workforce',
            items: [
                { name: 'Employees', path: '/employees', icon: <Users size={18} />, module: 'employees' },
                { name: 'Onboarding', path: '/onboarding', icon: <UserPlus size={18} />, module: 'employees' },
                { name: 'Offboarding', path: '/offboarding', icon: <UserMinus size={18} />, module: 'offboarding' },
            ]
        },
        {
            title: 'Finance',
            items: [
                { name: 'Payroll', path: '/payroll', icon: <Wallet size={18} />, module: 'payroll' },
                { name: 'Salary Advances', path: '/salary-advances', icon: <Banknote size={18} />, module: 'payroll' },
                { name: 'PaySlip', path: '/payslips', icon: <FileText size={18} />, module: 'payroll' },
            ]
        },
        {
            title: 'System',
            items: [
                { name: 'Assets', path: '/assets', icon: <Package size={18} />, module: 'assets' },
                { name: 'Reports', path: '/reports', icon: <FileText size={18} />, module: 'reports' },
                { name: 'Configurations', path: '/admin', icon: <Settings size={18} />, module: 'configuration' },
            ]
        }
    ];

    // Auto-expand group containing current path
    useEffect(() => {
        const currentPath = location.pathname;
        const activeGroup = menuGroups.find(group => 
            group.items.some(item => item.path === currentPath)
        );
        if (activeGroup) {
            setExpandedGroups({ [activeGroup.title]: true });
        }
    }, [location.pathname]);

    return (
        <aside className={`sidebar ${isOpen ? 'mobile-open' : ''}`}>
            <div className="sidebar-header" style={{ marginBottom: '2rem' }}>
                <img
                    src="/api/logo"
                    alt="Avantgarde HRMS Logo"
                    style={{ 
                        width: '80%', 
                        maxHeight: 'none', 
                        marginBottom: '0.75rem', 
                        transition: 'transform 0.3s' 
                    }}
                    onMouseOver={(e) => e.currentTarget.style.transform = 'scale(1.02)'}
                    onMouseOut={(e) => e.currentTarget.style.transform = 'scale(1)'}
                    onError={(e) => { e.target.style.display = 'none'; }}
                />
                <div className="sidebar-subtitle" style={{ 
                    fontFamily: 'var(--font-heading)', 
                    color: 'rgba(255,255,255,0.4)',
                    fontSize: '0.65rem',
                    fontWeight: '700',
                    letterSpacing: '0.12em'
                }}>
                    {companyName.toUpperCase()}
                </div>
            </div>

            <nav className="sidebar-nav" style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                {isAdmin && viewMode !== 'employee' && (
                    <NavLink
                        to="/dashboard"
                        className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`}
                        style={{ marginBottom: '0.25rem' }}
                    >
                        {({ isActive }) => (
                            <>
                                <LayoutDashboard size={18} />
                                <span style={{ fontWeight: isActive ? '700' : '600', textTransform: 'uppercase', letterSpacing: '0.05em' }}>DASHBOARD</span>
                                {isActive && <div style={{ 
                                    marginLeft: 'auto', 
                                    width: '4px', 
                                    height: '4px', 
                                    borderRadius: '50%', 
                                    background: 'var(--color-rose-gold)',
                                    boxShadow: '0 0 8px var(--color-rose-gold)'
                                }} />}
                            </>
                        )}
                    </NavLink>
                )}

                {menuGroups.map((group) => {
                    const isEmployeeView = !isAdmin || (isAdmin && viewMode === 'employee');

                    // Filter entire groups for employees
                    const adminOnlyGroups = ['Workforce', 'Finance', 'System'];
                    if (isEmployeeView && adminOnlyGroups.includes(group.title)) {
                        return null;
                    }

                    // Hide Self Service and Administration for admin dashboard view
                    if (!isEmployeeView && (group.title === 'Self Service' || group.title === 'Administration')) {
                        return null;
                    }

                    // Filter items based on employee view
                    const filteredItems = group.items.filter(item => {
                        if (isEmployeeView && item.name === 'My Portal') return false;
                        if (isEmployeeView && item.name === 'Attendance') return false;
                        
                        // RBAC check for non-employees
                        if (!isEmployeeView && item.module) {
                            // The user requested Admin Page (Configurations) to be accessible to all system roles that have configuration access
                            if (item.name === 'Configurations') {
                                return useAuthStore.getState().hasPermission('configuration', 'view');
                            }
                            
                            const hasAccess = useAuthStore.getState().hasModuleAccess(item.module);
                            console.log(`Checking access for module ${item.module}:`, hasAccess, useAuthStore.getState().user?.permissions);
                            if (!hasAccess) return false;
                        }
                        
                        return true;
                    });

                    if (filteredItems.length === 0) return null;

                    // If it's employee view, just return the items directly without a collapsible group wrapper
                    // UNLESS it's the 'Administration' group, which we want to render as a collapsible menu
                    if (isEmployeeView && group.title !== 'Administration') {
                        return (
                            <div key={group.title} style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem', marginBottom: '0.25rem' }}>
                                {filteredItems.map((item) => (
                                    <NavLink
                                        key={item.name}
                                        to={item.path}
                                        className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`}
                                        style={{ position: 'relative' }}
                                    >
                                        {({ isActive }) => (
                                            <>
                                                {item.icon}
                                                <span style={{ fontWeight: isActive ? '700' : '600', textTransform: 'uppercase', letterSpacing: '0.05em' }}>{item.name}</span>
                                                {isActive && <div style={{ 
                                                    marginLeft: 'auto', 
                                                    width: '4px', 
                                                    height: '4px', 
                                                    borderRadius: '50%', 
                                                    background: 'var(--color-primary)',
                                                    boxShadow: '0 0 8px var(--color-primary)'
                                                }} />}
                                            </>
                                        )}
                                    </NavLink>
                                ))}
                            </div>
                        );
                    }

                    const isExpanded = expandedGroups[group.title];
                    return (
                        <div key={group.title} className="menu-group">
                            <button 
                                onClick={() => toggleGroup(group.title)}
                                className="menu-group-header" 
                                style={{
                                    width: '100%',
                                    background: 'none',
                                    border: 'none',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                    padding: '0.5rem 1rem',
                                    cursor: 'pointer',
                                    textAlign: 'left',
                                    transition: 'color 0.2s'
                                }}
                            >
                                <span style={{
                                    fontSize: '0.65rem',
                                    fontWeight: '800',
                                    textTransform: 'uppercase',
                                    letterSpacing: '0.1em',
                                    color: isExpanded ? 'rgba(255,255,255,0.6)' : 'rgba(255,255,255,0.3)',
                                    fontFamily: 'var(--font-heading)'
                                }}>
                                    {group.title}
                                </span>
                                <ChevronRight 
                                    size={12} 
                                    style={{ 
                                        color: 'rgba(255,255,255,0.3)',
                                        transform: isExpanded ? 'rotate(90deg)' : 'rotate(0deg)',
                                        transition: 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
                                    }} 
                                />
                            </button>
                            
                            <div style={{ 
                                display: 'grid',
                                gridTemplateRows: isExpanded ? '1fr' : '0fr',
                                transition: 'grid-template-rows 0.3s cubic-bezier(0.4, 0, 0.2, 1)',
                                overflow: 'hidden'
                            }}>
                                <div style={{ minHeight: 0 }}>
                                    <div style={{ 
                                        display: 'flex', 
                                        flexDirection: 'column', 
                                        gap: '4px',
                                        padding: '4px 0 8px 0'
                                    }}>
                                        {filteredItems.map((item) => (
                                            <NavLink
                                                key={item.name}
                                                to={item.path}
                                                className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`}
                                                style={{ position: 'relative' }}
                                            >
                                                {({ isActive }) => (
                                                    <>
                                                        {item.icon}
                                                        <span style={{ fontWeight: isActive ? '600' : '400' }}>{item.name}</span>
                                                        {isActive && <div style={{ 
                                                            marginLeft: 'auto', 
                                                            width: '4px', 
                                                            height: '4px', 
                                                            borderRadius: '50%', 
                                                            background: 'var(--color-rose-gold)',
                                                            boxShadow: '0 0 8px var(--color-rose-gold)'
                                                        }} />}
                                                    </>
                                                )}
                                            </NavLink>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </nav>

            <div style={{ 
                padding: '1.25rem 0.5rem', 
                borderTop: '1px solid rgba(255,255,255,0.08)', 
                marginTop: 'auto',
                display: 'flex',
                flexDirection: 'column',
                gap: '1rem'
            }}>
                {isAdmin && (
                    <NavLink
                        to={viewMode === 'employee' ? '/dashboard' : '/employee-profile'}
                        onClick={() => {
                            const newMode = viewMode === 'employee' ? 'admin' : 'employee';
                            setViewMode(newMode);
                            localStorage.setItem('adminViewMode', newMode);
                        }}
                        className="nav-item"
                        style={{
                            width: '100%',
                            border: 'none',
                            background: 'rgba(255,255,255,0.03)',
                            cursor: 'pointer',
                            color: 'rgba(255,255,255,0.7)',
                            fontSize: '0.875rem',
                            transition: 'all 0.2s',
                            borderRadius: 'var(--radius-md)',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '12px',
                            padding: '0.75rem 1rem'
                        }}
                        onMouseOver={(e) => e.currentTarget.style.background = 'rgba(255,255,255,0.08)'}
                        onMouseOut={(e) => {
                            if (!e.currentTarget.classList.contains('active')) {
                                e.currentTarget.style.background = 'rgba(255,255,255,0.03)';
                            }
                        }}
                    >
                        {viewMode === 'employee' ? (
                            <>
                                <LayoutDashboard size={18} />
                                <span>Admin Dashboard</span>
                            </>
                        ) : (
                            <>
                                <User size={18} />
                                <span>Employee Profile</span>
                            </>
                        )}
                    </NavLink>
                )}
                <div style={{ 
                    fontSize: '0.65rem', 
                    opacity: 0.3, 
                    textAlign: 'center', 
                    letterSpacing: '0.05em',
                    fontWeight: '500'
                }}>
                    <div>&copy; 2026 {companyName} SYSTEM</div>
                    <div style={{ marginTop: '0.25rem', fontSize: '0.55rem' }}>Version v{packageJson.version}</div>
                </div>
            </div>
        </aside>
    );
};

export default memo(Sidebar);

