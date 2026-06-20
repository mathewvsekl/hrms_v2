import { useState, useEffect } from 'react';
import { Search, Plus, Filter, Globe } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useLayoutStore from '../store/useLayoutStore';
import { getSecureMediaUrl } from '../utils/mediaHelper';
import { ROLE_IDS } from '../utils/roleConstants';

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

const Employees = () => {
    const [employees, setEmployees] = useState([]);
    const [countries, setCountries] = useState([]);
    const [activeTab, setActiveTab] = useState('global');
    const [searchTerm, setSearchTerm] = useState('');
    const [loading, setLoading] = useState(true);
    const navigate = useNavigate();
    const { user } = useAuthStore();
    const isSuperAdmin = user?.role_id === ROLE_IDS.SUPER_ADMIN || user?.role_id === ROLE_IDS.ADMIN;
    const isGlobalAdmin = isSuperAdmin || useAuthStore.getState().hasPermission('employees', 'view');
    const isEmployeeView = localStorage.getItem('adminViewMode') === 'employee';
    const isAdmin = isGlobalAdmin && !isEmployeeView;
    const canCreate = isSuperAdmin || useAuthStore.getState().hasPermission('employees', 'create');
    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        setPageTitle(isAdmin ? 'Employees' : 'Company Directory');
        setPageSubtitle(isAdmin ? 'Manage directory and onboarding' : 'Find and connect with colleagues');
        return () => resetPageHeader();
    }, [isAdmin]);

    useEffect(() => {
        if (isAdmin) {
            fetchCountries();
        }
    }, [isAdmin]);

    useEffect(() => {
        fetchEmployees();
    }, [activeTab]);

    const fetchCountries = async () => {
        try {
            const res = await api.get('/attendance/countries');
            const data = res.data?.data || res.data;
            setCountries(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Failed to fetch countries', error);
        }
    };

    const fetchEmployees = async () => {
        try {
            setLoading(true);
            const url = `/employees${activeTab !== 'global' ? `?country_id=${activeTab}` : ''}`;
            const res = await api.get(url);
            const data = res.data?.data || res.data;
            setEmployees(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Failed to fetch employees', error);
        } finally {
            setLoading(false);
        }
    };

    const filteredEmployees = employees.filter(emp => {
        const searchLower = searchTerm.toLowerCase();
        return (
            emp.first_name?.toLowerCase().includes(searchLower) ||
            emp.last_name?.toLowerCase().includes(searchLower) ||
            emp.email?.toLowerCase().includes(searchLower) ||
            emp.employee_code?.toLowerCase().includes(searchLower) ||
            emp.designation?.toLowerCase().includes(searchLower) ||
            emp.department_name?.toLowerCase().includes(searchLower)
        );
    });

    return (
        <div>
            {/* Actions Bar */}
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '24px' }}>
                {canCreate && (
                    <button className="btn btn-primary" onClick={() => navigate('/onboarding')}>
                        <Plus size={18} /> Add Employee
                    </button>
                )}
            </div>

            {isAdmin && (
                <div style={{ display: 'flex', gap: '12px', marginBottom: '32px', overflowX: 'auto', paddingBottom: '8px' }}>
                    <button 
                        onClick={() => setActiveTab('global')}
                        style={{
                            padding: '8px 20px',
                            borderRadius: '20px',
                            border: 'none',
                            background: activeTab === 'global' ? '#065f46' : 'white',
                            color: activeTab === 'global' ? 'white' : 'var(--text-main)',
                            fontWeight: '600',
                            fontSize: '14px',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            boxShadow: activeTab === 'global' ? 'none' : '0 1px 3px rgba(0,0,0,0.1)',
                            transition: 'all 0.2s'
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
                                background: activeTab === country.id ? '#065f46' : 'white',
                                color: activeTab === country.id ? 'white' : 'var(--text-main)',
                                fontWeight: '600',
                                fontSize: '14px',
                                cursor: 'pointer',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '8px',
                                whiteSpace: 'nowrap',
                                boxShadow: activeTab === country.id ? 'none' : '0 1px 3px rgba(0,0,0,0.1)',
                                transition: 'all 0.2s'
                            }}
                        >
                            {renderFlag(country)}
                            <span>{country.name}</span>
                        </button>
                    ))}
                </div>
            )}

            <div className="card" style={{ padding: '0', overflow: 'hidden' }}>
                <div style={{
                    padding: '20px',
                    borderBottom: '1px solid var(--border-gray)',
                    display: 'flex',
                    gap: '16px'
                }}>
                    <div style={{ flex: 1, position: 'relative' }}>
                        <Search size={18} style={{ position: 'absolute', left: '12px', top: '11px', color: 'var(--text-secondary)' }} />
                        <input
                            type="text"
                            className="form-input"
                            placeholder="Search by name, email, or ID..."
                            style={{ paddingLeft: '40px' }}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                    <button className="btn btn-secondary">
                        <Filter size={18} /> Filter
                    </button>
                </div>

                <div style={!isAdmin ? { padding: '24px' } : { overflowX: 'auto' }}>
                    {!isAdmin ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '48px' }}>
                            {loading ? (
                                <div style={{ textAlign: 'center', padding: '60px' }}>
                                    <div className="loader-content">
                                        <div className="loader-spinner"></div>
                                        <div className="loader-text">SCANNING DIRECTORY...</div>
                                    </div>
                                </div>
                            ) : filteredEmployees.length === 0 ? (
                                <div style={{ textAlign: 'center', padding: '40px', color: 'var(--text-secondary)' }}>
                                    {searchTerm ? `No colleagues found matching "${searchTerm}"` : 'No colleagues found.'}
                                </div>
                            ) : (
                                Object.entries(
                                    filteredEmployees.reduce((acc, emp) => {
                                        const country = emp.primary_country || 'Global / Other';
                                        if (!acc[country]) acc[country] = [];
                                        acc[country].push(emp);
                                        return acc;
                                    }, {})
                                )
                                .sort(([a], [b]) => a.localeCompare(b))
                                .map(([country, items]) => (
                                    <div key={country}>
                                        <div style={{ 
                                            display: 'flex', 
                                            alignItems: 'center', 
                                            gap: '16px', 
                                            marginBottom: '24px',
                                            padding: '0 8px'
                                        }}>
                                            <h2 style={{ 
                                                margin: 0, 
                                                fontSize: '1.25rem', 
                                                fontWeight: '700', 
                                                color: 'var(--primary-brand)',
                                                textTransform: 'uppercase',
                                                letterSpacing: '0.05em'
                                            }}>{country}</h2>
                                            <div style={{ flex: 1, height: '1px', background: 'linear-gradient(to right, var(--border-gray), transparent)' }}></div>
                                            <span style={{ fontSize: '13px', color: 'var(--text-secondary)', fontWeight: '600' }}>
                                                {items.length} {items.length === 1 ? 'colleague' : 'colleagues'}
                                            </span>
                                        </div>
                                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '24px' }}>
                                            {items.map((emp) => (
                                                <div 
                                                    key={emp.id}
                                                    onClick={() => navigate(`/employee-profile?id=${emp.id}`)}
                                                    style={{
                                                        background: 'var(--bg-white)',
                                                        border: '1px solid var(--border-gray)',
                                                        borderRadius: '16px',
                                                        padding: '24px',
                                                        display: 'flex',
                                                        flexDirection: 'column',
                                                        alignItems: 'center',
                                                        textAlign: 'center',
                                                        cursor: 'pointer',
                                                        transition: 'all 0.2s',
                                                    }}
                                                    onMouseOver={(e) => {
                                                        e.currentTarget.style.transform = 'translateY(-4px)';
                                                        e.currentTarget.style.boxShadow = '0 12px 24px rgba(0,0,0,0.06)';
                                                        e.currentTarget.style.borderColor = 'var(--primary-brand)';
                                                    }}
                                                    onMouseOut={(e) => {
                                                        e.currentTarget.style.transform = 'translateY(0)';
                                                        e.currentTarget.style.boxShadow = 'none';
                                                        e.currentTarget.style.borderColor = 'var(--border-gray)';
                                                    }}
                                                >
                                                    <div style={{
                                                        width: '88px',
                                                        height: '88px',
                                                        borderRadius: '50%',
                                                        background: emp.profile_image_path ? '#f8fafc' : 'linear-gradient(135deg, var(--primary-brand), var(--primary-brand-dark, #0f172a))',
                                                        color: 'white',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        fontSize: '28px',
                                                        fontWeight: '700',
                                                        marginBottom: '16px',
                                                        boxShadow: '0 8px 16px rgba(15, 23, 42, 0.15)',
                                                        border: '3px solid white',
                                                        outline: '1px solid var(--border-gray)',
                                                        overflow: 'hidden'
                                                    }}>
                                                        {emp.profile_image_path ? (
                                                            <img src={getSecureMediaUrl(emp.profile_image_path)} alt={emp.first_name} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                                                        ) : (
                                                            <>{emp.first_name?.[0]?.toUpperCase()}{emp.last_name?.[0]?.toUpperCase()}</>
                                                        )}
                                                    </div>
                                                    <h3 style={{ margin: '0 0 4px 0', fontSize: '1.15rem', color: 'var(--text-primary)', fontWeight: '700' }}>
                                                        {emp.first_name} {emp.last_name}
                                                    </h3>
                                                    <div style={{ color: 'var(--primary-brand)', fontWeight: '600', fontSize: '0.85rem', marginBottom: '4px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                                                        {emp.designation || 'Staff'}
                                                    </div>
                                                    <div style={{ color: 'var(--text-secondary)', fontSize: '0.85rem', marginBottom: '20px' }}>
                                                        {emp.department_name || 'Organization'}
                                                    </div>
                                                    
                                                    <div style={{ 
                                                        marginTop: 'auto', 
                                                        width: '100%', 
                                                        borderTop: '1px solid var(--border-gray)', 
                                                        paddingTop: '16px', 
                                                        display: 'flex', 
                                                        justifyContent: 'center', 
                                                        gap: '24px' 
                                                    }}>
                                                        <a 
                                                            href={`mailto:${emp.email}`} 
                                                            onClick={(e) => e.stopPropagation()}
                                                            style={{ 
                                                                color: 'var(--text-secondary)', 
                                                                textDecoration: 'none', 
                                                                display: 'flex', 
                                                                alignItems: 'center', 
                                                                gap: '6px', 
                                                                fontSize: '0.85rem',
                                                                transition: 'color 0.2s',
                                                                fontWeight: '500'
                                                            }} 
                                                            onMouseOver={e=>e.currentTarget.style.color = 'var(--primary-brand)'} 
                                                            onMouseOut={e=>e.currentTarget.style.color = 'var(--text-secondary)'}
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                                            Message
                                                        </a>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    ) : (
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Staff ID</th>
                                    <th>Designation</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    {isAdmin && <th>Actions</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {loading ? (
                                    <tr>
                                        <td colSpan="6" style={{ textAlign: 'center', padding: '60px' }}>
                                            <div className="loader-content">
                                                <div className="loader-spinner"></div>
                                                <div className="loader-text">LOADING EMPLOYEES...</div>
                                            </div>
                                        </td>
                                    </tr>
                                ) : filteredEmployees.length === 0 ? (
                                    <tr>
                                        <td colSpan="6" style={{ textAlign: 'center', padding: '40px', color: 'var(--text-secondary)' }}>
                                            {searchTerm ? `No employees found matching "${searchTerm}"` : 'No employees found.'}
                                        </td>
                                    </tr>
                                ) : (
                                    filteredEmployees.map((emp) => (
                                        <tr key={emp.id} onClick={() => navigate(`/employee-profile?id=${emp.id}`)} style={{ cursor: 'pointer' }}>
                                            <td>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                    <div style={{
                                                        width: '40px',
                                                        height: '40px',
                                                        borderRadius: '50%',
                                                        background: emp.profile_image_path ? '#f8fafc' : 'var(--primary-brand)',
                                                        color: 'white',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        fontSize: '14px',
                                                        fontWeight: '600',
                                                        overflow: 'hidden',
                                                        border: '2px solid white',
                                                        boxShadow: '0 2px 4px rgba(0,0,0,0.05)'
                                                    }}>
                                                        {emp.profile_image_path ? (
                                                            <img src={getSecureMediaUrl(emp.profile_image_path)} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                                                        ) : (
                                                            <>{emp.first_name?.[0]?.toUpperCase()}{emp.last_name?.[0]?.toUpperCase()}</>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <div style={{ fontWeight: '500' }}>{emp.first_name} {emp.last_name}</div>
                                                        <div style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>{emp.email}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>{emp.employee_code || 'N/A'}</td>
                                            <td>{emp.designation || 'N/A'}</td>
                                            <td>{emp.department_name || 'N/A'}</td>
                                            <td>
                                                <span style={{
                                                    padding: '4px 8px',
                                                    borderRadius: '12px',
                                                    fontSize: '12px',
                                                    fontWeight: '500',
                                                    backgroundColor: emp.status === 'active' ? 'var(--primary-brand)' : 'var(--bg-light-gray)',
                                                    color: emp.status === 'active' ? 'var(--bg-white)' : 'var(--text-secondary)',
                                                    textTransform: 'capitalize'
                                                }}>
                                                    {emp.status || 'active'}
                                                </span>
                                            </td>
                                            {isAdmin && (
                                                <td>
                                                    <button
                                                        className="btn btn-secondary"
                                                        style={{ padding: '6px 12px', fontSize: '12px' }}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            navigate(`/employee-profile?id=${emp.id}`);
                                                        }}
                                                    >View</button>
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </div>
    );
};

export default Employees;
