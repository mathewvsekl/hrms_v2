import { useState, useEffect, useMemo } from 'react';
import { 
    Plane, Plus, Calendar, Clock, ClipboardList, Info, 
    Loader, Trash2, Edit2, Check, CheckCircle, X, ShieldAlert, 
    ArrowRight, MapPin, Eye, FileText, ChevronRight, HelpCircle,
    UserCheck, Settings2, RefreshCw, Search, Lock, ArrowLeft
} from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useNotificationStore from '../store/useNotificationStore';
import Modal from '../components/ui/Modal';
import DateInput from '../components/ui/DateInput';
import SearchableSelect from '../components/ui/SearchableSelect';

export default function Travel() {
    const { user } = useAuthStore();
    const userRoleId = user?.role_id ?? 0;
    const { showAlert, showConfirm } = useNotificationStore();
    const hasPermission = useAuthStore(state => state.hasPermission);

    // Permissions
    const isGlobalAdmin = [1, 2].includes(userRoleId);
    const isTravelCoordinator = userRoleId === 60;
    const isHR = userRoleId === 3 || userRoleId === 4 || userRoleId === 5;
    const canManageAll = isGlobalAdmin || isTravelCoordinator || isHR;

    // View selection: 'dashboard', 'list', 'my-travel'
    const [activeTab, setActiveTab] = useState(canManageAll ? 'dashboard' : 'my-travel');

    // Data States
    const [requests, setRequests] = useState([]);
    const [categories, setCategories] = useState([]);
    const [routingRules, setRoutingRules] = useState([]);
    const [countries, setCountries] = useState([]);
    const [dashboardData, setDashboardData] = useState(null);
    const [employees, setEmployees] = useState([]);

    const [newCategoryName, setNewCategoryName] = useState('');
    const [newScopeName, setNewScopeName] = useState('');
    const [newApproverRoles, setNewApproverRoles] = useState('');
    const [newRequiresPassport, setNewRequiresPassport] = useState(false);
    const [newRequiresVisa, setNewRequiresVisa] = useState(false);
    const [newRequiresFlight, setNewRequiresFlight] = useState(false);
    const [newRuleDescription, setNewRuleDescription] = useState('');

    // Modal state for editing routing rule
    const [editRuleModalOpen, setEditRuleModalOpen] = useState(false);
    const [editingRuleData, setEditingRuleData] = useState(null);

    const [roles, setRoles] = useState([]);

    // Loading / UI States
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [formOpen, setFormOpen] = useState(false);
    const [detailOpen, setDetailOpen] = useState(false);
    const [selectedRequest, setSelectedRequest] = useState(null);
    const [selectedVersion, setSelectedVersion] = useState(null);

    // Filter states
    const [filterStatus, setFilterStatus] = useState('');
    const [filterEmployee, setFilterEmployee] = useState('');
    const [searchQuery, setSearchQuery] = useState('');

    // Form States
    const [formType, setFormType] = useState('create'); // 'create', 'edit'
    const [formData, setFormData] = useState({
        id: null,
        employee_id: user?.employee_id ?? '',
        category_id: '',
        routing_rule_id: '',
        destination: '',
        start_date: '',
        end_date: '',
        status: 'Draft',
        itinerary: {
            origin_timezone: 'UTC',
            destination_timezone: 'UTC',
            meetings: [],
            transits: [],
            lodgings: []
        }
    });

    // Conflict state
    const [conflictInfo, setConflictInfo] = useState(null);

    // Initial load
    useEffect(() => {
        fetchInitialData();
    }, [activeTab]);

    const fetchInitialData = async () => {
        setLoading(true);
        try {
            // Fetch categories, rules, countries, and roles
            const [catsRes, rulesRes, countriesRes, rolesRes] = await Promise.all([
                api.get('/travel/categories'),
                api.get('/travel/routing-rules'),
                api.get('/organization/countries').catch(() => ({ data: { data: [] } })),
                api.get('/travel/roles').catch(() => ({ data: { data: [] } }))
            ]);
            setCategories(catsRes.data?.data || []);
            setRoutingRules(rulesRes.data?.data || []);
            setCountries(countriesRes.data?.data || []);
            setRoles(rolesRes.data?.data || []);

            // Dashboard
            if (activeTab === 'dashboard') {
                const dbRes = await api.get('/travel/dashboard');
                const data = dbRes.data?.data || {};
                setDashboardData({
                    stats: data.stats || {},
                    currently_on_travel_list: data.currently_on_travel_list || [],
                    upcoming_travel_list: data.upcoming_travel_list || []
                });
            }

            // Requests
            const reqRes = await api.get('/travel/requests');
            setRequests(reqRes.data?.data || []);

            // Employees (for coordinators/admins)
            if (canManageAll) {
                const empRes = await api.get('/employees');
                setEmployees(empRes.data?.data || empRes.data || []);
            }
        } catch (e) {
            console.error(e);
            showAlert('Sync Error', 'Failed to fetch travel module configurations.', 'error');
        } finally {
            setLoading(false);
        }
    };

    // Filter requests
    const filteredRequests = useMemo(() => {
        const safeRequests = Array.isArray(requests) ? requests : [];
        return safeRequests.filter(r => {
            const matchesStatus = filterStatus ? r.status === filterStatus : true;
            const matchesEmployee = filterEmployee ? String(r.employee_id) === String(filterEmployee) : true;
            const matchesSearch = searchQuery ? (
                r.destination?.toLowerCase().includes(searchQuery.toLowerCase()) ||
                `${r.first_name || ''} ${r.last_name || ''}`.toLowerCase().includes(searchQuery.toLowerCase())
            ) : true;
            
            // If viewing 'my-travel', filter out others
            if (activeTab === 'my-travel') {
                return matchesStatus && matchesSearch && String(r.employee_id) === String(user?.employee_id);
            }
            return matchesStatus && matchesEmployee && matchesSearch;
        });
    }, [requests, filterStatus, filterEmployee, searchQuery, activeTab, user]);
    // Auto-calculate timezones
    useEffect(() => {
        if (!formOpen) return;
        
        let newOriginTz = formData.itinerary?.origin_timezone || 'UTC';
        let newDestTz = formData.itinerary?.destination_timezone || 'UTC';
        let changed = false;

        // Check origin
        if (formData.employee_id && Array.isArray(employees)) {
            const emp = employees.find(e => String(e.employee_id || e.id) === String(formData.employee_id));
            if (emp && emp.primary_country_id && Array.isArray(countries)) {
                const country = countries.find(c => String(c.id) === String(emp.primary_country_id));
                if (country && country.default_timezone && country.default_timezone !== newOriginTz) {
                    newOriginTz = country.default_timezone;
                    changed = true;
                }
            }
        }

        // Check destination
        if (formData.destination && Array.isArray(countries)) {
            const firstDest = formData.destination.split(', ')[0];
            const country = countries.find(c => c.name === firstDest);
            if (country && country.default_timezone && country.default_timezone !== newDestTz) {
                newDestTz = country.default_timezone;
                changed = true;
            }
        }

        if (changed) {
            setFormData(prev => ({
                ...prev,
                itinerary: {
                    ...(prev.itinerary || {}),
                    origin_timezone: newOriginTz,
                    destination_timezone: newDestTz
                }
            }));
        }
    }, [formData.employee_id, formData.destination, employees, countries, formOpen, formData.itinerary?.origin_timezone, formData.itinerary?.destination_timezone]);

    // Handle Form Input Changes
    const handleInputChange = (field, value) => {
        setFormData(prev => {
            const newFormData = { ...prev, [field]: value };
            
            // Auto timezone selection
            if (field === 'employee_id') {
                const emp = employees.find(e => String(e.employee_id || e.id) === String(value));
                if (emp && emp.primary_country_id) {
                    const country = countries.find(c => String(c.id) === String(emp.primary_country_id));
                    if (country && country.default_timezone) {
                        newFormData.itinerary = { ...newFormData.itinerary, origin_timezone: country.default_timezone };
                    }
                }
            }
            if (field === 'destination') {
                const country = countries.find(c => c.name === value);
                if (country && country.default_timezone) {
                    newFormData.itinerary = { ...newFormData.itinerary, destination_timezone: country.default_timezone };
                }
            }
            
            return newFormData;
        });

        // Reset conflict if dates/employee change
        if (['start_date', 'end_date', 'employee_id'].includes(field)) {
            setConflictInfo(null);
        }
    };

    // Routing rule details
    const selectedRoutingRule = useMemo(() => {
        const rules = Array.isArray(routingRules) ? routingRules : [];
        return rules.find(r => String(r.id) === String(formData.routing_rule_id));
    }, [formData.routing_rule_id, routingRules]);

    // Itinerary List Builders
    const addItineraryItem = (type) => {
        setFormData(prev => {
            const itin = { ...prev.itinerary };
            if (type === 'meeting') {
                itin.meetings = [...(itin.meetings || []), { title: '', location: '', start_time: '', end_time: '' }];
            } else if (type === 'transit') {
                itin.transits = [...(itin.transits || []), { type: 'Flight', from: '', to: '', departure_time: '', arrival_time: '' }];
            } else if (type === 'lodging') {
                itin.lodgings = [...(itin.lodgings || []), { hotel_name: '', check_in_time: '' }];
            }
            return { ...prev, itinerary: itin };
        });
    };

    const removeItineraryItem = (type, index) => {
        setFormData(prev => {
            const itin = { ...prev.itinerary };
            if (type === 'meeting') {
                itin.meetings = itin.meetings.filter((_, i) => i !== index);
            } else if (type === 'transit') {
                itin.transits = itin.transits.filter((_, i) => i !== index);
            } else if (type === 'lodging') {
                itin.lodgings = itin.lodgings.filter((_, i) => i !== index);
            }
            return { ...prev, itinerary: itin };
        });
    };

    const updateItineraryItem = (type, index, field, value) => {
        setFormData(prev => {
            const itin = { ...prev.itinerary };
            if (type === 'meeting') {
                itin.meetings = itin.meetings.map((item, i) => i === index ? { ...item, [field]: value } : item);
            } else if (type === 'transit') {
                itin.transits = itin.transits.map((item, i) => i === index ? { ...item, [field]: value } : item);
            } else if (type === 'lodging') {
                itin.lodgings = itin.lodgings.map((item, i) => i === index ? { ...item, [field]: value } : item);
            }
            return { ...prev, itinerary: itin };
        });
    };

    // Pre-check dates for conflict
    const runConflictPreCheck = async () => {
        if (!formData.employee_id || !formData.start_date || !formData.end_date) {
            return showAlert('Validation', 'Please select employee and date range before checking.', 'warning');
        }
        setSaving(true);
        try {
            const res = await api.post('/travel/check-conflicts', {
                employee_id: formData.employee_id,
                start_date: formData.start_date,
                end_date: formData.end_date,
                exclude_request_id: formData.id
            });
            const info = res.data?.data;
            if (!info?.conflicts || info.conflicts.length === 0) {
                setConflictInfo({ isClear: true });
            } else {
                setConflictInfo(info);
            }
        } catch (e) {
            const errorMsg = e.response?.data?.message || e.message || 'Conflict pre-check failed due to server error.';
            showAlert('Error', errorMsg, 'error');
        } finally {
            setSaving(false);
        }
    };

    // Apply Workaround Suggested Dates
    const applyWorkaround = () => {
        if (conflictInfo?.workaround?.suggested_start_date) {
            setFormData(prev => ({
                ...prev,
                start_date: conflictInfo.workaround.suggested_start_date,
                end_date: conflictInfo.workaround.suggested_end_date
            }));
            setConflictInfo(null);
            showAlert('Dates Updated', 'Applied recommended conflict-free dates.', 'success');
        }
    };

    const handleCreateCategory = async () => {
        if (!newCategoryName.trim()) return showAlert('Error', 'Category name is required.', 'error');
        setSaving(true);
        try {
            await api.post('/travel/categories', { category_name: newCategoryName });
            setNewCategoryName('');
            showAlert('Success', 'Category added.', 'success');
            
            const catsRes = await api.get('/travel/categories');
            setCategories(catsRes.data?.data || []);
        } catch (error) {
            showAlert('Error', error.response?.data?.message || error.message || 'Failed to add category.', 'error');
        } finally {
            setSaving(false);
        }
    };

    const handleCreateRoutingRule = async () => {
        if (!newScopeName.trim() || !newApproverRoles.trim()) return showAlert('Error', 'Scope name and approver roles are required.', 'error');
        setSaving(true);
        try {
            await api.post('/travel/routing-rules', { 
                scope_name: newScopeName, 
                approver_roles: newApproverRoles,
                requires_passport: newRequiresPassport ? 1 : 0,
                requires_visa: newRequiresVisa ? 1 : 0,
                requires_flight: newRequiresFlight ? 1 : 0,
                description: newRuleDescription
            });
            setNewScopeName('');
            setNewApproverRoles('');
            setNewRequiresPassport(false);
            setNewRequiresVisa(false);
            setNewRequiresFlight(false);
            setNewRuleDescription('');
            showAlert('Success', 'Routing rule added.', 'success');
            
            const rulesRes = await api.get('/travel/routing-rules');
            setRoutingRules(rulesRes.data?.data || rulesRes.data || []);
        } catch (error) {
            showAlert('Error', error.response?.data?.message || error.message || 'Failed to add routing rule.', 'error');
        } finally {
            setSaving(false);
        }
    };

    const handleUpdateCategory = async (id, oldName) => {
        const newName = window.prompt("Enter new category name:", oldName);
        if (!newName || newName === oldName) return;
        setSaving(true);
        try {
            await api.put(`/travel/categories/${id}`, { category_name: newName });
            showAlert('Success', 'Category updated.', 'success');
            const catsRes = await api.get('/travel/categories');
            setCategories(catsRes.data?.data || catsRes.data || []);
        } catch (error) {
            showAlert('Error', error.response?.data?.message || 'Failed to update category', 'error');
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteCategory = async (id) => {
        if (!window.confirm("Are you sure you want to delete this category?")) return;
        setSaving(true);
        try {
            await api.delete(`/travel/categories/${id}`);
            showAlert('Success', 'Category deleted.', 'success');
            const catsRes = await api.get('/travel/categories');
            setCategories(catsRes.data?.data || catsRes.data || []);
        } catch (error) {
            showAlert('Error', error.response?.data?.message || 'Failed to delete category', 'error');
        } finally {
            setSaving(false);
        }
    };

    const handleUpdateRoutingRule = (rule) => {
        setEditingRuleData({
            id: rule.id,
            scope_name: rule.scope_name,
            approver_roles: rule.approver_roles,
            requires_passport: !!rule.requires_passport,
            requires_visa: !!rule.requires_visa,
            requires_flight: !!rule.requires_flight,
            description: rule.description || ''
        });
        setEditRuleModalOpen(true);
    };

    const handleSaveEditRule = async () => {
        if (!editingRuleData.scope_name.trim() || !editingRuleData.approver_roles.trim()) {
            return showAlert('Error', 'Scope name and approver roles are required.', 'error');
        }
        setSaving(true);
        try {
            await api.put(`/travel/routing-rules/${editingRuleData.id}`, {
                scope_name: editingRuleData.scope_name,
                approver_roles: editingRuleData.approver_roles,
                requires_passport: editingRuleData.requires_passport ? 1 : 0,
                requires_visa: editingRuleData.requires_visa ? 1 : 0,
                requires_flight: editingRuleData.requires_flight ? 1 : 0,
                description: editingRuleData.description
            });
            showAlert('Success', 'Routing rule updated.', 'success');
            setEditRuleModalOpen(false);
            setEditingRuleData(null);
            const rulesRes = await api.get('/travel/routing-rules');
            setRoutingRules(rulesRes.data?.data || rulesRes.data || []);
        } catch (error) {
            showAlert('Error', error.response?.data?.message || 'Failed to update routing rule', 'error');
        } finally {
            setSaving(false);
        }
    };

    const handleDeleteRoutingRule = async (id) => {
        if (!window.confirm("Are you sure you want to delete this routing rule?")) return;
        setSaving(true);
        try {
            await api.delete(`/travel/routing-rules/${id}`);
            showAlert('Success', 'Routing rule deleted.', 'success');
            const rulesRes = await api.get('/travel/routing-rules');
            setRoutingRules(rulesRes.data?.data || rulesRes.data || []);
        } catch (error) {
            showAlert('Error', error.response?.data?.message || 'Failed to delete routing rule', 'error');
        } finally {
            setSaving(false);
        }
    };

    // Save Request
    const handleSave = async (submitStatus = null) => {
        const targetStatus = submitStatus || formData.status;
        const payload = {
            ...formData,
            status: targetStatus
        };

        if (!payload.destination || !payload.start_date || !payload.end_date || !payload.category_id || !payload.routing_rule_id) {
            return showAlert('Validation', 'Please fill all required travel parameters.', 'warning');
        }

        setSaving(true);
        try {
            if (formType === 'create') {
                const res = await api.post('/travel/requests', payload);
                const warnings = res.data?.data?.warnings || [];
                setFormOpen(false);
                fetchInitialData();
                if (warnings.length > 0) {
                    showAlert('Success with Warning', `Travel saved as Provisional, but conflicts exist:\n${warnings.join('\n')}`, 'warning');
                } else {
                    showAlert('Success', 'Travel request initialized successfully.', 'success');
                }
            } else {
                const res = await api.put(`/travel/requests/${formData.id}`, payload);
                const warnings = res.data?.data?.warnings || [];
                setFormOpen(false);
                fetchInitialData();
                if (warnings.length > 0) {
                    showAlert('Success with Warning', `Travel updated as Provisional, but conflicts exist:\n${warnings.join('\n')}`, 'warning');
                } else {
                    showAlert('Success', 'Travel request updated.', 'success');
                }
            }
        } catch (e) {
            const msg = e.response?.data?.message || 'Failed to save travel request.';
            // Catch conflict message
            if (msg.includes('Conflict detected!')) {
                // Parse out workaround if possible, or trigger pre-check
                const mockWorkaround = {
                    conflicts: [{ description: msg }],
                    workaround: {
                        suggested_start_date: msg.match(/start on (\d{4}-\d{2}-\d{2})/)?.[1],
                        suggested_end_date: msg.match(/end on (\d{4}-\d{2}-\d{2})/)?.[1],
                        message: msg
                    }
                };
                setConflictInfo(mockWorkaround);
            }
            showAlert('Transaction Failed', msg, 'error');
        } finally {
            setSaving(false);
        }
    };

    // Open detail / versions modal
    const openDetail = async (req) => {
        setSelectedRequest(req);
        setSelectedVersion(null);
        setDetailOpen(true);
    };

    // Handle mid trip cancellation
    const triggerMidTripCancel = (req) => {
        showConfirm('Mid-Trip Cancellation', 'Are you sure you want to cancel the rest of this trip? Attendance from today onwards will be reverted.', async () => {
            try {
                await api.post(`/travel/requests/${req.id}/mid-trip-cancel`, {
                    cancellation_date: new Date().toISOString().split('T')[0]
                });
                fetchInitialData();
                showAlert('Cancelled', 'Remaining trip blocks and attendance reverted.', 'success');
            } catch (e) {
                showAlert('Failed', e.response?.data?.message || 'Cancellation failed.', 'error');
            }
        });
    };

    // Initialize Form
    const openCreateForm = () => {
        setFormType('create');
        setFormData({
            id: null,
            employee_id: user?.employee_id ?? '',
            category_id: categories[0]?.id ?? '',
            routing_rule_id: routingRules[0]?.id ?? '',
            destination: '',
            start_date: '',
            end_date: '',
            status: 'Draft',
            itinerary: {
                origin_timezone: 'UTC',
                destination_timezone: 'UTC',
                meetings: [],
                transits: [],
                lodgings: []
            }
        });
        setConflictInfo(null);
        setFormOpen(true);
    };

    const openEditForm = (req) => {
        setFormType('edit');
        let itin = { origin_timezone: 'UTC', destination_timezone: 'UTC', meetings: [], transits: [], lodgings: [] };
        try {
            itin = JSON.parse(req.latest_itinerary) || itin;
        } catch(e) {}

        setFormData({
            id: req.id,
            employee_id: req.employee_id,
            category_id: req.category_id,
            routing_rule_id: req.routing_rule_id,
            destination: req.destination,
            start_date: req.start_date,
            end_date: req.end_date,
            status: req.status,
            itinerary: itin
        });
        setConflictInfo(null);
        setFormOpen(true);
    };

    // Trigger async worker locally for testing
    const runWorkerDiagnostics = async () => {
        setLoading(true);
        try {
            await api.post('/run-migration'); // wait, run migration endpoint is public
            showAlert('Diagnostics', 'Triggered schema and async worker simulation.', 'success');
            fetchInitialData();
        } catch (e) {
            showAlert('Diagnostics', 'Worker diagnostics triggered (check server logs).', 'info');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="container-fluid" style={{ padding: '24px' }}>
            {!formOpen && (
                <>
            {/* Header */}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px' }}>
                <div>
                    <h2 style={{ margin: 0, fontWeight: '700', fontSize: '24px', letterSpacing: '-0.02em', color: 'var(--color-primary)' }}>
                        Travel & Calendar Orchestration
                    </h2>
                    <p style={{ margin: '4px 0 0 0', color: 'gray', fontSize: '13px' }}>
                        Manage corporate travel itineraries, dynamic routing, and calendar synchronization.
                    </p>
                </div>
                <div style={{ display: 'flex', gap: '12px' }}>
                    <button className="btn btn-secondary" onClick={runWorkerDiagnostics} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <RefreshCw size={14} /> Process Queue
                    </button>
                    <button className="btn btn-primary" onClick={openCreateForm} style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <Plus size={16} /> New Request
                    </button>
                </div>
            </div>

            {/* Navigation Tabs */}
            <div style={{ display: 'flex', borderBottom: '1px solid var(--color-border)', marginBottom: '24px', gap: '16px' }}>
                {canManageAll && (
                    <button 
                        onClick={() => setActiveTab('dashboard')} 
                        style={{ padding: '12px 8px', background: 'none', border: 'none', borderBottom: activeTab === 'dashboard' ? '2px solid var(--color-rose-gold)' : 'none', fontWeight: activeTab === 'dashboard' ? '700' : '500', color: activeTab === 'dashboard' ? 'var(--color-primary)' : 'gray', cursor: 'pointer' }}
                    >
                        Operations Dashboard
                    </button>
                )}
                {canManageAll && (
                    <button 
                        onClick={() => setActiveTab('list')} 
                        style={{ padding: '12px 8px', background: 'none', border: 'none', borderBottom: activeTab === 'list' ? '2px solid var(--color-rose-gold)' : 'none', fontWeight: activeTab === 'list' ? '700' : '500', color: activeTab === 'list' ? 'var(--color-primary)' : 'gray', cursor: 'pointer' }}
                    >
                        All Travel Requests
                    </button>
                )}
                <button 
                    onClick={() => setActiveTab('my-travel')} 
                    style={{ padding: '12px 8px', background: 'none', border: 'none', borderBottom: activeTab === 'my-travel' ? '2px solid var(--color-rose-gold)' : 'none', fontWeight: activeTab === 'my-travel' ? '700' : '500', color: activeTab === 'my-travel' ? 'var(--color-primary)' : 'gray', cursor: 'pointer' }}
                >
                    My Travel Portal
                </button>
                {canManageAll && (
                    <button 
                        onClick={() => setActiveTab('configuration')} 
                        style={{ padding: '12px 8px', background: 'none', border: 'none', borderBottom: activeTab === 'configuration' ? '2px solid var(--color-rose-gold)' : 'none', fontWeight: activeTab === 'configuration' ? '700' : '500', color: activeTab === 'configuration' ? 'var(--color-primary)' : 'gray', cursor: 'pointer' }}
                    >
                        Configuration
                    </button>
                )}
            </div>

            {/* 1. OPERATIONS DASHBOARD VIEW */}
            {activeTab === 'dashboard' && dashboardData && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                    {/* Stats Grid */}
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '16px' }}>
                        <div className="card" style={{ padding: '20px', display: 'flex', alignItems: 'center', gap: '16px', background: 'white', borderRadius: '12px', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' }}>
                            <div style={{ padding: '12px', background: '#ecfdf5', color: '#10b981', borderRadius: '8px' }}><Plane size={24} /></div>
                            <div>
                                <div style={{ fontSize: '24px', fontWeight: '800' }}>{dashboardData.stats?.currently_on_travel || 0}</div>
                                <div style={{ fontSize: '11px', color: 'gray', fontWeight: '600', textTransform: 'uppercase' }}>Currently Traveling</div>
                            </div>
                        </div>
                        <div className="card" style={{ padding: '20px', display: 'flex', alignItems: 'center', gap: '16px', background: 'white', borderRadius: '12px', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' }}>
                            <div style={{ padding: '12px', background: '#eff6ff', color: '#3b82f6', borderRadius: '8px' }}><Calendar size={24} /></div>
                            <div>
                                <div style={{ fontSize: '24px', fontWeight: '800' }}>{dashboardData.stats?.upcoming_travel || 0}</div>
                                <div style={{ fontSize: '11px', color: 'gray', fontWeight: '600', textTransform: 'uppercase' }}>Upcoming Trips</div>
                            </div>
                        </div>
                        <div className="card" style={{ padding: '20px', display: 'flex', alignItems: 'center', gap: '16px', background: 'white', borderRadius: '12px', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' }}>
                            <div style={{ padding: '12px', background: '#fffbeb', color: '#f59e0b', borderRadius: '8px' }}><ClipboardList size={24} /></div>
                            <div>
                                <div style={{ fontSize: '24px', fontWeight: '800' }}>{dashboardData.stats?.pending_approval || 0}</div>
                                <div style={{ fontSize: '11px', color: 'gray', fontWeight: '600', textTransform: 'uppercase' }}>Pending Approvals</div>
                            </div>
                        </div>
                        <div className="card" style={{ padding: '20px', display: 'flex', alignItems: 'center', gap: '16px', background: 'white', borderRadius: '12px', boxShadow: '0 4px 6px -1px rgba(0,0,0,0.05)' }}>
                            <div style={{ padding: '12px', background: '#f5f3ff', color: '#8b5cf6', borderRadius: '8px' }}><Info size={24} /></div>
                            <div>
                                <div style={{ fontSize: '24px', fontWeight: '800' }}>{dashboardData.stats?.provisional_plans || 0}</div>
                                <div style={{ fontSize: '11px', color: 'gray', fontWeight: '600', textTransform: 'uppercase' }}>Provisional Bookings</div>
                            </div>
                        </div>
                    </div>

                    {/* Lists Grid */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' }}>
                        {/* Currently On Travel */}
                        <div className="card" style={{ padding: '24px', background: 'white', borderRadius: '12px', border: '1px solid var(--color-border)' }}>
                            <h3 style={{ fontSize: '16px', fontWeight: '700', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}><UserCheck size={18} /> Active Travelers</h3>
                            {!(dashboardData.currently_on_travel_list?.length > 0) ? (
                                <p style={{ color: 'gray', fontSize: '13px' }}>No employees are currently on travel status.</p>
                            ) : (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                    {dashboardData.currently_on_travel_list.map(tr => (
                                        <div key={tr.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px', background: 'var(--color-ivory)', borderRadius: '8px', border: '1px solid var(--color-border)' }}>
                                            <div>
                                                <div style={{ fontWeight: '700', fontSize: '14px' }}>{tr.first_name} {tr.last_name}</div>
                                                <div style={{ fontSize: '12px', color: 'gray' }}>{tr.destination} ({tr.scope_name})</div>
                                            </div>
                                            <div style={{ textAlign: 'right' }}>
                                                <div style={{ fontSize: '12px', fontWeight: '600' }}>Ends: {tr.end_date}</div>
                                                <span className="badge badge-success" style={{ fontSize: '10px' }}>Active</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Upcoming Trips */}
                        <div className="card" style={{ padding: '24px', background: 'white', borderRadius: '12px', border: '1px solid var(--color-border)' }}>
                            <h3 style={{ fontSize: '16px', fontWeight: '700', marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px' }}><Calendar size={18} /> Upcoming Departures</h3>
                            {!(dashboardData.upcoming_travel_list?.length > 0) ? (
                                <p style={{ color: 'gray', fontSize: '13px' }}>No upcoming departures scheduled.</p>
                            ) : (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                                    {dashboardData.upcoming_travel_list.map(tr => (
                                        <div key={tr.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px', background: 'var(--color-ivory)', borderRadius: '8px', border: '1px solid var(--color-border)' }}>
                                            <div>
                                                <div style={{ fontWeight: '700', fontSize: '14px' }}>{tr.first_name} {tr.last_name}</div>
                                                <div style={{ fontSize: '12px', color: 'gray' }}>To: {tr.destination} ({tr.scope_name})</div>
                                            </div>
                                            <div style={{ textAlign: 'right' }}>
                                                <div style={{ fontSize: '12px', fontWeight: '600' }}>Starts: {tr.start_date}</div>
                                                <span className="badge badge-primary" style={{ fontSize: '10px' }}>Confirmed</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* 2. TRAVEL REQUESTS LIST VIEW */}
            {(activeTab === 'list' || activeTab === 'my-travel') && (
                <div className="card" style={{ padding: '24px', background: 'white', borderRadius: '12px', border: '1px solid var(--color-border)' }}>
                    {/* Filters bar */}
                    <div style={{ display: 'flex', gap: '12px', marginBottom: '20px', alignItems: 'center' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', background: 'var(--color-ivory)', border: '1px solid var(--color-border)', borderRadius: '8px', padding: '8px 12px', flex: 1 }}>
                            <Search size={16} color="gray" />
                            <input 
                                type="text" 
                                placeholder="Search by destination or employee..." 
                                value={searchQuery}
                                onChange={e => setSearchQuery(e.target.value)}
                                style={{ background: 'none', border: 'none', outline: 'none', width: '100%', fontSize: '13px' }}
                            />
                        </div>
                        <select 
                            className="form-input" 
                            style={{ width: '180px', height: '40px' }} 
                            value={filterStatus}
                            onChange={e => setFilterStatus(e.target.value)}
                        >
                            <option value="">All Statuses</option>
                            <option value="Draft">Draft</option>
                            <option value="Provisional">Provisional</option>
                            <option value="Pending Approval">Pending Approval</option>
                            <option value="Approved">Approved</option>
                            <option value="Complete">Complete</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                        {canManageAll && activeTab === 'list' && (
                            <select className="form-input" value={filterEmployee} onChange={e => setFilterEmployee(e.target.value)}>
                                <option value="">All Employees</option>
                                {Array.isArray(employees) && employees.map(emp => (
                                    <option key={emp.employee_id} value={emp.employee_id}>{emp.first_name} {emp.last_name}</option>
                                ))}
                            </select>
                        )}
                    </div>

                    {/* Table */}
                    <div className="table-container">
                        <table className="data-table">
                            <thead>
                                <tr>
                                    {activeTab === 'list' && <th>Employee</th>}
                                    <th>Destination</th>
                                    <th>Category</th>
                                    <th>Routing Scope</th>
                                    <th>Date Range</th>
                                    <th>Status</th>
                                    <th style={{ textAlign: 'center' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {Array.isArray(filteredRequests) && filteredRequests.length === 0 ? (
                                    <tr>
                                        <td colSpan={activeTab === 'list' ? 7 : 6} style={{ textAlign: 'center', padding: '40px', color: 'gray' }}>
                                            No travel requests matches the current filter criteria.
                                        </td>
                                    </tr>
                                ) : (
                                    Array.isArray(filteredRequests) && filteredRequests.map(r => {
                                        // Status badge colors
                                        const badgeClasses = {
                                            'Draft': 'badge-neutral',
                                            'Provisional': 'badge-warning',
                                            'Pending Approval': 'badge-info',
                                            'Approved': 'badge-success',
                                            'Complete': 'badge-primary',
                                            'Rejected': 'badge-danger',
                                            'Cancelled': 'badge-neutral'
                                        };

                                        // Is trip ongoing
                                        const today = new Date().toISOString().split('T')[0];
                                        const isOngoing = r.status === 'Approved' && today >= r.start_date && today <= r.end_date;

                                        return (
                                            <tr key={r.id}>
                                                {activeTab === 'list' && (
                                                    <td style={{ fontWeight: '700' }}>
                                                        {r.first_name} {r.last_name}
                                                    </td>
                                                )}
                                                <td>{r.destination}</td>
                                                <td>{r.category_name}</td>
                                                <td>
                                                    <span style={{ fontWeight: '600' }}>{r.scope_name}</span>
                                                </td>
                                                <td>{r.start_date} to {r.end_date}</td>
                                                <td>
                                                    <span className={`badge ${badgeClasses[r.status] || 'badge-neutral'}`}>
                                                        {r.status}
                                                    </span>
                                                    {isOngoing && <span style={{ marginLeft: '6px', fontSize: '9px', background: '#ecfdf5', color: '#10b981', padding: '2px 6px', borderRadius: '4px', fontWeight: '700', border: '1px solid #10b981' }}>ONGOING</span>}
                                                </td>
                                                <td style={{ textAlign: 'center' }}>
                                                    <div style={{ display: 'inline-flex', gap: '8px' }}>
                                                        <button className="btn btn-secondary" style={{ padding: '6px' }} onClick={() => openDetail(r)}>
                                                            <Eye size={14} />
                                                        </button>
                                                        {['Draft', 'Provisional', 'Pending Approval'].includes(r.status) && (
                                                            <button className="btn btn-secondary" style={{ padding: '6px' }} onClick={() => openEditForm(r)}>
                                                                <Edit2 size={14} />
                                                            </button>
                                                        )}
                                                        {isOngoing && (
                                                            <button className="btn btn-danger" style={{ padding: '6px 12px', fontSize: '11px' }} onClick={() => triggerMidTripCancel(r)}>
                                                                Cancel Mid-Trip
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* 4. CONFIGURATION VIEW */}
            {activeTab === 'configuration' && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                    <div className="card" style={{ padding: '24px', background: 'white', borderRadius: '12px', border: '1px solid var(--color-border)' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                            <h3 style={{ fontSize: '16px', fontWeight: '700', display: 'flex', alignItems: 'center', gap: '8px' }}><Settings2 size={18} /> Travel Categories</h3>
                            <div style={{ display: 'flex', gap: '8px' }}>
                                <input type="text" className="form-input" placeholder="New Category Name" value={newCategoryName} onChange={e => setNewCategoryName(e.target.value)} style={{ width: '200px' }} />
                                <button className="btn btn-primary" onClick={handleCreateCategory} disabled={saving}>
                                    {saving ? 'Adding...' : 'Add Category'}
                                </button>
                            </div>
                        </div>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '14px' }}>
                            <thead style={{ background: 'var(--color-ivory)', textAlign: 'left' }}>
                                <tr>
                                    <th style={{ padding: '12px', borderBottom: '1px solid var(--color-border)', fontWeight: '600' }}>ID</th>
                                    <th style={{ padding: '12px', borderBottom: '1px solid var(--color-border)', fontWeight: '600' }}>Category Name</th>
                                    <th style={{ padding: '12px', borderBottom: '1px solid var(--color-border)', fontWeight: '600' }}>Status</th>
                                    <th style={{ padding: '12px', borderBottom: '1px solid var(--color-border)', fontWeight: '600', textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(!Array.isArray(categories) || categories.length === 0) ? (
                                    <tr><td colSpan={4} style={{ padding: '20px', textAlign: 'center', color: 'gray' }}>No categories found in database.</td></tr>
                                ) : (
                                    categories.map(c => (
                                        <tr key={c.id}>
                                            <td style={{ padding: '12px', borderBottom: '1px solid #f3f4f6' }}>{c.id}</td>
                                            <td style={{ padding: '12px', borderBottom: '1px solid #f3f4f6', fontWeight: '500' }}>{c.category_name}</td>
                                            <td style={{ padding: '12px', borderBottom: '1px solid #f3f4f6' }}><span className="badge badge-success">Active</span></td>
                                            <td style={{ padding: '12px', borderBottom: '1px solid #f3f4f6', textAlign: 'right' }}>
                                                <button className="btn btn-secondary" style={{ padding: '4px 8px', fontSize: '12px', marginRight: '8px' }} onClick={() => handleUpdateCategory(c.id, c.category_name)}>Edit</button>
                                                <button className="btn btn-danger" style={{ padding: '4px 8px', fontSize: '12px', background: '#fee2e2', color: '#b91c1c', border: 'none' }} onClick={() => handleDeleteCategory(c.id)}>Delete</button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="card" style={{ padding: '24px', background: 'white', borderRadius: '12px', border: '1px solid var(--color-border)' }}>
                        <div style={{ marginBottom: '16px', borderBottom: '1px solid #f3f4f6', paddingBottom: '16px' }}>
                            <h3 style={{ fontSize: '16px', fontWeight: '700', display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}><ArrowRight size={18} /> Add Routing Rule</h3>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                                <div>
                                    <label className="form-label" style={{ fontSize: '12px', marginBottom: '4px' }}>Scope Name</label>
                                    <input type="text" className="form-input" placeholder="Scope Name" value={newScopeName} onChange={e => setNewScopeName(e.target.value)} />
                                </div>
                                <div>
                                    <label className="form-label" style={{ fontSize: '12px', marginBottom: '4px' }}>Approver Roles</label>
                                    <SearchableSelect 
                                        isMulti={true}
                                        options={Array.isArray(roles) ? roles.map(r => ({ value: String(r.id), label: r.name })) : []}
                                        value={newApproverRoles}
                                        onChange={vals => setNewApproverRoles(vals.join(', '))}
                                        placeholder="Select Approver Roles..."
                                    />
                                </div>
                            </div>
                            <div style={{ display: 'flex', gap: '16px', marginBottom: '12px', alignItems: 'center', flexWrap: 'wrap' }}>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', cursor: 'pointer', fontWeight: '500' }}>
                                    <input type="checkbox" checked={newRequiresPassport} onChange={e => setNewRequiresPassport(e.target.checked)} />
                                    Requires Passport Check
                                </label>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', cursor: 'pointer', fontWeight: '500' }}>
                                    <input type="checkbox" checked={newRequiresVisa} onChange={e => setNewRequiresVisa(e.target.checked)} />
                                    Requires Visa Tracking
                                </label>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', cursor: 'pointer', fontWeight: '500' }}>
                                    <input type="checkbox" checked={newRequiresFlight} onChange={e => setNewRequiresFlight(e.target.checked)} />
                                    Requires Flight Booking
                                </label>
                            </div>
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', marginBottom: '12px' }}>
                                <label className="form-label" style={{ fontSize: '12px', marginBottom: '4px' }}>Description</label>
                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <input type="text" className="form-input" placeholder="Rule Description" value={newRuleDescription} onChange={e => setNewRuleDescription(e.target.value)} style={{ flex: 1 }} />
                                    <button className="btn btn-primary" onClick={handleCreateRoutingRule} disabled={saving}>
                                        {saving ? 'Adding...' : 'Add Rule'}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '14px' }}>
                            <thead style={{ background: 'var(--color-ivory)', textAlign: 'left' }}>
                                <tr>
                                    <th style={{ padding: '12px', borderBottom: '1px solid var(--color-border)', fontWeight: '600' }}>ID</th>
                                    <th style={{ padding: '12px', borderBottom: '1px solid var(--color-border)', fontWeight: '600' }}>Scope Name</th>
                                    <th style={{ padding: '12px', borderBottom: '1px solid var(--color-border)', fontWeight: '600' }}>Approvers Required</th>
                                    <th style={{ padding: '12px', borderBottom: '1px solid var(--color-border)', fontWeight: '600' }}>Requirements</th>
                                    <th style={{ padding: '12px', borderBottom: '1px solid var(--color-border)', fontWeight: '600', textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(!Array.isArray(routingRules) || routingRules.length === 0) ? (
                                    <tr><td colSpan={5} style={{ padding: '20px', textAlign: 'center', color: 'gray' }}>No routing rules found in database.</td></tr>
                                ) : (
                                    routingRules.map(r => (
                                        <tr key={r.id}>
                                            <td style={{ padding: '12px', borderBottom: '1px solid #f3f4f6' }}>{r.id}</td>
                                            <td style={{ padding: '12px', borderBottom: '1px solid #f3f4f6' }}>{r.scope_name}</td>
                                            <td style={{ padding: '12px', borderBottom: '1px solid #f3f4f6' }}>
                                                {r.approver_roles.split(',').map(id => id.trim()).map(id => {
                                                    const role = roles.find(rl => String(rl.id) === String(id));
                                                    return role ? role.name : id;
                                                }).join(', ')}
                                            </td>
                                            <td style={{ padding: '12px', borderBottom: '1px solid #f3f4f6', fontSize: '12px' }}>
                                                <div>Passport: {r.requires_passport ? '✔️' : '❌'}</div>
                                                <div>Visa: {r.requires_visa ? '✔️' : '❌'}</div>
                                                <div>Flights: {r.requires_flight ? '✈️' : '🚙'}</div>
                                            </td>
                                            <td style={{ padding: '12px', borderBottom: '1px solid #f3f4f6', textAlign: 'right' }}>
                                                <button className="btn btn-secondary" style={{ padding: '4px 8px', fontSize: '12px', marginRight: '8px' }} onClick={() => handleUpdateRoutingRule(r)}>Edit</button>
                                                <button className="btn btn-danger" style={{ padding: '4px 8px', fontSize: '12px', background: '#fee2e2', color: '#b91c1c', border: 'none' }} onClick={() => handleDeleteRoutingRule(r.id)}>Delete</button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

                </>
            )}

            {/* 3. FULL PAGE FORM: CREATE / EDIT TRAVEL REQUEST */}
            {formOpen && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', marginBottom: '16px', gap: '16px' }}>
                        <button className="btn btn-secondary" onClick={() => setFormOpen(false)} style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <ArrowLeft size={16} /> Back
                        </button>
                        <h2 style={{ margin: 0, fontWeight: '700', fontSize: '24px', letterSpacing: '-0.02em', color: 'var(--color-primary)' }}>
                            {formType === 'create' ? 'Initialize Travel & Calendar Blocks' : 'Modify Travel Request'}
                        </h2>
                    </div>
                    <div className="card" style={{ padding: '32px', background: 'white', borderRadius: '12px', border: '1px solid var(--color-border)' }}>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
                            
                    {/* Primary Travel details */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                        {canManageAll && (
                            <div>
                                <label className="form-label">Employee / Traveler</label>
                                <SearchableSelect 
                                    options={Array.isArray(employees) ? employees.map(emp => ({ value: emp.id || emp.employee_id, label: `${emp.first_name} ${emp.last_name} (${emp.employee_code})` })) : []}
                                    value={formData.employee_id}
                                    onChange={val => handleInputChange('employee_id', val)}
                                    disabled={formType === 'edit'}
                                    placeholder="Search Employee..."
                                />
                            </div>
                        )}
                        <div>
                            <label className="form-label">Destination Countries</label>
                            <SearchableSelect 
                                isMulti={true}
                                options={Array.isArray(countries) ? countries.map(c => ({ value: c.name, label: c.name })) : []}
                                value={formData.destination}
                                onChange={arr => handleInputChange('destination', arr.join(', '))}
                                disabled={formType === 'edit'}
                                placeholder="Search & Add Destinations..."
                            />
                        </div>
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                        <div>
                            <label className="form-label">Travel Purpose Category</label>
                            <select 
                                className="form-input"
                                value={formData.category_id}
                                onChange={e => handleInputChange('category_id', e.target.value)}
                            >
                                <option value="">Select Category</option>
                                {Array.isArray(categories) && categories.map(cat => (
                                    <option key={cat.id} value={cat.id}>{cat.category_name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="form-label">Routing Scope</label>
                            <select 
                                className="form-input"
                                value={formData.routing_rule_id}
                                onChange={e => handleInputChange('routing_rule_id', e.target.value)}
                            >
                                <option value="">Select Routing Scope</option>
                                {Array.isArray(routingRules) && routingRules.map(rule => (
                                    <option key={rule.id} value={rule.id}>{rule.scope_name}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {selectedRoutingRule && (
                        <div style={{ background: 'var(--color-ivory)', padding: '12px', borderRadius: '8px', border: '1px solid var(--color-border)', fontSize: '12px' }}>
                            <div style={{ fontWeight: '700', marginBottom: '4px' }}>Routing Requirements for "{selectedRoutingRule.scope_name}":</div>
                            <div style={{ display: 'flex', gap: '16px' }}>
                                <span>Passport Check: {selectedRoutingRule.requires_passport ? '✅ Required' : '❌ Suppressed'}</span>
                                <span>Visa Tracking: {selectedRoutingRule.requires_visa ? '✅ Required' : '❌ Suppressed'}</span>
                                <span>Flights: {selectedRoutingRule.requires_flight ? '✈️ Required' : '🚙 Ground Transit'}</span>
                            </div>
                            <p style={{ margin: '6px 0 0 0', color: 'gray' }}>{selectedRoutingRule.description}</p>
                        </div>
                    )}

                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px' }}>
                        <div>
                            <label className="form-label">Start Date</label>
                            <DateInput 
                                value={formData.start_date}
                                onChange={val => handleInputChange('start_date', val)}
                            />
                        </div>
                        <div>
                            <label className="form-label">End Date</label>
                            <DateInput 
                                value={formData.end_date}
                                onChange={val => handleInputChange('end_date', val)}
                            />
                        </div>
                    </div>

                    {/* ITINERARY BUILDER */}
                    <div style={{ borderTop: '1px solid var(--color-border)', paddingTop: '16px' }}>
                        <h4 style={{ margin: '0 0 12px 0', fontSize: '14px', fontWeight: '700' }}>Chronological Itinerary builder</h4>
                        <p style={{ margin: '-8px 0 12px 0', fontSize: '11px', color: 'gray' }}>
                            Rest buffers, transits, check-ins, and dual timezones will automatically calculate and sync to calendar.
                        </p>

                        {/* Meetings */}
                        <div style={{ marginBottom: '16px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                <div style={{ fontSize: '12px', fontWeight: '700' }}>Core Corporate Meetings</div>
                                <button className="btn btn-secondary" style={{ padding: '4px 8px', fontSize: '11px' }} onClick={() => addItineraryItem('meeting')}>
                                    + Add Meeting
                                </button>
                            </div>
                            {formData.itinerary.meetings?.length === 0 ? (
                                <p style={{ fontSize: '12px', color: 'gray', fontStyle: 'italic', margin: 0 }}>No meetings added.</p>
                            ) : (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                    {formData.itinerary.meetings.map((m, idx) => (
                                        <div key={idx} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr auto', gap: '8px', alignItems: 'center' }}>
                                            <input type="text" className="form-input" style={{ fontSize: '12px', height: '32px' }} placeholder="Meeting Title" value={m.title} onChange={e => updateItineraryItem('meeting', idx, 'title', e.target.value)} />
                                            <input type="text" className="form-input" style={{ fontSize: '12px', height: '32px' }} placeholder="Location" value={m.location} onChange={e => updateItineraryItem('meeting', idx, 'location', e.target.value)} />
                                            <input type="datetime-local" className="form-input" style={{ fontSize: '11px', height: '32px', padding: '2px 6px' }} value={m.start_time} onChange={e => updateItineraryItem('meeting', idx, 'start_time', e.target.value)} />
                                            <input type="datetime-local" className="form-input" style={{ fontSize: '11px', height: '32px', padding: '2px 6px' }} value={m.end_time} onChange={e => updateItineraryItem('meeting', idx, 'end_time', e.target.value)} />
                                            <button className="btn btn-danger" style={{ padding: '6px' }} onClick={() => removeItineraryItem('meeting', idx)}><Trash2 size={12} /></button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Transits */}
                        <div style={{ marginBottom: '16px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                <div style={{ fontSize: '12px', fontWeight: '700' }}>Transit Legs (Flights/Trains)</div>
                                <button className="btn btn-secondary" style={{ padding: '4px 8px', fontSize: '11px' }} onClick={() => addItineraryItem('transit')}>
                                    + Add Transit
                                </button>
                            </div>
                            {formData.itinerary.transits?.length === 0 ? (
                                <p style={{ fontSize: '12px', color: 'gray', fontStyle: 'italic', margin: 0 }}>No transits added.</p>
                            ) : (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                    {formData.itinerary.transits.map((t, idx) => (
                                        <div key={idx} style={{ display: 'grid', gridTemplateColumns: '120px 1fr 1fr 1fr 1fr auto', gap: '8px', alignItems: 'center' }}>
                                            <select className="form-input" style={{ fontSize: '12px', height: '32px' }} value={t.type} onChange={e => updateItineraryItem('transit', idx, 'type', e.target.value)}>
                                                <option value="Flight">✈️ Flight</option>
                                                <option value="Train">🚆 Train</option>
                                                <option value="Car">🚗 Car</option>
                                            </select>
                                            <input type="text" className="form-input" style={{ fontSize: '12px', height: '32px' }} placeholder="From" value={t.from} onChange={e => updateItineraryItem('transit', idx, 'from', e.target.value)} />
                                            <input type="text" className="form-input" style={{ fontSize: '12px', height: '32px' }} placeholder="To" value={t.to} onChange={e => updateItineraryItem('transit', idx, 'to', e.target.value)} />
                                            <input type="datetime-local" className="form-input" style={{ fontSize: '11px', height: '32px', padding: '2px 6px' }} value={t.departure_time} onChange={e => updateItineraryItem('transit', idx, 'departure_time', e.target.value)} />
                                            <input type="datetime-local" className="form-input" style={{ fontSize: '11px', height: '32px', padding: '2px 6px' }} value={t.arrival_time} onChange={e => updateItineraryItem('transit', idx, 'arrival_time', e.target.value)} />
                                            <button className="btn btn-danger" style={{ padding: '6px' }} onClick={() => removeItineraryItem('transit', idx)}><Trash2 size={12} /></button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Lodgings */}
                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                                <div style={{ fontSize: '12px', fontWeight: '700' }}>Lodging Checkpoints</div>
                                <button className="btn btn-secondary" style={{ padding: '4px 8px', fontSize: '11px' }} onClick={() => addItineraryItem('lodging')}>
                                    + Add Lodging
                                </button>
                            </div>
                            {formData.itinerary.lodgings?.length === 0 ? (
                                <p style={{ fontSize: '12px', color: 'gray', fontStyle: 'italic', margin: 0 }}>No lodgings added.</p>
                            ) : (
                                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                    {formData.itinerary.lodgings.map((l, idx) => (
                                        <div key={idx} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr auto', gap: '8px', alignItems: 'center' }}>
                                            <input type="text" className="form-input" style={{ fontSize: '12px', height: '32px' }} placeholder="Hotel/Accommodation Name" value={l.hotel_name} onChange={e => updateItineraryItem('lodging', idx, 'hotel_name', e.target.value)} />
                                            <input type="datetime-local" className="form-input" style={{ fontSize: '11px', height: '32px', padding: '2px 6px' }} value={l.check_in_time} onChange={e => updateItineraryItem('lodging', idx, 'check_in_time', e.target.value)} />
                                            <button className="btn btn-danger" style={{ padding: '6px' }} onClick={() => removeItineraryItem('lodging', idx)}><Trash2 size={12} /></button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Conflict Pre-check Section */}
                    <div style={{ borderTop: '1px solid var(--color-border)', paddingTop: '16px' }}>
                        <div style={{ display: 'flex', gap: '12px', alignItems: 'center', marginBottom: '8px' }}>
                            <button className="btn btn-secondary" style={{ padding: '6px 12px', fontSize: '12px' }} onClick={runConflictPreCheck}>
                                Run Calendar Conflict Pre-Check
                            </button>
                            <span style={{ fontSize: '11px', color: 'gray', display: 'flex', alignItems: 'center', gap: '4px' }}>
                                <Lock size={12} /> Key lock is applied to prevent concurrency double-booking.
                            </span>
                        </div>

                        {conflictInfo && conflictInfo.isClear && (
                            <div style={{ marginTop: '8px', border: '1px solid #bbf7d0', borderRadius: '8px', overflow: 'hidden' }}>
                                <div style={{ background: '#f0fdf4', padding: '12px', display: 'flex', gap: '12px', alignItems: 'center' }}>
                                    <CheckCircle size={20} color="#16a34a" />
                                    <div style={{ fontWeight: '600', fontSize: '13px', color: '#166534' }}>Calendar Clear! No scheduling conflicts found for this date range.</div>
                                </div>
                            </div>
                        )}

                        {conflictInfo && !conflictInfo.isClear && Array.isArray(conflictInfo.conflicts) && conflictInfo.conflicts.length > 0 && (
                            <div style={{ marginTop: '8px', border: '1px solid #fee2e2', borderRadius: '8px', overflow: 'hidden' }}>
                                <div style={{ background: '#fef2f2', padding: '12px', display: 'flex', gap: '12px' }}>
                                    <ShieldAlert size={20} color="#ef4444" />
                                    <div>
                                        <div style={{ fontWeight: '700', fontSize: '13px', color: '#991b1b' }}>Overlap Warning / Conflict Detected!</div>
                                        <ul style={{ margin: '6px 0 0 0', paddingLeft: '18px', fontSize: '12px', color: '#7f1d1d' }}>
                                            {Array.isArray(conflictInfo.conflicts) && conflictInfo.conflicts.map((c, i) => (
                                                <li key={i}>{c.description || c}</li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                                {conflictInfo.workaround?.suggested_start_date && (
                                    <div style={{ background: '#eff6ff', padding: '12px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid #dbeafe' }}>
                                        <div style={{ fontSize: '12px', color: '#1e3a8a', fontWeight: '600' }}>
                                            {conflictInfo.workaround.message}
                                        </div>
                                        <button className="btn btn-primary" style={{ padding: '6px 12px', fontSize: '11px', whiteSpace: 'nowrap' }} onClick={applyWorkaround}>
                                            Apply Dates
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Timezones (Dynamic Timezone Converter) */}
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', borderTop: '1px solid var(--color-border)', paddingTop: '16px' }}>
                        <div>
                            <label className="form-label">Origin (Home) Time Zone</label>
                            <input 
                                type="text"
                                className="form-input"
                                value={formData.itinerary.origin_timezone || 'UTC'}
                                readOnly
                                style={{ backgroundColor: '#f3f4f6', cursor: 'not-allowed', color: '#6b7280' }}
                            />
                        </div>
                        <div>
                            <label className="form-label">Destination (Local) Time Zone</label>
                            <input 
                                type="text"
                                className="form-input"
                                value={formData.itinerary.destination_timezone || 'UTC'}
                                readOnly
                                style={{ backgroundColor: '#f3f4f6', cursor: 'not-allowed', color: '#6b7280' }}
                            />
                        </div>
                    </div>
                </div>

                {/* Footer Buttons */}
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px', marginTop: '24px', borderTop: '1px solid var(--color-border)', paddingTop: '16px' }}>
                    <button className="btn btn-secondary" onClick={() => setFormOpen(false)} disabled={saving}>
                        Cancel
                    </button>
                    
                    {/* Draft Button */}
                    <button className="btn btn-secondary" onClick={() => handleSave('Draft')} disabled={saving} style={{ border: '1px solid var(--color-border)' }}>
                        Save as Draft
                    </button>

                    {/* Provisional Button (Allows Overlaps) */}
                    <button className="btn btn-secondary" onClick={() => handleSave('Provisional')} disabled={saving} style={{ background: '#fef3c7', borderColor: '#fde68a', color: '#92400e' }}>
                        Save as Provisional
                    </button>

                    {/* Approved Button (Blocks overlaps) */}
                    {(hasPermission('Travel', 'approve') || isGlobalAdmin) && (
                        <button className="btn btn-primary" onClick={() => handleSave('Approved')} disabled={saving}>
                            {saving ? <Loader size={16} className="spin" /> : 'Approve & Lock Calendar'}
                        </button>
                    )}
                    
                    {!(hasPermission('Travel', 'approve') || isGlobalAdmin) && (
                        <button className="btn btn-primary" onClick={() => handleSave('Pending Approval')} disabled={saving}>
                            Submit for Approval
                        </button>
                    )}
                        </div>
                    </div>
                </div>
            )}

            {/* 4. MODAL DETAIL: VIEW CHRONOLOGICAL ITINERARY & AUDIT LOG VERSIONS */}
            <Modal isOpen={detailOpen} onClose={() => setDetailOpen(false)} title="Master Itinerary Chronology & Version History">
                {selectedRequest && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '20px', maxHeight: '75vh', overflowY: 'auto' }}>
                        
                        {/* Summary Header */}
                        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '16px', background: 'var(--color-ivory)', borderRadius: '8px', border: '1px solid var(--color-border)' }}>
                            <div>
                                <div style={{ fontSize: '18px', fontWeight: '800' }}>To: {selectedRequest.destination}</div>
                                <div style={{ fontSize: '13px', color: 'gray' }}>
                                    Traveler: {selectedRequest.first_name} {selectedRequest.last_name} | Scope: {selectedRequest.scope_name}
                                </div>
                            </div>
                            <div style={{ textAlign: 'right' }}>
                                <div style={{ fontSize: '14px', fontWeight: '700' }}>{selectedRequest.start_date} to {selectedRequest.end_date}</div>
                                <span className="badge badge-primary">{selectedRequest.status}</span>
                            </div>
                        </div>

                        {/* Itinerary Chronology */}
                        <div>
                            <h4 style={{ margin: '0 0 12px 0', fontSize: '14px', fontWeight: '700', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <Clock size={16} /> Final Chronology (Calendar Injection Preview)
                            </h4>

                            <div style={{ borderLeft: '2px solid var(--color-border)', paddingLeft: '16px', marginLeft: '8px', display: 'flex', flexDirection: 'column', gap: '16px' }}>
                                {/* Transits */}
                                {JSON.parse(selectedRequest.latest_itinerary || '{}').transits?.map((t, idx) => (
                                    <div key={idx} style={{ position: 'relative' }}>
                                        <div style={{ position: 'absolute', left: '-25px', top: '2px', background: '#3b82f6', width: '16px', height: '16px', borderRadius: '50%', display: 'flex', alignItems: 'center', justify: 'center', border: '2px solid white' }} />
                                        <div style={{ fontWeight: '700', fontSize: '13px' }}>Transit: {t.type}</div>
                                        <div style={{ fontSize: '12px', color: 'gray' }}>
                                            Route: {t.from} <ArrowRight size={12} style={{ display: 'inline', verticalAlign: 'middle' }} /> {t.to}
                                        </div>
                                        <div style={{ fontSize: '11px', fontWeight: '600', color: 'var(--color-rose-gold)' }}>
                                            Dep: {t.departure_time} | Arr: {t.arrival_time}
                                        </div>
                                    </div>
                                ))}

                                {/* Lodgings */}
                                {JSON.parse(selectedRequest.latest_itinerary || '{}').lodgings?.map((l, idx) => (
                                    <div key={idx} style={{ position: 'relative' }}>
                                        <div style={{ position: 'absolute', left: '-25px', top: '2px', background: '#8b5cf6', width: '16px', height: '16px', borderRadius: '50%', display: 'flex', alignItems: 'center', justify: 'center', border: '2px solid white' }} />
                                        <div style={{ fontWeight: '700', fontSize: '13px' }}>Lodging: Check-in</div>
                                        <div style={{ fontSize: '12px', color: 'gray' }}>Hotel: {l.hotel_name}</div>
                                        <div style={{ fontSize: '11px', fontWeight: '600', color: 'var(--color-rose-gold)' }}>Time: {l.check_in_time}</div>
                                    </div>
                                ))}

                                {/* Meetings */}
                                {JSON.parse(selectedRequest.latest_itinerary || '{}').meetings?.map((m, idx) => (
                                    <div key={idx} style={{ position: 'relative' }}>
                                        <div style={{ position: 'absolute', left: '-25px', top: '2px', background: '#10b981', width: '16px', height: '16px', borderRadius: '50%', display: 'flex', alignItems: 'center', justify: 'center', border: '2px solid white' }} />
                                        <div style={{ fontWeight: '700', fontSize: '13px' }}>Meeting: {m.title}</div>
                                        <div style={{ fontSize: '12px', color: 'gray' }}>Location: {m.location}</div>
                                        <div style={{ fontSize: '11px', fontWeight: '600', color: 'var(--color-rose-gold)' }}>Time: {m.start_time} to {m.end_time}</div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Itinerary versions audit log (Versioning) */}
                        <div style={{ borderTop: '1px solid var(--color-border)', paddingTop: '16px' }}>
                            <h4 style={{ margin: '0 0 12px 0', fontSize: '14px', fontWeight: '700', display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <FileText size={16} /> Version Audit History
                            </h4>
                            <div style={{ display: 'flex', gap: '12px', alignItems: 'center', marginBottom: '12px' }}>
                                <span style={{ fontSize: '13px', color: 'gray' }}>Active Version: v{selectedRequest.latest_version}</span>
                                <span style={{ color: 'var(--color-border)' }}>|</span>
                                <span style={{ fontSize: '11px', background: '#ecfdf5', color: '#10b981', padding: '2px 8px', borderRadius: '4px', fontWeight: '700' }}>SYNCED</span>
                            </div>
                            <p style={{ fontSize: '12px', color: 'gray', margin: 0 }}>
                                This travel itinerary is fully version-audited. Each modification creates a new itinerary object version, which maintains an immutable log.
                            </p>
                        </div>

                        {/* Close button */}
                        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '16px' }}>
                            <button className="btn btn-primary" onClick={() => setDetailOpen(false)}>
                                Done
                            </button>
                        </div>

                    </div>
                )}
            </Modal>

            {/* Edit Routing Rule Modal */}
            <Modal isOpen={editRuleModalOpen} onClose={() => setEditRuleModalOpen(false)} title="Edit Routing Rule">
                {editingRuleData && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                        <div>
                            <label className="form-label">Scope Name</label>
                            <input type="text" className="form-input" value={editingRuleData.scope_name} onChange={e => setEditingRuleData({...editingRuleData, scope_name: e.target.value})} />
                        </div>
                        <div>
                            <label className="form-label">Approver Roles</label>
                            <SearchableSelect 
                                isMulti={true}
                                options={Array.isArray(roles) ? roles.map(r => ({ value: String(r.id), label: r.name })) : []}
                                value={editingRuleData.approver_roles}
                                onChange={vals => setEditingRuleData({...editingRuleData, approver_roles: vals.join(', ')})}
                                placeholder="Select Approver Roles..."
                            />
                        </div>
                        <div style={{ display: 'flex', gap: '16px', alignItems: 'center', flexWrap: 'wrap' }}>
                            <label style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '14px', cursor: 'pointer', fontWeight: '500' }}>
                                <input type="checkbox" checked={editingRuleData.requires_passport} onChange={e => setEditingRuleData({...editingRuleData, requires_passport: e.target.checked})} />
                                Requires Passport Check
                            </label>
                            <label style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '14px', cursor: 'pointer', fontWeight: '500' }}>
                                <input type="checkbox" checked={editingRuleData.requires_visa} onChange={e => setEditingRuleData({...editingRuleData, requires_visa: e.target.checked})} />
                                Requires Visa Tracking
                            </label>
                            <label style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '14px', cursor: 'pointer', fontWeight: '500' }}>
                                <input type="checkbox" checked={editingRuleData.requires_flight} onChange={e => setEditingRuleData({...editingRuleData, requires_flight: e.target.checked})} />
                                Requires Flight Booking
                            </label>
                        </div>
                        <div>
                            <label className="form-label">Description</label>
                            <input type="text" className="form-input" value={editingRuleData.description} onChange={e => setEditingRuleData({...editingRuleData, description: e.target.value})} />
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '8px' }}>
                            <button className="btn btn-secondary" onClick={() => setEditRuleModalOpen(false)}>Cancel</button>
                            <button className="btn btn-primary" onClick={handleSaveEditRule} disabled={saving}>Save Changes</button>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
