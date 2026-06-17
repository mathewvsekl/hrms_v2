import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Save, ChevronLeft, Plus, Trash2, Search, User } from 'lucide-react';
import api from '../../services/api';
import useLayoutStore from '../../store/useLayoutStore';

const KPIConfig = () => {
    const { employeeId } = useParams();
    const navigate = useNavigate();
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        if (employeeId === 'search') {
            setPageTitle("Configure Employee KPIs");
            setPageSubtitle("Select an employee to define their performance targets");
            setBackPath('/appraisals');
        }
        return () => resetPageHeader();
    }, [employeeId]);
    
    const [employees, setEmployees] = useState([]);
    const [selectedEmployee, setSelectedEmployee] = useState(null);
    const [kpis, setKpis] = useState([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');

    useEffect(() => {
        if (employeeId === 'search') {
            fetchEmployees();
        } else {
            fetchEmployeeKPIs(employeeId);
        }
    }, [employeeId]);

    const fetchEmployees = async () => {
        try {
            setLoading(true);
            const res = await api.get('/employees');
            setEmployees(res.data.data || res.data || []);
        } catch (error) {
            console.error('Error fetching employees:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchEmployeeKPIs = async (id) => {
        try {
            setLoading(true);
            // We need employee details too
            const empRes = await api.get(`/employees/${id}`);
            const empData = empRes.data.data || empRes.data;
            setSelectedEmployee(empData);
            
            setPageTitle("KPI Configuration");
            setPageSubtitle(`Setting targets for: ${empData.first_name} ${empData.last_name} (ID: ${empData.employee_code})`);
            setBackPath('/appraisals/config/search');
            
            const kpiRes = await api.get(`/appraisals/config?employee_id=${id}`);
            const data = kpiRes.data.status === 'success' ? kpiRes.data.data : kpiRes.data;
            setKpis(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Error fetching KPIs:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async () => {
        try {
            setSaving(true);
            const res = await api.post('/appraisals/config', {
                employee_id: employeeId,
                kpis: kpis
            });
            alert(res.data.message || 'KPIs saved successfully');
            navigate('/appraisals');
        } catch (error) {
            alert(error.response?.data?.message || 'Error saving KPIs');
        } finally {
            setSaving(false);
        }
    };

    const addKPI = () => {
        setKpis([...kpis, { kpi_name: '', target_description: '', weightage: 0 }]);
    };

    const removeKPI = (index) => {
        const newList = [...kpis];
        newList.splice(index, 1);
        setKpis(newList);
    };

    const filteredEmployees = employees.filter(e => 
        e.first_name?.toLowerCase().includes(searchTerm.toLowerCase()) || 
        e.last_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        e.employee_code?.toLowerCase().includes(searchTerm.toLowerCase())
    );

    if (loading) return (
        <div style={{ padding: '60px', textAlign: 'center' }}>
            <div className="loader-content">
                <div className="loader-spinner"></div>
                <div className="loader-text">FETCHING DATA...</div>
            </div>
        </div>
    );

    if (employeeId === 'search') {
        return (
            <div className="kpi-config">
                <div style={{ marginBottom: '24px' }}></div>
                
                <div className="card" style={{ padding: '24px' }}>
                    <div style={{ position: 'relative', marginBottom: '20px' }}>
                        <Search size={18} style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-secondary)' }} />
                        <input 
                            type="text" 
                            className="form-control" 
                            placeholder="Search by name or employee code..." 
                            style={{ width: '100%', paddingLeft: '40px' }}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                    
                    <div className="employee-list" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '16px' }}>
                        {filteredEmployees.map(emp => (
                            <div key={emp.id} className="card-item" style={{ 
                                padding: '16px', border: '1px solid var(--border-gray)', borderRadius: '8px', cursor: 'pointer',
                                display: 'flex', alignItems: 'center', gap: '16px'
                            }} onClick={() => navigate(`/appraisals/config/${emp.id}`)}>
                                <div style={{ width: '48px', height: '48px', background: '#e2e8f0', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#64748b' }}>
                                    <User size={24} />
                                </div>
                                <div>
                                    <div style={{ fontWeight: 600 }}>{emp.first_name} {emp.last_name}</div>
                                    <div style={{ fontSize: '0.8rem', color: 'var(--text-secondary)' }}>ID: {emp.employee_code} • {emp.designation_name || 'No Designation'}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="kpi-config">
            <div className="header-actions" style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '24px' }}>
                <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
                    <Save size={18} /> {saving ? 'Saving...' : 'Save Configuration'}
                </button>
            </div>

            <div className="card" style={{ padding: '24px' }}>
                <div style={{ marginBottom: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <h3>Configured KRAs / KPIs</h3>
                    <button className="btn btn-outline" onClick={addKPI}>
                        <Plus size={18} /> Add New KRA
                    </button>
                </div>

                {kpis.length === 0 ? (
                    <div style={{ padding: '40px', textAlign: 'center', border: '2px dashed var(--border-gray)', borderRadius: '12px', color: 'var(--text-secondary)' }}>
                        No KPIs configured for this employee. Click "Add New KRA" to start.
                    </div>
                ) : (
                    <div className="config-list">
                        {kpis.map((kpi, idx) => (
                            <div key={idx} style={{ padding: '20px', border: '1px solid var(--border-gray)', borderRadius: '8px', marginBottom: '16px', background: '#f9fafb' }}>
                                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '16px' }}>
                                    <h4 style={{ margin: 0 }}>KPI {String(idx + 1).padStart(2, '0')}</h4>
                                    <button className="btn btn-ghost" style={{ color: '#ef4444' }} onClick={() => removeKPI(idx)}>
                                        <Trash2 size={16} />
                                    </button>
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr 100px', gap: '20px' }}>
                                    <div>
                                        <label className="field-label">KRA / KPI Area</label>
                                        <input 
                                            type="text" 
                                            className="form-control" 
                                            value={kpi.kpi_name} 
                                            onChange={(e) => {
                                                const newList = [...kpis];
                                                newList[idx].kpi_name = e.target.value;
                                                setKpis(newList);
                                            }}
                                            placeholder="e.g. Monthly Sales Revenue"
                                        />
                                    </div>
                                    <div>
                                        <label className="field-label">Target Description / Goals</label>
                                        <textarea 
                                            className="form-control" 
                                            rows="2"
                                            value={kpi.target_description} 
                                            onChange={(e) => {
                                                const newList = [...kpis];
                                                newList[idx].target_description = e.target.value;
                                                setKpis(newList);
                                            }}
                                            placeholder="Detailed objectives for this cycle..."
                                        ></textarea>
                                    </div>
                                    <div>
                                        <label className="field-label">Weight %</label>
                                        <input 
                                            type="number" 
                                            className="form-control" 
                                            value={kpi.weightage} 
                                            onChange={(e) => {
                                                const newList = [...kpis];
                                                newList[idx].weightage = e.target.value;
                                                setKpis(newList);
                                            }}
                                        />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                
                <div style={{ marginTop: '24px', padding: '16px', background: '#ecfdf5', borderRadius: '8px', color: '#065f46', fontSize: '0.9rem' }}>
                    <strong>Note:</strong> These KPIs will be automatically populated into the employee's appraisal form whenever a new cycle is initiated.
                </div>
            </div>
        </div>
    );
};

export default KPIConfig;
