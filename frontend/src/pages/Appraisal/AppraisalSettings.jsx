import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { ChevronLeft, Plus, Trash2, Settings, AlertCircle, Save, Star, Users, ClipboardList } from 'lucide-react';
import api from '../../services/api';
import useLayoutStore from '../../store/useLayoutStore';
import AppraisalMatrixBuilder from './AppraisalMatrixBuilder';
import AppraisalTemplateBuilder from './AppraisalTemplateBuilder';

const AppraisalSettings = () => {
    const navigate = useNavigate();
    const isEmployeeView = localStorage.getItem('adminViewMode') === 'employee';

    useEffect(() => {
        if (isEmployeeView) {
            navigate('/employee-profile');
        }
    }, [isEmployeeView, navigate]);

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [configTab, setConfigTab] = useState('settings'); // 'settings', 'matrices', 'templates'
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();


    useEffect(() => {
        setPageTitle("Appraisal System Configuration");
        setPageSubtitle("Manage department rules, rating systems, and mass de-activation");
        setBackPath('/appraisals');
        return () => resetPageHeader();
    }, []);
    
    // State for configuration
    const [globalMinKPIs, setGlobalMinKPIs] = useState(3);
    const [deptRequirements, setDeptRequirements] = useState([]);
    const [softSkills, setSoftSkills] = useState([]);
    const [ratingMapping, setRatingMapping] = useState([]);
    const [offices, setOffices] = useState([]);

    useEffect(() => {
        fetchSettings();
        fetchOffices();
    }, []);

    const fetchSettings = async () => {
        try {
            setLoading(true);
            const res = await api.get('/appraisals/settings');
            const data = res.data.data || res.data;
            const { settings, department_requirements } = data;
            
            if (settings) {
                setGlobalMinKPIs(settings.default_min_kpis_global || 3);
                
                let loadedSoftSkills = settings.soft_skills_criteria;
                if (!loadedSoftSkills || loadedSoftSkills.length === 0) {
                    loadedSoftSkills = ["Communication", "Teamwork", "Problem Solving", "Adaptability", "Leadership"];
                }
                setSoftSkills(loadedSoftSkills);

                let loadedRatingMapping = settings.rating_system_mapping;
                if (!loadedRatingMapping || loadedRatingMapping.length === 0) {
                    loadedRatingMapping = [
                        { rating: 5, stars: 5, label: "Outstanding Performance" },
                        { rating: 4, stars: 4, label: "Strong Performance" },
                        { rating: 3, stars: 3, label: "Effective Performance" },
                        { rating: 2, stars: 2, label: "Developing Performance" },
                        { rating: 1, stars: 1, label: "Performance Below Expectations" }
                    ];
                }
                setRatingMapping(loadedRatingMapping);
            }
            
            if (department_requirements) {
                setDeptRequirements(department_requirements);
            }
        } catch (error) {
            console.error('Error fetching settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchOffices = async () => {
        try {
            const res = await api.get('/organization/companies');
            const data = res.data.data || res.data;
            setOffices(data || []);
        } catch (error) {
            console.error('Error fetching offices:', error);
        }
    };

    const handleSave = async () => {
        try {
            setSaving(true);
            const payload = {
                settings: {
                    default_min_kpis_global: globalMinKPIs,
                    soft_skills_criteria: softSkills,
                    rating_system_mapping: ratingMapping
                },
                department_requirements: deptRequirements.map(d => ({
                    id: d.id,
                    min_kpis: d.min_kpis
                }))
            };
            
            await api.post('/appraisals/settings', payload);
            alert('Settings saved successfully');
        } catch (error) {
            console.error('Error saving settings:', error);
            alert('Failed to save settings');
        } finally {
            setSaving(false);
        }
    };

    const handleMassDeactivate = async (scope) => {
        const confirmMsg = scope === 'all' 
            ? "Are you sure you want to de-activate ALL active appraisal records globally? This action cannot be easily undone."
            : `Are you sure you want to de-activate all active appraisal records for the selected office?`;
            
        if (!confirm(confirmMsg)) return;
        
        try {
            await api.post('/appraisals/mass-deactivate', { scope });
            alert('Records de-activated successfully');
        } catch (error) {
            console.error('Error in mass de-activation:', error);
            alert('Mass de-activation failed');
        }
    };

    if (loading) return <div style={{ padding: '40px', textAlign: 'center' }}>Loading system configuration...</div>;

    return (
        <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
            <div style={{ padding: '20px', borderBottom: '1px solid var(--color-border)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#fcfcfc', position: 'sticky', top: 0, zIndex: 10 }}>
                <div>
                    <h3 style={{ margin: 0, display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <Settings size={18} style={{ color: 'var(--color-rose-gold)' }} /> Appraisals Architect
                    </h3>
                    <p style={{ margin: '4px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>Configure system rules, templates, and workflows</p>
                </div>
                <div style={{ display: 'flex', gap: '12px' }}>
                    {configTab === 'settings' && (
                        <button className="btn btn-primary" onClick={handleSave} disabled={saving} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <Save size={18} /> {saving ? 'Saving...' : 'Save Configuration'}
                        </button>
                    )}
                </div>
            </div>

            <div style={{ display: 'flex', background: '#fff', minHeight: '600px' }}>
                <div style={{ width: '220px', borderRight: '1px solid var(--color-border)', padding: '10px', position: 'sticky', top: '75px', height: 'fit-content' }}>
                    <button 
                        className={`nav-item ${configTab === 'settings' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'settings' ? 'var(--color-rose-gold)' : 'inherit', padding: '10px 16px', background: configTab === 'settings' ? 'var(--color-bg-light)' : 'transparent', border: 'none', cursor: 'pointer', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '8px', fontWeight: configTab === 'settings' ? 600 : 400 }}
                        onClick={() => setConfigTab('settings')}
                    >
                        <Settings size={16} /> Global Rules
                    </button>
                    <button 
                        className={`nav-item ${configTab === 'matrices' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'matrices' ? 'var(--color-rose-gold)' : 'inherit', padding: '10px 16px', background: configTab === 'matrices' ? 'var(--color-bg-light)' : 'transparent', border: 'none', cursor: 'pointer', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '8px', fontWeight: configTab === 'matrices' ? 600 : 400 }}
                        onClick={() => setConfigTab('matrices')}
                    >
                        <Users size={16} /> Approval Matrices
                    </button>
                    <button 
                        className={`nav-item ${configTab === 'templates' ? 'active' : ''}`} 
                        style={{ width: '100%', textAlign: 'left', marginBottom: '4px', color: configTab === 'templates' ? 'var(--color-rose-gold)' : 'inherit', padding: '10px 16px', background: configTab === 'templates' ? 'var(--color-bg-light)' : 'transparent', border: 'none', cursor: 'pointer', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '8px', fontWeight: configTab === 'templates' ? 600 : 400 }}
                        onClick={() => setConfigTab('templates')}
                    >
                        <ClipboardList size={16} /> Manage Templates
                    </button>
                </div>
                <div style={{ flex: 1, padding: '24px', overflowY: 'auto' }}>
                    {configTab === 'settings' && (
                        <div>
                            {/* KPI Requirements Section */}
                            <div className="card" style={{ marginBottom: '32px' }}>
                                <h3 style={{ fontSize: '1.1rem', fontWeight: 600, marginBottom: '20px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    KPI Requirements
                                </h3>
                                
                                <div style={{ marginBottom: '24px' }}>
                                    <label style={{ display: 'block', fontSize: '0.9rem', color: '#64748b', marginBottom: '8px' }}>Default Minimum KPIs (Global)</label>
                                    <input 
                                        type="number" 
                                        className="form-input" 
                                        style={{ width: '120px' }}
                                        value={globalMinKPIs}
                                        onChange={(e) => setGlobalMinKPIs(parseInt(e.target.value))}
                                    />
                                    <p style={{ fontSize: '0.8rem', color: '#94a3b8', marginTop: '4px' }}>Fallback count for departments without specific rules.</p>
                                </div>

                                <div>
                                    <label style={{ display: 'block', fontSize: '0.9rem', color: '#64748b', marginBottom: '16px' }}>Department-Specific Exceptions</label>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', width: '100%' }}>
                                        {deptRequirements.map((dept, idx) => (
                                            <div key={dept.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px', background: '#f8fafc', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                                                <span style={{ fontSize: '0.95rem' }}>{dept.name}</span>
                                                <input 
                                                    type="number" 
                                                    className="form-input" 
                                                    style={{ width: '80px', background: '#fff' }}
                                                    value={dept.min_kpis || 0}
                                                    onChange={(e) => {
                                                        const newList = [...deptRequirements];
                                                        newList[idx].min_kpis = parseInt(e.target.value);
                                                        setDeptRequirements(newList);
                                                    }}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* Soft Skills Section */}
                            <div className="card" style={{ marginBottom: '32px' }}>
                                <h3 style={{ fontSize: '1.1rem', fontWeight: 600, marginBottom: '12px' }}>Soft Skills Assessment Criteria</h3>
                                <p style={{ color: '#64748b', fontSize: '0.9rem', marginBottom: '20px' }}>These skills will be rated on a scale of 1 to 10 by both the employee and manager during the appraisal.</p>
                                
                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '12px' }}>
                                    {softSkills.map((skill, index) => (
                                        <div key={index} style={{ 
                                            display: 'flex', alignItems: 'center', gap: '8px', padding: '8px 12px', 
                                            background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px' 
                                        }}>
                                            <span style={{ fontSize: '0.9rem' }}>{skill}</span>
                                            <button 
                                                onClick={() => setSoftSkills(softSkills.filter((_, i) => i !== index))}
                                                style={{ color: '#ef4444', border: 'none', background: 'none', cursor: 'pointer', padding: 0 }}
                                            >
                                                <Trash2 size={14} />
                                            </button>
                                        </div>
                                    ))}
                                    <button 
                                        className="btn btn-secondary" 
                                        style={{ color: 'var(--color-rose-gold)', border: '1px dashed var(--color-rose-gold)' }}
                                        onClick={() => {
                                            const newSkill = prompt('Enter new soft skill name:');
                                            if (newSkill) setSoftSkills([...softSkills, newSkill]);
                                        }}
                                    >
                                        <Plus size={16} /> Add Soft Skill
                                    </button>
                                </div>
                            </div>

                            {/* Rating System Section */}
                            <div className="card" style={{ marginBottom: '32px' }}>
                                <h3 style={{ fontSize: '1.1rem', fontWeight: 600, marginBottom: '20px' }}>Rating System (1-5 Scale)</h3>
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                    {ratingMapping.map((r, idx) => (
                                        <div key={r.rating} style={{ display: 'grid', gridTemplateColumns: '40px 120px 1fr', alignItems: 'center', gap: '20px', padding: '12px', background: '#f8fafc', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                                            <span style={{ fontWeight: 600 }}>#{r.rating}</span>
                                            <div style={{ display: 'flex', color: 'var(--color-rose-gold)' }}>
                                                {[...Array(r.stars)].map((_, i) => <Star key={i} size={16} fill="var(--color-rose-gold)" />)}
                                            </div>
                                            <input 
                                                type="text" 
                                                className="form-input" 
                                                value={r.label}
                                                onChange={(e) => {
                                                    const newList = [...ratingMapping];
                                                    newList[idx].label = e.target.value;
                                                    setRatingMapping(newList);
                                                }}
                                                style={{ background: '#fff' }}
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Critical Actions Section */}
                            <div className="card" style={{ padding: '24px', borderRadius: '12px', border: '1px solid #fee2e2' }}>
                                <h3 style={{ fontSize: '1.1rem', fontWeight: 600, marginBottom: '20px', color: '#b91c1c', display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    <AlertCircle size={20} /> Critical Actions
                                </h3>
                                
                                <div style={{ padding: '20px', background: '#fef2f2', borderRadius: '8px' }}>
                                    <div style={{ marginBottom: '16px' }}>
                                        <h4 style={{ fontSize: '1rem', fontWeight: 600, color: '#991b1b', margin: 0 }}>Mass De-activation</h4>
                                        <p style={{ color: '#b91c1c', fontSize: '0.85rem', marginTop: '4px' }}>Mark all active appraisal runs as 'De-activated'. This is non-destructive but prevents further editing.</p>
                                    </div>
                                    
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '12px' }}>
                                        <button 
                                            className="btn btn-sm" 
                                            style={{ background: '#ef4444', color: '#fff', border: 'none', padding: '8px 12px', borderRadius: '6px', cursor: 'pointer' }}
                                            onClick={() => handleMassDeactivate('all')}
                                        >
                                            De-activate All Records
                                        </button>
                                        {offices.map(office => (
                                            <button 
                                                key={office.id}
                                                className="btn btn-sm" 
                                                style={{ background: '#fff', color: '#b91c1c', border: '1px solid #fecaca', padding: '8px 12px', borderRadius: '6px', cursor: 'pointer' }}
                                                onClick={() => handleMassDeactivate(office.id)}
                                            >
                                                De-activate {office.name}
                                            </button>
                                        ))}
                                        <button 
                                            className="btn btn-sm" 
                                            style={{ background: '#fff', color: '#b91c1c', border: '1px solid #fecaca', padding: '8px 12px', borderRadius: '6px', cursor: 'pointer' }}
                                            onClick={() => handleMassDeactivate('global')}
                                        >
                                            De-activate Global
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}
                    {configTab === 'matrices' && <AppraisalMatrixBuilder />}
                    {configTab === 'templates' && <AppraisalTemplateBuilder />}
                </div>
            </div>
        </div>
    );
};

export default AppraisalSettings;
