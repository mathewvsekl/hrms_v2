import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Save, Send, CheckCircle, ChevronLeft, User, BarChart, FileText, Settings, Star, AlertCircle } from 'lucide-react';
import api from '../../services/api';
import useAuthStore from '../../store/useAuthStore';
import useLayoutStore from '../../store/useLayoutStore';
import useNotificationStore from '../../store/useNotificationStore';
import { formatDate } from '../../utils/dateUtils';

const AppraisalForm = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const user = useAuthStore(state => state.user);
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();
    const showAlert = useNotificationStore(state => state.showAlert);

    useEffect(() => {
        setBackPath('/appraisals');
        return () => resetPageHeader();
    }, []);

    const [appraisal, setAppraisal] = useState(null);
    const [template, setTemplate] = useState(null);
    const [ratings, setRatings] = useState([]);
    const [comments, setComments] = useState([]);
    const [matrixApprovals, setMatrixApprovals] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [activeTab, setActiveTab] = useState('performance');

    useEffect(() => {
        fetchData();
    }, [id]);

    const fetchData = async () => {
        try {
            setLoading(true);
            const apprRes = await api.get(`/appraisals/${id}`);

            if (apprRes.data?.status === 'success') {
                const data = apprRes.data.data;
                if (data && data.appraisal) {
                    setAppraisal(data.appraisal);
                    setRatings(data.ratings || []);
                    setComments(data.comments || []);
                    setMatrixApprovals(data.approvals || []);
                    
                    setPageTitle(data.appraisal.cycle_name || 'Performance Review Cycle');
                    setPageSubtitle(`Employee ID: ${data.appraisal.employee_id} • Status: ${data.appraisal.status.replace('_', ' ').toUpperCase()}`);

                    // Fetch specific template
                    try {
                        const templateId = data.appraisal.template_id;
                        const tempUrl = templateId ? `/appraisals/template?id=${templateId}` : '/appraisals/template';
                        const tempRes = await api.get(tempUrl);
                        if (tempRes.data?.status === 'success' || tempRes.data) {
                            const tData = tempRes.data.data || tempRes.data;
                            setTemplate(tData);
                        }
                    } catch (e) {
                        console.error('Error fetching template:', e);
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching appraisal data:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleRatingChange = (qId, type, val) => {
        if (appraisal.status === 'finalized') return;

        setRatings(prev => {
            const index = prev.findIndex(r => r.question_id === qId);
            const newList = [...prev];
            if (index > -1) {
                newList[index] = { ...newList[index], [type]: val };
            } else {
                newList.push({ question_id: qId, [type]: val });
            }
            return newList;
        });
    };

    const handleCommentChange = (section, text) => {
        if (appraisal.status === 'finalized') return;

        setComments(prev => {
            const index = prev.findIndex(c => c.section === section);
            const newList = [...prev];
            if (index > -1) {
                newList[index] = { ...newList[index], comment_text: text };
            } else {
                newList.push({ section, comment_text: text });
            }
            return newList;
        });
    };

    const onSave = async (submitStatus = null) => {
        try {
            setSaving(true);
            const payload = {
                appraisal_id: id,
                ratings,
                comments
            };

            // Validation for submission using dynamic requirement
            if (submitStatus === 'manager' && isEmployee) {
                const minRequired = appraisal.min_kpis_required || 3;
                const kpiCount = ratings.filter(r => r.kra_name && r.kra_name.trim() !== '').length;
                if (kpiCount < minRequired) {
                    showAlert('Validation Error', `Minimum ${minRequired} KPIs are required for your department before submitting.`, 'error');
                    return;
                }
            }

            let res;
            if (submitStatus === 'manager') {
                res = await api.post(`/appraisals/${id}/submit-manager`);
                showAlert('Success', 'Submitted to Manager for review', 'success');
            } else if (submitStatus === 'approve') {
                const comment = prompt('Enter approval comment (optional):');
                res = await api.post(`/appraisals/${id}/approve`, { comment });
                showAlert('Success', 'Appraisal Approved', 'success');
            } else if (submitStatus === 'return') {
                const comment = prompt('Enter reason for return (required):');
                if (!comment) return;
                res = await api.post(`/appraisals/${id}/return`, { comment });
                showAlert('Success', 'Appraisal Returned to Employee', 'success');
            } else if (submitStatus === 'finalize') {
                res = await api.post(`/appraisals/${id}/finalize`, { 
                    final_rating: appraisal.final_rating, 
                    eligible_for_increment: appraisal.eligible_for_increment,
                    eligible_for_bonus: appraisal.eligible_for_bonus
                });
                const msg = res.data.message || (res.data.data && res.data.data.message) || 'Appraisal Finalized';
                showAlert('Success', msg, 'success');
            } else {
                res = await api.post('/appraisals/draft', payload);
                const msg = res.data.message || (res.data.data && res.data.data.message) || 'Draft saved successfully';
                showAlert('Success', msg, 'success');
            }

            fetchData();
        } catch (error) {
            console.error('Error saving:', error);
            showAlert('Error', error.response?.data?.message || 'Error processing appraisal', 'error');
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div style={{ padding: '40px', textAlign: 'center' }}>Loading appraisal details...</div>;
    if (!appraisal) return <div style={{ padding: '40px', textAlign: 'center' }}>Appraisal not found</div>;

    const isAppraisee = user?.employee_id == appraisal.employee_id || user?.id == appraisal.employee_id;
    const isManagerRole = user?.role === 'HR Manager' || user?.role === 'CountryManager';
    const isDirectManager = user?.employee_id == appraisal.reporting_manager_id || user?.id == appraisal.reporting_manager_id;
    const isHR = user?.role === 'HR Manager' || user?.role === 'HRAssistant' || user?.role === 'Super Admin';

    // A person can edit as an employee if it's their own appraisal, OR if they're literally the 'Employee' role testing it
    const canEditEmployee = appraisal.status === 'draft' && (isAppraisee || user?.role === 'Employee');
    
    const isManagerTier = ['l1_review', 'l2_review', 'l3_review'].includes(appraisal.status);
    const canEditManager = isManagerTier && (isManagerRole || isDirectManager); // Backend validates exact manager identity
    const canEditHR = appraisal.status === 'hr_calibration' && isHR;

    return (
        <div className="appraisal-detail">
            <div style={{fontSize: '10px', color: 'red'}}>DEBUG: user.id={user?.id}, user.employee_id={user?.employee_id}, user.role={user?.role}, appraisal.employee_id={appraisal.employee_id}</div>
            <div className="header-actions" style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end', marginBottom: '24px' }}>
                <button className="btn btn-secondary" onClick={() => onSave()} disabled={saving || appraisal.status === 'finalized'}>
                    <Save size={18} /> {saving ? 'Saving...' : 'Save Draft'}
                </button>

                {canEditEmployee && (
                    <button className="btn btn-primary" onClick={() => onSave('manager')}>
                        <Send size={18} /> Submit to Manager
                    </button>
                )}
                {canEditManager && (
                    <>
                        <button className="btn btn-secondary" style={{ color: '#ef4444', borderColor: '#ef4444' }} onClick={() => onSave('return')}>
                            Return for Review
                        </button>
                        <button className="btn btn-primary" onClick={() => onSave('approve')}>
                            <CheckCircle size={18} /> Approve Appraisal
                        </button>
                    </>
                )}
                {canEditHR && (
                    <button className="btn btn-primary" style={{ background: 'var(--color-charcoal)' }} onClick={() => onSave('finalize')}>
                        <CheckCircle size={18} /> Finalize Appraisal
                    </button>
                )}
                {appraisal.status === 'finalized' && (
                    <button className="btn btn-primary" style={{ background: '#3b82f6', borderColor: '#3b82f6' }} onClick={() => navigate(`/appraisals/letter/${appraisal.id}`)}>
                        <FileText size={18} /> View Salary Revision Letter
                    </button>
                )}
            </div>

            {/* Employee Details Profile Card */}
            <div className="card" style={{ marginBottom: '24px', display: 'flex', alignItems: 'center', gap: '20px', padding: '24px' }}>
                <div style={{
                    width: '72px', height: '72px', borderRadius: '50%', backgroundColor: '#f1f5f9',
                    display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '1.5rem', fontWeight: 'bold', color: '#64748b', overflow: 'hidden'
                }}>
                    {appraisal.profile_image_path ? (
                        <img src={`http://localhost:8000/${appraisal.profile_image_path}`} alt="Profile" style={{ width: '100%', height: '100%', objectFit: 'cover' }} onError={(e) => { e.target.style.display='none'; e.target.nextSibling.style.display='flex'; }} />
                    ) : null}
                    <span style={{ display: appraisal.profile_image_path ? 'none' : 'flex' }}>
                        {appraisal.first_name?.[0]}{appraisal.last_name?.[0]}
                    </span>
                </div>
                <div style={{ flex: 1 }}>
                    <h2 style={{ margin: '0 0 4px 0', fontSize: '1.4rem', color: '#1e293b' }}>{appraisal.first_name} {appraisal.last_name}</h2>
                    <div style={{ display: 'flex', gap: '16px', color: '#64748b', fontSize: '0.9rem' }}>
                        <span><strong style={{ color: '#475569' }}>Code:</strong> {appraisal.employee_code}</span>
                        <span><strong style={{ color: '#475569' }}>Department:</strong> {appraisal.department_name || 'N/A'}</span>
                        <span><strong style={{ color: '#475569' }}>Designation:</strong> {appraisal.designation_title || 'N/A'}</span>
                    </div>
                </div>
                <div style={{ textAlign: 'right' }}>
                    <span style={{ 
                        display: 'inline-block', padding: '6px 12px', borderRadius: '20px', fontSize: '0.85rem', fontWeight: 600, textTransform: 'uppercase',
                        background: appraisal.status === 'finalized' ? '#dcfce7' : '#f1f5f9',
                        color: appraisal.status === 'finalized' ? '#166534' : '#475569'
                    }}>
                        Status: {appraisal.status.replace('_', ' ')}
                    </span>
                </div>
            </div>
                
                {/* Visual Timeline */}
                <div style={{ display: 'flex', gap: '20px', marginTop: '24px', padding: '20px', background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0', overflowX: 'auto' }}>
                    {[
                        { label: 'Employee Self-Assessment', status: 'draft', deadline: appraisal.employee_deadline },
                        { label: 'Managerial Evaluation (L1-L3)', status: appraisal.status, deadline: appraisal.manager_deadline },
                        { label: 'HR Calibration', status: 'hr_calibration', deadline: appraisal.hr_deadline }
                    ].map((s, i) => {
                        const isActive = appraisal.status === s.status || (i === 1 && ['l1_review', 'l2_review', 'l3_review'].includes(appraisal.status));
                        const isPast = appraisal.status === 'finalized' || 
                                       (i === 0 && appraisal.status !== 'draft') || 
                                       (i === 1 && ['hr_calibration', 'finalized'].includes(appraisal.status));
                        
                        return (
                            <div key={i} style={{ flex: 1, minWidth: '150px' }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '8px' }}>
                                    <div style={{ 
                                        width: '28px', height: '28px', borderRadius: '50%', 
                                        background: isPast ? 'var(--color-charcoal)' : (isActive ? 'var(--color-rose-gold)' : '#cbd5e1'),
                                        color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold', fontSize: '0.85rem'
                                    }}>
                                        {isPast ? <CheckCircle size={16} /> : i + 1}
                                    </div>
                                    <span style={{ fontWeight: 600, fontSize: '0.9rem', color: isActive ? '#1e293b' : '#64748b' }}>{s.label}</span>
                                </div>
                                <div style={{ fontSize: '0.8rem', color: isActive ? 'var(--color-rose-gold)' : '#94a3b8', paddingLeft: '38px' }}>
                                    Deadline: <span style={{ fontWeight: 500 }}>{s.deadline ? formatDate(s.deadline) : 'Not Set'}</span>
                                </div>
                                {isActive && (
                                    <div style={{ marginTop: '4px', fontSize: '0.75rem', color: 'var(--color-rose-gold)', fontWeight: 600, paddingLeft: '38px' }}>
                                        ACTIVE STAGE
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
                
                {/* Matrix Approval Status Tracker */}
                <div className="matrix-tracker" style={{ marginTop: '20px', display: 'flex', gap: '24px', padding: '12px', background: '#f8fafc', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                    {matrixApprovals.map((appr, i) => (
                        <div key={appr.id} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <div style={{ 
                                width: '24px', height: '24px', borderRadius: '50%', background: appr.status === 'approved' ? 'var(--color-charcoal)' : (appr.status === 'returned' ? '#ef4444' : '#cbd5e1'),
                                display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontSize: '0.8rem', fontWeight: 'bold'
                            }}>{i + 1}</div>
                            <div>
                                <div style={{ fontSize: '0.8rem', fontWeight: 600 }}>{appr.first_name} {appr.last_name}</div>
                                <div style={{ fontSize: '0.7rem', color: 'var(--color-text-muted)', textTransform: 'capitalize' }}>{appr.status}</div>
                            </div>
                            {i < matrixApprovals.length - 1 && <div style={{ width: '40px', height: '1px', background: '#cbd5e1', marginLeft: '12px' }}></div>}
                        </div>
                    ))}
                    {matrixApprovals.length === 0 && <span style={{ fontSize: '0.85rem', color: 'var(--color-text-muted)' }}>Matrix Approval: Not assigned</span>}
            </div>

            <div className="card" style={{ marginBottom: '24px' }}>
                <div style={{ padding: '32px' }}>
                    {/* SECTION A: Soft Skills */}
                    <div style={{ marginBottom: '48px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '24px', paddingBottom: '12px', borderBottom: '1px solid #e2e8f0' }}>
                            <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold' }}>A</div>
                            <h2 style={{ margin: 0, fontSize: '1.25rem' }}>Core Soft Skills & Competencies</h2>
                        </div>
                        
                        <div className="ratings-grid">
                            {template?.questions.filter(q => q.section === 'B_SOFT_SKILL').map((q) => {
                                const ratingObj = ratings.find(r => r.question_id === q.id) || {};
                                return (
                                    <div key={q.id} style={{ padding: '20px', border: '1px solid #e2e8f0', borderRadius: '8px', marginBottom: '16px', background: '#f8fafc' }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '12px' }}>
                                            <h4 style={{ margin: 0, fontSize: '1rem', fontWeight: 600 }}>{q.question_text}</h4>
                                        </div>

                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '32px' }}>
                                            <div>
                                                <label style={{ display: 'block', fontSize: '0.85rem', marginBottom: '8px', color: '#64748b' }}>Employee Self Rating (1-10)</label>
                                                <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                                                    {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map(val => (
                                                        <button key={val} 
                                                            style={{ 
                                                                width: '32px', height: '32px', borderRadius: '4px', border: '1px solid ' + (ratingObj.employee_rating === val ? 'var(--color-rose-gold)' : '#e2e8f0'),
                                                                background: ratingObj.employee_rating === val ? 'var(--color-rose-gold)' : '#fff',
                                                                color: ratingObj.employee_rating === val ? '#fff' : '#1e293b',
                                                                cursor: 'pointer', fontSize: '0.8rem'
                                                            }}
                                                            onClick={() => handleRatingChange(q.id, 'employee_rating', val)}
                                                        >{val}</button>
                                                    ))}
                                                </div>
                                            </div>
                                            <div>
                                                <label style={{ display: 'block', fontSize: '0.85rem', marginBottom: '8px', color: '#64748b' }}>Manager's Rating (1-10)</label>
                                                <div style={{ display: 'flex', gap: '4px', flexWrap: 'wrap' }}>
                                                    {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map(val => (
                                                        <button key={val} 
                                                            style={{ 
                                                                width: '32px', height: '32px', borderRadius: '4px', border: '1px solid ' + (ratingObj.manager_rating === val ? 'var(--color-rose-gold)' : '#e2e8f0'),
                                                                background: ratingObj.manager_rating === val ? 'var(--color-rose-gold)' : '#fff',
                                                                color: ratingObj.manager_rating === val ? '#fff' : '#1e293b',
                                                                cursor: 'pointer', fontSize: '0.8rem'
                                                            }}
                                                            onClick={() => handleRatingChange(q.id, 'manager_rating', val)}
                                                        >{val}</button>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* SECTION B: Performance KPIs */}
                    <div style={{ marginBottom: '48px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px', paddingBottom: '12px', borderBottom: '1px solid #e2e8f0' }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold' }}>B</div>
                                <h2 style={{ margin: 0, fontSize: '1.25rem' }}>Performance KPIs / KRAs</h2>
                            </div>
                            <button className="btn btn-sm" style={{ background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', gap: '4px' }} onClick={() => setRatings([...ratings, { kra_name: '', achievements: '', employee_rating: 0 }])}>
                                <span style={{ fontSize: '20px', lineHeight: '1' }}>+</span> Add KPI Area
                            </button>
                        </div>

                        <div className="kpi-list">
                            {ratings.filter(r => r.kra_name !== undefined || !r.question_id).length === 0 && (
                                <div style={{ padding: '40px', textAlign: 'center', background: '#f8fafc', borderRadius: '8px', border: '1px dashed #cbd5e1', color: '#64748b' }}>
                                    No specific Performance KPIs configured for this appraisal yet. Click "Add KPI Area" to start.
                                </div>
                            )}
                            {ratings.filter(r => r.kra_name !== undefined || !r.question_id).map((r, idx) => (
                                <div key={idx} style={{ padding: '24px', border: '1px solid #e2e8f0', borderRadius: '12px', marginBottom: '20px', background: '#fff', boxShadow: '0 2px 4px rgba(0,0,0,0.02)' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '16px', alignItems: 'center' }}>
                                        <h4 style={{ margin: 0, color: 'var(--color-rose-gold)', fontSize: '0.9rem', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                                            KPI {String(idx + 1).padStart(2, '0')}
                                        </h4>
                                        <button className="btn btn-secondary btn-sm" style={{ color: '#ef4444' }} 
                                            onClick={() => {
                                                const newList = [...ratings];
                                                newList.splice(ratings.indexOf(r), 1);
                                                setRatings(newList);
                                            }}>
                                            Remove
                                        </button>
                                    </div>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr 1fr', gap: '24px' }}>
                                        <div>
                                            <label className="field-label" style={{ fontWeight: 600, color: '#334155' }}>KRA / KPI Area</label>
                                            <input type="text" className="form-input" style={{ width: '100%', marginTop: '4px' }} placeholder="e.g. Sales Target" 
                                                value={r.kra_name || ''} onChange={e => {
                                                    const newList = [...ratings];
                                                    newList[ratings.indexOf(r)] = { ...r, kra_name: e.target.value };
                                                    setRatings(newList);
                                                }} />
                                        </div>
                                        <div>
                                            <label className="field-label" style={{ fontWeight: 600, color: '#334155' }}>Key Achievements</label>
                                            <textarea className="form-input" rows="3" style={{ width: '100%', marginTop: '4px' }} placeholder="Summary of what was achieved..."
                                                value={r.achievements || ''} onChange={e => {
                                                    const newList = [...ratings];
                                                    newList[ratings.indexOf(r)] = { ...r, achievements: e.target.value };
                                                    setRatings(newList);
                                                }}></textarea>
                                        </div>
                                        <div>
                                            <label className="field-label" style={{ fontWeight: 600, color: '#334155' }}>Self Rating (1-5)</label>
                                            <select className="form-input" style={{ width: '100%', marginTop: '4px' }} value={r.employee_rating || 0} onChange={e => {
                                                    const newList = [...ratings];
                                                    newList[ratings.indexOf(r)] = { ...r, employee_rating: parseInt(e.target.value) };
                                                    setRatings(newList);
                                                }}>
                                                <option value="0">Select...</option>
                                                {((template?.rating_mapping && template.rating_mapping.length > 0) ? template.rating_mapping : [
                                                    {rating: 5, label: 'Outstanding Performance'}, 
                                                    {rating: 4, label: 'Strong Performance'}, 
                                                    {rating: 3, label: 'Effective Performance'}, 
                                                    {rating: 2, label: 'Developing Performance'}, 
                                                    {rating: 1, label: 'Performance Below Expectations'}
                                                ]).map(m => (
                                                    <option key={m.rating} value={m.rating}>
                                                        {m.rating} - {m.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <div style={{ marginTop: '16px' }}>
                                                <label className="field-label" style={{ fontWeight: 600, color: 'var(--color-rose-gold)' }}>Manager Rating (1-5)</label>
                                                <select className="form-input" style={{ width: '100%', marginTop: '4px' }} value={r.manager_rating || 0} onChange={e => {
                                                    const newList = [...ratings];
                                                    newList[ratings.indexOf(r)] = { ...r, manager_rating: parseInt(e.target.value) };
                                                    setRatings(newList);
                                                }}>
                                                    <option value="0">Select...</option>
                                                    {((template?.rating_mapping && template.rating_mapping.length > 0) ? template.rating_mapping : [
                                                        {rating: 5, label: 'Outstanding Performance'}, 
                                                        {rating: 4, label: 'Strong Performance'}, 
                                                        {rating: 3, label: 'Effective Performance'}, 
                                                        {rating: 2, label: 'Developing Performance'}, 
                                                        {rating: 1, label: 'Performance Below Expectations'}
                                                    ]).map(m => (
                                                        <option key={m.rating} value={m.rating}>
                                                            {m.rating} - {m.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* SECTION C & D: Summary */}
                    <div style={{ marginBottom: '48px' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '24px', paddingBottom: '12px', borderBottom: '1px solid #e2e8f0' }}>
                            <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold' }}>C</div>
                            <h2 style={{ margin: 0, fontSize: '1.25rem' }}>Development Plan & Summary</h2>
                        </div>
                        
                        <div className="mb-4" style={{ marginBottom: '24px' }}>
                            <p style={{ color: '#64748b', fontSize: '0.9rem', marginBottom: '12px' }}>Summarize results, challenges, and training needs.</p>
                            <textarea
                                className="form-input"
                                rows="6"
                                style={{ width: '100%', padding: '12px', border: '1px solid #e2e8f0', borderRadius: '6px' }}
                                placeholder="Employee: Summary of results and development plan..."
                                value={comments.find(c => c.section === 'C_SUMMARY')?.comment_text || ''}
                                onChange={(e) => handleCommentChange('C_SUMMARY', e.target.value)}
                            ></textarea>
                        </div>

                        <div className="mb-4">
                            <h3 style={{ fontSize: '1.1rem', marginBottom: '12px' }}>Section D: Manager's Recommendation (Confidential)</h3>
                            <p style={{ color: '#64748b', fontSize: '0.9rem', marginBottom: '12px' }}>This section is hidden from the employee during the process.</p>
                            <textarea
                                className="form-input"
                                rows="6"
                                style={{ width: '100%', padding: '12px', border: '1px solid #e2e8f0', borderRadius: '6px' }}
                                placeholder="Manager: Assessment of potential and recommendation..."
                                value={comments.find(c => c.section === 'D_MANAGER')?.comment_text || ''}
                                onChange={(e) => handleCommentChange('D_MANAGER', e.target.value)}
                            ></textarea>
                        </div>
                    </div>

                    {/* SECTION E: HR Audit */}
                    <div style={{ background: '#f8fafc', padding: '32px', borderRadius: '12px', border: '1px solid #e2e8f0' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '24px', paddingBottom: '12px', borderBottom: '1px solid #e2e8f0' }}>
                            <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-charcoal)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold' }}>E</div>
                            <h2 style={{ margin: 0, fontSize: '1.25rem' }}>HR Review & Finalization</h2>
                        </div>
                        
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '32px' }}>
                            <div>
                                <label className="checkbox-label" style={{ display: 'flex', alignItems: 'center', gap: '12px', fontSize: '1rem', cursor: 'pointer', padding: '12px', background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                                    <input type="checkbox" checked={appraisal.eligible_for_increment} 
                                        onChange={e => setAppraisal({...appraisal, eligible_for_increment: e.target.checked})}
                                        style={{ width: '20px', height: '20px' }} />
                                    <span>Eligible for Salary Increment</span>
                                </label>
                            </div>
                            <div>
                                <label className="checkbox-label" style={{ display: 'flex', alignItems: 'center', gap: '12px', fontSize: '1rem', cursor: 'pointer', padding: '12px', background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                                    <input type="checkbox" checked={appraisal.eligible_for_bonus} 
                                        onChange={e => setAppraisal({...appraisal, eligible_for_bonus: e.target.checked})}
                                        style={{ width: '20px', height: '20px' }} />
                                    <span>Eligible for Performance Bonus</span>
                                </label>
                            </div>
                        </div>
                        <div style={{ marginTop: '24px' }}>
                            <label style={{ display: 'block', marginBottom: '8px', fontWeight: 600 }}>Final HR Adjusted Rating (1-5 Stars)</label>
                            <select className="form-input" style={{ width: '100%', maxWidth: '600px', fontSize: '1.05rem', padding: '12px' }} 
                                value={appraisal.final_rating || 0} 
                                onChange={e => setAppraisal({...appraisal, final_rating: parseFloat(e.target.value)})}
                                disabled={!canEditHR}
                            >
                                <option value="0" disabled>Select Final Rating...</option>
                                <option value="5">5 - Outstanding Performance (Consistently surpasses objectives & demonstrates exceptional initiative)</option>
                                <option value="4">4 - Strong Performance (Frequently exceeds expectations & delivers results above standard)</option>
                                <option value="3">3 - Effective Performance (Reliably meets job expectations & delivers quality results)</option>
                                <option value="2">2 - Developing Performance (Partially meets expectations; improvement required in key areas)</option>
                                <option value="1">1 - Performance Below Expectations (Does not consistently meet position requirements)</option>
                            </select>
                        </div>

                        <div style={{ marginTop: '32px', padding: '20px', background: '#ecfdf5', border: '1px solid var(--color-charcoal)', borderRadius: '12px', color: '#065f46', display: 'flex', gap: '12px' }}>
                            <AlertCircle size={24} style={{ flexShrink: 0 }} />
                            <div>
                                <strong>Finalization Note:</strong> Once finalized, this data will be synced with the Payroll module to update the salary structure for the next month.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AppraisalForm;
