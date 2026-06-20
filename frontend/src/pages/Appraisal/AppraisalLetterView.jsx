import React, { useState, useEffect } from 'react';
import api from '../../services/api';
import { FileText, CheckCircle, AlertCircle } from 'lucide-react';
import useAuthStore from '../../store/useAuthStore';
import useNotificationStore from '../../store/useNotificationStore';
import { useParams } from 'react-router-dom';
import html2pdf from 'html2pdf.js';
import { ROLE_IDS } from '../../utils/roleConstants';

const AppraisalLetterView = ({ appraisalId: propAppraisalId, onAcknowledged }) => {
    const { id: urlAppraisalId } = useParams();
    const appraisalId = propAppraisalId || urlAppraisalId;
    const user = useAuthStore(state => state.user) || {};
    const isSuperAdmin = user?.role_id === ROLE_IDS.SUPER_ADMIN || user?.role_id === ROLE_IDS.ADMIN;
    const isHR = isSuperAdmin || useAuthStore.getState().hasPermission('appraisals', 'edit');
    const showAlert = useNotificationStore(state => state.showAlert);

    const [letter, setLetter] = useState(null);
    const [loading, setLoading] = useState(true);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (appraisalId) fetchLetter();
    }, [appraisalId]);

    const fetchLetter = async () => {
        try {
            setLoading(true);
            const response = await api.get(`/appraisals/${appraisalId}/letter`);
            setLetter(response.data);
        } catch (error) {
            console.error("Error fetching letter", error);
        } finally {
            setLoading(false);
        }
    };

    const handleAcknowledge = async () => {
        if (!window.confirm('By clicking OK, you digitally acknowledge the receipt and acceptance of your final appraisal and salary revision.')) {
            return;
        }

        try {
            setSubmitting(true);
            await api.post(`/appraisals/${letter.appraisal_id}/acknowledge-letter`);
            showAlert('Success', 'Letter acknowledged successfully.', 'success');
            fetchLetter();
            if (onAcknowledged) onAcknowledged();
        } catch (error) {
            showAlert('Error', 'Failed to acknowledge letter.', 'error');
        } finally {
            setSubmitting(false);
        }
    };

    const handlePublish = async () => {
        if (!window.confirm('Are you sure you want to publish this letter to the employee?')) return;
        try {
            setSubmitting(true);
            await api.post(`/appraisals/${letter.appraisal_id}/publish-letter`);
            showAlert('Success', 'Letter published successfully.', 'success');
            fetchLetter();
        } catch (error) {
            showAlert('Error', 'Failed to publish letter.', 'error');
        } finally {
            setSubmitting(false);
        }
    };

    const handleDownload = () => {
        const element = document.getElementById('letter-content-render');
        if (!element) return;
        
        const opt = {
            margin:       1,
            filename:     `Salary_Revision_Letter_${appraisalId}.pdf`,
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2 },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        // Make element visible for pdf generation
        element.style.display = 'block';
        html2pdf().set(opt).from(element).save().then(() => {
            element.style.display = 'none';
        });
    };

    if (loading) return <div style={{ padding: '40px', textAlign: 'center', color: 'var(--color-text-muted)' }}>Loading appraisal letter details...</div>;

    if (!letter) {
        return (
            <div style={{ background: '#eff6ff', borderLeft: '4px solid #3b82f6', padding: '16px', display: 'flex', alignItems: 'flex-start', borderRadius: '4px' }}>
                <AlertCircle style={{ color: '#3b82f6', marginRight: '12px', marginTop: '2px' }} size={20} />
                <p style={{ margin: 0, color: '#1e3a8a' }}>No appraisal letter has been published for this cycle yet.</p>
            </div>
        );
    }

    if (letter.status === 'Draft' && !isHR) {
        return (
            <div style={{ background: '#fefce8', borderLeft: '4px solid #eab308', padding: '16px', display: 'flex', alignItems: 'flex-start', borderRadius: '4px' }}>
                <AlertCircle style={{ color: '#eab308', marginRight: '12px', marginTop: '2px' }} size={20} />
                <p style={{ margin: 0, color: '#854d0e' }}>Your letter is currently being processed.</p>
            </div>
        );
    }

    return (
        <div className="card">
            <div className="card-header" style={{ background: 'var(--color-bg-light)' }}>
                <h2 style={{ margin: 0, fontSize: '1.2rem' }}>Final Appraisal & Salary Revision Letter</h2>
            </div>
            
            <div className="card-body" style={{ padding: '24px' }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', alignItems: 'center', gap: '16px' }}>
                    <div>
                        <h3 style={{ margin: '0 0 8px 0', fontSize: '1rem', color: 'var(--color-charcoal)' }}>Your official salary revision letter is available.</h3>
                        <p style={{ margin: '0 0 12px 0', fontSize: '0.9rem', color: 'var(--color-text-muted)' }}>Published on: {new Date(letter.published_at).toLocaleDateString()}</p>
                        <p style={{ margin: 0, fontSize: '0.9rem' }}>
                            <span style={{ fontWeight: 600, color: 'var(--color-charcoal)' }}>Status: </span> 
                            <span style={{
                                padding: '4px 10px',
                                borderRadius: '4px',
                                fontSize: '0.8rem',
                                fontWeight: 600,
                                background: letter.status === 'Published' ? '#dbeafe' : '#dcfce7',
                                color: letter.status === 'Published' ? '#1e40af' : '#166534',
                                marginLeft: '8px'
                            }}>
                                {letter.status}
                            </span>
                        </p>
                    </div>
                    
                    <div style={{ display: 'flex', gap: '12px' }}>
                        {letter.status === 'Draft' && isHR && (
                            <button 
                                className="btn btn-primary"
                                style={{ display: 'flex', alignItems: 'center', gap: '8px', opacity: submitting ? 0.7 : 1 }}
                                onClick={handlePublish}
                                disabled={submitting}
                            >
                                <CheckCircle size={16} />
                                {submitting ? 'Publishing...' : 'Publish to Employee'}
                            </button>
                        )}

                        <button 
                            className="btn btn-secondary"
                            style={{ display: 'flex', alignItems: 'center', gap: '8px' }}
                            onClick={handleDownload}
                        >
                            <FileText size={16} />
                            View / Download PDF
                        </button>

                        {letter.status === 'Published' && !isHR && (
                            <button 
                                className="btn btn-primary"
                                style={{ display: 'flex', alignItems: 'center', gap: '8px', opacity: submitting ? 0.7 : 1 }}
                                onClick={handleAcknowledge}
                                disabled={submitting}
                            >
                                <CheckCircle size={16} />
                                {submitting ? 'Processing...' : 'Acknowledge & Accept'}
                            </button>
                        )}
                    </div>
                </div>
                
                {letter.status === 'Acknowledged' && (
                    <div style={{ marginTop: '24px', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '6px', padding: '16px', display: 'flex', alignItems: 'flex-start' }}>
                        <CheckCircle style={{ color: '#22c55e', marginRight: '12px', marginTop: '2px' }} size={20} />
                        <div>
                            <p style={{ margin: '0 0 4px 0', fontSize: '0.95rem', color: '#166534', fontWeight: 600 }}>Digital Acknowledgment Complete</p>
                            <p style={{ margin: 0, fontSize: '0.85rem', color: '#15803d' }}>You digitally acknowledged this letter on {new Date(letter.acknowledged_at).toLocaleString()}</p>
                        </div>
                    </div>
                )}
            </div>

            {/* Hidden div purely for PDF generation */}
            <div style={{ display: 'none' }}>
                <div id="letter-content-render" style={{ padding: '40px', fontFamily: 'Arial, sans-serif', color: '#000', fontSize: '14px', lineHeight: '1.6' }}>
                    <div style={{ textAlign: 'center', marginBottom: '40px', borderBottom: '2px solid #333', paddingBottom: '20px' }}>
                        <h1 style={{ margin: 0, fontSize: '24px', letterSpacing: '2px', textTransform: 'uppercase' }}>AvantGarde HRMS</h1>
                        <p style={{ margin: '5px 0 0 0', color: '#666' }}>Official Salary Revision & Appraisal Letter</p>
                    </div>

                    <div style={{ marginBottom: '30px' }}>
                        <p><strong>Date:</strong> {new Date().toLocaleDateString()}</p>
                        <p><strong>Status:</strong> {letter.status}</p>
                    </div>

                    <div style={{ whiteSpace: 'pre-wrap', marginBottom: '40px' }}>
                        {letter.letter_content}
                    </div>

                    <div style={{ marginTop: '50px', borderTop: '1px solid #ddd', paddingTop: '20px' }}>
                        <p style={{ fontStyle: 'italic', color: '#555' }}>This is an automatically generated document. It does not require a physical signature.</p>
                        {letter.status === 'Acknowledged' && (
                            <p style={{ color: 'green', fontWeight: 'bold' }}>Electronically Acknowledged on {new Date(letter.acknowledged_at).toLocaleString()}</p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default AppraisalLetterView;
