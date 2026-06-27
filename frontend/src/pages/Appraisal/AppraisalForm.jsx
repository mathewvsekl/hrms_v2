import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Save, Send, CheckCircle, ChevronLeft, User, BarChart, FileText, Settings, Star, AlertCircle, ChevronDown, ChevronUp } from 'lucide-react';
import api from '../../services/api';
import useAuthStore from '../../store/useAuthStore';
import useLayoutStore from '../../store/useLayoutStore';
import useNotificationStore from '../../store/useNotificationStore';
import { formatDate } from '../../utils/dateUtils';
import { ROLE_IDS } from '../../utils/roleConstants';

const SOFTSKILL_DESCRIPTIONS = {
    'Communication': 'Communicates clearly and professionally with colleagues, customers, and stakeholders.',
    'Teamwork & Collaboration': 'Works effectively with others and contributes positively to team goals.',
    'Accountability & Ownership': 'Takes responsibility for assigned tasks and ensures work is completed as expected.',
    'Professional Conduct': 'Demonstrates professionalism, respect, and appropriate workplace behavior.',
    'Adaptability & Flexibility': 'Adjusts effectively to changes in work, priorities, or environment.',
    'Initiative & Proactiveness': 'Shows willingness to take initiative and go beyond assigned responsibilities when required.',
    'Problem-Solving Ability': 'Approaches challenges logically and contributes to effective solutions.',
    'Time Management & Discipline': 'Manages time effectively and meets deadlines consistently.',
    'Quality & Attention to Detail': 'Maintains accuracy and quality in work outputs.',
    'Integrity and Work Ethics': 'Acts with honesty, reliability, and adherence to company values.'
};

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
    const [openSection, setOpenSection] = useState(null);

    const toggleSection = (sec) => {
        setOpenSection(openSection === sec ? null : sec);
    };

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
            const index = prev.findIndex(r => r.question_id == qId);
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
            const isEmployee = user?.employee_id == appraisal?.employee_id || user?.id == appraisal?.employee_id;
            if (submitStatus === 'manager' && isEmployee) {
                const minRequired = appraisal.min_kpis_required !== undefined ? appraisal.min_kpis_required : 3;
                const addedKpis = ratings.filter(r => !r.question_id);
                
                // Validate that added KPIs are fully filled out
                for (const kpi of addedKpis) {
                    if (!kpi.kra_name || kpi.kra_name.trim() === '') {
                        showAlert('Validation Error', 'Please fill in the KRA / KPI Area name for all added KPIs, or remove the blank ones.', 'error');
                        return;
                    }
                    if (!kpi.employee_rating || kpi.employee_rating === 0) {
                        showAlert('Validation Error', `Please provide a Self Rating for KPI: ${kpi.kra_name}`, 'error');
                        return;
                    }
                }

                if (addedKpis.length < minRequired) {
                    showAlert('Validation Error', `Minimum ${minRequired} KPIs are required before submitting. You have ${addedKpis.length}.`, 'error');
                    return;
                }
            }

            // Always save current state first
            await api.post('/appraisals/draft', payload);

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
                const msg = 'Draft saved successfully';
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
    const isSuperAdmin = user?.role_id === ROLE_IDS.SUPER_ADMIN || user?.role_id === ROLE_IDS.ADMIN;
    const isManagerRole = isSuperAdmin || useAuthStore.getState().hasPermission('appraisals', 'approve');
    const isDirectManager = user?.employee_id == appraisal.reporting_manager_id || user?.id == appraisal.reporting_manager_id;
    const isHR = isSuperAdmin || useAuthStore.getState().hasPermission('appraisals', 'edit');

    // A person can edit as an employee if it's their own appraisal
    // Feature: Employee can edit until manager approves (hr_calibration)
    const isManagerTier = ['l1_review', 'l2_review', 'l3_review'].includes(appraisal.status);
    const canEditEmployee = (appraisal.status === 'draft' || isManagerTier) && (isAppraisee || (user?.role_id === ROLE_IDS.EMPLOYEE && isAppraisee));
    
    const canEditManager = isManagerTier && (isManagerRole || isDirectManager) && !isAppraisee; // Backend validates exact manager identity
    const canEditHR = appraisal.status === 'hr_calibration' && isHR;

    return (
        <div className="appraisal-detail">

            <div className="header-actions" style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end', marginBottom: '24px' }}>
                <button className="btn btn-secondary" onClick={() => onSave()} disabled={saving || appraisal.status === 'finalized'}>
                    <Save size={18} /> {saving ? 'Saving...' : 'Save Draft'}
                </button>

                {canEditEmployee && appraisal.status === 'draft' && (
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
                    <div style={{ marginBottom: '24px', border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', background: '#fff' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 24px', background: '#f8fafc', cursor: 'pointer' }} onClick={() => toggleSection('A')}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold' }}>A</div>
                                <h2 style={{ margin: 0, fontSize: '1.25rem' }}>Core Soft Skills & Competencies</h2>
                            </div>
                            {openSection === 'A' ? <ChevronUp size={24} color="#64748b" /> : <ChevronDown size={24} color="#64748b" />}
                        </div>
                        
                        {openSection === 'A' && (
                            <div style={{ padding: '24px', borderTop: '1px solid #e2e8f0' }}>
                                <div className="table-responsive">
                            <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #e2e8f0' }}>
                                <thead>
                                    <tr style={{ background: '#f8fafc' }}>
                                        <th style={{ padding: '12px', border: '1px solid #e2e8f0', textAlign: 'left', fontWeight: 600 }}>Soft Skills</th>
                                        <th style={{ padding: '12px', border: '1px solid #e2e8f0', textAlign: 'left', fontWeight: 600 }}>Guiding Description (for reference Only)</th>
                                        <th style={{ padding: '12px', border: '1px solid #e2e8f0', textAlign: 'center', fontWeight: 600 }}>Employee Rating</th>
                                        <th style={{ padding: '12px', border: '1px solid #e2e8f0', textAlign: 'center', fontWeight: 600 }}>Manager Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {template?.questions.filter(q => q.section === 'B_SOFT_SKILL').map((q) => {
                                        const ratingObj = ratings.find(r => r.question_id == q.id) || {};
                                        const description = q.description || SOFTSKILL_DESCRIPTIONS[q.question_text] || '';
                                        const maxRating = q.rating_scale_max || 5;
                                        const scaleOptions = Array.from({length: maxRating}, (_, i) => i + 1);
                                        return (
                                            <tr key={q.id}>
                                                <td style={{ padding: '12px', border: '1px solid #e2e8f0', fontWeight: 500 }}>{q.question_text}</td>
                                                <td style={{ padding: '12px', border: '1px solid #e2e8f0', fontSize: '0.9rem', color: '#64748b' }}>{description}</td>
                                                <td style={{ padding: '12px', border: '1px solid #e2e8f0', textAlign: 'center' }}>
                                                    <select className="form-input" style={{ width: '100%', padding: '6px' }} value={Number(ratingObj.employee_rating) || 0} onChange={e => {
                                                            canEditEmployee && handleRatingChange(q.id, 'employee_rating', parseInt(e.target.value));
                                                        }} disabled={!canEditEmployee}>
                                                        <option value="0">Select...</option>
                                                        {scaleOptions.map(val => (
                                                            <option key={val} value={val}>{val}</option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td style={{ padding: '12px', border: '1px solid #e2e8f0', textAlign: 'center' }}>
                                                    <select className="form-input" style={{ width: '100%', padding: '6px', borderColor: 'var(--color-rose-gold)' }} value={Number(ratingObj.manager_rating) || 0} onChange={e => {
                                                            canEditManager && handleRatingChange(q.id, 'manager_rating', parseInt(e.target.value));
                                                        }} disabled={!canEditManager}>
                                                        <option value="0">Select...</option>
                                                        {scaleOptions.map(val => (
                                                            <option key={val} value={val}>{val}</option>
                                                        ))}
                                                    </select>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    <tr style={{ background: '#f1f5f9', fontWeight: 600 }}>
                                        <td colSpan="2" style={{ padding: '12px', border: '1px solid #e2e8f0', textAlign: 'left' }}>Overall Rating</td>
                                        <td style={{ padding: '12px', border: '1px solid #e2e8f0', textAlign: 'center' }}>
                                            {template?.questions.filter(q => q.section === 'B_SOFT_SKILL').reduce((sum, q) => sum + (Number(ratings.find(r => r.question_id == q.id)?.employee_rating) || 0), 0)}
                                        </td>
                                        <td style={{ padding: '12px', border: '1px solid #e2e8f0', textAlign: 'center', color: 'var(--color-rose-gold)' }}>
                                            {template?.questions.filter(q => q.section === 'B_SOFT_SKILL').reduce((sum, q) => sum + (Number(ratings.find(r => r.question_id == q.id)?.manager_rating) || 0), 0)}
                                        </td>
                                    </tr>
                                </tbody>
                                </table>
                            </div>
                        </div>
                        )}
                    </div>

                    {/* SECTION B: Performance KPIs */}
                    <div style={{ marginBottom: '24px', border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', background: '#fff' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 24px', background: '#f8fafc', cursor: 'pointer' }} onClick={() => toggleSection('B')}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold' }}>B</div>
                                <h2 style={{ margin: 0, fontSize: '1.25rem' }}>Performance KPIs / KRAs</h2>
                            </div>
                            {openSection === 'B' ? <ChevronUp size={24} color="#64748b" /> : <ChevronDown size={24} color="#64748b" />}
                        </div>

                        {openSection === 'B' && (
                            <div style={{ padding: '24px', borderTop: '1px solid #e2e8f0' }}>
                                <div className="kpi-list">
                            {ratings.filter(r => !r.question_id).length === 0 && (
                                <div style={{ padding: '40px', textAlign: 'center', background: '#f8fafc', borderRadius: '8px', border: '1px dashed #cbd5e1', color: '#64748b' }}>
                                    No specific Performance KPIs configured for this appraisal yet. Click "Add KPI Area" to start.
                                </div>
                            )}
                            {ratings.filter(r => !r.question_id).map((r, idx) => (
                                <div key={idx} style={{ padding: '24px', border: '1px solid #e2e8f0', borderRadius: '12px', marginBottom: '20px', background: '#fff', boxShadow: '0 2px 4px rgba(0,0,0,0.02)' }}>
                                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '16px', alignItems: 'center' }}>
                                        <h4 style={{ margin: 0, color: '#166534', fontSize: '0.9rem', fontWeight: 700, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                                            KPI {String(idx + 1).padStart(2, '0')}
                                        </h4>
                                        {canEditEmployee && (
                                            <button className="btn btn-secondary btn-sm" style={{ color: '#ef4444' }} 
                                                onClick={() => {
                                                    const newList = [...ratings];
                                                    newList.splice(ratings.indexOf(r), 1);
                                                    setRatings(newList);
                                                }}>
                                                Remove
                                            </button>
                                        )}
                                    </div>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr 1fr', columnGap: '24px', rowGap: '20px' }}>
                                        {/* ROW 1: Employee Inputs */}
                                        <div>
                                            <label className="field-label" style={{ fontWeight: 600, color: '#1e3a8a' }}>KRA / KPI Area</label>
                                            <input type="text" className="form-input" style={{ width: '100%', marginTop: '4px' }} placeholder="e.g. Sales Target" 
                                                value={r.kra_name || ''} onChange={e => {
                                                    const newList = [...ratings];
                                                    const actualIdx = ratings.indexOf(r);
                                                    if (actualIdx > -1) {
                                                        newList[actualIdx] = { ...newList[actualIdx], kra_name: e.target.value };
                                                        setRatings(newList);
                                                    }
                                                }} disabled={!canEditEmployee} />
                                        </div>
                                        <div>
                                            <label className="field-label" style={{ fontWeight: 600, color: '#1e3a8a' }}>Key Achievements</label>
                                            <textarea className="form-input" rows="2" style={{ width: '100%', marginTop: '4px' }} placeholder="Summary of what was achieved..."
                                                value={r.achievements || ''} onChange={e => {
                                                    const newList = [...ratings];
                                                    const actualIdx = ratings.indexOf(r);
                                                    if (actualIdx > -1) {
                                                        newList[actualIdx] = { ...newList[actualIdx], achievements: e.target.value };
                                                        setRatings(newList);
                                                    }
                                                }} disabled={!canEditEmployee}></textarea>
                                        </div>
                                        <div>
                                            <label className="field-label" style={{ fontWeight: 600, color: '#1e3a8a' }}>Self Rating</label>
                                            <select className="form-input" style={{ width: '100%', marginTop: '4px' }} value={Number(r.employee_rating) || 0} onChange={e => {
                                                    const newList = [...ratings];
                                                    const actualIdx = ratings.indexOf(r);
                                                    if (actualIdx > -1) {
                                                        newList[actualIdx] = { ...newList[actualIdx], employee_rating: parseInt(e.target.value) };
                                                        setRatings(newList);
                                                    }
                                                }} disabled={!canEditEmployee}>
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

                                        {/* ROW 2: Manager Inputs */}
                                        <div></div> {/* Empty column under KRA */}
                                        <div>
                                            <label className="field-label" style={{ fontWeight: 600, color: '#166534' }}>Manager's Feedback</label>
                                            <textarea className="form-input" rows="2" style={{ width: '100%', marginTop: '4px', borderColor: '#166534' }} placeholder="Manager's thoughts on this KPI..."
                                                value={r.manager_comment || ''} onChange={e => {
                                                    const newList = [...ratings];
                                                    const actualIdx = ratings.indexOf(r);
                                                    if (actualIdx > -1) {
                                                        newList[actualIdx] = { ...newList[actualIdx], manager_comment: e.target.value };
                                                        setRatings(newList);
                                                    }
                                                }} disabled={!canEditManager}></textarea>
                                        </div>
                                        <div>
                                            <label className="field-label" style={{ fontWeight: 600, color: '#166534' }}>Manager Rating</label>
                                            <select className="form-input" style={{ width: '100%', marginTop: '4px', borderColor: '#166534' }} value={Number(r.manager_rating) || 0} onChange={e => {
                                                const newList = [...ratings];
                                                const actualIdx = ratings.indexOf(r);
                                                if (actualIdx > -1) {
                                                    newList[actualIdx] = { ...newList[actualIdx], manager_rating: parseInt(e.target.value) };
                                                    setRatings(newList);
                                                }
                                            }} disabled={!canEditManager}>
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
                            ))}
                            {canEditEmployee && (
                                <div style={{ display: 'flex', justifyContent: 'center', marginTop: '24px' }}>
                                    <button className="btn" style={{ background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 24px', borderRadius: '8px', fontWeight: 'bold' }} onClick={() => setRatings([...ratings, { kra_name: '', achievements: '', employee_rating: 0 }])}>
                                        <span style={{ fontSize: '20px', lineHeight: '1' }}>+</span> Add KPI Area
                                    </button>
                                </div>
                            )}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* SECTION C & D: Summary */}
                    <div style={{ marginBottom: '24px', border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', background: '#fff' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 24px', background: '#f8fafc', cursor: 'pointer' }} onClick={() => toggleSection('C')}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold' }}>C</div>
                                <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#166534' }}>Employee Summary and Development Plan</h2>
                            </div>
                            {openSection === 'C' ? <ChevronUp size={24} color="#64748b" /> : <ChevronDown size={24} color="#64748b" />}
                        </div>
                        
                        {openSection === 'C' && (
                            <div style={{ padding: '24px', borderTop: '1px solid #e2e8f0' }}>
                                <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #1e293b', marginBottom: '32px' }}>
                            <thead>
                                <tr>
                                    <th style={{ width: '40%', padding: '12px', border: '1px solid #1e293b', background: '#d1d5db', textAlign: 'center', fontWeight: 600, color: '#111827' }}>Item</th>
                                    <th style={{ width: '60%', padding: '12px', border: '1px solid #1e293b', background: '#d1d5db', textAlign: 'center', fontWeight: 600, color: '#111827' }}>Description / Employee Input</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                        <strong style={{ color: '#111827' }}>Overall Performance Summary</strong> (Briefly summarize your performance and contribution during the year.)
                                    </td>
                                    <td style={{ padding: '0', border: '1px solid #1e293b' }}>
                                        <textarea className="form-input" rows="4" style={{ width: '100%', height: '100%', border: 'none', resize: 'vertical', borderRadius: 0 }}
                                            value={comments.find(c => c.section === 'C_SUMMARY_OVERALL')?.comment_text || comments.find(c => c.section === 'C_SUMMARY')?.comment_text || ''}
                                            onChange={(e) => handleCommentChange('C_SUMMARY_OVERALL', e.target.value)}
                                            disabled={!canEditEmployee}></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                        <strong style={{ color: '#111827' }}>Challenges Faced</strong> (Mention key obstacles or situations that affected your performance and how you managed them.)
                                    </td>
                                    <td style={{ padding: '0', border: '1px solid #1e293b' }}>
                                        <textarea className="form-input" rows="4" style={{ width: '100%', height: '100%', border: 'none', resize: 'vertical', borderRadius: 0 }}
                                            value={comments.find(c => c.section === 'C_SUMMARY_CHALLENGES')?.comment_text || ''}
                                            onChange={(e) => handleCommentChange('C_SUMMARY_CHALLENGES', e.target.value)}
                                            disabled={!canEditEmployee}></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                        <strong style={{ color: '#111827' }}>Areas of Improvement / Development</strong> (Identify skills or areas you wish to improve in the next review period.)
                                    </td>
                                    <td style={{ padding: '0', border: '1px solid #1e293b' }}>
                                        <textarea className="form-input" rows="4" style={{ width: '100%', height: '100%', border: 'none', resize: 'vertical', borderRadius: 0 }}
                                            value={comments.find(c => c.section === 'C_SUMMARY_IMPROVEMENT')?.comment_text || ''}
                                            onChange={(e) => handleCommentChange('C_SUMMARY_IMPROVEMENT', e.target.value)}
                                            disabled={!canEditEmployee}></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                        <strong style={{ color: '#111827' }}>Training / Support Required</strong> (Specify if any training, mentoring, or support is needed from the organization to enhance your performance.)
                                    </td>
                                    <td style={{ padding: '0', border: '1px solid #1e293b' }}>
                                        <textarea className="form-input" rows="4" style={{ width: '100%', height: '100%', border: 'none', resize: 'vertical', borderRadius: 0 }}
                                            value={comments.find(c => c.section === 'C_SUMMARY_TRAINING')?.comment_text || ''}
                                            onChange={(e) => handleCommentChange('C_SUMMARY_TRAINING', e.target.value)}
                                            disabled={!canEditEmployee}></textarea>
                                    </td>
                                </tr>
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* SECTION D: Manager Assessment */}
                    <div style={{ marginBottom: '24px', border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', background: '#fff' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 24px', background: '#f8fafc', cursor: 'pointer' }} onClick={() => toggleSection('D')}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold' }}>D</div>
                                <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#166534' }}>Manager Assessment – Overall Performance Evaluation</h2>
                            </div>
                            {openSection === 'D' ? <ChevronUp size={24} color="#64748b" /> : <ChevronDown size={24} color="#64748b" />}
                        </div>
                        
                        {openSection === 'D' && (
                            <div style={{ padding: '24px', borderTop: '1px solid #e2e8f0' }}>
                                <p style={{ color: '#64748b', fontSize: '0.9rem', marginBottom: '12px' }}>This section is hidden from the employee during the process.</p>
                                
                                <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #1e293b', marginBottom: '32px' }}>
                                <thead>
                                    <tr>
                                        <th style={{ width: '40%', padding: '12px', border: '1px solid #1e293b', background: '#d1d5db', textAlign: 'left', fontWeight: 600, color: '#111827' }}>Evaluation Area</th>
                                        <th style={{ width: '60%', padding: '12px', border: '1px solid #1e293b', background: '#d1d5db', textAlign: 'left', fontWeight: 600, color: '#111827' }}>Manager Comments / Summary and Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                            <strong style={{ color: '#111827' }}>Overall Achievement of KPIs</strong> (Summarize the employee's performance across all KPIs (e.g., met expectations, exceeded in certain areas, or needs improvement).)
                                        </td>
                                        <td style={{ padding: '0', border: '1px solid #1e293b' }}>
                                            <textarea className="form-input" rows="4" style={{ width: '100%', height: '100%', border: 'none', resize: 'vertical', borderRadius: 0 }}
                                                value={comments.find(c => c.section === 'D_MANAGER_ACHIEVEMENT')?.comment_text || ''}
                                                onChange={(e) => handleCommentChange('D_MANAGER_ACHIEVEMENT', e.target.value)}
                                                disabled={!canEditManager}></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                            <strong style={{ color: '#111827' }}>Manager's Final Overall Rating</strong> (Provide your overall performance rating for the employee)
                                        </td>
                                        <td style={{ padding: '0', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                            <select className="form-input" style={{ width: '100%', height: '100%', border: 'none', borderRadius: 0, padding: '12px' }}
                                                value={comments.find(c => c.section === 'D_MANAGER_RATING')?.comment_text || ''}
                                                onChange={(e) => handleCommentChange('D_MANAGER_RATING', e.target.value)}
                                                disabled={!canEditManager}
                                            >
                                                <option value="" disabled>Select Rating...</option>
                                                <option value="5">5 - Outstanding Performance</option>
                                                <option value="4">4 - Strong Performance</option>
                                                <option value="3">3 - Effective Performance</option>
                                                <option value="2">2 - Developing Performance</option>
                                                <option value="1">1 - Performance Below Expectations</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                            <strong style={{ color: '#111827' }}>Manager's Recommendation</strong> (Indicate if the employee is ready for higher responsibility, needs development, or is a consistent performer.)
                                        </td>
                                        <td style={{ padding: '0', border: '1px solid #1e293b' }}>
                                            <textarea className="form-input" rows="4" style={{ width: '100%', height: '100%', border: 'none', resize: 'vertical', borderRadius: 0 }}
                                                value={comments.find(c => c.section === 'D_MANAGER_RECOMMENDATION')?.comment_text || comments.find(c => c.section === 'D_MANAGER')?.comment_text || ''}
                                                onChange={(e) => handleCommentChange('D_MANAGER_RECOMMENDATION', e.target.value)}
                                                disabled={!canEditManager}></textarea>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>

                    {/* SECTION E: HR Audit */}
                    <div style={{ marginBottom: '24px', border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', background: '#fff' }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '16px 24px', background: '#f8fafc', cursor: 'pointer' }} onClick={() => toggleSection('E')}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: 'var(--color-rose-gold)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 'bold' }}>E</div>
                                <h2 style={{ margin: 0, fontSize: '1.25rem', color: '#166534' }}>HR Review & Finalization</h2>
                            </div>
                            {openSection === 'E' ? <ChevronUp size={24} color="#64748b" /> : <ChevronDown size={24} color="#64748b" />}
                        </div>
                        
                        {openSection === 'E' && (
                            <div style={{ padding: '24px', borderTop: '1px solid #e2e8f0' }}>
                                <table style={{ width: '100%', borderCollapse: 'collapse', border: '1px solid #1e293b', marginBottom: '24px', background: '#fff' }}>
                            <thead>
                                <tr>
                                    <th style={{ width: '40%', padding: '12px', border: '1px solid #1e293b', background: '#d1d5db', textAlign: 'left', fontWeight: 600, color: '#111827' }}>Review Area</th>
                                    <th style={{ width: '60%', padding: '12px', border: '1px solid #1e293b', background: '#d1d5db', textAlign: 'left', fontWeight: 600, color: '#111827' }}>HR Action / Input</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'middle' }}>
                                        <strong style={{ color: '#111827' }}>Increment Eligibility</strong>
                                    </td>
                                    <td style={{ padding: '12px', border: '1px solid #1e293b' }}>
                                        <label className="checkbox-label" style={{ display: 'flex', alignItems: 'center', gap: '12px', fontSize: '1rem', cursor: 'pointer', margin: 0 }}>
                                            <input type="checkbox" checked={appraisal.eligible_for_increment} 
                                                onChange={e => setAppraisal({...appraisal, eligible_for_increment: e.target.checked})}
                                                style={{ width: '20px', height: '20px' }} disabled={!canEditHR} />
                                            <span>Eligible for Salary Increment</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'middle' }}>
                                        <strong style={{ color: '#111827' }}>Bonus Eligibility</strong>
                                    </td>
                                    <td style={{ padding: '12px', border: '1px solid #1e293b' }}>
                                        <label className="checkbox-label" style={{ display: 'flex', alignItems: 'center', gap: '12px', fontSize: '1rem', cursor: 'pointer', margin: 0 }}>
                                            <input type="checkbox" checked={appraisal.eligible_for_bonus} 
                                                onChange={e => setAppraisal({...appraisal, eligible_for_bonus: e.target.checked})}
                                                style={{ width: '20px', height: '20px' }} disabled={!canEditHR} />
                                            <span>Eligible for Performance Bonus</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td style={{ padding: '12px', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                        <strong style={{ color: '#111827' }}>Final HR Adjusted Rating</strong>
                                    </td>
                                    <td style={{ padding: '0', border: '1px solid #1e293b', verticalAlign: 'top' }}>
                                        <select className="form-input" style={{ width: '100%', height: '100%', border: 'none', borderRadius: 0, padding: '12px' }} 
                                            value={appraisal.final_rating || 0} 
                                            onChange={e => setAppraisal({...appraisal, final_rating: parseFloat(e.target.value)})}
                                            disabled={!canEditHR}
                                        >
                                            <option value="0" disabled>Select Final Rating...</option>
                                            <option value="5">5 - Outstanding Performance</option>
                                            <option value="4">4 - Strong Performance</option>
                                            <option value="3">3 - Effective Performance</option>
                                            <option value="2">2 - Developing Performance</option>
                                            <option value="1">1 - Performance Below Expectations</option>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div style={{ marginTop: '32px', padding: '20px', background: '#ecfdf5', border: '1px solid var(--color-charcoal)', borderRadius: '12px', color: '#065f46', display: 'flex', gap: '12px' }}>
                            <AlertCircle size={24} style={{ flexShrink: 0 }} />
                            <div>
                                <strong>Finalization Note:</strong> Once finalized, this data will be synced with the Payroll module to update the salary structure for the next month.
                            </div>
                            </div>
                        </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AppraisalForm;
