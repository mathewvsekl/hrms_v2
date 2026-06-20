import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import {
    ArrowLeft, Pencil, Save, Building, FileText, Image,
    Download, MapPin, Plus, Mail, Phone, Hash, Shield, 
    Globe, Briefcase, User, Archive, Clock, CreditCard,
    Award, Activity, X, Upload, Trash2, Eye, ExternalLink, Edit
} from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useLayoutStore from '../store/useLayoutStore';
import COUNTRY_DATA from '../data/countryData';
import DateInput from '../components/ui/DateInput';
import Modal from '../components/ui/Modal';
import PhoneInput from '../components/ui/PhoneInput';
import { formatDate } from '../utils/dateUtils';
import useNotificationStore from '../store/useNotificationStore';
import { getSecureMediaUrl } from '../utils/mediaHelper';
import { ROLE_IDS } from '../utils/roleConstants';

const EmployeeProfile = () => {
    const { user } = useAuthStore();
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const employeeId = searchParams.get('id') || user?.employee_id;
    const isSuperAdmin = user?.role_id === ROLE_IDS.SUPER_ADMIN || user?.role_id === ROLE_IDS.ADMIN;
    const isGlobalAdmin = isSuperAdmin || useAuthStore.getState().hasPermission('employees', 'view');
    const isEmployeeView = localStorage.getItem('adminViewMode') === 'employee';
    
    // RBAC Permissions mapped directly to UI controls
    const canEditEmployee = useAuthStore.getState().hasPermission('employees', 'edit');
    const canDeleteEmployee = useAuthStore.getState().hasPermission('employees', 'delete');
    const canManageAssets = useAuthStore.getState().hasPermission('assets', 'manage');
    const canEditPayroll = useAuthStore.getState().hasPermission('payroll', 'edit');
    
    const isAdmin = canEditEmployee && !isEmployeeView;
    const isOwnProfile = user?.employee_id == employeeId || user?.id == employeeId;
    
    const canViewConfidential = isAdmin || isOwnProfile;
    const canViewAttendance = useAuthStore.getState().hasPermission('attendance', 'view') || isOwnProfile;
    const canViewLeave = useAuthStore.getState().hasPermission('leave', 'view') || isOwnProfile;
    const canViewPayrollInfo = useAuthStore.getState().hasPermission('payroll', 'view') || isOwnProfile;
    const canViewAssetsInfo = useAuthStore.getState().hasPermission('assets', 'view') || isOwnProfile;
    const canViewDocuments = useAuthStore.getState().hasPermission('documents', 'view') || isOwnProfile;
    
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();
    const { showAlert, showConfirm } = useNotificationStore();

    const [employee, setEmployee] = useState(null);
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState(false);
    const [editForm, setEditForm] = useState({});
    const [attendance, setAttendance] = useState({ present: 0, absent: 0, leave: 0, logs: [] });
    const [currentMonth, setCurrentMonth] = useState(new Date().getMonth() + 1);
    const [currentYear, setCurrentYear] = useState(new Date().getFullYear());
    const [leaveYear, setLeaveYear] = useState(new Date().getFullYear());
    const [leaveBalances, setLeaveBalances] = useState([]);
    const [documents, setDocuments] = useState([]);
    const [payslips, setPayslips] = useState([]);
    const [referenceDocs, setReferenceDocs] = useState([]);
    const [assets, setAssets] = useState([]);
    const [salaryAdvances, setSalaryAdvances] = useState([]);
    const [previewDoc, setPreviewDoc] = useState(null);
    const [showPhotoModal, setShowPhotoModal] = useState(false);
    const [showSalaryModal, setShowSalaryModal] = useState(false);
    const [showAdvanceModal, setShowAdvanceModal] = useState(false);
    const [advanceForm, setAdvanceForm] = useState({ amount: '', reason: '' });
    const [submittingAdvance, setSubmittingAdvance] = useState(false);
    const [salaryForm, setSalaryForm] = useState({ effective_date: '', currency_code: 'UGX', components: {} });
    const [savingSalary, setSavingSalary] = useState(false);
    const [payrollComponents, setPayrollComponents] = useState([]);
    const [employeeSalaryData, setEmployeeSalaryData] = useState([]);
    const [globalSettings, setGlobalSettings] = useState({});

    useEffect(() => {
        if (employee) {
            setPageTitle(`${employee.first_name} ${employee.last_name}`);
            setPageSubtitle(employee.designation || 'Staff Profile');
        } else {
            setPageTitle("Employee Profile");
        }
        setBackPath('/employees');
        return () => resetPageHeader();
    }, [employee]);
    

    // Organization Data for editing
    const [companies, setCompanies] = useState([]);
    const [departments, setDepartments] = useState([]);
    const [designations, setDesignations] = useState([]);
    const [managers, setManagers] = useState([]);
    const [roles, setRoles] = useState([]);
    const [customFieldDefs, setCustomFieldDefs] = useState([]);
    const [primaryCompanyName, setPrimaryCompanyName] = useState('Avantgarde HRMS');
    const [allStatuses, setAllStatuses] = useState({ attendance: [], leave: [], all: [] });
    const [profileImageError, setProfileImageError] = useState(false);

    useEffect(() => {
        const fetchStatuses = async () => {
            try {
                // Get company ID from employee if available to get office-specific colors
                const companyId = employee?.primary_company_id || (employee?.companies?.find(c => c.is_primary)?.id);
                const url = `/attendance/statuses${companyId ? `?company_id=${companyId}` : ''}`;
                
                const res = await api.get(url);
                const data = res.data?.data || res.data || {};
                
                // Create a unified lookup list
                const all = data.all || [...(data.attendance || []), ...(data.leave || [])];
                
                setAllStatuses({
                    attendance: data.attendance || [],
                    leave: data.leave || [],
                    all: all
                });
            } catch (error) {
                console.error('Failed to fetch attendance statuses', error);
            }
        };
        fetchStatuses();
    }, [employee?.id]); // Refetch if employee changes (to get correct company context)

    useEffect(() => {
        // Guard: Wait for employeeId to be available from searchParams or user context
        if (!employeeId) {
            console.warn("EmployeeProfile: No employeeId found yet. Waiting...");
            return;
        }

        const fetchData = async () => {
            setLoading(true);
            try {
                await Promise.all([
                    loadProfile(),
                    loadAttendance(),
                    loadLeaveBalances(),
                    loadDocuments(),
                    loadPayslips(),
                    loadSalaryAdvances(),
                    loadReferenceDocs(),
                    loadAssets(),
                ]);
            } catch (error) {
                console.error("Error loading profile data:", error);
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, [employeeId, user]);

    useEffect(() => {
        if (employeeId) {
            loadAttendance();
        }
    }, [employeeId, currentMonth, currentYear]);

    useEffect(() => {
        if (employeeId) {
            loadLeaveBalances();
        }
    }, [employeeId, leaveYear]);

    useEffect(() => {
        // Fetch Primary Company Name and global settings
        api.get('/organization/settings').then(res => {
            const list = res.data?.data || res.data || [];
            const companySetting = list.find(s => s.setting_key === 'company_name');
            if (companySetting) {
                setPrimaryCompanyName(companySetting.setting_value);
            }
            
            const settingsObj = {};
            list.forEach(s => settingsObj[s.setting_key] = s.setting_value);
            setGlobalSettings(settingsObj);
        }).catch(err => console.error("Failed to fetch settings", err));
    }, [employeeId, user]);

    const [showLeaveHistoryModal, setShowLeaveHistoryModal] = useState(false);
    const [selectedLeaveBalance, setSelectedLeaveBalance] = useState(null);
    const [leaveHistory, setLeaveHistory] = useState([]);
    const [loadingLeaveHistory, setLoadingLeaveHistory] = useState(false);
    const [debugAllRequests, setDebugAllRequests] = useState([]);

    const handleLeaveBalanceClick = async (balance) => {
        setSelectedLeaveBalance(balance);
        setShowLeaveHistoryModal(true);
        setLoadingLeaveHistory(true);
        try {
            const res = await api.get(`/leave?employee_id=${employee.id}`);
            const data = res.data?.data || res.data || [];
            
            // Filter by leave_type_id or name (include all statuses so users see pending/rejected too)
            const history = (Array.isArray(data) ? data : []).filter(r => 
                r.leave_type_id == balance.leave_type_id || r.leave_type_name === balance.leave_type_name
            );
            setLeaveHistory(history);
        } catch (error) {
            console.error("Failed to load leave history", error);
        } finally {
            setLoadingLeaveHistory(false);
        }
    };

    const [showAssetModal, setShowAssetModal] = useState(false);
    const [availableAssets, setAvailableAssets] = useState([]);
    const [assetAllocationForm, setAssetAllocationForm] = useState({
        asset_id: '',
        allocation_date: new Date().toISOString().split('T')[0],
        expected_return_date: '',
        remarks: ''
    });

    const [showDocModal, setShowDocModal] = useState(false);
    const [showEditDocModal, setShowEditDocModal] = useState(false);
    const [editDocForm, setEditDocForm] = useState({ id: null, name: '', expiryDate: '' });
    const [docForm, setDocForm] = useState({
        type: 'Passport',
        name: '',
        expiryDate: '',
        file: null
    });

    const documentTypes = [
        "Passport",
        "Visa",
        "Work Permit",
        "Driving License",
        "Work Contract",
        "ID Card",
        "Educational Certificate",
        "Health Certificate",
        "Background Check",
        "Payslip",
        "Other"
    ];

    const loadProfile = async () => {
        setProfileImageError(false); // Reset error state on new load
        try {
            const res = await api.get(`/employees/${employeeId}`);
            const e = res.data?.data || res.data;
            setEmployee(e);

            // Initial form state
            const initialForm = {
                first_name: e.first_name || '',
                last_name: e.last_name || '',
                email: e.email || '',
                personal_email: e.personal_email || '',
                phone: e.phone || '',
                personal_phone: e.personal_phone || '',
                gender: e.gender || '',
                nationality: e.nationality || '',
                date_of_birth: e.date_of_birth || '',
                hire_date: e.hire_date || '',
                employment_type: e.employment_type || 'full_time',
                status: e.status || 'active',
                company_ids: (e.companies || []).filter(c => c.is_active != 0).map(c => c.id),
                payroll_company_ids: (e.companies || []).filter(c => c.is_active != 0 && c.include_in_payroll == 1).map(c => c.id),
                primary_company_id: (e.companies || []).filter(c => c.is_active != 0).find(c => c.is_primary)?.id || (e.companies?.filter(c => c.is_active != 0)?.[0]?.id || ''),
                department_id: e.department_id || '',
                designation_id: e.designation_id || '',
                reporting_manager_id: e.reporting_manager_id || '',
                role_id: e.role_id || '',
                tin_number: e.tin_number || '',
                nssf_number: e.nssf_number || '',
                bank_account_no: e.bank_account_no || '',
                bank_name: e.bank_name || '',
                custom_fields: e.custom_data || {},
                employee_code: e.employee_code || ''
            };
            setEditForm(initialForm);

            if (e.companies && e.companies.length > 0) {
                const primaryId = initialForm.primary_company_id;
                if (primaryId) {
                    fetchCustomFieldDefs(primaryId);
                } else {
                    fetchCustomFieldDefs(e.companies[0].id);
                }
            }
        } catch (err) { console.error('Profile load error:', err); }
    };

    const fetchCustomFieldDefs = async (companyId) => {
        try {
            const res = await api.get(`/organization/companies/${companyId}/custom_fields`);
            setCustomFieldDefs(res.data?.data || []);
        } catch (e) {
            console.error('Failed to fetch custom fields', e);
        }
    };

    const loadEditData = async () => {
        try {
            const [oRes, dRes, desRes, mRes, rRes] = await Promise.all([
                api.get('/organization/companies'),
                api.get('/organization/departments'),
                api.get('/organization/designations'),
                api.get('/employees'),
                api.get('/rbac/roles')
            ]);
            setCompanies(oRes.data?.data || oRes.data || []);
            setDepartments(dRes.data?.data || dRes.data || []);
            setDesignations(desRes.data?.data || desRes.data || []);
            setManagers((mRes.data?.data || mRes.data || []).filter(m => m.id != employeeId));
            setRoles(rRes.data?.data || rRes.data || []);
        } catch (e) {
            console.error('Failed to load organizational data', e);
        }
    };

    useEffect(() => {
        if (editing && companies.length === 0) {
            loadEditData();
        }
    }, [editing]);

    const selectedDesignation = designations.find(dg => dg.id == editForm.designation_id);
    const selectedLevel = selectedDesignation ? parseInt(selectedDesignation.level) : null;

    const filteredManagers = (selectedLevel !== null && !isNaN(selectedLevel)) 
        ? managers.filter(m => {
            const mLevel = parseInt(m.designation_level);
            if (isNaN(mLevel)) return false;
            return mLevel < selectedLevel;
        })
        : managers;

    const loadAttendance = async () => {
        try {
            const res = await api.get(`/attendance/summary?employee_id=${employeeId}&month=${currentMonth}&year=${currentYear}`);
            const data = res.data?.data || res.data;
            if (data) {
                const stats = data.stats || [];
                const logs = data.logs || [];
                
                // Extract counts for all statuses dynamically
                const counts = {};
                stats.forEach(s => { counts[s.status] = s.count; });

                // Fix timezone parsing for calendar days (YYYY-MM-DD split)
                const calDays = logs.map(l => {
                    if (!l.attendance_date) return null;
                    const parts = l.attendance_date.split('-');
                    return { 
                        day: parseInt(parts[2], 10), 
                        status: l.status,
                        is_leave: !!l.is_leave,
                        is_future: !!l.is_future
                    };
                }).filter(Boolean);

                setAttendance({ ...counts, logs: calDays });
            }
        } catch { /* silent */ }
    };

    const loadLeaveBalances = async (year = leaveYear) => {
        try {
            const res = await api.get(`/leave/balances?employee_id=${employeeId}&year=${year}`);
            const data = res.data?.data || res.data;
            if (Array.isArray(data)) setLeaveBalances(data);
        } catch { /* silent */ }
    };

    const loadDocuments = async () => {
        try {
            const res = await api.get(`/documents?employee_id=${employeeId}`);
            setDocuments(res.data?.data || res.data || []);
        } catch (error) {
            console.error('Failed to load documents', error);
        }
    };

    const loadSalaryAdvances = async () => {
        try {
            const res = await api.get(`/salary-advances?employee_id=${employeeId}`);
            const data = res.data?.data || res.data || [];
            setSalaryAdvances(data);
        } catch (error) {
            console.error("Failed to load salary advances", error);
        }
    };

    const handleRequestAdvance = async (e) => {
        e.preventDefault();
        setSubmittingAdvance(true);
        try {
            const res = await api.post('/salary-advances', {
                employee_id: employeeId,
                amount: advanceForm.amount,
                reason: advanceForm.reason,
                currency_code: employee?.currency_code || 'UGX'
            });
            if (res.data.success) {
                showAlert('success', 'Salary advance request submitted successfully');
                setShowAdvanceModal(false);
                setAdvanceForm({ amount: '', reason: '' });
                loadSalaryAdvances();
            } else {
                showAlert('error', res.data.message || 'Failed to request advance');
            }
        } catch (error) {
            showAlert('error', 'Error submitting request');
        } finally {
            setSubmittingAdvance(false);
        }
    };

    const loadPayslips = async () => {
        try {
            const res = await api.get(`/payslips?employee_id=${employeeId}`);
            setPayslips(res.data?.data || res.data || []);
        } catch (err) { console.error('Payslip load error:', err); }
    };

    const loadPayrollComponents = async () => {
        try {
            if (!employee?.companies?.length) return;
            const companyIds = employee.companies.map(c => c.id);
            const promises = companyIds.map(id => api.get(`/payroll/components?company_id=${id}`));
            const responses = await Promise.all(promises);
            let allComponents = [];
            responses.forEach(res => {
                allComponents = allComponents.concat(res.data?.data || []);
            });
            setPayrollComponents(allComponents);
        } catch (err) { console.error('Failed to load payroll components', err); }
    };

    const loadEmployeeSalaryData = async () => {
        try {
            const res = await api.get(`/payroll/employee-components?employee_id=${employeeId}`);
            setEmployeeSalaryData(res.data?.data || []);
        } catch (err) { console.error('Failed to load employee salary data', err); }
    };

    useEffect(() => {
        if (employee?.id) {
            loadPayrollComponents();
            loadEmployeeSalaryData();
        }
    }, [employee?.id]);

    const handleSaveSalary = async () => {
        if (!salaryForm.effective_date) {
            return showAlert('Required', 'Effective date is required', 'warning');
        }
        setSavingSalary(true);
        try {
            const components = Object.entries(salaryForm.components)
                .filter(([_, amount]) => amount !== '' && amount !== undefined)
                .map(([component_id, amount]) => {
                    let cleanAmount = String(amount).replace(/,/g, '');
                    return { component_id: parseInt(component_id), amount: parseFloat(cleanAmount) || 0 };
                });

            await api.post('/payroll/employee-components', {
                employee_id: employeeId,
                effective_date: salaryForm.effective_date,
                currency_code: salaryForm.currency_code,
                company_id: salaryForm.company_id,
                components
            });
            showAlert('Success', 'Salary updated successfully', 'success');
            setShowSalaryModal(false);
            loadEmployeeSalaryData();
        } catch (err) {
            console.error('Failed to update salary:', err);
            showAlert('Error', err.response?.data?.message || 'Failed to update salary', 'error');
        } finally {
            setSavingSalary(false);
        }
    };

    const handleDeleteSalary = async (effectiveDate, companyId) => {
        showConfirm('Delete Salary Configuration', 'Are you sure you want to delete this salary record?', async () => {
            try {
                await api.delete(`/payroll/employee-components?employee_id=${employeeId}&effective_date=${effectiveDate}&company_id=${companyId}`);
                showAlert('Success', 'Salary configuration deleted.', 'success');
                loadEmployeeSalaryData();
            } catch (err) {
                showAlert('Error', err.response?.data?.message || 'Failed to delete salary', 'error');
            }
        });
    };

    const handleEditSalary = (dateGroup) => {
        const compMap = {};
        dateGroup.items.forEach(item => {
            compMap[item.component_id] = item.amount;
        });
        setSalaryForm({
            effective_date: dateGroup.effective_date,
            currency_code: dateGroup.items[0]?.currency_code || 'UGX',
            company_id: dateGroup.company_id || '',
            components: compMap
        });
        setShowSalaryModal(true);
    };

    const loadReferenceDocs = async () => {
        try {
            const res = await api.get(`/company-documents?employee_id=${employeeId}`);
            setReferenceDocs(res.data?.data || []);
        } catch (error) {
            console.error('Failed to load reference documents', error);
        }
    };

    const loadAssets = async () => {
        try {
            const res = await api.get(`/assets/employee/${employeeId}`);
            setAssets(res.data?.data || res.data || []);
        } catch { /* silent */ }
    };

    const handleSave = async () => {
        try {
            await api.put(`/employees/${employeeId}`, editForm);
            if (editForm.primary_company_id) {
                fetchCustomFieldDefs(editForm.primary_company_id);
            }
            setEditing(false);
            loadProfile();
        } catch (err) {
            const data = err.response?.data;
            const errorMsg = data?.message || data?.error || err.message || 'Unknown';
            const details = data?.errors ? '\n' + Object.values(data.errors).join('\n') : '';
            showAlert('Error', 'Update failed: ' + errorMsg + details, 'error');
            console.error('Update failed:', err);
        }
    };

    const handleFieldChange = (field, value) => {
        setEditForm(prev => {
            const next = { ...prev, [field]: value };
            if (field === 'company_ids') {
                const primaryId = Array.from(value)[0];
                if (primaryId) fetchCustomFieldDefs(primaryId);
            }
            if (field === 'nationality') {
                handleNationalityChange(prev.nationality, value);
            }
            return next;
        });
    };

    const handleNationalityChange = (oldNat, newNat) => {
        if (!newNat || oldNat === newNat) return;

        // Find a company in the new country
        const newCountryComp = companies.find(c => c.country_name === newNat);
        
        setEditForm(prev => {
            const archives = prev.custom_fields?.archives || {};
            const currentRegionalData = {};
            
            // Collect current custom field values to archive
            customFieldDefs.forEach(def => {
                if (prev.custom_fields?.[def.field_key]) {
                    currentRegionalData[def.field_key] = prev.custom_fields[def.field_key];
                }
            });

            const newArchives = {
                ...archives,
                [oldNat || 'Unknown']: {
                    ...currentRegionalData,
                    archived_at: formatDate(new Date())
                }
            };

            const nextCustomFields = { ...prev.custom_fields, archives: newArchives };
            
            // Clear current regional fields to make room for new ones
            customFieldDefs.forEach(def => {
                delete nextCustomFields[def.field_key];
            });

            return {
                ...prev,
                custom_fields: nextCustomFields,
                primary_company_id: newCountryComp ? newCountryComp.id : prev.primary_company_id
            };
        });

        if (newCountryComp) {
            fetchCustomFieldDefs(newCountryComp.id);
        } else {
            setCustomFieldDefs([]);
        }
    };

    const handleCustomFieldChange = (key, value) => {
        setEditForm(prev => ({
            ...prev,
            custom_fields: { ...prev.custom_fields, [key]: value }
        }));
    };


    const getInitials = () => {
        if (!employee) return '?';
        return ((employee.first_name?.[0] || '') + (employee.last_name?.[0] || '')).toUpperCase();
    };

    const customData = employee?.custom_data
        ? (typeof employee.custom_data === 'string' ? JSON.parse(employee.custom_data) : employee.custom_data)
        : {};

    const getStatusStyles = (statusKey, isFutureLeave = false) => {
        const sk = String(statusKey);
        // Find status in the dynamic unified list from backend
        const status = allStatuses.all?.find(s => 
            String(s.id) === sk || 
            s.status_key === sk || 
            s.code === sk ||
            (s.status_key && s.status_key.toLowerCase() === sk.toLowerCase())
        );
        
        if (status?.color_code) {
            return { 
                backgroundColor: isFutureLeave ? 'transparent' : status.color_code, 
                color: isFutureLeave ? status.color_code : '#fff', 
                borderColor: status.color_code,
                borderStyle: isFutureLeave ? 'dashed' : 'solid',
                borderWidth: isFutureLeave ? '2px' : '1px',
                boxShadow: isFutureLeave ? 'none' : `0 0 0 1px ${status.color_code}30`
            };
        }
        
        // Fallbacks for standard system prefixes if not customized in DB
        const getFallback = (sk) => {
            const fallbacks = {
                present: { backgroundColor: '#ecfdf5', color: '#047857', borderColor: '#10b981' },
                absent: { backgroundColor: '#fef2f2', color: '#b91c1c', borderColor: '#ef4444' },
                late: { backgroundColor: '#fef3c7', color: '#92400e', borderColor: '#fbbf24' },
                weekend: { backgroundColor: '#f9fafb', color: '#6b7280', borderColor: '#e5e7eb' },
                public_holiday: { backgroundColor: '#eff6ff', color: '#1d4ed8', borderColor: '#3b82f6' },
                holiday: { backgroundColor: '#eff6ff', color: '#1d4ed8', borderColor: '#3b82f6' },
                on_leave: { backgroundColor: '#fffbeb', color: '#b45309', borderColor: '#f59e0b' },
                leave: { backgroundColor: '#fffbeb', color: '#b45309', borderColor: '#f59e0b' }
            };
            return fallbacks[sk.toLowerCase()] || {};
        };

        const res = getFallback(sk);
        if (isFutureLeave) {
            return {
                ...res,
                backgroundColor: 'transparent',
                color: res.borderColor,
                borderStyle: 'dashed',
                borderWidth: '2px'
            };
        }
        return res;
    };

    // Build calendar for current month
    const renderCalendar = () => {
        const year = currentYear;
        const month = currentMonth - 1; // 0-indexed for Date constructor
        const firstDay = new Date(year, month, 1).getDay(); // 0=Sun
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const offset = firstDay === 0 ? 6 : firstDay - 1; // shift to Mon start

        const cells = [];
        for (let i = 0; i < offset; i++) cells.push(<div key={`e${i}`} className="cal-day empty" />);
        for (let d = 1; d <= daysInMonth; d++) {
            const log = attendance.logs.find(c => c.day === d);
            const isFutureLeave = !!(log?.is_future && log?.is_leave);
            const style = log ? getStatusStyles(log.status, isFutureLeave) : {};
            const isToday = d === new Date().getDate() && month === new Date().getMonth() && year === new Date().getFullYear();
            const cls = `cal-day ${log ? log.status : ''} ${isToday ? 'today' : ''} ${isFutureLeave ? 'future-leave' : ''}`;
            cells.push(
                <div key={d} className={cls} style={style}>
                    <span className="day-number">{d}</span>
                </div>
            );
        }
        return cells;
    };

    const handleRecalculateBalances = async () => {
        if (!isAdmin) return;
        try {
            await api.post('/leave/recalculate', { employee_id: employeeId });
            loadLeaveBalances();
        } catch (error) {
            console.error('Failed to recalculate balances', error);
        }
    };

    const handlePhotoUpload = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('photo', file);
        formData.append('employee_id', employeeId);

        try {
            setLoading(true);
            const res = await api.post('/employees/upload_photo', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            // Check nested data structure based on backend response
            const responseData = res.data?.data || res.data;
            const newPath = responseData?.file_path;
            
            if (newPath) {
                setEmployee(prev => ({ ...prev, profile_image_path: newPath }));
                // Force a reload of the profile to ensure everything is synced
                loadProfile();
                showAlert('Success', 'Profile photo updated successfully.', 'success');
            } else {
                console.error('Upload success but no file path returned:', res.data);
                showAlert('Warning', 'Photo uploaded but server did not return the new path.', 'warning');
            }
        } catch (err) {
            console.error('Photo upload failed:', err);
            const msg = err.response?.data?.message || err.response?.data?.error || err.message;
            showAlert('Error', 'Failed to upload photo: ' + msg, 'error');
        } finally {
            setLoading(false);
        }
    };
    const handleDeletePhoto = async () => {
        showConfirm('Remove Photo', 'Are you sure you want to remove your profile photo?', async () => {
            try {
                setLoading(true);
                await api.delete(`/employees/upload_photo?employee_id=${employeeId}`);
                setEmployee(prev => ({ ...prev, profile_image_path: null }));
                setProfileImageError(false);
                showAlert('Success', 'Photo removed.', 'success');
            } catch (err) {
                console.error('Photo deletion failed:', err);
                const msg = err.response?.data?.message || err.response?.data?.error || err.message;
                showAlert('Error', 'Failed to delete photo: ' + msg, 'error');
            } finally {
                setLoading(false);
            }
        });
    };

    const handleSendWelcomeEmail = async () => {
        showConfirm('Send Welcome Email', 'This will send the welcome email containing the login instructions to the employee. Are you sure you want to proceed?', async () => {
            try {
                setLoading(true);
                await api.post(`/employees/${employeeId}/send-welcome-email`);
                showAlert('Success', 'Welcome email sent successfully.', 'success');
            } catch (err) {
                console.error('Failed to send welcome email:', err);
                const msg = err.response?.data?.message || err.response?.data?.error || err.message;
                showAlert('Error', 'Failed to send welcome email: ' + msg, 'error');
            } finally {
                setLoading(false);
            }
        });
    };

    const handleOpenAssetModal = async () => {
        try {
            const res = await api.get('/assets');
            const allAssets = res.data?.data || res.data || [];
            setAvailableAssets(allAssets.filter(a => a.status === 'available'));
            setShowAssetModal(true);
        } catch (error) {
            console.error('Failed to fetch available assets', error);
            showAlert('Failed to load available assets', 'error');
        }
    };

    const handleAllocateAssetSubmit = async (e) => {
        e.preventDefault();
        if (!assetAllocationForm.asset_id) {
            showAlert('Please select an asset to allocate.', 'warning');
            return;
        }
        try {
            const formData = new FormData();
            formData.append('employee_id', employeeId);
            formData.append('asset_id', assetAllocationForm.asset_id);
            formData.append('allocation_date', assetAllocationForm.allocation_date);
            
            if (assetAllocationForm.expected_return_date !== '') {
                formData.append('expected_return_date', assetAllocationForm.expected_return_date);
            }
            if (assetAllocationForm.remarks !== '') {
                formData.append('remarks', assetAllocationForm.remarks);
            }
            if (assetAllocationForm.attachment) {
                formData.append('attachment', assetAllocationForm.attachment);
            }

            await api.post('/assets/allocate', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            setShowAssetModal(false);
            setAssetAllocationForm({
                asset_id: '',
                allocation_date: new Date().toISOString().split('T')[0],
                expected_return_date: '',
                remarks: '',
                attachment: null
            });
            showAlert('Wait', 'Asset allocated successfully', 'success');
            loadAssets();
        } catch (error) {
            console.error('Failed to allocate asset', error);
            showAlert('Error', error.response?.data?.message || 'Failed to allocate asset', 'error');
        }
    };

    const handleDocumentModalSubmit = async (e) => {
        e.preventDefault();
        if (!docForm.file) {
            showAlert('Wait', "Please select a file.", 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('document', docForm.file);
        formData.append('employee_id', employeeId);
        formData.append('document_name', docForm.name || docForm.file.name);
        formData.append('document_type', docForm.type);
        formData.append('expiry_date', docForm.expiryDate);

        try {
            setLoading(true);
            await api.post('/documents', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            showAlert('Success', 'Document uploaded successfully!', 'success');
            setShowDocModal(false);
            setDocForm({ type: 'Passport', name: '', expiryDate: '', file: null });
            loadDocuments(); // Refresh the list
        } catch (err) {
            console.error('Document upload failed:', err);
            const msg = err.response?.data?.message || err.response?.data?.error || err.message;
            showAlert('Error', 'Failed to upload document: ' + msg, 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleEditDocumentSubmit = async (e) => {
        e.preventDefault();
        if (!editDocForm.name) {
            showAlert('Required', "Document name is required.", 'warning');
            return;
        }

        try {
            setLoading(true);
            await api.put(`/documents/${editDocForm.id}`, {
                document_name: editDocForm.name,
                expiry_date: editDocForm.expiryDate
            });
            showAlert('Success', "Document updated successfully!", 'success');
            setShowEditDocModal(false);
            loadDocuments(); // Refresh the list
        } catch (err) {
            console.error("Document update failed:", err);
            const msg = err.response?.data?.message || err.response?.data?.error || err.message;
            showAlert('Error', "Failed to update document: " + msg, 'error');
        } finally {
            setLoading(false);
        }
    };

    const handleDeleteDocument = async (docId) => {
        if (!isAdmin) return;
        showConfirm('Delete Document', 'Are you sure you want to delete this document? This action cannot be undone.', async () => {
            try {
                setLoading(true);
                await api.delete(`/documents/${docId}`);
                loadDocuments(); // Refresh the list
                showAlert('Success', 'Document deleted.', 'success');
            } catch (err) {
                console.error('Document deletion failed:', err);
                showAlert('Error', 'Failed to delete document: ' + (err.response?.data?.message || err.message), 'error');
            } finally {
                setLoading(false);
            }
        });
    };

    const getFlagEmoji = (isoCode) => {
        if (!isoCode) return <MapPin size={14} />;
        
        const ISO3_TO_ISO2 = {
            'AFG': 'af', 'ALB': 'al', 'DZA': 'dz', 'AGO': 'ao', 'ARG': 'ar', 'AUS': 'au', 'AUT': 'at', 'BHR': 'bh', 'BGD': 'bd', 
            'BEL': 'be', 'BWA': 'bw', 'BRA': 'br', 'CMR': 'cm', 'CAN': 'ca', 'CHL': 'cl', 'CHN': 'cn', 'COL': 'co', 'COD': 'cd', 
            'CIV': 'ci', 'CZE': 'cz', 'DNK': 'dk', 'EGY': 'eg', 'ETH': 'et', 'FIN': 'fi', 'FRA': 'fr', 'DEU': 'de', 'GHA': 'gh', 
            'GRC': 'gr', 'HUN': 'hu', 'IND': 'in', 'IDN': 'id', 'IRQ': 'iq', 'IRL': 'ie', 'ISR': 'il', 'ITA': 'it', 'JPN': 'jp', 
            'JOR': 'jo', 'KEN': 'ke', 'KWT': 'kw', 'LBN': 'lb', 'LBY': 'ly', 'MWI': 'mw', 'MYS': 'my', 'MEX': 'mx', 'MAR': 'ma', 
            'MOZ': 'mz', 'NAM': 'na', 'NLD': 'nl', 'NZL': 'nz', 'NGA': 'ng', 'NOR': 'no', 'OMN': 'om', 'PAK': 'pk', 'PHL': 'ph', 
            'POL': 'pl', 'PRT': 'pt', 'QAT': 'qa', 'ROU': 'ro', 'RUS': 'ru', 'RWA': 'rw', 'SAU': 'sa', 'SEN': 'sn', 'SGP': 'sg', 
            'ZAF': 'za', 'KOR': 'kr', 'SSD': 'ss', 'ESP': 'es', 'LKA': 'lk', 'SDN': 'sd', 'SWE': 'se', 'CHE': 'ch', 'TZA': 'tz', 
            'THA': 'th', 'TUN': 'tn', 'TUR': 'tr', 'UGA': 'ug', 'UKR': 'ua', 'ARE': 'ae', 'GBR': 'gb', 'USA': 'us', 'VNM': 'vn', 
            'ZMB': 'zm', 'ZWE': 'zw'
        };

        const code = (isoCode.length === 3 ? ISO3_TO_ISO2[isoCode.toUpperCase()] : isoCode.toLowerCase()) || isoCode.substring(0, 2).toLowerCase();
        if (!code || code.length !== 2) return <MapPin size={14} />;

        return (
            <img 
                src={`https://flagcdn.com/w40/${code}.png`} 
                alt={code}
                style={{ width: '20px', height: 'auto', borderRadius: '2px', verticalAlign: 'middle' }}
                onError={(e) => { e.target.style.display = 'none'; e.target.nextSibling.style.display = 'inline'; }}
            />
        );
    };

    const isMale = employee?.gender === 'male';
    const isFemale = employee?.gender === 'female';

    const filteredBalances = leaveBalances.filter(b => {
        if (b.gender_restriction === 'male' && !isMale) return false;
        if (b.gender_restriction === 'female' && !isFemale) return false;
        return true;
    });

    if (loading) {

        return (
            <div style={{ padding: '4rem', textAlign: 'center', color: 'var(--color-text-muted)' }}>
                Loading employee profile...
            </div>
        );
    }

    if (!employeeId) {
        return (
            <div style={{ padding: '4rem', textAlign: 'center', color: 'var(--color-text-muted)' }}>
                Session expired or invalid employee. Please log in again.
                <button className="btn btn-primary" onClick={() => navigate('/login')} style={{ marginTop: '1rem', display: 'block', margin: '1rem auto' }}>
                    Go to Login
                </button>
            </div>
        );
    }

    if (!employee) {
        return (
            <div style={{ padding: '4rem', textAlign: 'center', color: 'var(--color-text-muted)' }}>
                Employee not found.
                {isAdmin && (
                    <button className="btn btn-outline" onClick={() => navigate('/employees')} style={{ marginTop: '1rem', display: 'block', margin: '1rem auto' }}>
                        Back to Directory
                    </button>
                )}
            </div>
        );
    }

    return (
        <>
            <style>{`
                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .profile-container {
                    animation: slideUp 0.4s ease forwards;
                    max-width: 1400px;
                    margin: 0 auto;
                    font-family: var(--font-body);
                }
                .profile-grid {
                    display: grid;
                    grid-template-columns: 1fr 340px;
                    gap: 2rem;
                    align-items: start;
                }
                .profile-header-card {
                    background: var(--color-white);
                    border-radius: var(--radius-lg);
                    overflow: hidden;
                    border: 1px solid var(--color-border);
                    margin-bottom: 2rem;
                    box-shadow: var(--shadow-sm);
                    position: relative;
                }
                .cover-photo {
                    height: 180px;
                    background: linear-gradient(135deg, var(--color-rose-gold) 0%, #083b23 100%);
                    position: relative;
                }
                .cover-photo::after {
                    content: '';
                    position: absolute;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCI+PGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMiIgZmlsbD0icmdiYSgyNTUsIDI1NSwgMjU1LCAwLjEpIi8+PC9zdmc+') repeat;
                }
                .avatar-container {
                    position: absolute;
                    bottom: -65px;
                    left: 32px;
                    z-index: 10;
                    display: flex;
                    align-items: flex-end;
                    gap: 16px;
                }
                .profile-avatar {
                    width: 130px; height: 130px;
                    border-radius: 28px; border: 5px solid var(--color-white);
                    background: var(--color-ivory);
                    display: flex; align-items: center; justify-content: center;
                    font-size: 42px; font-weight: 700; color: var(--color-rose-gold);
                    box-shadow: var(--shadow-md);
                    overflow: hidden;
                    position: relative;
                }
                .profile-avatar img {
                    width: 100%; height: 100%; object-fit: cover;
                }
                .avatar-edit-overlay {
                    position: absolute;
                    inset: 0;
                    background: rgba(0,0,0,0.4);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    transition: opacity 0.2s;
                    color: white;
                }
                .profile-avatar:hover .avatar-edit-overlay {
                    opacity: 1;
                }
                .avatar-upload-btn {
                    width: 36px; height: 36px; border-radius: 12px;
                    background: var(--color-white); border: 1px solid var(--color-border);
                    display: flex; align-items: center; justify-content: center;
                    color: var(--color-charcoal); cursor: pointer;
                    box-shadow: var(--shadow-sm);
                    transition: all 0.2s;
                    margin-bottom: 8px;
                }
                .avatar-upload-btn:hover { background: var(--color-ivory); transform: scale(1.05); }
                .optimal-format-card {
                    background: rgba(255, 255, 255, 0.9);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    padding: 10px 14px;
                    border-radius: 16px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                    min-width: 160px;
                    margin-bottom: 8px;
                    animation: slideUp 0.3s ease;
                }
                .profile-info-area {
                    padding: 75px 2rem 2rem 2rem;
                    position: relative;
                }
                .profile-actions {
                    position: absolute; top: 1.5rem; right: 2rem;
                    display: flex; gap: 12px;
                }
                .profile-name { 
                    font-family: var(--font-heading);
                    font-size: 30px; 
                    font-weight: 700; 
                    color: var(--color-charcoal); 
                    margin-bottom: 6px; 
                    letter-spacing: -0.02em;
                }
                .profile-title-text { 
                    font-size: 16px; 
                    color: var(--color-text-muted); 
                    margin-bottom: 12px; 
                    font-weight: 500;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .profile-location { 
                    font-size: 14px; 
                    color: var(--color-text-muted); 
                    margin-bottom: 1.5rem; 
                    display: flex; 
                    align-items: center; 
                    gap: 8px; 
                    font-weight: 500;
                }
                .section-card {
                    background: var(--color-white); 
                    border-radius: var(--radius-lg);
                    border: 1px solid var(--color-border); 
                    padding: 1.75rem; 
                    margin-bottom: 2rem;
                    box-shadow: var(--shadow-sm);
                }
                .section-header { 
                    display: flex; 
                    justify-content: space-between; 
                    align-items: center; 
                    margin-bottom: 1.5rem; 
                    border-bottom: 1px solid var(--color-ivory);
                    padding-bottom: 1rem;
                }
                .section-title { 
                    font-family: var(--font-heading);
                    font-size: 18px; 
                    font-weight: 700; 
                    color: var(--color-charcoal); 
                    margin: 0; 
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .detail-row { display: flex; margin-bottom: 16px; align-items: flex-start; }
                .detail-row.vertical { flex-direction: column; gap: 6px; }
                .detail-label { 
                    font-size: 11px; 
                    color: var(--color-text-muted); 
                    font-weight: 700; 
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    min-width: 160px;
                }
                .detail-value { 
                    font-size: 14px; 
                    color: var(--color-charcoal); 
                    font-weight: 600;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex: 1;
                }
                .experience-item { display: flex; gap: 1.25rem; margin-bottom: 1.5rem; padding: 1rem; border-radius: var(--radius-md); background: var(--color-ivory); }
                .exp-icon {
                    width: 44px; height: 44px; background: var(--color-white);
                    border-radius: 12px; display: flex; align-items: center;
                    justify-content: center; color: var(--color-rose-gold); flex-shrink: 0;
                    box-shadow: var(--shadow-sm);
                }
                .exp-details h4 { font-family: var(--font-heading); font-size: 15px; font-weight: 700; margin-bottom: 4px; color: var(--color-charcoal); }
                .exp-details p { font-size: 13px; color: var(--color-text-muted); margin-bottom: 2px; }
                
                .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
                .stat-box { background: var(--color-white); border: 1px solid var(--color-border); padding: 1rem; border-radius: 12px; text-align: center; }
                .stat-value { font-family: var(--font-heading); font-size: 24px; font-weight: 800; color: var(--color-charcoal); margin-bottom: 4px; }
                .stat-label { font-size: 10px; color: var(--color-text-muted); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
                
                .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; margin-top: 1rem; }
                .cal-day-header { text-align: center; font-size: 11px; font-weight: 800; color: var(--color-text-muted); padding-bottom: 4px; }
                .cal-day {
                    aspect-ratio: 1; background: var(--color-white); border: 1px solid var(--color-border);
                    border-radius: 10px; display: flex; align-items: center;
                    justify-content: center; font-size: 13px; font-weight: 600;
                    color: var(--color-charcoal); position: relative; transition: all 0.2s;
                }
                .day-number { position: relative; z-index: 2; }
                .cal-day.present { background: #ecfdf5; border-color: #10b981; color: #065f46; }
                .cal-day.absent  { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
                .cal-day.leave, .cal-day.on_leave { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
                .cal-day.late    { background: #fef3c7; border-color: #fbbf24; color: #92400e; }
                .cal-day.holiday, .cal-day.public_holiday { background: #eff6ff; border-color: #3b82f6; color: #1e40af; }
                .cal-day.weekend  { background: #f9fafb; color: #6b7280; borderColor: #e5e7eb; opacity: 0.6; }
                .cal-day.empty   { border: none; background: transparent; }
                .cal-day.today {
                    border: 2px solid var(--color-rose-gold) !important;
                    box-shadow: 0 0 12px rgba(181, 148, 114, 0.3);
                    font-weight: 800;
                    color: var(--color-rose-gold) !important;
                }
                
                .doc-table { width: 100%; border-collapse: collapse; }
                .doc-table th { text-align: left; padding: 1rem 0.5rem; border-bottom: 1px solid var(--color-border); font-size: 11px; color: var(--color-text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; }
                .doc-table td { padding: 1.25rem 0.5rem; border-bottom: 1px solid var(--color-ivory); font-size: 14px; }
                
                .badge { padding: 4px 12px; border-radius: 100px; font-size: 11px; font-weight: 700; }
                .badge-success { background: #ecfdf5; color: #059669; }
                .badge-blue { background: #eff6ff; color: #2563eb; }
                .badge-neutral { background: var(--color-ivory); color: var(--color-charcoal); }
                
                .form-control {
                    width: 100%; padding: 10px 14px; border: 1px solid var(--color-border);
                    border-radius: var(--radius-md); font-size: 14px; background: var(--color-white); font-family: inherit;
                    transition: border-color 0.2s, box-shadow 0.2s;
                }
                .form-control:focus { outline: none; border-color: var(--color-rose-gold); box-shadow: 0 0 0 3px rgba(181, 148, 114, 0.1); }
            `}</style>

            <div className="profile-container">

                <div className="profile-grid" style={{ gridTemplateColumns: (canViewConfidential || canViewAttendance || canViewLeave) ? '1fr 340px' : '1fr' }}>
                    {/* ── Left Main Column ── */}
                    <div>
                        {/* Hero Card */}
                        <div className="profile-header-card">
                            <div className="cover-photo">
                                <div className="avatar-container">
                                    <div 
                                        className="profile-avatar"
                                        style={{ cursor: (editing || employee.profile_image_path) ? 'pointer' : 'default' }}
                                        onClick={() => {
                                            if (editing) {
                                                setShowPhotoModal(true);
                                            } else if (employee.profile_image_path) {
                                                setPreviewDoc({
                                                    document_name: `${employee.first_name} ${employee.last_name}`,
                                                    document_type: 'Profile Picture',
                                                    file_path: employee.profile_image_path,
                                                    created_at_utc: new Date().toISOString()
                                                });
                                            }
                                        }}
                                    >
                                        {employee.profile_image_path && !profileImageError ? (
                                            <img src={getSecureMediaUrl(employee.profile_image_path)} alt="Profile" 
                                                 onError={() => setProfileImageError(true)} />
                                        ) : getInitials()}
                                        
                                        {editing && (isAdmin || isOwnProfile) && (
                                            <div className="avatar-edit-overlay">
                                                <Pencil size={24} />
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="profile-info-area">
                                <div className="profile-actions">
                                    {!editing && isAdmin && (
                                        <button className="btn btn-secondary" onClick={handleSendWelcomeEmail} disabled={loading} style={{ gap: '8px', background: '#eff6ff', color: '#1e40af', border: '1px solid #bfdbfe' }}>
                                            <Mail size={16} /> Send Welcome Email
                                        </button>
                                    )}
                                    {editing ? (
                                        <button className="btn btn-outline" onClick={handleSave} style={{ gap: '8px' }}>
                                            <Save size={16} /> Save Changes
                                        </button>
                                    ) : (
                                        (isAdmin || isOwnProfile) && (
                                            <button className="btn btn-primary" onClick={() => setEditing(true)} style={{ gap: '8px' }}>
                                                <Pencil size={16} /> Edit Profile
                                            </button>
                                        )
                                    )}
                                </div>

                                {editing ? (
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '16px' }}>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>First Name</label>
                                            <input type="text" className="form-control" value={editForm.first_name}
                                                disabled={!isAdmin}
                                                onChange={e => handleFieldChange('first_name', e.target.value)} />
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Last Name</label>
                                            <input type="text" className="form-control" value={editForm.last_name}
                                                disabled={!isAdmin}
                                                onChange={e => handleFieldChange('last_name', e.target.value)} />
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Work Email</label>
                                            <input type="email" className="form-control" value={editForm.email}
                                                disabled={!isAdmin}
                                                onChange={e => handleFieldChange('email', e.target.value)} />
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Personal Email</label>
                                            <input type="email" className="form-control" value={editForm.personal_email || ''}
                                                onChange={e => handleFieldChange('personal_email', e.target.value)} />
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Work Phone</label>
                                            <PhoneInput
                                                value={editForm.phone || ''}
                                                defaultCountry={employee?.companies?.find(c => c.is_primary)?.iso_code || employee?.companies?.[0]?.iso_code || 'ae'}
                                                onChange={val => handleFieldChange('phone', val)}
                                            />
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Personal Contact Number</label>
                                            <PhoneInput
                                                value={editForm.personal_phone || ''}
                                                defaultCountry={employee?.companies?.find(c => c.is_primary)?.iso_code || employee?.companies?.[0]?.iso_code || 'ae'}
                                                onChange={val => handleFieldChange('personal_phone', val)}
                                            />
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Date of Birth</label>
                                            <DateInput value={editForm.date_of_birth}
                                                onChange={val => handleFieldChange('date_of_birth', val)} />
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Gender</label>
                                            <select className="form-control" value={editForm.gender} onChange={e => handleFieldChange('gender', e.target.value)}>
                                                <option value="">Select gender</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Nationality</label>
                                            <select className="form-control" value={editForm.nationality} 
                                                disabled={!isAdmin}
                                                onChange={e => handleFieldChange('nationality', e.target.value)}>
                                                <option value="">Select nationality</option>
                                                {COUNTRY_DATA.map(c => <option key={c.iso_code} value={c.name}>{c.name}</option>)}
                                            </select>
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Status</label>
                                            <select className="form-control" value={editForm.status} 
                                                disabled={!isAdmin}
                                                onChange={e => handleFieldChange('status', e.target.value)}>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                                <option value="onboarding">Onboarding</option>
                                                <option value="offboarding">Offboarding</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Employment Type</label>
                                            <select className="form-control" value={editForm.employment_type} 
                                                disabled={!isAdmin}
                                                onChange={e => handleFieldChange('employment_type', e.target.value)}>
                                                <option value="full_time">Full Time</option>
                                                <option value="part_time">Part Time</option>
                                                <option value="contractor">Contractor</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Staff ID</label>
                                            <input type="text" className="form-control" value={editForm.employee_code}
                                                disabled={!isAdmin}
                                                onChange={e => handleFieldChange('employee_code', e.target.value)} />
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <h1 className="profile-name">{employee.first_name} {employee.last_name}</h1>
                                        <div className="profile-title-text" style={{ fontSize: '15px' }}>
                                            {employee.designation_title || employee.designation || 'Designation'} &bull; {employee.companies?.find(c => c.is_primary)?.name || employee.companies?.[0]?.name || primaryCompanyName}
                                        </div>
                                        {employee.manager_first_name && (
                                            <div className="profile-manager-info" style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '14px', color: 'var(--color-text-muted)', marginBottom: '8px' }}>
                                                <User size={14} />
                                                Reporting to: <span style={{ fontWeight: 600, color: 'var(--color-primary)' }}>{employee.manager_first_name} {employee.manager_last_name}</span>
                                            </div>
                                        )}
                                        <div className="profile-location">
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '16px', flexWrap: 'wrap' }}>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                    {(() => {
                                                        const primaryComp = employee.companies?.find(c => c.is_primary) || employee.companies?.[0];
                                                        return (
                                                            <>
                                                                <span style={{ display: 'flex', alignItems: 'center' }}>
                                                                    {getFlagEmoji(primaryComp?.iso_code)}
                                                                    <MapPin size={14} style={{ display: 'none' }} />
                                                                </span>
                                                                {primaryComp?.country_name || employee.country || 'International'}
                                                            </>
                                                        );
                                                    })()}
                                                </div>
                                                <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }} title="Work Email">
                                                    <Mail size={14} />
                                                    <a href={`mailto:${employee.email}`} style={{ color: 'inherit' }}>{employee.email}</a>
                                                </div>
                                                {employee.personal_email && (
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }} title="Personal Email">
                                                        <Mail size={14} style={{ opacity: 0.6 }} />
                                                        <a href={`mailto:${employee.personal_email}`} style={{ color: 'inherit' }}>{employee.personal_email} (Personal)</a>
                                                    </div>
                                                )}
                                                {employee.phone && (
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }} title="Work Phone">
                                                        <Phone size={14} />
                                                        <a href={`tel:${employee.phone}`} style={{ color: 'inherit' }}>{employee.phone}</a>
                                                    </div>
                                                )}
                                                {employee.personal_phone && (
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }} title="Personal Contact Number">
                                                        <Phone size={14} style={{ opacity: 0.6 }} />
                                                        <a href={`tel:${employee.personal_phone}`} style={{ color: 'inherit' }}>{employee.personal_phone} (Personal)</a>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="detail-row vertical" style={{ height: 'auto', minHeight: '60px' }}>
                                            <span className="detail-label">
                                                {(isAdmin || isOwnProfile) && (employee.companies || []).filter(c => c.is_active != 0).length > 1 ? 'Associated Offices' : 'Primary Office'}
                                            </span>
                                            <div className="company-tag-container" style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', marginTop: '8px' }}>
                                                {(() => {
                                                    const activeCompanies = (employee.companies || []).filter(c => c.is_active != 0);
                                                    const companiesToShow = (isAdmin || isOwnProfile) 
                                                        ? activeCompanies 
                                                        : activeCompanies.filter(c => c.is_primary).slice(0, 1);
                                                    
                                                    if (companiesToShow.length === 0 && activeCompanies.length > 0) {
                                                        companiesToShow.push(activeCompanies[0]);
                                                    }

                                                    return companiesToShow.map(comp => {
                                                        const isPrimary = comp.is_primary || activeCompanies.length === 1;
                                                        return (
                                                            <div key={comp.id} className="company-tag" style={{
                                                                background: isPrimary ? 'rgba(52, 152, 219, 0.15)' : 'rgba(149, 165, 166, 0.1)',
                                                                border: isPrimary ? '1px solid var(--color-primary)' : '1px solid var(--color-border)',
                                                                padding: '4px 12px',
                                                                borderRadius: '20px',
                                                                fontSize: '13px',
                                                                display: 'flex',
                                                                alignItems: 'center',
                                                                gap: '6px'
                                                            }}>
                                                                <Building size={14} style={{ color: isPrimary ? 'var(--color-primary)' : 'var(--color-text-muted)' }} />
                                                                <span style={{ fontWeight: isPrimary ? '600' : '400', color: isPrimary ? 'var(--color-primary)' : 'inherit' }}>
                                                                    {comp.name}
                                                                </span>
                                                                {(isAdmin || isOwnProfile) && activeCompanies.length > 1 && !!comp.is_primary && (
                                                                    <span style={{ fontSize: '10px', color: 'var(--color-primary)', fontWeight: 700, textTransform: 'uppercase' }}>Primary</span>
                                                                )}
                                                            </div>
                                                        );
                                                    });
                                                })()}
                                                {/* Deactivated companies (greyed out) */}
                                                {(employee.companies || []).filter(c => c.is_active == 0).length > 0 && (
                                                    <div style={{ width: '100%', marginTop: '8px' }}>
                                                        <span style={{ fontSize: '10px', color: 'var(--color-text-muted)', fontWeight: 600, cursor: 'pointer', textDecoration: 'underline' }}
                                                            onClick={() => {
                                                                const el = document.getElementById('deactivated-companies');
                                                                if (el) el.style.display = el.style.display === 'none' ? 'flex' : 'none';
                                                            }}>
                                                            Show {(employee.companies || []).filter(c => c.is_active == 0).length} deactivated
                                                        </span>
                                                        <div id="deactivated-companies" style={{ display: 'none', flexWrap: 'wrap', gap: '8px', marginTop: '6px' }}>
                                                            {(employee.companies || []).filter(c => c.is_active == 0).map(comp => (
                                                                <div key={comp.id} className="company-tag" style={{
                                                                    background: 'rgba(0,0,0,0.03)',
                                                                    border: '1px dashed var(--color-border)',
                                                                    padding: '4px 12px',
                                                                    borderRadius: '20px',
                                                                    fontSize: '13px',
                                                                    display: 'flex',
                                                                    alignItems: 'center',
                                                                    gap: '6px',
                                                                    opacity: 0.5
                                                                }}>
                                                                    <Building size={14} style={{ color: 'var(--color-text-muted)' }} />
                                                                    <span style={{ fontWeight: '400', textDecoration: 'line-through' }}>{comp.name}</span>
                                                                    <span style={{ fontSize: '10px', color: '#ef4444', fontWeight: 600 }}>Deactivated</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="section-divider" style={{ margin: '15px 0' }}></div>
                                    </>
                                )}
                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <span className={`badge ${employee.status === 'active' ? 'badge-success' : 'badge-neutral'}`}>
                                        {(employee.status || 'active').charAt(0).toUpperCase() + (employee.status || 'active').slice(1)}
                                    </span>
                                    <span className="badge badge-blue">
                                        {(employee.employment_type || 'full_time').split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}
                                    </span>
                                    <span className="badge badge-neutral">{employee.employee_code}</span>
                                    {canViewConfidential && employee.role_id && (
                                        <span className="badge badge-blue" style={{ background: '#3730a3', color: '#fff' }}>
                                            {roles.find(r => r.id == employee.role_id)?.name.replace('_', ' ') || 'Assigned Access'}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Employment Details Card (Edit Mode) */}
                        {editing && isAdmin && (
                            <div className="section-card">
                                <div className="section-header">
                                    <h2 className="section-title">Employment Details</h2>
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                                    <div className="detail-row vertical">
                                        <span className="detail-label" style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            Companies
                                            <span style={{ fontSize: '10px', color: 'var(--color-text-muted)', fontWeight: 400, textTransform: 'none', letterSpacing: 0 }}>
                                                (toggle to add/remove • first selected = primary)
                                            </span>
                                        </span>
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px', marginBottom: '10px' }}>
                                            {companies.map(comp => {
                                                const isSelected = editForm.company_ids?.includes(comp.id);
                                                const isPrimary = editForm.primary_company_id == comp.id;
                                                return (
                                                    <div key={comp.id}
                                                        onClick={() => {
                                                            const currentIds = editForm.company_ids || [];
                                                            const newIds = isSelected
                                                                ? currentIds.filter(id => id !== comp.id)
                                                                : [...currentIds, comp.id];
                                                            setEditForm(prev => {
                                                                let updatedForm = { ...prev, company_ids: newIds };
                                                                // If primary was removed, reset it or pick a new one if available
                                                                if (isSelected && isPrimary) {
                                                                    updatedForm.primary_company_id = newIds.length > 0 ? newIds[0] : '';
                                                                } else if (!isSelected && newIds.length === 1) {
                                                                    // If this is the first company selected, make it primary
                                                                    updatedForm.primary_company_id = comp.id;
                                                                }
                                                                // Remove from payroll_company_ids if unselected
                                                                if (isSelected) {
                                                                    updatedForm.payroll_company_ids = (prev.payroll_company_ids || []).filter(id => id !== comp.id);
                                                                } else {
                                                                    // Default to included when added
                                                                    updatedForm.payroll_company_ids = [...(prev.payroll_company_ids || []), comp.id];
                                                                }
                                                                return updatedForm;
                                                            });
                                                        }}
                                                        style={{
                                                            cursor: 'pointer',
                                                            padding: '6px 14px',
                                                            borderRadius: '8px',
                                                            border: '1px solid',
                                                            borderColor: isSelected ? 'var(--color-primary)' : 'var(--color-border)',
                                                            backgroundColor: isSelected ? 'rgba(52, 152, 219, 0.05)' : 'transparent',
                                                            fontSize: '13px',
                                                            transition: 'all 0.2s'
                                                        }}>
                                                        {comp.name}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                        {editForm.company_ids?.length > 0 && (
                                            <div style={{ padding: '10px', backgroundColor: 'var(--color-bg-secondary)', borderRadius: '8px', border: '1px solid var(--color-border)' }}>
                                                <span className="detail-label" style={{ marginBottom: '8px', display: 'block' }}>Designate Primary Company:</span>
                                                <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                                    {companies.filter(c => editForm.company_ids.includes(c.id)).map(c => (
                                                        <div key={c.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '4px 0' }}>
                                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                                <span style={{ fontSize: '13px', width: '120px' }}>{c.name}</span>
                                                                <label style={{ fontSize: '11px', display: 'flex', alignItems: 'center', gap: '4px', cursor: 'pointer', margin: 0 }}>
                                                                    <input 
                                                                        type="checkbox" 
                                                                        checked={editForm.payroll_company_ids?.includes(c.id) || false}
                                                                        onChange={(e) => {
                                                                            setEditForm(prev => {
                                                                                const currentPayrollIds = prev.payroll_company_ids || [];
                                                                                const newPayrollIds = e.target.checked 
                                                                                    ? [...currentPayrollIds, c.id]
                                                                                    : currentPayrollIds.filter(id => id !== c.id);
                                                                                return { ...prev, payroll_company_ids: newPayrollIds };
                                                                            });
                                                                        }}
                                                                    />
                                                                    Include in Payroll
                                                                </label>
                                                            </div>
                                                            <button
                                                                type="button"
                                                                onClick={() => {
                                                                    setEditForm({ ...editForm, primary_company_id: c.id });
                                                                    fetchCustomFieldDefs(c.id);
                                                                }}
                                                                className={`btn-icon ${editForm.primary_company_id == c.id ? 'active' : ''}`}
                                                                style={{
                                                                    fontSize: '11px',
                                                                    padding: '4px 10px',
                                                                    borderRadius: '4px',
                                                                    backgroundColor: editForm.primary_company_id == c.id ? 'var(--color-primary)' : 'var(--color-bg-primary)',
                                                                    color: editForm.primary_company_id == c.id ? '#fff' : 'var(--color-text)',
                                                                    border: '1px solid var(--color-primary)',
                                                                    cursor: 'pointer'
                                                                }}>
                                                                {editForm.primary_company_id == c.id ? 'Primary' : 'Select as Primary'}
                                                            </button>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                    <div className="section-divider" style={{ margin: '15px 0' }}></div>
                                    <div>
                                        <label style={{ fontSize: '12px', fontWeight: '500' }}>Department</label>
                                        <select className="form-control" value={editForm.department_id} onChange={e => handleFieldChange('department_id', e.target.value)}>
                                            <option value="">Select Department</option>
                                            {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                        </select>
                                    </div>
                                    <div>
                                        <label style={{ fontSize: '12px', fontWeight: '500' }}>Designation</label>
                                        <select className="form-control" value={editForm.designation_id} onChange={e => handleFieldChange('designation_id', e.target.value)}>
                                            <option value="">Select Designation</option>
                                            {designations.filter(dg => !editForm.department_id || dg.department_id == editForm.department_id).map(dg => (
                                                <option key={dg.id} value={dg.id}>{dg.title}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label style={{ fontSize: '12px', fontWeight: '500' }}>Reporting Manager</label>
                                        <select className="form-control" value={editForm.reporting_manager_id} onChange={e => handleFieldChange('reporting_manager_id', e.target.value)}>
                                            <option value="">Select Manager</option>
                                            {filteredManagers.map(m => (
                                                <option key={m.id} value={m.id}>{m.first_name} {m.last_name} ({m.designation})</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label style={{ fontSize: '12px', fontWeight: '500' }}>Hire Date</label>
                                        <DateInput value={editForm.hire_date || ''} onChange={val => handleFieldChange('hire_date', val)} />
                                    </div>
                                    <div>
                                        <label style={{ fontSize: '12px', fontWeight: '500' }}>System Access Level *</label>
                                        <select className="form-control" value={editForm.role_id} onChange={e => handleFieldChange('role_id', e.target.value)}>
                                            <option value="">Select Role</option>
                                            {roles.map(r => <option key={r.id} value={r.id}>{r.name.replace('_', ' ')}</option>)}
                                        </select>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Experience Card */}
                        {/* Professional Experience Card */}
                        <div className="section-card">
                            <div className="section-header">
                                <h2 className="section-title"><Award size={20} /> Professional Experience</h2>
                            </div>
                            <div className="experience-item" style={{ display: 'flex', gap: '16px' }}>
                                <div className="exp-icon" style={{ 
                                    width: '40px', 
                                    height: '40px', 
                                    background: 'rgba(236,72,153,0.1)', 
                                    borderRadius: '10px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    color: '#EC4899'
                                }}>
                                    <Briefcase size={20} />
                                </div>
                                <div className="exp-details" style={{ flex: 1 }}>
                                    <h4 style={{ margin: 0, fontSize: '15px', color: 'var(--color-charcoal)' }}>{employee.designation || 'Specialist'}</h4>
                                    <p style={{ margin: '4px 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>
                                        {employee.companies?.find(c => c.is_primary)?.name || primaryCompanyName} &bull; {employee.employment_type?.replace('_', ' ') || 'Full Time'}
                                    </p>
                                    <p style={{ fontSize: '12px', opacity: 0.8, fontWeight: 500, margin: 0 }}>
                                        <Clock size={12} style={{ display: 'inline', marginRight: '4px', verticalAlign: 'middle' }} />
                                        {employee.hire_date ? formatDate(employee.hire_date) : 'Jan 2024'} - Present
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Documents Card */}
                        {canViewDocuments && (
                        <div className="section-card">
                            <div className="section-header">
                                <h2 className="section-title"><FileText size={20} /> Documents &amp; Registry</h2>
                                {isAdmin && (
                                    <button 
                                        className="btn btn-outline btn-sm" 
                                        style={{ gap: '8px', cursor: 'pointer', padding: '6px 12px' }}
                                        onClick={() => setShowDocModal(true)}
                                    >
                                        <Plus size={16} /> Upload Document
                                    </button>
                                )}
                            </div>
                            {documents.length > 0 ? (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                    {documents.map((doc, idx) => (
                                        <div key={idx} style={{ 
                                            padding: '12px', 
                                            background: 'var(--color-ivory)', 
                                            borderRadius: '12px',
                                            border: '1px solid var(--color-border)',
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'center'
                                        }}>
                                            <div 
                                                style={{ display: 'flex', alignItems: 'center', gap: '12px', cursor: 'pointer', flex: 1 }}
                                                onClick={() => setPreviewDoc(doc)}
                                                onMouseOver={(e) => e.currentTarget.querySelector('.doc-name').style.color = '#3B82F6'}
                                                onMouseOut={(e) => e.currentTarget.querySelector('.doc-name').style.color = 'var(--color-charcoal)'}
                                            >
                                                <div style={{ padding: '8px', background: 'rgba(59, 130, 246, 0.1)', borderRadius: '8px', color: '#3B82F6' }}>
                                                    <FileText size={20} />
                                                </div>
                                                <div>
                                                    <div className="doc-name" style={{ fontWeight: 600, color: 'var(--color-charcoal)', fontSize: '13px', transition: 'color 0.2s' }}>{doc.document_name}</div>
                                                    <div style={{ fontSize: '11px', color: 'var(--color-text-muted)', textTransform: 'capitalize' }}>
                                                        {doc.document_type || 'Document'} &bull; {formatDate(doc.created_at_utc)}
                                                        {doc.expiry_date && (
                                                            <span style={{ marginLeft: '8px', color: new Date(doc.expiry_date) < new Date() ? 'var(--color-rose)' : 'var(--color-sage)' }}>
                                                                &bull; Expires: {formatDate(doc.expiry_date)}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                {isAdmin && (
                                                    <button 
                                                        onClick={() => {
                                                            setEditDocForm({
                                                                id: doc.id,
                                                                name: doc.document_name,
                                                                expiryDate: doc.expiry_date || ""
                                                            });
                                                            setShowEditDocModal(true);
                                                        }}
                                                        style={{ 
                                                            padding: "8px", 
                                                            borderRadius: "50%", 
                                                            color: "var(--color-primary)", 
                                                            background: "rgba(52, 152, 219, 0.08)",
                                                            transition: "all 0.2s",
                                                            display: "flex",
                                                            alignItems: "center",
                                                            justifyContent: "center"
                                                        }}
                                                        onMouseOver={(e) => e.currentTarget.style.background = "rgba(52, 152, 219, 0.15)"}
                                                        onMouseOut={(e) => e.currentTarget.style.background = "rgba(52, 152, 219, 0.08)"}
                                                        title="Edit"
                                                    >
                                                        <Pencil size={18} />
                                                    </button>
                                                )}
                                                <button 
                                                    onClick={() => { console.log('Previewing:', doc); setPreviewDoc(doc); }}
                                                    style={{ 
                                                        padding: '8px', 
                                                        borderRadius: '50%', 
                                                        color: '#3B82F6', 
                                                        background: 'rgba(59, 130, 246, 0.08)',
                                                        transition: 'all 0.2s',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center'
                                                    }}
                                                    onMouseOver={(e) => e.currentTarget.style.background = 'rgba(59, 130, 246, 0.15)'}
                                                    onMouseOut={(e) => e.currentTarget.style.background = 'rgba(59, 130, 246, 0.08)'}
                                                    title="Preview"
                                                >
                                                    <Eye size={18} />
                                                </button>
                                                <a 
                                                    href={getSecureMediaUrl(doc.file_path)} 
                                                    download 
                                                    style={{ 
                                                        padding: '8px', 
                                                        borderRadius: '50%', 
                                                        color: 'var(--color-success)', 
                                                        background: 'rgba(16, 185, 129, 0.08)',
                                                        transition: 'all 0.2s',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center'
                                                    }}
                                                    onMouseOver={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.15)'}
                                                    onMouseOut={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.08)'}
                                                    title="Download"
                                                >
                                                    <Download size={18} />
                                                </a>
                                                {isAdmin && (
                                                    <button 
                                                        onClick={() => handleDeleteDocument(doc.id)}
                                                        style={{ 
                                                            padding: '8px', 
                                                            borderRadius: '50%', 
                                                            color: 'var(--color-error)', 
                                                            background: 'rgba(239, 68, 68, 0.08)',
                                                            transition: 'all 0.2s',
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            justifyContent: 'center'
                                                        }}
                                                        onMouseOver={(e) => e.currentTarget.style.background = 'rgba(239, 68, 68, 0.15)'}
                                                        onMouseOut={(e) => e.currentTarget.style.background = 'rgba(239, 68, 68, 0.08)'}
                                                        title="Delete"
                                                    >
                                                        <Trash2 size={18} />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div style={{ textAlign: 'center', padding: '24px', background: 'var(--color-ivory)', borderRadius: '12px', border: '1px dashed var(--color-border)' }}>
                                    <p style={{ fontSize: '14px', color: 'var(--color-text-muted)', margin: 0 }}>No documents uploaded yet.</p>
                                </div>
                            )}
                        </div>
                        )}

                        {/* Remuneration / Payslips Card */}
                        {canViewPayrollInfo && (
                        <div className="section-card">
                            <div className="section-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <h2 className="section-title"><CreditCard size={20} /> Remuneration Profile</h2>
                                {isAdmin && (
                                <button className="btn-secondary" onClick={() => {
                                    const payrollCompanies = employee?.companies?.filter(c => Number(c.include_in_payroll) === 1) || [];
                                    if (payrollCompanies.length === 0) {
                                        alert('This employee is not included in the payroll for any associated office.');
                                        return;
                                    }
                                    const defaultCompanyId = payrollCompanies[0]?.id || '';
                                    const compMap = {};
                                    payrollComponents.filter(c => Number(c.company_id) === Number(defaultCompanyId) && c.type === 'EARNING').forEach(c => { compMap[c.id] = ''; });
                                    const defaultCurrency = payrollCompanies[0]?.currency_code || globalSettings.base_currency || 'UGX';
                                    setSalaryForm({ effective_date: '', currency_code: defaultCurrency, company_id: defaultCompanyId, components: compMap });
                                    setShowSalaryModal(true);
                                }}>
                                    <Plus size={16} /> Update Salary
                                </button>
                                )}
                            </div>
                            
                            {/* Dynamic Salary Structures */}
                            <div style={{ marginBottom: '24px' }}>
                                <h3 style={{ fontSize: '14px', fontWeight: 'bold', marginBottom: '12px' }}>Salary Configurations</h3>
                                {(() => {
                                    // Group employee salary data by effective_date and company_id
                                    const grouped = {};
                                    employeeSalaryData.forEach(item => {
                                        const key = `${item.effective_date}_${item.company_id}`;
                                        if (!grouped[key]) {
                                            grouped[key] = { effective_date: item.effective_date, company_id: item.company_id, company_name: item.company_name, items: [] };
                                        }
                                        grouped[key].items.push(item);
                                    });
                                    const dateGroups = Object.values(grouped).sort((a, b) => {
                                        const dateDiff = b.effective_date.localeCompare(a.effective_date);
                                        return dateDiff !== 0 ? dateDiff : (a.company_name || '').localeCompare(b.company_name || '');
                                    });

                                    if (dateGroups.length === 0) {
                                        return <div style={{ fontSize: '13px', color: '#64748b', fontStyle: 'italic' }}>No salary configurations added yet.</div>;
                                    }

                                    return (
                                        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                            {dateGroups.map((group) => {
                                                const currency = group.items[0]?.currency_code || 'UGX';
                                                const totalEarnings = group.items.filter(i => i.component_type === 'EARNING').reduce((sum, i) => sum + parseFloat(i.amount || 0), 0);
                                                return (
                                                    <div key={`${group.effective_date}_${group.company_id}`} style={{ 
                                                        padding: '12px', background: 'var(--color-ivory)', borderRadius: '8px', 
                                                        border: '1px solid var(--color-border)'
                                                    }}>
                                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                                                            <div>
                                                                <div style={{ fontSize: '12px', color: '#64748b', fontWeight: '500', marginBottom: '4px' }}>
                                                                    {group.company_name || 'Primary Company'}
                                                                </div>
                                                                <div style={{ fontWeight: 'bold', fontSize: '14px', marginBottom: '6px' }}>Gross: {currency} {totalEarnings.toLocaleString()}</div>
                                                                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                                                                    {group.items.map(item => (
                                                                        <span key={item.id} style={{ 
                                                                            fontSize: '12px', padding: '2px 8px', borderRadius: '4px',
                                                                            background: item.component_type === 'EARNING' ? '#ecfdf5' : '#fef2f2',
                                                                            color: item.component_type === 'EARNING' ? '#047857' : '#b91c1c'
                                                                        }}>
                                                                            {item.component_name}: {currency} {parseFloat(item.amount).toLocaleString()}
                                                                        </span>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                            <div style={{ textAlign: 'right', fontSize: '13px', display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '8px' }}>
                                                                <div style={{ fontWeight: '500', color: 'var(--color-primary)' }}>Effective: {formatDate(group.effective_date)}</div>
                                                                {isAdmin && (
                                                                <div style={{ display: 'flex', gap: '8px' }}>
                                                                    <button className="btn-icon" onClick={() => handleEditSalary(group)} title="Edit Configuration">
                                                                        <Edit size={14} />
                                                                    </button>
                                                                    <button className="btn-icon" style={{ color: 'var(--color-danger)' }} onClick={() => handleDeleteSalary(group.effective_date, group.company_id)} title="Delete Configuration">
                                                                        <Trash2 size={14} />
                                                                    </button>
                                                                </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    );
                                })()}
                            </div>

                            <h3 style={{ fontSize: '14px', fontWeight: 'bold', marginBottom: '12px' }}>Payslips</h3>
                            {payslips.length > 0 ? (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                                    {Object.entries(payslips.reduce((acc, ps) => {
                                        const company = ps.company_name || 'Main Company';
                                        if (!acc[company]) acc[company] = [];
                                        acc[company].push(ps);
                                        return acc;
                                    }, {})).map(([companyName, companyPayslips]) => (
                                        <div key={companyName}>
                                            <h4 style={{ fontSize: '13px', fontWeight: '600', color: 'var(--color-text-muted)', marginBottom: '8px', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                                                {companyName}
                                            </h4>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                                {companyPayslips.map((doc, idx) => (
                                                    <div key={idx} style={{ 
                                                        padding: '12px', 
                                                        background: 'var(--color-ivory)', 
                                                        borderRadius: '12px',
                                                        border: '1px solid var(--color-border)',
                                                        display: 'flex',
                                                        justifyContent: 'space-between',
                                                        alignItems: 'center'
                                                    }}>
                                                        <div 
                                                            style={{ display: 'flex', alignItems: 'center', gap: '12px', cursor: 'pointer', flex: 1 }}
                                                            onClick={() => setPreviewDoc(doc)}
                                                            onMouseOver={(e) => e.currentTarget.querySelector('.doc-name').style.color = '#10B981'}
                                                            onMouseOut={(e) => e.currentTarget.querySelector('.doc-name').style.color = 'var(--color-charcoal)'}
                                                        >
                                                            <div style={{ padding: '8px', background: 'rgba(16, 185, 129, 0.1)', borderRadius: '8px', color: '#10B981' }}>
                                                                <CreditCard size={20} />
                                                            </div>
                                                            <div>
                                                                <div className="doc-name" style={{ fontWeight: 600, color: 'var(--color-charcoal)', fontSize: '13px', transition: 'color 0.2s' }}>
                                                                    Payslip - {new Date(doc.year, doc.month - 1).toLocaleString('default', { month: 'long', year: 'numeric' })}
                                                                </div>
                                                                <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>
                                                                    Uploaded on {formatDate(doc.created_at)}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                            <button 
                                                                onClick={() => setPreviewDoc(doc)}
                                                                style={{ 
                                                                    padding: '8px', 
                                                                    borderRadius: '50%', 
                                                                    color: '#10B981', 
                                                                    background: 'rgba(16, 185, 129, 0.08)',
                                                                    transition: 'all 0.2s',
                                                                    display: 'flex',
                                                                    alignItems: 'center',
                                                                    justifyContent: 'center',
                                                                    border: 'none',
                                                                    cursor: 'pointer'
                                                                }}
                                                                onMouseOver={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.15)'}
                                                                onMouseOut={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.08)'}
                                                                title="View Payslip"
                                                            >
                                                                <Eye size={18} />
                                                            </button>
                                                            <a 
                                                                href={getSecureMediaUrl(doc.file_path)} 
                                                                download 
                                                                style={{ 
                                                                    padding: '8px', 
                                                                    borderRadius: '50%', 
                                                                    color: 'var(--color-success)', 
                                                                    background: 'rgba(16, 185, 129, 0.08)',
                                                                    transition: 'all 0.2s',
                                                                    display: 'flex',
                                                                    alignItems: 'center',
                                                                    justifyContent: 'center'
                                                                }}
                                                                onMouseOver={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.15)'}
                                                                onMouseOut={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.08)'}
                                                                title="Download"
                                                            >
                                                                <Download size={18} />
                                                            </a>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div style={{ textAlign: 'center', padding: '24px', background: 'var(--color-ivory)', borderRadius: '12px', border: '1px dashed var(--color-border)' }}>
                                    <p style={{ fontSize: '14px', color: 'var(--color-text-muted)', margin: 0 }}>No payslips available.</p>
                                </div>
                            )}
                        </div>
                        )}

                        {/* Salary Advances Card */}
                        {canViewPayrollInfo && (
                        <div className="section-card">
                            <div className="section-header">
                                <h2 className="section-title"><CreditCard size={20} /> Salary Advances</h2>
                            </div>
                            
                            {salaryAdvances.length > 0 ? (
                                <table className="doc-table" style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', fontSize: '13px' }}>
                                    <thead>
                                        <tr style={{ background: 'var(--color-ivory)', borderBottom: '1px solid var(--color-border)' }}>
                                            <th style={{ padding: '12px', fontWeight: '600', color: 'var(--color-text-muted)' }}>Date Requested</th>
                                            <th style={{ padding: '12px', fontWeight: '600', color: 'var(--color-text-muted)' }}>Amount</th>
                                            <th style={{ padding: '12px', fontWeight: '600', color: 'var(--color-text-muted)' }}>Reason</th>
                                            <th style={{ padding: '12px', fontWeight: '600', color: 'var(--color-text-muted)' }}>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {salaryAdvances.map((adv) => (
                                            <tr key={adv.id} style={{ borderBottom: '1px solid var(--color-border)' }}>
                                                <td style={{ padding: '12px', color: 'var(--color-charcoal)' }}>{formatDate(adv.date_requested)}</td>
                                                <td style={{ padding: '12px', fontWeight: '600' }}>{adv.currency_code} {parseFloat(adv.amount).toLocaleString()}</td>
                                                <td style={{ padding: '12px', color: 'var(--color-text-muted)' }}>{adv.reason || '-'}</td>
                                                <td style={{ padding: '12px' }}>
                                                    <span style={{
                                                        padding: '4px 10px',
                                                        borderRadius: '20px',
                                                        fontSize: '11px',
                                                        fontWeight: '600',
                                                        backgroundColor: adv.status.toLowerCase() === 'approved' ? 'rgba(16, 185, 129, 0.15)' : 
                                                                       adv.status.toLowerCase() === 'pending' ? 'rgba(245, 158, 11, 0.15)' : 
                                                                       adv.status.toLowerCase() === 'rejected' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(100, 116, 139, 0.15)',
                                                        color: adv.status.toLowerCase() === 'approved' ? '#047857' : 
                                                               adv.status.toLowerCase() === 'pending' ? '#b45309' : 
                                                               adv.status.toLowerCase() === 'rejected' ? '#b91c1c' : '#475569'
                                                    }}>
                                                        {adv.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <div style={{ textAlign: 'center', padding: '24px', background: 'var(--color-ivory)', borderRadius: '12px', border: '1px dashed var(--color-border)' }}>
                                    <p style={{ fontSize: '13px', color: 'var(--color-text-muted)', margin: 0 }}>No salary advances requested.</p>
                                </div>
                            )}
                        </div>
                        )}

                        {/* Allocated Assets Card */}
                        {canViewAssetsInfo && (
                        <div className="section-card">
                            <div className="section-header">
                                <h2 className="section-title"><Archive size={20} /> Allocated Assets</h2>
                            </div>
                            {assets.length > 0 ? (
                                <table className="doc-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
                                    <thead>
                                        <tr style={{ textAlign: 'left', borderBottom: '1px solid var(--color-border)' }}>
                                            <th style={{ padding: '12px', fontSize: '11px', textTransform: 'uppercase', color: 'var(--color-text-muted)' }}>Asset / Company</th>
                                            <th style={{ padding: '12px', fontSize: '11px', textTransform: 'uppercase', color: 'var(--color-text-muted)' }}>Details</th>
                                            <th style={{ padding: '12px', fontSize: '11px', textTransform: 'uppercase', color: 'var(--color-text-muted)' }}>Allocated On</th>
                                            <th style={{ padding: '12px', fontSize: '11px', textTransform: 'uppercase', color: 'var(--color-text-muted)' }}>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {assets.map((asset, idx) => (
                                            <tr key={idx} style={{ borderBottom: '1px solid rgba(0,0,0,0.05)' }}>
                                                <td style={{ padding: '12px' }}>
                                                    <div style={{ fontWeight: 600, fontSize: '13px' }}>{asset.asset_name}</div>
                                                    <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>{asset.company_name}</div>
                                                </td>
                                                <td style={{ padding: '12px', fontSize: '12px' }}>
                                                    <div>{asset.serial_number || 'N/A'}</div>
                                                    <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>{asset.model_number || 'Model -'}</div>
                                                </td>
                                                <td style={{ padding: '12px', fontSize: '12px' }}>{formatDate(asset.allocation_date)}</td>
                                                <td style={{ padding: '12px' }}>
                                                    <span style={{ 
                                                        fontSize: '10px', 
                                                        fontWeight: 700, 
                                                        textTransform: 'uppercase',
                                                        padding: '4px 8px',
                                                        borderRadius: '12px',
                                                        background: asset.status === 'active' ? '#ecfdf5' : '#f3f4f6',
                                                        color: asset.status === 'active' ? '#10b981' : '#6b7280'
                                                    }}>
                                                        {asset.status}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <p style={{ fontSize: '14px', color: 'var(--color-text-muted)', textAlign: 'center', padding: '20px' }}>
                                    No company assets currently allocated.
                                </p>
                            )}
                        </div>
                        )}
                    </div>

                    {/* ── Right Sidebar Column ── */}
                    <div>
                        {/* Attendance Calendar */}
                        {canViewAttendance && (
                        <div className="section-card">
                            <div className="section-header" style={{ marginBottom: '12px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <h2 className="section-title" style={{ fontSize: '16px', margin: 0 }}>Attendance</h2>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    <button 
                                        className="btn-icon" 
                                        onClick={() => {
                                            if (currentMonth === 1) {
                                                setCurrentMonth(12);
                                                setCurrentYear(prev => prev - 1);
                                            } else {
                                                setCurrentMonth(prev => prev - 1);
                                            }
                                        }}
                                        style={{ padding: '4px', background: 'transparent', border: 'none', cursor: 'pointer', color: 'var(--color-text-muted)' }}
                                    >
                                        <ArrowLeft size={14} />
                                    </button>
                                    <span style={{ fontSize: '12px', fontWeight: 600, minWidth: '80px', textAlign: 'center' }}>
                                        {new Date(currentYear, currentMonth - 1).toLocaleString('default', { month: 'long', year: 'numeric' })}
                                    </span>
                                    <button 
                                        className="btn-icon" 
                                        onClick={() => {
                                            if (currentMonth === 12) {
                                                setCurrentMonth(1);
                                                setCurrentYear(prev => prev + 1);
                                            } else {
                                                setCurrentMonth(prev => prev + 1);
                                            }
                                        }}
                                        style={{ padding: '4px', background: 'transparent', border: 'none', cursor: 'pointer', color: 'var(--color-text-muted)' }}
                                    >
                                        <ArrowLeft size={14} style={{ transform: 'rotate(180deg)' }} />
                                    </button>
                                </div>
                            </div>
                            <div className="stat-grid" style={{ 
                                marginBottom: '16px', 
                                display: 'grid', 
                                gridTemplateColumns: 'repeat(auto-fit, minmax(80px, 1fr))', 
                                gap: '8px' 
                            }}>
                                {/* All Statuses (Only show if count > 0) */}
                                {[...allStatuses.attendance, ...allStatuses.leave]
                                    .filter(s => (attendance[s.status_key || s.id] || 0) > 0)
                                    .map(s => {
                                        const key = s.status_key || s.id;
                                        const count = attendance[key] || 0;
                                        const styles = getStatusStyles(key);
                                        return (
                                            <div key={key} className="stat-box" style={{ 
                                                background: (styles.backgroundColor || '#f3f4f6') + '20', 
                                                borderColor: styles.borderColor || '#d1d5db',
                                                padding: '8px',
                                                textAlign: 'center'
                                            }}>
                                                <div className="stat-value" style={{ color: styles.borderColor || '#374151', fontSize: '18px', fontWeight: '800' }}>{count}</div>
                                                <div className="stat-label" style={{ color: styles.borderColor || '#6b7280', fontSize: '10px', textTransform: 'uppercase' }}>{s.status_label || s.name}</div>
                                            </div>
                                        );
                                    })}
                            </div>

                            <div className="calendar-grid">
                                {['M', 'T', 'W', 'T', 'F', 'S', 'S'].map((d, i) => (
                                    <div key={i} className="cal-day-header">{d}</div>
                                ))}
                                {renderCalendar()}
                            </div>

                            <div style={{ 
                                display: 'flex', 
                                gap: '12px', 
                                marginTop: '16px', 
                                fontSize: '10px', 
                                color: 'rgba(0,0,0,0.6)', 
                                justifyContent: 'center',
                                flexWrap: 'wrap'
                            }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                                    <span style={{ width: '8px', height: '8px', borderRadius: '50%', border: '1px dashed #6b7280' }} />
                                    <span>Upcoming Leave</span>
                                </div>
                                {[...allStatuses.attendance, ...allStatuses.leave]
                                    .filter(s => (attendance[s.status_key || s.id] || 0) > 0)
                                    .map(s => {
                                        const key = s.status_key || s.id;
                                        const styles = getStatusStyles(key);
                                        return (
                                            <div key={key} style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                                                <span style={{ width: '8px', height: '8px', borderRadius: '50%', background: styles.borderColor || '#6b7280' }} />
                                                <span>{s.status_label || s.name}</span>
                                            </div>
                                        );
                                    })}
                            </div>
                        </div>
                        )}

                        {/* Leave Balances Sidebar */}
                        {canViewLeave && (
                        <div className="section-card">
                            <div className="section-header" style={{ marginBottom: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                <h2 className="section-title" style={{ fontSize: '16px', margin: 0 }}>Leave Balances</h2>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                    <select 
                                        value={leaveYear} 
                                        onChange={(e) => setLeaveYear(Number(e.target.value))}
                                        style={{ padding: '4px 8px', fontSize: '12px', borderRadius: '4px', border: '1px solid var(--color-border)', outline: 'none', background: 'var(--color-white)', color: 'var(--color-charcoal)' }}
                                    >
                                        {[...Array(5)].map((_, i) => {
                                            const y = new Date().getFullYear() - 2 + i; // +/- 2 years from current
                                            return <option key={y} value={y}>{y}</option>;
                                        })}
                                    </select>
                                    {isAdmin && (
                                        <button 
                                            onClick={handleRecalculateBalances}
                                            style={{ 
                                                background: '#f8fafc', border: '1px solid #e2e8f0', cursor: 'pointer', color: 'var(--color-primary)',
                                                display: 'flex', alignItems: 'center', gap: '4px', fontSize: '11px', padding: '4px 8px', borderRadius: '6px',
                                                fontWeight: '600', transition: 'all 0.2s'
                                            }}
                                            onMouseEnter={e => e.currentTarget.style.background = '#f1f5f9'}
                                            onMouseLeave={e => e.currentTarget.style.background = '#f8fafc'}
                                            title="Recalculate based on Attendance"
                                        >
                                            <Activity size={12} /> Sync Balances
                                        </button>
                                    )}
                                </div>
                            </div>
                            {filteredBalances.length > 0 ? (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                                    {[...filteredBalances].sort((a, b) => (a.leave_type_name || '').localeCompare(b.leave_type_name || '')).map((b, i) => {
                                        const used = parseFloat(b.used_days || 0);
                                        const total = parseFloat(b.allocated_days || 0);
                                        const balance = total - used;
                                        const pct = total > 0 ? (balance / total) * 100 : 0;
                                        
                                        // Use database color or fallback to logic
                                        let color = b.color_code || '#10B981'; 
                                        if (!b.color_code) {
                                            if (b.leave_type_name?.toLowerCase().includes('sick')) color = '#F59E0B'; 
                                            if (b.leave_type_name?.toLowerCase().includes('casual')) color = '#3B82F6'; 
                                            if (b.leave_type_name?.toLowerCase().includes('unpaid')) color = '#EF4444'; 
                                            if (b.leave_type_name?.toLowerCase().includes('maternity')) color = '#EC4899'; 
                                            if (b.leave_type_name?.toLowerCase().includes('paternity')) color = '#8B5CF6'; 
                                        }

                                        return (
                                            <div key={i} onClick={() => handleLeaveBalanceClick(b)} style={{ 
                                                padding: '12px', 
                                                background: 'var(--color-ivory)', 
                                                borderRadius: '12px',
                                                border: '1px solid var(--color-border)',
                                                transition: 'transform 0.2s ease, box-shadow 0.2s ease',
                                                cursor: 'pointer'
                                            }}
                                            onMouseEnter={e => e.currentTarget.style.boxShadow = 'var(--shadow-md)'}
                                            onMouseLeave={e => e.currentTarget.style.boxShadow = 'none'}>
                                                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '13px', marginBottom: '4px' }}>
                                                    <span style={{ fontWeight: 700, color: 'var(--color-charcoal)' }}>{b.leave_type_name}</span>
                                                    <span style={{ fontWeight: 700, color: color }}>{balance} days left</span>
                                                </div>
                                                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '11px', color: 'var(--color-text-muted)', marginBottom: '8px' }}>
                                                    <span>Credited for {b.year || new Date().getFullYear()}: <b>{total}</b></span>
                                                    <span>Used: <b>{used}</b></span>
                                                </div>
                                                <div style={{ width: '100%', height: '8px', background: '#e2e8f0', borderRadius: '4px', overflow: 'hidden' }}>
                                                    <div style={{ 
                                                        width: `${Math.min(100, Math.max(0, pct))}%`, 
                                                        height: '100%', 
                                                        background: color, 
                                                        borderRadius: '4px',
                                                        boxShadow: `0 0 8px ${color}44`
                                                    }} />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <div style={{ textAlign: 'center', padding: '24px', background: 'var(--color-ivory)', borderRadius: '12px', border: '1px dashed var(--color-border)' }}>
                                    <p style={{ fontSize: '14px', color: 'var(--color-text-muted)', margin: 0 }}>No active leave policies assigned{employee?.gender ? ` for ${employee.gender} employees` : ''}.</p>
                                </div>
                            )}
                        </div>
                        )}
                        
                        {/* Compliance & Personal Details Card */}
                        {canViewConfidential && (
                        <div className="section-card">
                            <div className="section-header" style={{ marginBottom: '16px' }}>
                                <h2 className="section-title" style={{ fontSize: '16px' }}>Compliance & Personal Details</h2>
                            </div>

                            {editing ? (
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr', gap: '16px' }}>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                                        <div>
                                            <label style={{ fontSize: '12px', fontWeight: '500' }}>Bank Name</label>
                                            <input type="text" className="form-control" value={editForm.bank_name || ''}
                                                onChange={e => handleFieldChange('bank_name', e.target.value)} />
                                        </div>
                                        <div>
                                            <label style={{ fontSize: '12px', fontWeight: '500' }}>Bank Account No</label>
                                            <input type="text" className="form-control" value={editForm.bank_account_no || ''}
                                                onChange={e => handleFieldChange('bank_account_no', e.target.value)} />
                                        </div>
                                    </div>

                                    <div className="section-divider" style={{ margin: '8px 0' }}></div>
                                    
                                    {customFieldDefs.length > 0 ? (
                                        <>
                                            <h4 style={{ fontSize: '13px', fontWeight: '600', color: 'var(--color-text-muted)', marginBottom: '4px' }}>Custom Company Fields</h4>
                                            {customFieldDefs.map(field => (
                                                <div key={field.id || field.field_key}>
                                                    <label style={{ fontSize: '12px', fontWeight: '500' }}>
                                                        {field.field_name}
                                                        {field.is_required ? <span style={{ color: 'red', marginLeft: '4px' }}>*</span> : ''}
                                                    </label>
                                                    {field.field_type === 'dropdown' ? (
                                                        <select className="form-control"
                                                            value={editForm.custom_fields?.[field.field_key] || ''}
                                                            onChange={e => handleCustomFieldChange(field.field_key, e.target.value)}>
                                                            <option value="">Select option</option>
                                                            {(field.field_options || []).map(opt => <option key={opt} value={opt}>{opt}</option>)}
                                                        </select>
                                                    ) : (
                                                        <input type={field.field_type === 'number' ? 'number' : 'text'}
                                                            className="form-control"
                                                            value={editForm.custom_fields?.[field.field_key] || ''}
                                                            onChange={e => handleCustomFieldChange(field.field_key, e.target.value)} />
                                                    )}
                                                </div>
                                            ))}
                                        </>
                                    ) : <p style={{ fontSize: '12px', color: 'var(--color-text-muted)' }}>No additional custom fields defined.</p>}
                                </div>
                            ) : (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                                        {employee.personal_email && (
                                            <div className="detail-row vertical">
                                                <span className="detail-label">Personal Email</span>
                                                <span className="detail-value">{employee.personal_email}</span>
                                            </div>
                                        )}
                                        {employee.personal_phone && (
                                            <div className="detail-row vertical">
                                                <span className="detail-label">Personal Contact Number</span>
                                                <span className="detail-value">{employee.personal_phone}</span>
                                            </div>
                                        )}
                                        {employee.date_of_birth && (
                                            <div className="detail-row vertical">
                                                <span className="detail-label">Date of Birth</span>
                                                <span className="detail-value">{formatDate(employee.date_of_birth)}</span>
                                            </div>
                                        )}
                                        {employee.gender && (
                                            <div className="detail-row vertical">
                                                <span className="detail-label">Gender</span>
                                                <span className="detail-value" style={{ textTransform: 'capitalize' }}>{employee.gender}</span>
                                            </div>
                                        )}
                                        {employee.nationality && (
                                            <div className="detail-row vertical">
                                                <span className="detail-label">Nationality</span>
                                                <span className="detail-value">{employee.nationality}</span>
                                            </div>
                                        )}
                                        {employee.bank_name && employee.bank_name !== "0" && (
                                            <div className="detail-row vertical">
                                                <span className="detail-label">Bank Name</span>
                                                <span className="detail-value">{employee.bank_name}</span>
                                            </div>
                                        )}
                                        {employee.bank_account_no && employee.bank_account_no !== "0" && (
                                            <div className="detail-row vertical">
                                                <span className="detail-label">Bank Account</span>
                                                <span className="detail-value" style={{ fontFamily: 'monospace' }}>{employee.bank_account_no}</span>
                                            </div>
                                        )}
                                    </div>

                                    {customFieldDefs.length > 0 && (
                                        <>
                                            <div className="section-divider" style={{ margin: '8px 0' }}></div>
                                            <h4 style={{ fontSize: '13px', fontWeight: '600', color: 'var(--color-text-muted)', marginBottom: '8px' }}>Custom Company Fields</h4>
                                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                                                {customFieldDefs.map(field => {
                                                    const val = customData?.[field.field_key];
                                                    if (!val || val === "0") return null;
                                                    return (
                                                        <div className="detail-row vertical" key={field.id || field.field_key}>
                                                            <span className="detail-label">{field.field_name}</span>
                                                            <span className="detail-value">
                                                                {val}
                                                            </span>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </>
                                    )}

                                    {customData?.archives && Object.keys(customData.archives).length > 0 && (
                                        <>
                                            <div className="section-divider" style={{ margin: '8px 0', borderStyle: 'dashed' }}></div>
                                            <h4 style={{ fontSize: '13px', fontWeight: '600', color: 'var(--color-rose-gold)', marginBottom: '8px' }}>Historical Regional Data (Inactive)</h4>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                                {Object.entries(customData.archives).map(([country, data]) => (
                                                    <div key={country} style={{ padding: '10px', background: '#f9fafb', borderRadius: '6px', border: '1px solid #e5e7eb' }}>
                                                        <div style={{ fontSize: '11px', fontWeight: '700', color: '#6b7280', textTransform: 'uppercase', marginBottom: '6px', display: 'flex', justifyContent: 'space-between' }}>
                                                            <span>{country}</span>
                                                            <span style={{ fontSize: '9px', fontWeight: '400' }}>Archived on {data.archived_at ? formatDate(data.archived_at) : 'Unknown'}</span>
                                                        </div>
                                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                                            {Object.entries(data).filter(([k]) => k !== 'archived_at').map(([key, val]) => (
                                                                <div key={key} className="detail-row vertical" style={{ margin: 0 }}>
                                                                    <span className="detail-label" style={{ fontSize: '10px' }}>{key.replace(/_/g, ' ')}</span>
                                                                    <span className="detail-value" style={{ fontSize: '12px', color: '#4b5563' }}>{val || '—'}</span>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </>
                                    )}
                                </div>
                            )}
                        </div>
                        )}
                        
                        {/* Policies & Reference Manuals Card */}
                        <div className="section-card">
                            <div className="section-header" style={{ marginBottom: '16px' }}>
                                <h2 className="section-title" style={{ fontSize: '16px' }}><Shield size={20} /> Policies & Reference</h2>
                            </div>
                            {referenceDocs.length > 0 ? (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                    {referenceDocs.map((doc, idx) => (
                                        <div key={idx} style={{ 
                                            padding: '12px', 
                                            background: 'var(--color-ivory)', 
                                            borderRadius: '12px',
                                            border: '1px solid var(--color-border)',
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'center'
                                        }}>
                                            <div 
                                                style={{ display: 'flex', alignItems: 'center', gap: '12px', cursor: 'pointer', flex: 1 }}
                                                onClick={() => {
                                                    const fullPath = getSecureMediaUrl(doc.file_path);
                                                    setPreviewDoc({ 
                                                        ...doc, 
                                                        file_path: fullPath,
                                                        document_type: doc.category 
                                                    });
                                                }}
                                                onMouseOver={(e) => e.currentTarget.querySelector('.doc-name').style.color = 'var(--color-primary)'}
                                                onMouseOut={(e) => e.currentTarget.querySelector('.doc-name').style.color = 'var(--color-charcoal)'}
                                            >
                                                <div style={{ padding: '8px', background: 'rgba(212, 175, 55, 0.1)', borderRadius: '8px', color: 'var(--primary-brand)' }}>
                                                    <Shield size={20} />
                                                </div>
                                                <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                                    <span className="doc-name" style={{ fontSize: '13px', fontWeight: 700, color: 'var(--color-charcoal)', transition: 'color 0.2s' }}>{doc.document_name}</span>
                                                    <span style={{ fontSize: '10px', color: 'var(--color-text-muted)', textTransform: 'uppercase', fontWeight: 600 }}>{doc.category}</span>
                                                </div>
                                            </div>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                <button 
                                                    onClick={() => {
                                                        const fullPath = getSecureMediaUrl(doc.file_path);
                                                        setPreviewDoc({ 
                                                            ...doc, 
                                                            file_path: fullPath,
                                                            document_type: doc.category 
                                                        });
                                                    }}
                                                    style={{ 
                                                        padding: '8px', 
                                                        borderRadius: '50%', 
                                                        color: 'var(--primary-brand)', 
                                                        background: 'rgba(212, 175, 55, 0.08)',
                                                        transition: 'all 0.2s',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        border: 'none',
                                                        cursor: 'pointer'
                                                    }}
                                                    onMouseOver={(e) => e.currentTarget.style.background = 'rgba(212, 175, 55, 0.15)'}
                                                    onMouseOut={(e) => e.currentTarget.style.background = 'rgba(212, 175, 55, 0.08)'}
                                                    title="View Document"
                                                >
                                                    <Eye size={18} />
                                                </button>
                                                <a 
                                                    href={getSecureMediaUrl(doc.file_path)} 
                                                    download 
                                                    style={{ 
                                                        padding: '8px', 
                                                        borderRadius: '50%', 
                                                        color: 'var(--color-success)', 
                                                        background: 'rgba(16, 185, 129, 0.08)',
                                                        transition: 'all 0.2s',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center'
                                                    }}
                                                    onMouseOver={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.15)'}
                                                    onMouseOut={(e) => e.currentTarget.style.background = 'rgba(16, 185, 129, 0.08)'}
                                                    title="Download"
                                                >
                                                    <Download size={18} />
                                                </a>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p style={{ fontSize: '12px', color: 'var(--color-text-muted)', textAlign: 'center', padding: '10px' }}>
                                    No company policies available for your office.
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
            {/* Document Upload Modal */}
            {/* Document Upload Modal */}
            <Modal 
                isOpen={showDocModal} 
                onClose={() => setShowDocModal(false)}
                title="Upload Employee Document"
            >
                <form onSubmit={handleDocumentModalSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <div>
                        <label className="form-label">Document Type *</label>
                        <select 
                            className="form-input" 
                            required
                            value={docForm.type}
                            onChange={e => setDocForm({...docForm, type: e.target.value})}
                        >
                            {documentTypes.map(t => <option key={t} value={t}>{t}</option>)}
                        </select>
                    </div>

                    <div>
                        <label className="form-label">Document Name/Reference</label>
                        <input 
                            type="text" 
                            className="form-input" 
                            placeholder="e.g. Passport Number, Contract ID"
                            value={docForm.name}
                            onChange={e => setDocForm({...docForm, name: e.target.value})}
                        />
                    </div>

                    <div>
                        <label className="form-label">Expiry Date (Optional)</label>
                        <DateInput 
                            value={docForm.expiryDate}
                            onChange={val => setDocForm({...docForm, expiryDate: val})}
                        />
                        <p style={{ fontSize: '10px', color: 'var(--color-text-muted)', marginTop: '4px' }}>Leave blank if the document does not expire.</p>
                    </div>

                    <div style={{ 
                        padding: '16px', 
                        border: '1px dashed var(--color-border)', 
                        borderRadius: '12px',
                        background: 'var(--color-ivory)',
                        textAlign: 'center'
                    }}>
                        <input 
                            type="file" 
                            id="docFile"
                            hidden 
                            onChange={e => setDocForm({...docForm, file: e.target.files[0]})}
                        />
                        <label htmlFor="docFile" style={{ cursor: 'pointer', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px' }}>
                            <Upload size={24} style={{ color: 'var(--color-rose-gold)' }} />
                            <span style={{ fontSize: '13px', fontWeight: 500 }}>
                                {docForm.file ? docForm.file.name : "Click to select document file"}
                            </span>
                            <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>
                                Max size: 5MB &bull; Format: PDF, JPG, PNG, DOCX, XLSX
                            </div>
                        </label>
                    </div>

                    <div style={{ marginTop: '12px' }}>
                        <button type="submit" className="btn btn-primary" style={{ width: '100%' }}>
                            Confirm Upload
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Edit Document Modal */}
            <Modal 
                isOpen={showEditDocModal} 
                onClose={() => setShowEditDocModal(false)}
                title="Edit Document"
                maxWidth="450px"
            >
                <form onSubmit={handleEditDocumentSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <div>
                        <label className="form-label">Document Name</label>
                        <input 
                            type="text" className="form-input" 
                            value={editDocForm.name}
                            onChange={e => setEditDocForm({...editDocForm, name: e.target.value})}
                            required 
                        />
                    </div>
                    <div>
                        <label className="form-label">Expiry Date (Optional)</label>
                        <DateInput 
                            value={editDocForm.expiryDate} 
                            onChange={val => setEditDocForm({...editDocForm, expiryDate: val})} 
                        />
                    </div>

                    <div style={{ marginTop: '12px' }}>
                        <button type="submit" className="btn btn-primary" style={{ width: '100%' }}>
                            Update Document
                        </button>
                    </div>
                </form>
            </Modal>
            <Modal
                isOpen={showPhotoModal}
                onClose={() => setShowPhotoModal(false)}
                title="Manage Profile Photo"
                maxWidth="450px"
            >
                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px', alignItems: 'center', padding: '10px 0' }}>
                    <div style={{ 
                        width: '180px', height: '180px', 
                        borderRadius: '40px', border: '1px solid var(--color-border)',
                        overflow: 'hidden', background: 'var(--color-ivory)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        fontSize: '64px', fontWeight: 700, color: 'var(--color-rose-gold)',
                        boxShadow: 'var(--shadow-md)'
                    }}>
                        {employee.profile_image_path && !profileImageError ? (
                            <img src={getSecureMediaUrl(employee.profile_image_path)} alt="Preview" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                        ) : getInitials()}
                    </div>

                    <div className="optimal-format-card" style={{ width: '100%', marginBottom: 0 }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <Shield size={14} style={{ color: 'var(--color-rose-gold)' }} />
                            <span style={{ fontSize: '11px', fontWeight: 800, color: 'var(--color-rose-gold)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>Optimal Format</span>
                        </div>
                        <div style={{ fontSize: '14px', fontWeight: 700, color: 'var(--color-charcoal)', marginTop: '4px' }}>
                            Square 512x512px
                        </div>
                        <div style={{ fontSize: '12px', color: 'var(--color-text-muted)', fontWeight: 500 }}>
                            WebP / JPG • Max 2MB
                        </div>
                    </div>

                    <div style={{ display: 'flex', width: '100%', gap: '12px' }}>
                        <label className="btn btn-primary" style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', cursor: 'pointer' }}>
                            <Upload size={18} /> Upload New
                            <input type="file" hidden accept="image/*" onChange={(e) => { handlePhotoUpload(e); setShowPhotoModal(false); }} />
                        </label>
                        
                        {employee.profile_image_path && (
                            <button 
                                onClick={() => { handleDeletePhoto(); setShowPhotoModal(false); }}
                                className="btn btn-outline" 
                                style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px', color: 'var(--color-error)', borderColor: 'var(--color-error)' }}
                            >
                                <Trash2 size={18} /> Remove
                            </button>
                        )}
                    </div>

                    <button className="btn btn-outline" onClick={() => setShowPhotoModal(false)} style={{ width: '100%' }}>
                        Close
                    </button>
                </div>
            </Modal>
            <Modal
                isOpen={!!previewDoc}
                onClose={() => setPreviewDoc(null)}
                title={previewDoc?.document_name || 'Document Preview'}
                maxWidth="1200px"
            >
                <div style={{ height: '70vh', display: 'flex', flexDirection: 'column' }}>
                    <div style={{ 
                        padding: '12px 0', 
                        borderBottom: '1px solid var(--color-border)', 
                        display: 'flex', 
                        justifyContent: 'space-between', 
                        alignItems: 'center',
                        marginBottom: '16px'
                    }}>
                        <div style={{ fontSize: '13px', color: 'var(--color-text-muted)' }}>
                            {previewDoc?.document_type || 'Document'} • {previewDoc && formatDate(previewDoc.created_at || previewDoc.created_at_utc)}
                        </div>
                        <a href={getSecureMediaUrl(previewDoc?.file_path)} download className="btn btn-primary">
                            Download File
                        </a>
                    </div>
                    <div style={{ flex: 1, backgroundColor: '#f1f5f9', borderRadius: '12px', overflow: 'hidden' }}>
                        {previewDoc?.file_path?.toLowerCase().endsWith('.pdf') ? (
                            <iframe src={`${getSecureMediaUrl(previewDoc.file_path)}#toolbar=0&navpanes=0&scrollbar=0`} style={{ width: '100%', height: '100%', border: 'none' }} />
                        ) : (['jpg', 'jpeg', 'png', 'gif', 'webp'].some(ext => previewDoc?.file_path?.toLowerCase().endsWith(ext))) ? (
                            <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                                <img src={getSecureMediaUrl(previewDoc.file_path)} style={{ maxWidth: '100%', maxHeight: '100%', objectFit: 'contain' }} />
                            </div>
                        ) : (
                            <div style={{ width: '100%', height: '100%', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', background: '#fff' }}>
                                <Archive size={48} style={{ color: '#cbd5e1' }} />
                                <p>Please download to view.</p>
                            </div>
                        )}
                    </div>
                </div>
            </Modal>

            {/* Allocate Asset Modal */}
            <Modal isOpen={showAssetModal} onClose={() => setShowAssetModal(false)} title="Issue Asset">
                <form onSubmit={handleAllocateAssetSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Select Asset *</label>
                        <select 
                            className="form-control" 
                            required 
                            value={assetAllocationForm.asset_id} 
                            onChange={e => setAssetAllocationForm({...assetAllocationForm, asset_id: e.target.value})}
                        >
                            <option value="">Choose an available asset...</option>
                            {availableAssets.map(asset => (
                                <option key={asset.id} value={asset.id}>{asset.name} {asset.serial_number ? `(${asset.serial_number})` : ''}</option>
                            ))}
                        </select>
                    </div>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Allocation Date</label>
                        <DateInput 
                            value={assetAllocationForm.allocation_date} 
                            onChange={val => setAssetAllocationForm({...assetAllocationForm, allocation_date: val})} 
                        />
                    </div>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Expected Return Date (Optional)</label>
                        <DateInput 
                            value={assetAllocationForm.expected_return_date} 
                            onChange={val => setAssetAllocationForm({...assetAllocationForm, expected_return_date: val})} 
                        />
                    </div>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Remarks / Condition</label>
                        <textarea 
                            className="form-control" 
                            rows="3" 
                            value={assetAllocationForm.remarks}
                            onChange={e => setAssetAllocationForm({...assetAllocationForm, remarks: e.target.value})}
                            placeholder="Note any scratches or issues before issuing"
                        ></textarea>
                    </div>
                    <div className="form-group">
                        <label className="form-label" style={{ fontSize: '13px', fontWeight: '500', marginBottom: '8px', display: 'block' }}>Attachment (Provision/Document)</label>
                        <input type="file" className="form-control" onChange={e => setAssetAllocationForm({...assetAllocationForm, attachment: e.target.files[0]})} />
                    </div>
                    <div style={{ marginTop: '12px' }}>
                        <button type="submit" className="btn btn-primary" style={{ width: '100%' }}>
                            Issue Asset
                        </button>
                    </div>
                </form>
            </Modal>
            {/* Salary Configuration Modal - Dynamic */}
            {showSalaryModal && (
                <div style={{
                    position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
                    background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000
                }}>
                    <div style={{ background: '#fff', borderRadius: '12px', width: '100%', maxWidth: '500px', padding: '24px', maxHeight: '90vh', overflowY: 'auto' }}>
                        <h3 style={{ marginTop: 0, marginBottom: '20px', fontSize: '18px', fontWeight: 'bold' }}>Update Salary Configuration</h3>
                        
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                            <div>
                                <label style={{ display: 'block', marginBottom: '6px', fontSize: '13px', fontWeight: '500' }}>Company</label>
                                <select 
                                    className="form-input" 
                                    value={salaryForm.company_id}
                                    onChange={e => {
                                        const compMap = {};
                                        payrollComponents.filter(c => Number(c.company_id) === Number(e.target.value) && c.type === 'EARNING').forEach(c => { compMap[c.id] = ''; });
                                        const newCurrency = employee?.companies?.find(c => Number(c.id) === Number(e.target.value))?.currency_code || globalSettings.base_currency || 'UGX';
                                        setSalaryForm({...salaryForm, company_id: e.target.value, components: compMap, currency_code: newCurrency});
                                    }}
                                    style={{ width: '100%' }}
                                >
                                    {employee?.companies?.filter(c => Number(c.include_in_payroll) === 1).map(c => (
                                        <option key={c.id} value={c.id}>{c.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label style={{ display: 'block', marginBottom: '6px', fontSize: '13px', fontWeight: '500' }}>Effective Date</label>
                                <input 
                                    type="date" 
                                    className="form-input" 
                                    value={salaryForm.effective_date}
                                    onChange={e => setSalaryForm({...salaryForm, effective_date: e.target.value})}
                                    style={{ width: '100%' }}
                                />
                            </div>
                            {(() => {
                                const selectedCompany = employee?.companies?.find(c => Number(c.id) === Number(salaryForm.company_id));
                                const primaryCurrency = selectedCompany?.currency_code || globalSettings.base_currency || 'UGX';
                                const reportingCurrency = globalSettings.secondary_reporting_currency || 'USD';
                                
                                // Ensure the form's currency_code is valid
                                if (salaryForm.currency_code !== primaryCurrency && salaryForm.currency_code !== reportingCurrency && primaryCurrency) {
                                    // Normally we would update state here but during render it's bad practice,
                                    // Instead we just make sure the select dropdown behaves correctly.
                                }

                                return (
                                    <div>
                                        <label style={{ display: 'block', marginBottom: '6px', fontSize: '13px', fontWeight: '500' }}>Currency</label>
                                        <select 
                                            className="form-input" 
                                            value={salaryForm.currency_code}
                                            onChange={e => setSalaryForm({...salaryForm, currency_code: e.target.value})}
                                            style={{ width: '100%' }}
                                        >
                                            <option value={primaryCurrency}>{primaryCurrency} (Primary)</option>
                                            {primaryCurrency !== reportingCurrency && (
                                                <option value={reportingCurrency}>{reportingCurrency} (Reporting)</option>
                                            )}
                                        </select>
                                    </div>
                                );
                            })()}

                            {(() => {
                                const activeComponents = payrollComponents.filter(c => Number(c.company_id) === Number(salaryForm.company_id));
                                if (activeComponents.length === 0) {
                                    return (
                                        <div style={{ padding: '16px', background: '#fef3c7', borderRadius: '8px', fontSize: '13px', color: '#92400e' }}>
                                            No payroll components configured for this company. Please configure them in Payroll → Configuration first.
                                        </div>
                                    );
                                }
                                return (
                                    <>
                                        {activeComponents.filter(c => c.type === 'EARNING' && !['FORMULA', 'PERCENTAGE'].includes(c.computation_type)).length > 0 && (
                                            <div>
                                                <h4 style={{ fontSize: '13px', fontWeight: '600', color: '#047857', marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                    <span style={{ width: '8px', height: '8px', background: '#10b981', borderRadius: '50%', display: 'inline-block' }} />
                                                    Earnings
                                                </h4>
                                                <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                                    {activeComponents.filter(c => c.type === 'EARNING' && !['FORMULA', 'PERCENTAGE'].includes(c.computation_type)).map(comp => (
                                                        <div key={comp.id}>
                                                            <label style={{ display: 'block', marginBottom: '4px', fontSize: '12px', fontWeight: '500' }}>{comp.name}</label>
                                                            <input 
                                                                type="number" 
                                                                className="form-input" 
                                                                placeholder={`Amount (${comp.computation_type})`}
                                                                value={salaryForm.components[comp.id] || ''}
                                                                onChange={e => setSalaryForm({...salaryForm, components: {...salaryForm.components, [comp.id]: e.target.value}})}
                                                                style={{ width: '100%' }}
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                        {activeComponents.filter(c => c.type === 'DEDUCTION' && !['FORMULA', 'PERCENTAGE'].includes(c.computation_type)).length > 0 && (
                                            <div>
                                                <h4 style={{ fontSize: '13px', fontWeight: '600', color: '#b91c1c', marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                                    <span style={{ width: '8px', height: '8px', background: '#ef4444', borderRadius: '50%', display: 'inline-block' }} />
                                                    Deductions
                                                </h4>
                                                <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                                    {activeComponents.filter(c => c.type === 'DEDUCTION' && !['FORMULA', 'PERCENTAGE'].includes(c.computation_type)).map(comp => (
                                                        <div key={comp.id}>
                                                            <label style={{ display: 'block', marginBottom: '4px', fontSize: '12px', fontWeight: '500' }}>
                                                                {comp.name}
                                                                {comp.is_statutory == 1 && <span style={{ fontSize: '10px', background: '#fef3c7', color: '#d97706', padding: '1px 4px', borderRadius: '3px', marginLeft: '6px' }}>STATUTORY</span>}
                                                            </label>
                                                            <input 
                                                                type="number" 
                                                                className="form-input" 
                                                                placeholder={`Amount`}
                                                                value={salaryForm.components[comp.id] || ''}
                                                                onChange={e => setSalaryForm({...salaryForm, components: {...salaryForm.components, [comp.id]: e.target.value}})}
                                                                style={{ width: '100%' }}
                                                            />
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </>
                                );
                            })()}
                        </div>

                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px', marginTop: '24px' }}>
                            <button className="btn-secondary" onClick={() => setShowSalaryModal(false)}>Cancel</button>
                            <button className="btn-primary" onClick={handleSaveSalary} disabled={savingSalary}>
                                {savingSalary ? 'Saving...' : 'Save Configuration'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <Modal isOpen={showAdvanceModal} onClose={() => setShowAdvanceModal(false)} title="Request Salary Advance">
                <form onSubmit={handleRequestAdvance}>
                    <div style={{ marginBottom: '16px' }}>
                        <label style={{ display: 'block', fontSize: '13px', fontWeight: '500', marginBottom: '8px' }}>Amount ({employee?.currency_code || 'UGX'}) <span style={{color:'red'}}>*</span></label>
                        <input 
                            type="number" 
                            className="form-input" 
                            required 
                            value={advanceForm.amount} 
                            onChange={(e) => setAdvanceForm({...advanceForm, amount: e.target.value})} 
                            style={{ width: '100%' }}
                            min="1"
                        />
                    </div>
                    <div style={{ marginBottom: '24px' }}>
                        <label style={{ display: 'block', fontSize: '13px', fontWeight: '500', marginBottom: '8px' }}>Reason <span style={{color:'red'}}>*</span></label>
                        <textarea 
                            className="form-input" 
                            required 
                            value={advanceForm.reason} 
                            onChange={(e) => setAdvanceForm({...advanceForm, reason: e.target.value})} 
                            style={{ width: '100%', minHeight: '80px', resize: 'vertical' }}
                            placeholder="Why do you need this advance?"
                        />
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px' }}>
                        <button type="button" className="btn btn-secondary" onClick={() => setShowAdvanceModal(false)} disabled={submittingAdvance}>Cancel</button>
                        <button type="submit" className="btn btn-primary" disabled={submittingAdvance}>
                            {submittingAdvance ? 'Submitting...' : 'Submit Request'}
                        </button>
                    </div>
                </form>
            </Modal>
            <Modal isOpen={showLeaveHistoryModal} onClose={() => setShowLeaveHistoryModal(false)} title={`${selectedLeaveBalance?.leave_type_name || 'Leave'} History`}>
                <div style={{ minWidth: 'min(90vw, 400px)' }}>
                    {loadingLeaveHistory ? (
                        <div style={{ textAlign: 'center', padding: '20px', color: 'var(--color-text-muted)' }}>Loading history...</div>
                    ) : leaveHistory.length > 0 ? (
                        <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left' }}>
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--color-border)' }}>
                                    <th style={{ padding: '8px', fontSize: '13px', color: 'var(--color-text-muted)', fontWeight: '600' }}>Date</th>
                                    <th style={{ padding: '8px', fontSize: '13px', color: 'var(--color-text-muted)', fontWeight: '600' }}>Status</th>
                                    <th style={{ padding: '8px', fontSize: '13px', color: 'var(--color-text-muted)', fontWeight: '600', textAlign: 'right' }}>Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                {leaveHistory.map((req, idx) => (
                                    <tr key={idx} style={{ borderBottom: '1px solid var(--color-ivory)' }}>
                                        <td style={{ padding: '12px 8px', fontSize: '13px', fontWeight: '500', color: 'var(--color-charcoal)' }}>
                                            {formatDate(req.start_date)} {req.start_date !== req.end_date ? `to ${formatDate(req.end_date)}` : ''}
                                        </td>
                                        <td style={{ padding: '12px 8px', fontSize: '12px', fontWeight: '600', color: 'var(--color-text-muted)', textTransform: 'capitalize' }}>
                                            {req.status}
                                        </td>
                                        <td style={{ padding: '12px 8px', fontSize: '13px', fontWeight: '700', color: 'var(--color-charcoal)', textAlign: 'right' }}>
                                            {req.total_days}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div style={{ textAlign: 'center', padding: '30px 20px', color: 'var(--color-text-muted)', fontSize: '13px' }}>
                            <div style={{ marginBottom: '8px' }}>No leave history found for {selectedLeaveBalance?.leave_type_name}.</div>
                            <div style={{ fontSize: '11px', opacity: 0.7 }}>
                                Any upcoming or past requests for this leave type will appear here.<br/>
                                (Note: Imported balances or manual adjustments do not create history records)
                            </div>
                        </div>
                    )}
                </div>
            </Modal>
        </>
    );
};

export default EmployeeProfile;
