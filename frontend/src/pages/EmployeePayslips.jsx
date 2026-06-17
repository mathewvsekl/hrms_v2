import { getSecureMediaUrl } from '../utils/mediaHelper';
import React, { useState, useEffect } from 'react';
import { FileText, Eye, Download, CreditCard, X } from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import { formatDate } from '../utils/dateUtils';
import useLayoutStore from '../store/useLayoutStore';

const EmployeePayslips = () => {
    const user = useAuthStore(state => state.user);
    const [payslips, setPayslips] = useState([]);
    const [loading, setLoading] = useState(true);
    const [previewDoc, setPreviewDoc] = useState(null);
    const { setPageTitle, setPageSubtitle } = useLayoutStore();

    useEffect(() => {
        setPageTitle('My Payslips');
        setPageSubtitle('View and download your monthly payslips');
        fetchPayslips();
    }, []);

    const fetchPayslips = async () => {
        try {
            setLoading(true);
            const employeeId = user?.employee_id || user?.id;
            const res = await api.get(`/payslips?employee_id=${employeeId}`);
            setPayslips(res.data?.data || res.data || []);
        } catch (error) {
            console.error('Failed to fetch payslips', error);
        } finally {
            setLoading(false);
        }
    };

    // Group payslips by year
    const payslipsByYear = payslips.reduce((acc, ps) => {
        const year = ps.year;
        if (!acc[year]) acc[year] = [];
        acc[year].push(ps);
        return acc;
    }, {});

    const sortedYears = Object.keys(payslipsByYear).sort((a, b) => b - a);

    return (
        <div style={{ animation: 'fadeIn 0.4s ease-out' }}>
            <div style={{ background: '#fff', borderRadius: '16px', boxShadow: '0 4px 20px rgba(0,0,0,0.03)', overflow: 'hidden' }}>
                <div style={{ padding: '20px 24px', borderBottom: '1px solid #f1f5f9' }}>
                    <h3 style={{ margin: 0, fontSize: '16px', fontWeight: '600', color: '#0f172a' }}>Payslip History</h3>
                </div>
                
                <div style={{ padding: '24px' }}>
                    {loading ? (
                        <div style={{ textAlign: 'center', padding: '40px', color: '#64748b' }}>Loading payslips...</div>
                    ) : sortedYears.length === 0 ? (
                        <div style={{ textAlign: 'center', padding: '40px', color: '#64748b', background: '#f8fafc', borderRadius: '12px', border: '1px dashed #e2e8f0' }}>
                            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '12px' }}>
                                <div style={{ width: '48px', height: '48px', background: '#fff', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                                    <FileText size={24} color="#94a3b8" />
                                </div>
                                <div>No payslips available for your profile yet.</div>
                            </div>
                        </div>
                    ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            {sortedYears.map(year => (
                                <div key={year}>
                                    <h4 style={{ margin: '0 0 12px 0', fontSize: '14px', fontWeight: '600', color: '#64748b', borderBottom: '1px solid #e2e8f0', paddingBottom: '8px' }}>{year}</h4>
                                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '16px' }}>
                                        {payslipsByYear[year].sort((a, b) => b.month - a.month).map(doc => (
                                            <div 
                                                key={doc.id}
                                                style={{ 
                                                    display: 'flex', 
                                                    alignItems: 'center', 
                                                    justifyContent: 'space-between', 
                                                    padding: '16px', 
                                                    background: '#fff', 
                                                    border: '1px solid #e2e8f0', 
                                                    borderRadius: '12px',
                                                    transition: 'all 0.2s',
                                                    boxShadow: '0 1px 3px rgba(0,0,0,0.05)'
                                                }}
                                                onMouseOver={(e) => {
                                                    e.currentTarget.style.borderColor = 'var(--color-primary)';
                                                    e.currentTarget.style.transform = 'translateY(-2px)';
                                                    e.currentTarget.style.boxShadow = '0 4px 6px rgba(0,0,0,0.05)';
                                                }}
                                                onMouseOut={(e) => {
                                                    e.currentTarget.style.borderColor = '#e2e8f0';
                                                    e.currentTarget.style.transform = 'none';
                                                    e.currentTarget.style.boxShadow = '0 1px 3px rgba(0,0,0,0.05)';
                                                }}
                                            >
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                    <div style={{ padding: '10px', background: 'rgba(16, 185, 129, 0.1)', borderRadius: '10px', color: '#10B981' }}>
                                                        <CreditCard size={20} />
                                                    </div>
                                                    <div>
                                                        <div style={{ fontWeight: 600, color: '#0f172a', fontSize: '14px' }}>
                                                            Payslip - {new Date(doc.year, doc.month - 1).toLocaleString('default', { month: 'long', year: 'numeric' })}
                                                        </div>
                                                        <div style={{ fontSize: '12px', color: '#64748b', marginTop: '4px' }}>
                                                            Uploaded on {formatDate(doc.created_at || doc.uploaded_at)}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                    <button 
                                                        onClick={() => {
                                                            const fullPath = getSecureMediaUrl(doc.file_path);
                                                            setPreviewDoc(`${fullPath}#toolbar=0&navpanes=0&scrollbar=0`);
                                                        }}
                                                        style={{ 
                                                            padding: '8px', 
                                                            borderRadius: '50%', 
                                                            color: '#10B981', 
                                                            background: 'rgba(16, 185, 129, 0.08)',
                                                            border: 'none',
                                                            cursor: 'pointer',
                                                            display: 'flex',
                                                            transition: 'background 0.2s'
                                                        }}
                                                        onMouseOver={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.15)'}
                                                        onMouseOut={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.08)'}
                                                        title="View Payslip"
                                                    >
                                                        <Eye size={16} />
                                                    </button>
                                                    <a 
                                                        href={getSecureMediaUrl(doc.file_path)} 
                                                        download 
                                                        style={{ 
                                                            padding: '8px', 
                                                            borderRadius: '50%', 
                                                            color: 'var(--color-primary)', 
                                                            background: 'rgba(37, 99, 235, 0.08)',
                                                            border: 'none',
                                                            cursor: 'pointer',
                                                            display: 'flex',
                                                            transition: 'background 0.2s'
                                                        }}
                                                        onMouseOver={(e) => e.currentTarget.style.background = 'rgba(37, 99, 235, 0.15)'}
                                                        onMouseOut={(e) => e.currentTarget.style.background = 'rgba(37, 99, 235, 0.08)'}
                                                        title="Download"
                                                    >
                                                        <Download size={16} />
                                                    </a>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Preview Modal */}
            {previewDoc && (
                <div style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, background: 'rgba(15,23,42,0.75)', backdropFilter: 'blur(4px)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1100, padding: '20px' }}>
                    <div style={{ background: '#fff', borderRadius: '16px', width: '100%', maxWidth: '900px', height: '85vh', display: 'flex', flexDirection: 'column', boxShadow: '0 20px 25px -5px rgba(0,0,0,0.1)' }}>
                        <div style={{ padding: '20px 24px', borderBottom: '1px solid #f1f5f9', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <h3 style={{ margin: 0, fontSize: '18px', fontWeight: '600', color: '#0f172a', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <FileText size={20} style={{ color: 'var(--color-primary)' }}/> Document Preview
                            </h3>
                            <button onClick={() => setPreviewDoc(null)} style={{ background: '#f1f5f9', border: 'none', cursor: 'pointer', color: '#64748b', width: '32px', height: '32px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <X size={18} />
                            </button>
                        </div>
                        <div style={{ flex: 1, padding: '24px', background: '#f8fafc', overflow: 'hidden' }}>
                            <iframe 
                                src={previewDoc} 
                                style={{ width: '100%', height: '100%', border: 'none', borderRadius: '8px', background: '#fff', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }} 
                                title="Document Preview" 
                            />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default EmployeePayslips;
