import { memo } from 'react';
import { LogOut, User, Menu, ChevronLeft } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import useAuthStore from '../../store/useAuthStore';
import useLayoutStore from '../../store/useLayoutStore';
import NotificationBell from '../notifications/NotificationBell';

const Header = ({ onMenuClick }) => {
    const navigate = useNavigate();
    const { logout, user } = useAuthStore();
    const { pageTitle, pageSubtitle, backPath } = useLayoutStore();

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    return (
        <header className="top-header" style={{
            height: '72px',
            backgroundColor: 'var(--color-white)',
            borderBottom: '1px solid var(--color-border)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            padding: '0 2rem',
            boxShadow: '0 1px 2px rgba(0,0,0,0.03)',
            position: 'sticky',
            top: 0,
            zIndex: 100
        }}>
            <div className="header-left" style={{ display: 'flex', alignItems: 'center', gap: '1.5rem' }}>
                <button 
                    className="mobile-menu-btn" 
                    onClick={onMenuClick}
                    style={{
                        display: 'none',
                        padding: '8px',
                        background: 'rgba(40, 107, 62, 0.05)',
                        border: '1px solid rgba(40, 107, 62, 0.1)',
                        borderRadius: '8px',
                        color: 'var(--color-rose-gold)',
                        cursor: 'pointer'
                    }}
                >
                    <Menu size={24} />
                </button>

                {pageTitle && (
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            {backPath && (
                                <button 
                                    onClick={() => navigate(backPath)}
                                    style={{ 
                                        display: 'flex', alignItems: 'center', background: 'none', border: 'none', 
                                        padding: 0, cursor: 'pointer', color: 'var(--color-text-muted)'
                                    }}
                                >
                                    <ChevronLeft size={20} />
                                </button>
                            )}
                            <h1 style={{ 
                                margin: 0, 
                                fontSize: '1.25rem', 
                                fontWeight: '700', 
                                color: 'var(--color-charcoal)',
                                fontFamily: 'var(--font-heading)'
                            }}>{pageTitle}</h1>
                        </div>
                        {pageSubtitle && (
                            <p style={{ 
                                margin: '2px 0 0 0', 
                                fontSize: '0.75rem', 
                                color: 'var(--color-text-muted)',
                                fontWeight: '500',
                                marginLeft: backPath ? '28px' : '0'
                            }}>{pageSubtitle}</p>
                        )}
                    </div>
                )}
            </div>

            <div className="header-right" style={{ display: 'flex', alignItems: 'center', gap: '1.25rem' }}>

                <div className="user-profile-v2" onClick={() => navigate('/employee-profile')} style={{ 
                    display: 'flex', 
                    alignItems: 'center', 
                    gap: '12px', 
                    cursor: 'pointer',
                    padding: '6px 12px 6px 6px',
                    borderRadius: '16px',
                    backgroundColor: 'var(--color-white)',
                    border: '1px solid var(--color-border)',
                    boxShadow: 'var(--shadow-sm)',
                    transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
                }} onMouseOver={(e) => {
                    e.currentTarget.style.borderColor = 'var(--color-rose-gold)';
                    e.currentTarget.style.boxShadow = '0 4px 12px rgba(40, 107, 62, 0.08)';
                }} onMouseOut={(e) => {
                    e.currentTarget.style.borderColor = 'var(--color-border)';
                    e.currentTarget.style.boxShadow = 'var(--shadow-sm)';
                }}>
                    <div style={{
                        width: '36px',
                        height: '36px',
                        borderRadius: '12px',
                        backgroundColor: 'var(--color-charcoal)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        color: 'var(--color-white)',
                        overflow: 'hidden',
                        position: 'relative'
                    }}>
                        <User size={18} />
                        <div style={{
                            position: 'absolute',
                            bottom: 0,
                            right: 0,
                            width: '10px',
                            height: '10px',
                            backgroundColor: 'var(--color-success)',
                            border: '2px solid var(--color-white)',
                            borderRadius: '50%'
                        }} />
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column' }}>
                        <div style={{ 
                            fontSize: '0.875rem', 
                            fontWeight: '700', 
                            color: 'var(--color-charcoal)',
                            fontFamily: 'var(--font-heading)',
                            lineHeight: 1.2
                        }}>
                            {user?.first_name} {user?.last_name}
                        </div>
                        <div style={{ fontSize: '0.7rem', color: 'var(--color-text-muted)', fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.025em' }}>
                            {user?.designation || user?.role || 'HR Administrator'}
                        </div>
                    </div>
                </div>
                
                <div style={{
                    padding: '4px',
                    borderRadius: '50%',
                    backgroundColor: 'rgba(40, 107, 62, 0.05)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    border: '1px solid rgba(40, 107, 62, 0.1)',
                    transition: 'all 0.2s'
                }}>
                    <NotificationBell />
                </div>

                <div style={{ width: '1px', height: '20px', backgroundColor: 'var(--color-border)' }}></div>

                <button
                    onClick={handleLogout}
                    style={{
                        padding: '0 12px',
                        height: '36px',
                        background: 'rgba(239, 68, 68, 0.05)',
                        border: '1px solid rgba(239, 68, 68, 0.1)',
                        color: 'var(--color-error)',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        gap: '8px',
                        borderRadius: '12px',
                        transition: 'all 0.2s',
                        fontFamily: 'var(--font-heading)',
                        fontSize: '0.875rem',
                        fontWeight: '600'
                    }}
                    onMouseOver={(e) => { 
                        e.currentTarget.style.backgroundColor = 'var(--color-error)'; 
                        e.currentTarget.style.color = 'white';
                        e.currentTarget.style.transform = 'scale(1.02)';
                    }}
                    onMouseOut={(e) => { 
                        e.currentTarget.style.backgroundColor = 'rgba(239, 68, 68, 0.05)'; 
                        e.currentTarget.style.color = 'var(--color-error)';
                        e.currentTarget.style.transform = 'scale(1)';
                    }}
                    title="Sign Out"
                >
                    <LogOut size={16} />
                    <span>Sign Out</span>
                </button>
            </div>
        </header>
    );
};

// Add CSS for the mobile-only button
const style = document.createElement('style');
style.textContent = `
  @media (max-width: 1024px) {
    .mobile-menu-btn {
      display: flex !important;
    }
  }
`;
document.head.appendChild(style);

export default memo(Header);

