import { useState, useEffect } from 'react';
import { Package, Archive } from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useLayoutStore from '../store/useLayoutStore';
import { formatDate } from '../utils/dateUtils';

const EmployeeAssets = () => {
    const { user } = useAuthStore();
    const [assets, setAssets] = useState([]);
    const [loading, setLoading] = useState(true);
    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        setPageTitle("My Allocated Assets");
        setPageSubtitle("View company assets allocated to you");
        return () => resetPageHeader();
    }, []);

    useEffect(() => {
        if (user?.employee_id || user?.id) {
            fetchAssets();
        }
    }, [user]);

    const fetchAssets = async () => {
        setLoading(true);
        try {
            const empId = user?.employee_id || user?.id;
            const res = await api.get(`/assets/employee/${empId}`);
            if (res.data?.data || res.data) {
                setAssets(res.data.data || res.data);
            }
        } catch (error) {
            console.error("Failed to fetch assets", error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div style={{ animation: 'fadeIn 0.4s ease-out' }}>
            <div style={{ background: '#fff', borderRadius: '16px', boxShadow: '0 4px 20px rgba(0,0,0,0.03)', overflow: 'hidden' }}>
                <div style={{ padding: '20px 24px', borderBottom: '1px solid #f1f5f9' }}>
                    <h3 style={{ margin: 0, fontSize: '16px', fontWeight: '600', color: '#0f172a' }}>Allocated Assets</h3>
                </div>
                
                <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                        <thead>
                            <tr style={{ background: '#f8fafc', borderBottom: '1px solid #e2e8f0' }}>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Asset Name / Company</th>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Serial / Model</th>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Allocated On</th>
                                <th style={{ padding: '16px 24px', fontSize: '13px', fontWeight: '600', color: '#64748b' }}>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan="4" style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>
                                        Loading assets...
                                    </td>
                                </tr>
                            ) : assets.length === 0 ? (
                                <tr>
                                    <td colSpan="4" style={{ padding: '40px', textAlign: 'center', color: '#64748b' }}>
                                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '12px' }}>
                                            <div style={{ width: '48px', height: '48px', background: '#f1f5f9', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                                <Archive size={24} color="#94a3b8" />
                                            </div>
                                            <div>No company assets are currently allocated to you.</div>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                assets.map((asset, idx) => (
                                    <tr key={idx} style={{ borderBottom: '1px solid #f1f5f9' }}>
                                        <td style={{ padding: '16px 24px' }}>
                                            <div style={{ fontWeight: '600', color: '#0f172a', fontSize: '14px' }}>{asset.asset_name}</div>
                                            <div style={{ fontSize: '12px', color: '#64748b', marginTop: '4px' }}>{asset.company_name}</div>
                                        </td>
                                        <td style={{ padding: '16px 24px' }}>
                                            <div style={{ color: '#475569', fontSize: '14px' }}>{asset.serial_number || 'N/A'}</div>
                                            <div style={{ fontSize: '12px', color: '#64748b', marginTop: '4px' }}>{asset.model_number || 'Model -'}</div>
                                        </td>
                                        <td style={{ padding: '16px 24px', color: '#475569', fontSize: '14px' }}>
                                            {formatDate(asset.allocation_date)}
                                        </td>
                                        <td style={{ padding: '16px 24px' }}>
                                            <span style={{ 
                                                fontSize: '11px', 
                                                fontWeight: 700, 
                                                textTransform: 'uppercase',
                                                padding: '4px 10px',
                                                borderRadius: '20px',
                                                background: asset.status === 'active' ? '#ecfdf5' : '#f3f4f6',
                                                color: asset.status === 'active' ? '#10b981' : '#6b7280',
                                                display: 'inline-block'
                                            }}>
                                                {asset.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default EmployeeAssets;
