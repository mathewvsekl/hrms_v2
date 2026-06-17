import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { FileText, Download, Activity, Calendar, PiggyBank, Award, TrendingUp, Eye } from 'lucide-react';
import DataExportModal from '../components/DataExportModal';
import useLayoutStore from '../store/useLayoutStore';

const Reports = () => {
    const [isExportModalOpen, setIsExportModalOpen] = useState(false);
    const navigate = useNavigate();
    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        setPageTitle("Reports");
        setPageSubtitle("Generate and download HR reports");
        return () => resetPageHeader();
    }, []);

    return (
        <div>
            {/* Actions Bar */}
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '24px' }}>
                <button className="btn btn-primary" onClick={() => setIsExportModalOpen(true)}>
                    <Download size={18} /> Export Data
                </button>
            </div>

            <DataExportModal 
                isOpen={isExportModalOpen} 
                onClose={() => setIsExportModalOpen(false)} 
            />

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '24px' }}>
                {[
                    { title: 'Attendance Report', desc: 'Monthly matrix view of employee attendance', icon: <Activity size={24} style={{ color: 'var(--primary-brand)' }} /> },
                    { title: 'Headcount Report', desc: 'Employee strength by office and department', icon: <FileText size={24} style={{ color: '#6366f1' }} /> },
                    { title: 'Leave Balances', desc: 'Current leave balances for all employees', icon: <Calendar size={24} style={{ color: '#ec4899' }} /> },
                    { title: 'Payroll Summary', desc: 'Monthly payroll breakdown by country', icon: <PiggyBank size={24} style={{ color: '#10b981' }} /> },
                    { title: 'Appraisal Status', desc: 'Performance review completion tracker', icon: <Award size={24} style={{ color: '#f59e0b' }} /> },
                    { title: 'Turnover Analysis', desc: 'Employee separation and retention rates', icon: <TrendingUp size={24} style={{ color: '#6366f1' }} /> },
                ].map((r) => (
                    <div className="card" key={r.title} style={{ transition: 'transform 0.2s', cursor: 'default' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '12px' }}>
                            <div style={{ padding: '8px', borderRadius: '8px', backgroundColor: 'var(--bg-main)' }}>
                                {r.icon}
                            </div>
                            <h3 className="card-title" style={{ margin: 0 }}>{r.title}</h3>
                        </div>
                        <p style={{ color: 'var(--text-secondary)', fontSize: '13px', marginBottom: '20px', lineHeight: '1.5' }}>{r.desc}</p>
                        
                        {r.title === 'Attendance Report' ? (
                            <button 
                                className="btn btn-primary" 
                                style={{ width: '100%', fontSize: '13px', display: 'flex', justifyContent: 'center', gap: '8px' }}
                                onClick={() => navigate('/attendance-report')}
                            >
                                <Eye size={14} /> View Report
                            </button>
                        ) : (
                            <button 
                                className="btn btn-outline" 
                                style={{ width: '100%', fontSize: '13px', display: 'flex', justifyContent: 'center', gap: '8px', borderStyle: 'dashed' }}
                                onClick={() => setIsExportModalOpen(true)}
                            >
                                <Download size={14} /> Configure Export
                            </button>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};

export default Reports;
