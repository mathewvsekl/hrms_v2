import { useState, useEffect } from 'react';
import { UserPlus, Briefcase, FileSignature, CheckCircle, ChevronRight, ChevronLeft, Save, Loader, ArrowLeft, Plus, User, Mail, Phone, Globe, Building2, Users, ShieldCheck, CreditCard, XCircle, Edit3, History } from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useLayoutStore from '../store/useLayoutStore';
import COUNTRY_DATA from '../data/countryData';
import DateInput from '../components/ui/DateInput';
import PhoneInput from '../components/ui/PhoneInput';
import { formatDate, formatDateTime } from '../utils/dateUtils';
import useNotificationStore from '../store/useNotificationStore';
import { ROLE_IDS } from '../utils/roleConstants';

const Onboarding = () => {
    const [view, setView] = useState('list');
    const [onboardingList, setOnboardingList] = useState([]);
    const [step, setStep] = useState(1);
    const [loading, setLoading] = useState(false);
    const [approvalHistory, setApprovalHistory] = useState([]);
    const [userCountryCode, setUserCountryCode] = useState('ae');
    const user = useAuthStore(state => state.user);
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();
    const { showAlert, showConfirm, showPrompt } = useNotificationStore();

    useEffect(() => {
        setPageTitle("Onboarding");
        let subtitle = 'Manage onboarding instances';
        if (view === 'form') subtitle = 'New employee wizard';
        if (view === 'preview') subtitle = 'Approval Preview';
        setPageSubtitle(subtitle);
        setBackPath('/employees');
        return () => resetPageHeader();
    }, [view]);

    // Core Form State
    const [formData, setFormData] = useState({
        // Step 1: Personal
        first_name: '', last_name: '', email: '', personal_email: '', phone: '', personal_phone: '', date_of_birth: '', gender: '', nationality: '',
        // Step 2: Employment
        employee_code: '', primary_company_id: '', department_id: '', designation_id: '', reporting_manager_id: '', role_id: '', employment_type: 'full_time', job_description: '', hire_date: '', send_welcome_email: true,
        // Step 3: Compliance
        bank_account_no: '', bank_name: '', custom_fields: {}, status: ''
    });

    // Reference Data States
    const [companies, setCompanies] = useState([]);
    const [allDepartments, setAllDepartments] = useState([]);
    const [allDesignations, setAllDesignations] = useState([]);
    const [managers, setManagers] = useState([]);
    const [roles, setRoles] = useState([]);
    const [customFields, setCustomFields] = useState([]);

    useEffect(() => {
        if (view === 'list') {
            setLoading(true);
            api.get('/employees?context=onboarding')
                .then(res => {
                    const data = res.data?.data || res.data;
                    setOnboardingList(Array.isArray(data) ? data : []);
                })
                .catch(e => {
                    console.error("Failed to load onboarding list", e);
                    setOnboardingList([]);
                })
                .finally(() => setLoading(false));
        }
    }, [view]);

    useEffect(() => {
        // Fetch base reference data
        const fetchRefs = async () => {
            try {
                const [cRes, dRes, dgRes, mRes, rRes] = await Promise.all([
                    api.get('/organization/companies'),
                    api.get('/organization/departments'),
                    api.get('/organization/designations'),
                    api.get('/employees'),
                    api.get('/rbac/roles')
                ]);
                setCompanies(Array.isArray(cRes.data?.data) ? cRes.data.data : (Array.isArray(cRes.data) ? cRes.data : []));
                setAllDepartments(Array.isArray(dRes.data?.data) ? dRes.data.data : (Array.isArray(dRes.data) ? dRes.data : []));
                setAllDesignations(Array.isArray(dgRes.data?.data) ? dgRes.data.data : (Array.isArray(dgRes.data) ? dgRes.data : []));
                setManagers(Array.isArray(mRes.data?.data) ? mRes.data.data : (Array.isArray(mRes.data) ? mRes.data : []));
                setRoles(Array.isArray(rRes.data?.data) ? rRes.data.data : (Array.isArray(rRes.data) ? rRes.data : []));

                // Determine default nationality based on user's country_id
                if (user && user.country_id) {
                    try {
                        const countriesRes = await api.get('/organization/countries');
                        const remoteCountries = countriesRes.data?.data || countriesRes.data || [];
                        const userCountry = remoteCountries.find(c => c.id == user.country_id);
                        if (userCountry) {
                            setFormData(prev => ({ ...prev, nationality: userCountry.name }));
                            setUserCountryCode(userCountry.iso_code || 'ae');
                        }
                    } catch (e) {
                        console.error("Failed to fetch countries for default nationality", e);
                    }
                }
            } catch (e) {
                console.error("Failed to fetch references", e);
            }
        };
        fetchRefs();
    }, [user]);

    // Fetch Custom Fields when Primary Company changes
    useEffect(() => {
        if (!formData.primary_company_id) {
            setCustomFields([]);
            return;
        }
        api.get(`/organization/companies/${formData.primary_company_id}/custom_fields`)
            .then(res => {
                const data = res.data?.data || res.data;
                setCustomFields(Array.isArray(data) ? data : []);
            })
            .catch(e => console.error("Failed to load custom fields", e));
    }, [formData.primary_company_id]);

    const updateF = (k, v) => setFormData(p => ({ ...p, [k]: v }));
    const updateCustomF = (k, v) => setFormData(p => ({ ...p, custom_fields: { ...p.custom_fields, [k]: v } }));

    // Derived filtered lists
    // Departments are global entities in this schema, so we do not filter them by company_id
    const filteredDepartments = allDepartments;

    const filteredDesignations = formData.department_id
        ? allDesignations.filter(dg => dg.department_id == formData.department_id)
        : allDesignations;

    const selectedDesignation = allDesignations.find(dg => dg.id == formData.designation_id);
    const selectedLevel = selectedDesignation ? parseInt(selectedDesignation.level) : null;

    const filteredManagers = (selectedLevel !== null && !isNaN(selectedLevel))
        ? managers.filter(m => {
            const mLevel = parseInt(m.designation_level);
            if (isNaN(mLevel)) return false;
            // Manager must have a higher rank (lower numerical level)
            return mLevel < selectedLevel;
        })
        : managers;

    // Global Derived Variables for both Form and Preview
    const primaryCompany = companies.find(c => c.id == formData.primary_company_id);
    const department = allDepartments.find(d => d.id == formData.department_id);
    const designation = allDesignations.find(dg => dg.id == formData.designation_id);
    const manager = managers.find(m => m.id == formData.reporting_manager_id);
    const role = roles.find(r => r.id == formData.role_id);

    const handleNext = () => setStep(s => Math.min(s + 1, 3));
    const handlePrev = () => setStep(s => Math.max(s - 1, 1));

    const handleSubmit = async (targetStatus = 'pending_approval') => {
        // Validation rules based on target status
        if (!formData.first_name || !formData.last_name) {
            showAlert('Required', 'First Name and Last Name are minimally required.', 'warning');
            return;
        }

        if (targetStatus === 'pending_approval') {
            // If submitting for approval, enforce strict validation
            if (!formData.email || !formData.primary_company_id || !formData.hire_date) {
                showAlert('Incomplete', 'Please ensure Email, Primary Company, and Hire Date are filled before submitting for approval!', 'warning');
                return;
            }
        }

        try {
            setLoading(true);
            const payload = {
                ...formData,
                company_ids: formData.primary_company_id ? [formData.primary_company_id] : [],
                status: targetStatus
            };
            if (formData.id) {
                await api.put('/employees/' + formData.id, payload);
            } else {
                await api.post('/employees', payload);
            }

            if (targetStatus === 'onboarding') {
                showAlert('Success', 'Onboarding saved as draft successfully!', 'success');
            } else {
                showAlert('Success', 'Employee onboarding submitted for approval!', 'success');
            }
            // Return to list view
            setView('list');
            setStep(1);
        } catch (e) {
            showAlert('Error', 'Failed to onboard: ' + (e.response?.data?.message || e.message), 'error');
        } finally {
            setLoading(false);
        }
    };

    const renderStepIndicator = () => (
        <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '32px', gap: '40px' }}>
            {[
                { num: 1, label: 'Personal', icon: UserPlus },
                { num: 2, label: 'Employment', icon: Briefcase },
                { num: 3, label: 'Compliance', icon: FileSignature }
            ].map(s => {
                const isActive = step >= s.num;
                const isCurrent = step === s.num;
                const Icon = s.icon;
                return (
                    <div key={s.num} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', color: isActive ? 'var(--primary-brand)' : 'var(--text-secondary)' }}>
                        <div style={{
                            width: '40px', height: '40px', borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center',
                            background: isActive ? 'var(--primary-brand)' : '#f3f4f6',
                            color: isActive ? '#fff' : '#9ca3af',
                            marginBottom: '8px', border: isCurrent ? '2px solid rgba(var(--primary-brand-rgb), 0.3)' : 'none',
                            boxShadow: isCurrent ? '0 0 0 4px rgba(40,107,62,0.1)' : 'none'
                        }}>
                            {step > s.num ? <CheckCircle size={20} /> : <Icon size={20} />}
                        </div>
                        <span style={{ fontSize: '13px', fontWeight: isActive ? '600' : '400' }}>{s.label}</span>
                    </div>
                )
            })}
        </div>
    );

    const handleRowClick = async (emp) => {
        if (emp.status === 'active') {
            window.location.href = `/employee-profile?id=${emp.id}`;
        } else {
            try {
                setLoading(true);
                const res = await api.get('/employees/' + emp.id);
                const fullEmp = res.data?.data || res.data;
                const companyId = Object.values(fullEmp.companies || {}).find(c => c.is_primary)?.id || fullEmp.companies?.[0]?.id || '';

                setFormData({
                    id: fullEmp.id,
                    first_name: fullEmp.first_name || '',
                    last_name: fullEmp.last_name || '',
                    email: fullEmp.email || '',
                    personal_email: fullEmp.personal_email || '',
                    phone: fullEmp.phone || '',
                    personal_phone: fullEmp.personal_phone || '',
                    date_of_birth: fullEmp.date_of_birth || '',
                    gender: fullEmp.gender || '',
                    nationality: fullEmp.nationality || '',
                    primary_company_id: companyId,
                    department_id: fullEmp.department_id || '',
                    designation_id: fullEmp.designation_id || '',
                    reporting_manager_id: fullEmp.reporting_manager_id || '',
                    role_id: fullEmp.role_id || '',
                    employment_type: fullEmp.employment_type || 'full_time',
                    hire_date: fullEmp.hire_date || '',
                    tin_number: fullEmp.tin_number || '',
                    nssf_number: fullEmp.nssf_number || '',
                    bank_account_no: fullEmp.bank_account_no || '',
                    bank_name: fullEmp.bank_name || '',
                    custom_fields: fullEmp.custom_data || {},
                    employee_code: fullEmp.employee_code || '',
                    status: fullEmp.status || '',
                    send_welcome_email: true
                });
                setStep(1);
                if (fullEmp.status === 'pending_approval') {
                    setView('preview');
                } else {
                    setView('form');
                }
                // Always fetch history if it exists
                api.get(`/employees/${fullEmp.id}/onboarding-history`).then(res => {
                    setApprovalHistory(res.data || []);
                }).catch(err => console.error(err));
            } catch (e) {
                console.error(e);
                showAlert('Error', "Failed to load draft details", 'error');
            } finally {
                setLoading(false);
            }
        }
    };

    const handleApprove = async (e, empId) => {
        if (e) e.stopPropagation(); // prevent row click
        showConfirm('Approve Onboarding', "Are you sure you want to approve this onboarding and activate the employee?", async () => {
            try {
                setLoading(true);
                await api.put('/employees/' + empId, { status: 'active', comment: 'Approved by HR' });
                setView('list');
                showAlert('Success', 'Onboarding approved. Employee is now active.', 'success');
            } catch (err) {
                console.error(err);
                showAlert('Error', "Failed to approve onboarding", 'error');
            } finally {
                setLoading(false);
            }
        });
    };

    const handleReject = async (e, empId) => {
        if (e) e.stopPropagation();
        showPrompt('Reject Onboarding', "Please provide a reason for rejection. This will be shared with the team.", async (comment) => {
            if (!comment || comment.trim() === '') {
                showAlert('Required', 'A rejection reason is required.', 'error');
                return;
            }
            try {
                setLoading(true);
                await api.put('/employees/' + empId, { 
                    status: 'onboarding',
                    comment: comment 
                });
                setView('list');
                showAlert('Success', 'Onboarding rejected and moved back to draft.', 'success');
            } catch (err) {
                console.error(err);
                showAlert('Error', "Failed to reject onboarding", 'error');
            } finally {
                setLoading(false);
            }
        }, "Please fix the following details...");
    };

    const renderList = () => (
        <div className="card" style={{ padding: '32px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
                <h3 style={{ fontSize: '18px', fontWeight: '600' }}>Recent Onboardings</h3>
                <button className="btn btn-primary" style={{ display: 'flex', alignItems: 'center', gap: '8px' }} onClick={() => { setView('form'); setStep(1); setFormData({ id: '', first_name: '', last_name: '', email: '', personal_email: '', phone: '', personal_phone: '', date_of_birth: '', gender: '', nationality: '', employee_code: '', primary_company_id: '', department_id: '', designation_id: '', reporting_manager_id: '', employment_type: 'full_time', hire_date: '', bank_account_no: '', bank_name: '', custom_fields: {}, status: '', send_welcome_email: true }); }}>
                    <Plus size={16} /> Start New Onboarding
                </button>
            </div>
            <table className="data-table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Hire Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                    {loading ? (
                        <tr><td colSpan={4} style={{ textAlign: 'center', padding: '20px' }}><Loader size={20} className="spin" style={{ margin: 'auto' }} /></td></tr>
                    ) : onboardingList.length === 0 ? (
                        <tr><td colSpan={4} style={{ textAlign: 'center', color: 'var(--text-secondary)' }}>No onboarding records found</td></tr>
                    ) : (
                        onboardingList.map(emp => (
                            <tr key={emp.id} onClick={() => handleRowClick(emp)} style={{ cursor: 'pointer' }} className="hover-row">
                                <td>{emp.first_name} {emp.last_name}</td>
                                <td>{emp.email}</td>
                                <td>{formatDate(emp.hire_date)}</td>
                                <td>
                                    <span style={{
                                        padding: '4px 8px', borderRadius: '12px', fontSize: '12px', fontWeight: '500',
                                        backgroundColor: emp.status === 'onboarding' ? '#fef3c7' : emp.status === 'pending_approval' ? '#e0e7ff' : '#dcfce7',
                                        color: emp.status === 'onboarding' ? '#92400e' : emp.status === 'pending_approval' ? '#3730a3' : '#166534'
                                    }}>
                                        {emp.status === 'onboarding' ? 'Draft' : emp.status === 'pending_approval' ? 'Pending Approval' : 'Active'}
                                    </span>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );

    const renderPreview = () => {

        const Section = ({ title, icon: Icon, children }) => (
            <div style={{ marginBottom: '32px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '16px', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px' }}>
                    <Icon size={20} style={{ color: 'var(--primary-brand)' }} />
                    <h4 style={{ fontSize: '16px', fontWeight: '600', margin: 0 }}>{title}</h4>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))', gap: '20px' }}>
                    {children}
                </div>
            </div>
        );

        const DataItem = ({ label, value, icon: Icon }) => {
            const displayValue = (value === 0 || value === '0' || !value) ? '---' : value;
            return (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                    <span style={{ fontSize: '12px', color: '#6b7280', fontWeight: '500' }}>{label}</span>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '14px', color: '#111827', fontWeight: '500' }}>
                        {Icon && <Icon size={14} style={{ color: '#9ca3af' }} />}
                        <span>{displayValue}</span>
                    </div>
                </div>
            );
        };

        return (
            <div className="card" style={{ padding: '40px', maxWidth: '1000px', margin: '0 auto' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                        <div style={{ width: '64px', height: '64px', borderRadius: '16px', background: 'var(--primary-brand)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '24px', fontWeight: '700' }}>
                            {formData.first_name?.[0]}{formData.last_name?.[0]}
                        </div>
                        <div>
                            <h2 style={{ fontSize: '24px', fontWeight: '700', color: '#111827', margin: 0 }}>{formData.first_name} {formData.last_name}</h2>
                            <p style={{ color: '#6b7280', margin: '4px 0 0 0', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <span style={{ padding: '2px 8px', background: '#e0e7ff', color: '#3730a3', borderRadius: '12px', fontSize: '12px', fontWeight: '600' }}>PENDING APPROVAL</span>
                                • {designation?.title || 'No Designation'}
                            </p>
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: '12px' }}>
                        <button className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: '8px' }} onClick={() => setView('form')}>
                            <Edit3 size={16} /> Edit Details
                        </button>
                    </div>
                </div>

                <Section title="Personal Information" icon={User}>
                    <DataItem label="Full Name" value={`${formData.first_name} ${formData.last_name}`} icon={User} />
                    <DataItem label="Email Address (Work)" value={formData.email} icon={Mail} />
                    <DataItem label="Personal Email" value={formData.personal_email} icon={Mail} />
                    <DataItem label="Phone Number (Work)" value={formData.phone} icon={Phone} />
                    <DataItem label="Personal Contact Number" value={formData.personal_phone} icon={Phone} />
                    <DataItem label="Date of Birth" value={formatDate(formData.date_of_birth)} />
                    <DataItem label="Gender" value={formData.gender ? (formData.gender.charAt(0).toUpperCase() + formData.gender.slice(1)) : ''} />
                    <DataItem label="Nationality" value={formData.nationality} icon={Globe} />
                </Section>

                <Section title="Employment Details" icon={Briefcase}>
                    <DataItem label="Primary Company" value={primaryCompany?.name} icon={Building2} />
                    <DataItem label="Department" value={department?.name} icon={Users} />
                    <DataItem label="Designation" value={designation?.title} />
                    <DataItem label="Reporting Manager" value={manager ? `${manager.first_name} ${manager.last_name}` : 'None'} icon={User} />
                    <DataItem label="Employment Type" value={formData.employment_type?.replace('_', ' ').toUpperCase()} />
                    <DataItem label="Hire Date" value={formatDate(formData.hire_date)} />
                    <DataItem label="System Role" value={role?.name?.replace('_', ' ')} icon={ShieldCheck} />
                    <DataItem label="Employee Code" value={formData.employee_code || 'Auto-generated'} />
                    <div style={{ gridColumn: '1 / -1', background: '#f9fafb', padding: '16px', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
                        <span style={{ fontSize: '12px', color: '#6b7280', fontWeight: '500', display: 'block', marginBottom: '8px' }}>Role Description / Summary</span>
                        <p style={{ fontSize: '14px', color: '#374151', margin: 0, lineHeight: '1.6', whiteSpace: 'pre-wrap' }}>
                            {formData.job_description || 'No description provided.'}
                        </p>
                    </div>
                </Section>

                {!(primaryCompany?.name?.includes('Avantgarde Enterprises')) && (formData.bank_name || formData.bank_account_no) && (
                    <Section title="Compliance & Banking" icon={CreditCard}>
                        <DataItem label="Bank Name" value={formData.bank_name} icon={Building2} />
                        <DataItem label="Account Number" value={formData.bank_account_no} icon={CreditCard} />
                    </Section>
                )}

                {customFields.length > 0 && (
                    <Section title="Additional Company Requirements" icon={FileSignature}>
                        {customFields.map(cf => (
                            <DataItem 
                                key={cf.field_key} 
                                label={cf.field_name} 
                                value={cf.field_type === 'date' ? formatDate(formData.custom_fields[cf.field_key]) : formData.custom_fields[cf.field_key]} 
                            />
                        ))}
                    </Section>
                )}

                {approvalHistory.length > 0 && (
                    <Section title="Onboarding Timeline & Comments" icon={History}>
                        <div style={{ gridColumn: '1 / -1' }}>
                            {approvalHistory.map((h, i) => (
                                <div key={i} style={{ display: 'flex', gap: '16px', marginBottom: '16px', borderLeft: '2px solid #e5e7eb', paddingLeft: '16px', position: 'relative' }}>
                                    <div style={{ width: '8px', height: '8px', borderRadius: '50%', background: h.action === 'rejected' ? '#ef4444' : h.action === 'approved' ? '#10b981' : '#6366f1', position: 'absolute', left: '-5px', top: '6px' }}></div>
                                    <div style={{ flex: 1 }}>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '4px' }}>
                                            <span style={{ fontWeight: '600', fontSize: '13px', color: '#111827' }}>
                                                {h.action.toUpperCase()} by {h.actor_name} ({h.role_name})
                                            </span>
                                            <span style={{ fontSize: '11px', color: '#9ca3af' }}>{formatDateTime(h.created_at_utc)}</span>
                                        </div>
                                        {h.comment && (
                                            <div style={{ background: '#f9fafb', padding: '10px 12px', borderRadius: '6px', fontSize: '13px', color: '#4b5563', border: '1px solid #f3f4f6' }}>
                                                "{h.comment}"
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Section>
                )}

                <div style={{ marginTop: '40px', padding: '24px', background: '#f8fafc', borderRadius: '12px', border: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                        <h4 style={{ fontSize: '15px', fontWeight: '600', color: '#1e293b', margin: 0 }}>Final Action Required</h4>
                        <p style={{ fontSize: '13px', color: '#64748b', margin: '4px 0 0 0' }}>Review the details above carefully before approving this employee.</p>
                    </div>
                    <div style={{ display: 'flex', gap: '16px' }}>
                        <button className="btn" style={{ background: '#fff', color: '#dc2626', border: '1px solid #fecaca', display: 'flex', alignItems: 'center', gap: '8px' }} onClick={(e) => handleReject(e, formData.id)}>
                            <XCircle size={18} /> Reject & Return to Draft
                        </button>
                        <button className="btn btn-primary" style={{ background: '#3730a3', display: 'flex', alignItems: 'center', gap: '8px', padding: '10px 24px' }} onClick={(e) => handleApprove(e, formData.id)}>
                            <CheckCircle size={18} /> Approve & Activate
                        </button>
                    </div>
                </div>
            </div>
        );
    };

    return (
        <div>
            {(view === 'form' || view === 'preview') && (
                <div style={{ marginBottom: '24px', display: 'flex', justifyContent: 'flex-end' }}>
                    <button className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: '8px' }} onClick={() => setView('list')}>
                        <ArrowLeft size={16} /> Back to List
                    </button>
                </div>
            )}

            {view === 'list' ? renderList() : view === 'preview' ? renderPreview() : (
                <div className="card" style={{ padding: '32px' }}>
                    {renderStepIndicator()}

                    {approvalHistory.length > 0 && approvalHistory[0].action === 'rejected' && (
                        <div style={{ background: '#fef2f2', border: '1px solid #fecaca', padding: '16px', borderRadius: '8px', marginBottom: '24px', display: 'flex', gap: '12px' }}>
                            <XCircle size={20} style={{ color: '#dc2626', flexShrink: 0 }} />
                            <div>
                                <h4 style={{ fontSize: '14px', fontWeight: '700', color: '#991b1b', margin: 0 }}>Onboarding Rejected</h4>
                                <p style={{ fontSize: '13px', color: '#b91c1c', margin: '4px 0 0 0', fontWeight: '500' }}>
                                    Reason: "{approvalHistory[0].comment}"
                                </p>
                                <p style={{ fontSize: '12px', color: '#ef4444', margin: '8px 0 0 0' }}>
                                    Please correct the details below and resubmit for approval.
                                </p>
                            </div>
                        </div>
                    )}

                    <div style={{ maxWidth: '800px', margin: '0 auto' }}>

                        {/* STEP 1: PERSONAL */}
                        {step === 1 && (
                            <div className="fade-in">
                                <h3 style={{ fontSize: '18px', fontWeight: '600', marginBottom: '20px', borderBottom: '1px solid #e5e7eb', paddingBottom: '12px' }}>Personal Data</h3>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                                    <div><label className="form-label">First Name *</label><input className="form-input" value={formData.first_name} onChange={e => updateF('first_name', e.target.value)} /></div>
                                    <div><label className="form-label">Last Name *</label><input className="form-input" value={formData.last_name} onChange={e => updateF('last_name', e.target.value)} /></div>
                                    <div><label className="form-label">Work Email *</label><input className="form-input" type="email" value={formData.email} onChange={e => updateF('email', e.target.value)} /></div>
                                    <div><label className="form-label">Personal Email</label><input className="form-input" type="email" value={formData.personal_email} onChange={e => updateF('personal_email', e.target.value)} /></div>
                                    <div>
                                        <label className="form-label">Work Phone</label>
                                        <PhoneInput
                                            value={formData.phone || ''}
                                            defaultCountry={userCountryCode}
                                            onChange={val => updateF('phone', val)}
                                        />
                                    </div>
                                    <div>
                                        <label className="form-label">Personal Contact Number</label>
                                        <PhoneInput
                                            value={formData.personal_phone || ''}
                                            defaultCountry={userCountryCode}
                                            onChange={val => updateF('personal_phone', val)}
                                        />
                                    </div>
                                    <div><label className="form-label">Date of Birth</label><DateInput value={formData.date_of_birth} onChange={val => updateF('date_of_birth', val)} /></div>
                                    <div><label className="form-label">Gender</label>
                                        <select className="form-input" value={formData.gender} onChange={e => updateF('gender', e.target.value)}>
                                            <option value="">Select...</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="form-label">Nationality</label>
                                        <select className="form-input" value={formData.nationality} onChange={e => updateF('nationality', e.target.value)}>
                                            <option value="">Select Nationality...</option>
                                            {COUNTRY_DATA.map(c => <option key={c.iso_code} value={c.name}>{c.name}</option>)}
                                        </select>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* STEP 2: EMPLOYMENT */}
                        {step === 2 && (
                            <div className="fade-in">
                                <h3 style={{ fontSize: '18px', fontWeight: '600', marginBottom: '20px', borderBottom: '1px solid #e5e7eb', paddingBottom: '12px' }}>Employment Details</h3>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                                    <div>
                                        <label className="form-label">Primary Company *</label>
                                        <select className="form-input" value={formData.primary_company_id} onChange={e => { updateF('primary_company_id', e.target.value); updateF('department_id', ''); updateF('designation_id', ''); }}>
                                            <option value="">Select Company...</option>
                                            {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                        </select>
                                    </div>
                                    <div style={{ display: 'flex', flexDirection: 'column', justifyContent: 'center' }}>
                                        <small style={{ color: '#6b7280', display: 'flex', alignItems: 'center', gap: '4px' }}>Selecting a company filters departments and fetches custom compliance fields.</small>
                                    </div>

                                    <div>
                                        <label className="form-label">Department</label>
                                        <select className="form-input" value={formData.department_id} onChange={e => { updateF('department_id', e.target.value); updateF('designation_id', ''); }} disabled={!formData.primary_company_id}>
                                            <option value="">Select Department...</option>
                                            {filteredDepartments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="form-label">Designation</label>
                                        <select className="form-input" value={formData.designation_id} onChange={e => updateF('designation_id', e.target.value)} disabled={!formData.department_id}>
                                            <option value="">Select Designation...</option>
                                            {filteredDesignations.map(dg => <option key={dg.id} value={dg.id}>{dg.title}</option>)}
                                        </select>
                                    </div>

                                    <div><label className="form-label">Hire Date *</label><DateInput value={formData.hire_date} onChange={val => updateF('hire_date', val)} /></div>
                                    <div><label className="form-label">Employment Type</label>
                                        <select className="form-input" value={formData.employment_type} onChange={e => updateF('employment_type', e.target.value)}>
                                            <option value="full_time">Full Time</option><option value="part_time">Part Time</option><option value="contractor">Contractor</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label className="form-label">Reporting Manager</label>
                                        <select className="form-input" value={formData.reporting_manager_id} onChange={e => updateF('reporting_manager_id', e.target.value)}>
                                            <option value="">Select Manager...</option>
                                            {filteredManagers.map(m => <option key={m.id} value={m.id}>{m.first_name} {m.last_name} ({m.designation})</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="form-label">System Access Level *</label>
                                        <select className="form-input" value={formData.role_id} onChange={e => updateF('role_id', e.target.value)}>
                                            <option value="">Select Role...</option>
                                            {roles.map(r => <option key={r.id} value={r.id}>{r.name.replace('_', ' ')}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="form-label">Staff ID / Employee Code (Optional)</label>
                                        <input className="form-input" 
                                            value={formData.employee_code} 
                                            onChange={e => updateF('employee_code', e.target.value)} 
                                            placeholder="Leave blank for auto-generation" />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1' }}>
                                        <label className="form-label">Brief Role Description / Summary</label>
                                        <textarea className="form-input" 
                                            style={{ minHeight: '80px', paddingTop: '10px' }}
                                            value={formData.job_description} 
                                            onChange={e => updateF('job_description', e.target.value)} 
                                            placeholder="Briefly describe the key responsibilities or role objectives..." />
                                    </div>
                                    <div style={{ gridColumn: '1 / -1', display: 'flex', alignItems: 'center', gap: '8px', marginTop: '10px' }}>
                                        <input type="checkbox" id="sendWelcomeEmail" checked={formData.send_welcome_email} onChange={e => updateF('send_welcome_email', e.target.checked)} style={{ width: '16px', height: '16px', cursor: 'pointer' }} />
                                        <label htmlFor="sendWelcomeEmail" style={{ fontSize: '14px', color: '#374151', cursor: 'pointer', userSelect: 'none' }}>Send welcome email with login credentials upon creation</label>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* STEP 3: COMPLIANCE */}
                        {step === 3 && (
                            <div className="fade-in">
                                <h3 style={{ fontSize: '18px', fontWeight: '600', marginBottom: '20px', borderBottom: '1px solid #e5e7eb', paddingBottom: '12px' }}>Compliance Data</h3>
                                {!(primaryCompany?.name?.includes('Avantgarde Enterprises')) && (
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '32px' }}>
                                        <div><label className="form-label">Bank Name</label><input className="form-input" value={formData.bank_name} onChange={e => updateF('bank_name', e.target.value)} placeholder="e.g. Stanbic Bank" /></div>
                                        <div><label className="form-label">Bank Account No</label><input className="form-input" value={formData.bank_account_no} onChange={e => updateF('bank_account_no', e.target.value)} placeholder="Account Number" /></div>
                                    </div>
                                )}

                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                                    {customFields.length > 0 ? (
                                        <div style={{ gridColumn: '1 / -1' }}>
                                            <h4 style={{ fontSize: '15px', fontWeight: '500', marginBottom: '16px', color: '#374151' }}>Custom Company Requirements</h4>
                                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px' }}>
                                                {customFields.map(c => (
                                                    <div key={c.field_key}>
                                                        <label className="form-label">{c.field_name} {c.is_required ? '*' : ''}</label>
                                                        {c.field_type === 'dropdown' ? (
                                                            <select className="form-input" value={formData.custom_fields[c.field_key] || ''} onChange={e => updateCustomF(c.field_key, e.target.value)}>
                                                                <option value="">Select...</option>
                                                                {(c.field_options || []).map(opt => <option key={opt} value={opt}>{opt}</option>)}
                                                            </select>
                                                        ) : c.field_type === 'date' ? (
                                                            <DateInput value={formData.custom_fields[c.field_key] || ''} onChange={val => updateCustomF(c.field_key, val)} />
                                                        ) : (
                                                            <input className="form-input"
                                                                type={c.field_type === 'number' ? 'number' : 'text'}
                                                                value={formData.custom_fields[c.field_key] || ''} onChange={e => updateCustomF(c.field_key, e.target.value)} />
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ) : (
                                        <div style={{ gridColumn: '1 / -1', textAlign: 'center', padding: '40px', color: '#6b7280', background: '#f9fafb', borderRadius: '8px', border: '1px dashed #d1d5db' }}>
                                            <p>No additional custom compliance fields configured for the selected company.</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}

                        {/* Navigation Buttons */}
                        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '32px', borderTop: '1px solid #e5e7eb', paddingTop: '20px' }}>
                            <button className="btn" style={{ visibility: step === 1 ? 'hidden' : 'visible', display: 'flex', alignItems: 'center', gap: '8px', background: '#f3f4f6' }} onClick={handlePrev}>
                                <ChevronLeft size={16} /> Previous
                            </button>

                            {step < 3 ? (
                                <button className="btn btn-primary" style={{ display: 'flex', alignItems: 'center', gap: '8px' }} onClick={handleNext}>
                                    Next <ChevronRight size={16} />
                                </button>
                            ) : (
                                <div style={{ display: 'flex', gap: '12px' }}>
                                    <button className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: '8px', background: '#eff6ff', border: '1px solid #bfdbfe', color: '#1e40af' }} onClick={() => handleSubmit('onboarding')} disabled={loading}>
                                        {loading ? <Loader size={16} className="spin" /> : <Save size={16} />}
                                        Save as Draft
                                    </button>
                                    <button className="btn btn-primary" style={{ display: 'flex', alignItems: 'center', gap: '8px', background: '#286B3E' }} onClick={() => handleSubmit('pending_approval')} disabled={loading || (formData.status && formData.status !== 'onboarding' && formData.status !== 'pending_approval')}>
                                        {loading ? <Loader size={16} className="spin" /> : <CheckCircle size={16} />}
                                        Submit for Approval
                                    </button>
                                    {formData.id && formData.status === 'pending_approval' && (user?.role_id === ROLE_IDS.SUPER_ADMIN || user?.role_id === ROLE_IDS.ADMIN || useAuthStore.getState().hasPermission('employees', 'edit')) && (
                                        <button className="btn btn-primary" style={{ display: 'flex', alignItems: 'center', gap: '8px', background: '#3730a3', color: '#fff' }} onClick={(e) => { e.preventDefault(); handleApprove(e, formData.id); }} disabled={loading}>
                                            {loading ? <Loader size={16} className="spin" /> : <CheckCircle size={16} />}
                                            Approve
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default Onboarding;
