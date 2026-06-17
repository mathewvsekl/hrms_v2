import { UserMinus } from 'lucide-react';

const Offboarding = () => {
    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
                <div>
                    <h1 className="page-title">Offboarding</h1>
                    <p style={{ color: 'var(--text-secondary)', marginTop: '4px' }}>Manage employee separation and exit processes</p>
                </div>
            </div>

            <div className="card" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-secondary)' }}>
                <UserMinus size={48} style={{ margin: '0 auto 16px', color: 'var(--border-gray)' }} />
                <h3>Offboarding module</h3>
                <p>Track clearance checklists, final settlements, and exit interviews.</p>
            </div>
        </div>
    );
};

export default Offboarding;
