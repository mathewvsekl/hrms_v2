import { useNavigate } from 'react-router-dom';
import { Home, ArrowLeft, Search } from 'lucide-react';

const NotFound = () => {
    const navigate = useNavigate();

    return (
        <div style={{
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            minHeight: '400px',
            textAlign: 'center',
            padding: '40px',
            fontFamily: 'Inter, system-ui, sans-serif'
        }}>
            <div style={{
                width: '80px',
                height: '80px',
                borderRadius: '50%',
                background: '#f1f5f9',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: '#64748b',
                marginBottom: '24px'
            }}>
                <Search size={40} />
            </div>
            
            <h1 style={{ fontSize: '32px', fontWeight: '700', color: '#1e293b', marginBottom: '12px' }}>Page Not Found</h1>
            <p style={{ color: '#64748b', fontSize: '16px', maxWidth: '400px', marginBottom: '32px' }}>
                The page you are looking for doesn't exist or has been moved.
            </p>

            <div style={{ display: 'flex', gap: '16px' }}>
                <button 
                    onClick={() => navigate(-1)}
                    style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        gap: '8px',
                        padding: '10px 20px',
                        borderRadius: '6px',
                        border: '1px solid #e2e8f0',
                        background: 'white',
                        cursor: 'pointer',
                        fontWeight: '600'
                    }}
                >
                    <ArrowLeft size={18} /> Go Back
                </button>
                <button 
                    onClick={() => navigate('/dashboard')}
                    style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        gap: '8px',
                        padding: '10px 20px',
                        borderRadius: '6px',
                        border: 'none',
                        background: '#2563eb',
                        color: 'white',
                        cursor: 'pointer',
                        fontWeight: '600'
                    }}
                >
                    <Home size={18} /> Return Home
                </button>
            </div>
        </div>
    );
};

export default NotFound;
