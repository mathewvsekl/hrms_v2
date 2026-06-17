import React, { useState, useEffect } from 'react';
import api from '../../services/api';
import { Plus, Trash2, Edit, Eye } from 'lucide-react';
import useNotificationStore from '../../store/useNotificationStore';
import useLayoutStore from '../../store/useLayoutStore';

const AppraisalTemplateBuilder = () => {
    const [templates, setTemplates] = useState([]);
    const [loading, setLoading] = useState(false);
    const [isModalVisible, setIsModalVisible] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [softSkills, setSoftSkills] = useState([]);
    const showAlert = useNotificationStore(state => state.showAlert);

    const [formData, setFormData] = useState({
        name: '',
        min_kpis: 1,
        max_kpis: 5
    });



    useEffect(() => {
        fetchTemplates();
    }, []);

    const fetchTemplates = async () => {
        try {
            setLoading(true);
            const res = await api.get('/appraisal-templates');
            setTemplates(res.data.data || res.data);
        } catch (error) {
            showAlert('Error', 'Failed to load templates', 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleAddSkill = () => {
        setSoftSkills([...softSkills, { id: Date.now(), skill_name: '', description: '', rating_scale_max: 10 }]);
    };

    const handleSkillChange = (id, field, value) => {
        setSoftSkills(softSkills.map(s => s.id === id ? { ...s, [field]: value } : s));
    };

    const handleRemoveSkill = (id) => {
        setSoftSkills(softSkills.filter(s => s.id !== id));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            const payload = {
                ...formData,
                soft_skills: softSkills
            };
            if (editingId) {
                await api.put(`/appraisal-templates/${editingId}`, payload);
                showAlert('Success', 'Template updated', 'success');
            } else {
                await api.post('/appraisal-templates', payload);
                showAlert('Success', 'Template created', 'success');
            }
            setIsModalVisible(false);
            fetchTemplates();
        } catch (error) {
            showAlert('Error', 'Failed to save template', 'error');
        }
    };

    const openEdit = (record) => {
        setEditingId(record.id);
        setFormData({ name: record.name, min_kpis: record.min_kpis, max_kpis: record.max_kpis });
        setSoftSkills(record.soft_skills || []);
        setIsModalVisible(true);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Are you sure you want to delete this template?')) return;
        try {
            await api.delete(`/appraisal-templates/${id}`);
            showAlert('Success', 'Template deleted', 'success');
            fetchTemplates();
        } catch (error) {
            showAlert('Error', 'Failed to delete template', 'error');
        }
    };

    const openCreate = () => {
        setEditingId(null);
        setFormData({ name: '', min_kpis: 1, max_kpis: 5 });
        setSoftSkills([]);
        setIsModalVisible(true);
    };

    return (
        <div>
            <div className="header-actions" style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end', marginBottom: '24px' }}>
                <button className="btn btn-secondary" onClick={async () => {
                    try {
                        const res = await api.get('/appraisals/settings');
                        const data = res.data.data || res.data;
                        const settings = data.settings || {};
                        const globalSkills = settings.soft_skills_criteria || ["Communication", "Teamwork", "Problem Solving", "Adaptability", "Leadership"];
                        
                        setEditingId(null);
                        setFormData({ 
                            name: 'Standard Annual Appraisal', 
                            min_kpis: settings.default_min_kpis_global || 3, 
                            max_kpis: 10 
                        });
                        setSoftSkills(globalSkills.map((skill, index) => ({
                            id: Date.now() + index,
                            skill_name: skill,
                            description: '',
                            rating_scale_max: 10
                        })));
                        setIsModalVisible(true);
                    } catch (err) {
                        showAlert('Error', 'Failed to load system defaults', 'error');
                    }
                }} style={{ display: 'flex', alignItems: 'center', gap: '8px', marginRight: 'auto' }}>
                    <Plus size={18} /> Standard Annual Appraisal (System Defaults)
                </button>
                <button className="btn btn-primary" onClick={openCreate} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <Plus size={18} /> Create Custom Template
                </button>
            </div>

            <div className="card">
                <div className="card-body" style={{ padding: 0 }}>
                    <div className="table-container">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    <th>Template Name</th>
                                    <th>Min KPIs</th>
                                    <th>Max KPIs</th>
                                    <th style={{ textAlign: 'center' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {loading ? (
                                    <tr><td colSpan="4" style={{ textAlign: 'center', padding: '20px' }}>Loading...</td></tr>
                                ) : templates.length === 0 ? (
                                    <tr><td colSpan="4" style={{ textAlign: 'center', padding: '20px' }}>No templates found</td></tr>
                                ) : (
                                    templates.map(t => (
                                        <tr key={t.id}>
                                            <td style={{ fontWeight: 500 }}>{t.name}</td>
                                            <td>{t.min_kpis}</td>
                                            <td>{t.max_kpis}</td>
                                            <td style={{ textAlign: 'center' }}>
                                                <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                                    <button onClick={() => openEdit(t)} className="btn btn-secondary" style={{ padding: '4px 8px', fontSize: '0.85rem' }}>
                                                        <Eye size={14} style={{ marginRight: '4px' }} /> View
                                                    </button>
                                                    <button onClick={() => openEdit(t)} className="btn btn-secondary" style={{ padding: '4px 8px', fontSize: '0.85rem' }}>
                                                        <Edit size={14} style={{ marginRight: '4px' }} /> Edit
                                                    </button>
                                                    <button onClick={() => handleDelete(t.id)} className="btn" style={{ padding: '4px 8px', fontSize: '0.85rem', color: '#ef4444', border: '1px solid #ef4444', background: 'transparent' }}>
                                                        <Trash2 size={14} style={{ marginRight: '4px' }} /> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {isModalVisible && (
                <div className="modal-overlay" style={{
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
                    backgroundColor: 'rgba(0,0,0,0.5)', display: 'flex',
                    alignItems: 'center', justifyContent: 'center', zIndex: 1000
                }}>
                    <div className="modal-content card" style={{ width: '800px', maxWidth: '90%', maxHeight: '90vh', display: 'flex', flexDirection: 'column' }}>
                        <div className="card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '16px 24px' }}>
                            <h2 style={{ margin: 0, fontSize: '1.2rem' }}>{editingId ? 'Edit Template' : 'New Template'}</h2>
                            <button onClick={() => setIsModalVisible(false)} style={{ background: 'none', border: 'none', fontSize: '1.5rem', cursor: 'pointer', color: 'var(--color-text-muted)' }}>&times;</button>
                        </div>
                        <div className="card-body" style={{ overflowY: 'auto', padding: '24px' }}>
                            <form id="templateForm" onSubmit={handleSubmit}>
                                <div style={{ marginBottom: '20px' }}>
                                    <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.9rem', fontWeight: 500 }}>Template Name</label>
                                    <input 
                                        required 
                                        className="form-input" 
                                        style={{ width: '100%' }}
                                        value={formData.name} 
                                        onChange={e => setFormData({...formData, name: e.target.value})} 
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '24px' }}>
                                    <div>
                                        <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.9rem', fontWeight: 500 }}>Min Required KPIs</label>
                                        <input 
                                            type="number" min="1" required 
                                            className="form-input" 
                                            style={{ width: '100%' }}
                                            value={formData.min_kpis} 
                                            onChange={e => setFormData({...formData, min_kpis: e.target.value})} 
                                        />
                                    </div>
                                    <div>
                                        <label style={{ display: 'block', marginBottom: '6px', fontSize: '0.9rem', fontWeight: 500 }}>Max Allowed KPIs</label>
                                        <input 
                                            type="number" min="1" required 
                                            className="form-input" 
                                            style={{ width: '100%' }}
                                            value={formData.max_kpis} 
                                            onChange={e => setFormData({...formData, max_kpis: e.target.value})} 
                                        />
                                    </div>
                                </div>

                                <div style={{ borderTop: '1px solid var(--color-border)', paddingTop: '24px', marginBottom: '16px' }}>
                                    <h3 style={{ margin: '0 0 16px 0', fontSize: '1.1rem' }}>Custom Soft Skills Matrix</h3>
                                    
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '12px', marginBottom: '20px' }}>
                                        {softSkills.map((skill) => (
                                            <div key={skill.id} style={{ display: 'flex', gap: '12px', alignItems: 'flex-start', padding: '16px', background: 'var(--color-bg-light)', border: '1px solid var(--color-border)', borderRadius: '6px' }}>
                                                <div style={{ flex: '1 1 30%' }}>
                                                    <label style={{ display: 'block', marginBottom: '4px', fontSize: '0.8rem', color: 'var(--color-text-muted)' }}>Skill Name</label>
                                                    <input 
                                                        className="form-input"
                                                        style={{ width: '100%' }}
                                                        value={skill.skill_name}
                                                        onChange={(e) => handleSkillChange(skill.id, 'skill_name', e.target.value)}
                                                        required
                                                    />
                                                </div>
                                                <div style={{ flex: '1 1 50%' }}>
                                                    <label style={{ display: 'block', marginBottom: '4px', fontSize: '0.8rem', color: 'var(--color-text-muted)' }}>Description</label>
                                                    <textarea 
                                                        className="form-input"
                                                        style={{ width: '100%', resize: 'none', height: '38px' }}
                                                        value={skill.description}
                                                        onChange={(e) => handleSkillChange(skill.id, 'description', e.target.value)}
                                                    />
                                                </div>
                                                <div style={{ flex: '0 0 80px' }}>
                                                    <label style={{ display: 'block', marginBottom: '4px', fontSize: '0.8rem', color: 'var(--color-text-muted)' }}>Max</label>
                                                    <input 
                                                        type="number" min="1"
                                                        className="form-input"
                                                        style={{ width: '100%' }}
                                                        value={skill.rating_scale_max}
                                                        onChange={(e) => handleSkillChange(skill.id, 'rating_scale_max', e.target.value)}
                                                    />
                                                </div>
                                                <div style={{ flex: '0 0 auto', paddingTop: '20px' }}>
                                                    <button type="button" onClick={() => handleRemoveSkill(skill.id)} style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', padding: '8px' }}>
                                                        <Trash2 size={20} />
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    
                                    <button 
                                        type="button" 
                                        onClick={handleAddSkill} 
                                        style={{ width: '100%', padding: '12px', border: '2px dashed var(--color-border)', background: 'transparent', borderRadius: '6px', color: 'var(--color-text-muted)', cursor: 'pointer', display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '8px', fontWeight: 500 }}
                                        onMouseOver={(e) => e.currentTarget.style.borderColor = 'var(--color-rose-gold)'}
                                        onMouseOut={(e) => e.currentTarget.style.borderColor = 'var(--color-border)'}
                                    >
                                        <Plus size={18} /> Add Soft Skill Category
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div style={{ padding: '16px 24px', borderTop: '1px solid var(--color-border)', display: 'flex', justifyContent: 'flex-end', gap: '12px', background: 'var(--color-bg-light)' }}>
                            <button type="button" onClick={() => setIsModalVisible(false)} className="btn btn-secondary">Cancel</button>
                            <button type="submit" form="templateForm" className="btn btn-primary">Save Template</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default AppraisalTemplateBuilder;
