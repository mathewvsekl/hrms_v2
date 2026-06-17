import { useState, useEffect } from 'react';
import { Search, Plus, Filter, Package, User, CheckCircle, AlertCircle, XCircle, Building, Globe } from 'lucide-react';
import api from '../services/api';
import useAuthStore from '../store/useAuthStore';
import useLayoutStore from '../store/useLayoutStore';
import DateInput from '../components/ui/DateInput';

const renderFlag = (country) => {
    if (!country) return <span>🌐</span>;
    const name = country.name?.toLowerCase() || '';
    const iso = country.iso_code?.toUpperCase() || '';

    // Mapping 3-letter ISO or Name to 2-letter for FlagCDN
    let code = '';
    if (iso === 'ARE' || name.includes('emirates')) code = 'ae';
    else if (iso === 'IND' || name.includes('india')) code = 'in';
    else if (iso === 'UGA' || name.includes('uganda')) code = 'ug';
    else if (iso === 'KEN' || name.includes('kenya')) code = 'ke';
    else if (iso === 'TZA' || name.includes('tanzania')) code = 'tz';
    else if (iso === 'GBR' || name.includes('united kingdom')) code = 'gb';
    else if (iso === 'USA' || name.includes('united states')) code = 'us';
    else if (iso === 'BGD' || name.includes('bangladesh')) code = 'bd';
    else if (iso === 'PAK' || name.includes('pakistan')) code = 'pk';
    else if (iso === 'PHL' || name.includes('philippines')) code = 'ph';
    else if (iso.length === 2) code = iso.toLowerCase();
    else if (iso.length === 3) code = iso.toLowerCase().slice(0, 2); // Fallback attempt

    if (!code) return <span>🏳️</span>;

    return (
        <img
            src={`https://flagcdn.com/w40/${code}.png`}
            srcSet={`https://flagcdn.com/w80/${code}.png 2x`}
            width="20"
            style={{
                borderRadius: '3px',
                boxShadow: '0 1px 2px rgba(0,0,0,0.1)',
                display: 'block'
            }}
            alt={country.name}
            onError={(e) => { e.target.style.display = 'none'; e.target.nextSibling.style.display = 'inline'; }}
        />
    );
};

const Assets = () => {
    const [assets, setAssets] = useState([]);
    const [countries, setCountries] = useState([]);
    const [companies, setCompanies] = useState([]);
    const [employees, setEmployees] = useState([]);
    const [activeCountryTab, setActiveCountryTab] = useState('global');
    const [searchTerm, setSearchTerm] = useState('');
    const [loading, setLoading] = useState(true);
    const [showAddModal, setShowAddModal] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [viewAssetData, setViewAssetData] = useState(null);
    const [viewTab, setViewTab] = useState('details');
    const [editMode, setEditMode] = useState(false);
    const [editForm, setEditForm] = useState({});
    
    const { user } = useAuthStore();
    const normalizedRole = user?.role || '';
    const isAdmin = normalizedRole && normalizedRole !== 'EMPLOYEE';
    const isGlobalAdmin = normalizedRole && normalizedRole !== 'EMPLOYEE';
    const { setPageTitle, setPageSubtitle, setBackPath, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        setPageTitle("Assets");
        setPageSubtitle("Manage company inventory and allocations");
        setBackPath('/dashboard');
        return () => resetPageHeader();
    }, []);

    // Form States
    const [assetForm, setAssetForm] = useState({
        name: '',
        category: 'laptop',
        serial_number: '',
        model_number: '',
        purchase_date: '',
        purchase_cost: '',
        base_currency_cost: '',
        currency_code: 'KES',
        remarks: '',
        company_id: ''
    });

    const [allocationForm, setAllocationForm] = useState({
        employee_id: '',
        allocation_date: new Date().toISOString().split('T')[0],
        expected_return_date: '',
        remarks: '',
        attachment: null
    });

    useEffect(() => {
        fetchCountries();
        fetchCompanies();
        if (isAdmin) {
            fetchEmployees();
        }
    }, []);

    useEffect(() => {
        fetchAssets();
    }, [activeCountryTab]);

    const fetchCountries = async () => {
        try {
            const res = await api.get('/attendance/countries');
            const data = res.data?.data || res.data;
            setCountries(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Failed to fetch countries', error);
        }
    };

    const fetchCompanies = async () => {
        try {
            const res = await api.get('/organization/companies');
            const data = res.data?.data || res.data;
            const companyList = Array.isArray(data) ? data : [];
            setCompanies(companyList);
            
            if (companyList.length > 0 && !assetForm.company_id) {
                setAssetForm(prev => ({ ...prev, company_id: companyList[0].id, currency_code: companyList[0].currency_code || prev.currency_code }));
            }
        } catch (error) {
            console.error('Failed to fetch companies', error);
        }
    };

    const fetchAssets = async () => {
        try {
            setLoading(true);
            const url = activeCountryTab === 'global' ? '/assets' : `/assets?country_id=${activeCountryTab}`;
            const res = await api.get(url);
            const data = res.data?.data || res.data;
            setAssets(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Failed to fetch assets', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchEmployees = async () => {
        try {
            const res = await api.get('/employees');
            const data = res.data?.data || res.data;
            setEmployees(Array.isArray(data) ? data : []);
        } catch (error) {
            console.error('Failed to fetch employees', error);
        }
    };

    const handleAddAsset = async (e) => {
        e.preventDefault();
        try {
            const payload = { ...assetForm };
            if (payload.purchase_cost === '') payload.purchase_cost = null;
            if (payload.base_currency_cost === '') payload.base_currency_cost = null;
            if (payload.purchase_date === '') payload.purchase_date = null;
            await api.post('/assets', payload);
            setShowAddModal(false);
            setAssetForm({
                name: '', category: 'laptop', serial_number: '', model_number: '',
                purchase_date: '', purchase_cost: '', currency_code: 'KES', remarks: '', 
                company_id: companies[0]?.id || ''
            });
            fetchAssets();
        } catch (error) {
            alert('Failed to add asset: ' + (error.response?.data?.message || error.message));
        }
    };

    const handleAllocateAsset = async (e) => {
        e.preventDefault();
        if (!viewAssetData) return;
        try {
            const formData = new FormData();
            formData.append('asset_id', viewAssetData.id);
            formData.append('employee_id', allocationForm.employee_id);
            formData.append('allocation_date', allocationForm.allocation_date);
            if (allocationForm.expected_return_date !== '') {
                formData.append('expected_return_date', allocationForm.expected_return_date);
            }
            if (allocationForm.remarks !== '') {
                formData.append('remarks', allocationForm.remarks);
            }
            if (allocationForm.attachment) {
                formData.append('attachment', allocationForm.attachment);
            }

            await api.post('/assets/allocate', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            setAllocationForm({
                employee_id: '', allocation_date: new Date().toISOString().split('T')[0],
                expected_return_date: '', remarks: '', attachment: null
            });
            fetchAssets();
            fetchAssetDetails(viewAssetData.id);
            setViewTab('history');
        } catch (error) {
            alert('Allocation failed: ' + (error.response?.data?.message || error.message));
        }
    };

    const handleDeallocateAsset = async (allocationId) => {
        if (!window.confirm('Are you sure you want to record the return of this asset?')) return;
        try {
            await api.post('/assets/deallocate', { allocation_id: allocationId });
            fetchAssets();
            if (viewAssetData) {
                fetchAssetDetails(viewAssetData.id);
            }
        } catch (error) {
            alert('Return failed: ' + (error.response?.data?.message || error.message));
        }
    };

    const fetchAssetDetails = async (id) => {
        try {
            const res = await api.get(`/assets/${id}`);
            const assetInfo = res.data?.data || res.data;
            setViewAssetData(assetInfo);
            setEditForm(assetInfo);
            setShowViewModal(true);
            setViewTab('details');
            setEditMode(false);
        } catch (error) {
            console.error('Failed to fetch asset details', error);
            alert('Could not fetch asset details.');
        }
    };

    const handleUpdateAsset = async (e) => {
        e.preventDefault();
        try {
            await api.put(`/assets/${viewAssetData.id}`, editForm);
            alert('Asset updated successfully');
            setEditMode(false);
            fetchAssets();
            fetchAssetDetails(viewAssetData.id);
        } catch (error) {
            console.error('Update failed', error);
            alert(error.response?.data?.message || 'Failed to update asset');
        }
    };

    const handleDeleteAsset = async (id) => {
        if (!window.confirm("Are you sure you want to delete this asset? This cannot be undone.")) return;
        try {
            await api.delete(`/assets/${id}`);
            alert('Asset deleted successfully');
            setShowViewModal(false);
            fetchAssets();
        } catch (error) {
            console.error('Delete failed', error);
            alert(error.response?.data?.message || 'Failed to delete asset. Ensure it is not allocated.');
        }
    };

    const filteredAssets = assets.filter(asset => {
        const searchLower = searchTerm.toLowerCase();
        return (
            asset.name?.toLowerCase().includes(searchLower) ||
            asset.serial_number?.toLowerCase().includes(searchLower) ||
            asset.model_number?.toLowerCase().includes(searchLower) ||
            asset.category?.toLowerCase().includes(searchLower) ||
            asset.first_name?.toLowerCase().includes(searchLower) ||
            asset.last_name?.toLowerCase().includes(searchLower)
        );
    });

    const getStatusBadge = (status) => {
        switch (status) {
            case 'available': return <span className="badge badge-success" style={{ background: '#10b981', color: 'white' }}>Available</span>;
            case 'allocated': return <span className="badge badge-primary" style={{ background: '#3b82f6', color: 'white' }}>Allocated</span>;
            case 'damaged': return <span className="badge badge-warning" style={{ background: '#f59e0b', color: 'white' }}>Damaged</span>;
            case 'lost': return <span className="badge badge-error" style={{ background: '#ef4444', color: 'white' }}>Lost</span>;
            default: return <span className="badge badge-secondary">{status}</span>;
        }
    };

    return (
        <div className="page-assets">
            {isAdmin && (
                <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '24px' }}>
                    <button className="btn btn-primary" onClick={() => setShowAddModal(true)}>
                        <Plus size={18} /> Add Asset
                    </button>
                </div>
            )}

            {/* Country Tabs */}
            <div style={{ display: 'flex', gap: '12px', marginBottom: '32px', overflowX: 'auto', paddingBottom: '8px' }}>
                <button 
                    onClick={() => setActiveCountryTab('global')}
                    style={{
                        padding: '8px 20px',
                        borderRadius: '20px',
                        border: 'none',
                        background: activeCountryTab === 'global' ? '#065f46' : 'white',
                        color: activeCountryTab === 'global' ? 'white' : 'var(--text-main)',
                        fontWeight: '600',
                        fontSize: '14px',
                        cursor: 'pointer',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '8px',
                        boxShadow: activeCountryTab === 'global' ? 'none' : '0 1px 3px rgba(0,0,0,0.1)',
                        transition: 'all 0.2s',
                        whiteSpace: 'nowrap'
                    }}
                >
                    <Globe size={16} /> Global
                </button>
                {countries.map(country => (
                    <button 
                        key={country.id}
                        onClick={() => setActiveCountryTab(country.id)}
                        style={{
                            padding: '8px 20px',
                            borderRadius: '20px',
                            border: 'none',
                            background: activeCountryTab === country.id ? '#065f46' : 'white',
                            color: activeCountryTab === country.id ? 'white' : 'var(--text-main)',
                            fontWeight: '600',
                            fontSize: '14px',
                            cursor: 'pointer',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px',
                            boxShadow: activeCountryTab === country.id ? 'none' : '0 1px 3px rgba(0,0,0,0.1)',
                            transition: 'all 0.2s',
                            whiteSpace: 'nowrap'
                        }}
                    >
                        {renderFlag(country)}
                        <span>{country.name}</span>
                    </button>
                ))}
            </div>

            <div className="card" style={{ padding: '0', overflow: 'hidden' }}>
                <div style={{
                    padding: '20px',
                    borderBottom: '1px solid var(--border-gray)',
                    display: 'flex',
                    gap: '16px'
                }}>
                    <div style={{ flex: 1, position: 'relative' }}>
                        <Search size={18} style={{ position: 'absolute', left: '12px', top: '11px', color: 'var(--text-secondary)' }} />
                        <input
                            type="text"
                            className="form-input"
                            placeholder="Search by name, serial, or model..."
                            style={{ paddingLeft: '40px' }}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                    <button className="btn btn-secondary">
                        <Filter size={18} /> Filter
                    </button>
                </div>

                <div style={{ overflowX: 'auto' }}>
                    <table className="data-table">
                        <thead>
                            <tr>
                                <th>Asset Detail</th>
                                <th>Category</th>
                                <th>Serial / Model</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                {isAdmin && <th>Actions</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr><td colSpan="6" style={{ textAlign: 'center', padding: '40px' }}>Loading assets...</td></tr>
                            ) : filteredAssets.length === 0 ? (
                                <tr><td colSpan="6" style={{ textAlign: 'center', padding: '40px' }}>No assets found.</td></tr>
                            ) : filteredAssets.map((asset) => (
                                <tr key={asset.id}>
                                    <td>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                            <div style={{ padding: '8px', background: 'var(--bg-light-gray)', borderRadius: '8px' }}>
                                                <Package size={20} color="var(--primary-brand)" />
                                            </div>
                                            <div>
                                                <div style={{ fontWeight: '600' }}>{asset.name}</div>
                                                <div style={{ fontSize: '11px', color: 'var(--text-secondary)' }}>
                                                    {companies.find(c => c.id == asset.company_id)?.name || 'Company ID: ' + asset.company_id}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style={{ textTransform: 'capitalize' }}>{asset.category}</td>
                                    <td>
                                        <div style={{ fontSize: '13px' }}>{asset.serial_number || 'N/A'}</div>
                                        <div style={{ fontSize: '11px', color: 'var(--text-secondary)' }}>{asset.model_number || '-'}</div>
                                    </td>
                                    <td>{getStatusBadge(asset.status)}</td>
                                    <td>
                                        {asset.first_name ? (
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                                <div style={{ width: '24px', height: '24px', borderRadius: '50%', background: 'var(--primary-brand)', color: 'white', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '10px' }}>
                                                    {asset.first_name[0]}{asset.last_name[0]}
                                                </div>
                                                <span style={{ fontSize: '13px', fontWeight: '500' }}>{asset.first_name} {asset.last_name}</span>
                                            </div>
                                        ) : (
                                            <span style={{ color: 'var(--text-secondary)', fontSize: '13px' }}>Unassigned</span>
                                        )}
                                    </td>
                                    {isAdmin && (
                                        <td>
                                            <button 
                                                className="btn btn-secondary btn-sm"
                                                onClick={() => fetchAssetDetails(asset.id)}
                                            >View</button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Add Asset Modal */}
            {showAddModal && (
                <div className="modal-overlay" style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
                    <div className="modal-content" style={{ background: 'white', padding: '24px', borderRadius: '16px', width: '100%', maxWidth: '650px' }}>
                        <h2 style={{ marginBottom: '16px', fontSize: '1.25rem' }}>Add New Asset</h2>
                        <form onSubmit={handleAddAsset}>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                                <div className="form-group">
                                    <label className="form-label">Company *</label>
                                    <select className="form-input" required value={assetForm.company_id} onChange={e => {
                                        const selectedId = e.target.value;
                                        const comp = companies.find(c => c.id == selectedId);
                                        setAssetForm({...assetForm, company_id: selectedId, currency_code: comp?.currency_code || 'KES'});
                                    }}>
                                        <option value="">Select Company</option>
                                        {companies.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </select>
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Asset Name *</label>
                                    <input type="text" className="form-input" required value={assetForm.name} onChange={e => setAssetForm({...assetForm, name: e.target.value})} />
                                </div>
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                                <div className="form-group">
                                    <label className="form-label">Category</label>
                                    <select className="form-input" value={assetForm.category} onChange={e => setAssetForm({...assetForm, category: e.target.value})}>
                                        <option value="laptop">Laptop</option>
                                        <option value="mobile">Mobile</option>
                                        <option value="hardware">Hardware</option>
                                        <option value="software">Software</option>
                                        <option value="furniture">Furniture</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Serial Number</label>
                                    <input type="text" className="form-input" value={assetForm.serial_number} onChange={e => setAssetForm({...assetForm, serial_number: e.target.value})} />
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Model Number</label>
                                    <input type="text" className="form-input" value={assetForm.model_number} onChange={e => setAssetForm({...assetForm, model_number: e.target.value})} />
                                </div>
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: '1.2fr 0.8fr 1fr 1fr', gap: '12px', marginBottom: '20px' }}>
                                <div className="form-group">
                                    <label className="form-label">Purchase Date</label>
                                    <DateInput value={assetForm.purchase_date} onChange={val => setAssetForm({...assetForm, purchase_date: val})} />
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Currency</label>
                                    <input type="text" className="form-input" value={assetForm.currency_code} onChange={e => setAssetForm({...assetForm, currency_code: e.target.value.toUpperCase()})} maxLength="3" placeholder="e.g. USD" />
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Purchase Cost</label>
                                    <input type="number" className="form-input" value={assetForm.purchase_cost} onChange={e => setAssetForm({...assetForm, purchase_cost: e.target.value})} />
                                </div>
                                <div className="form-group">
                                    <label className="form-label" title="Base Currency Cost (Company Base)">Base Cost</label>
                                    <input type="number" className="form-input" value={assetForm.base_currency_cost} onChange={e => setAssetForm({...assetForm, base_currency_cost: e.target.value})} />
                                </div>
                            </div>
                            <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                                <button type="button" className="btn btn-secondary" onClick={() => setShowAddModal(false)}>Cancel</button>
                                <button type="submit" className="btn btn-primary">Save Asset</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}


            {/* View/Edit Asset Modal */}
            {showViewModal && viewAssetData && (
                <div className="modal-overlay" style={{ position: 'fixed', top: 0, left: 0, right: 0, bottom: 0, background: 'rgba(0,0,0,0.5)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1000 }}>
                    <div className="modal-content" style={{ background: 'white', padding: '24px', borderRadius: '16px', width: '100%', maxWidth: '650px', maxHeight: '90vh', overflowY: 'auto' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                            <h2 style={{ margin: 0, fontSize: '1.25rem' }}>{viewAssetData.name}</h2>
                            <button className="btn btn-secondary btn-sm" onClick={() => setShowViewModal(false)}>✕</button>
                        </div>
                        
                        <div style={{ display: 'flex', gap: '0', marginBottom: '16px', borderBottom: '1px solid var(--border-gray)' }}>
                            {['details', 'allocate', 'history'].map(tab => (
                                <button key={tab}
                                    style={{ padding: '8px 16px', border: 'none', background: 'none', cursor: 'pointer', borderBottom: viewTab === tab ? '2px solid var(--primary-brand)' : '2px solid transparent', fontWeight: viewTab === tab ? '600' : 'normal', color: viewTab === tab ? 'var(--primary-brand)' : 'var(--text-secondary)', fontSize: '13px', textTransform: 'capitalize' }}
                                    onClick={() => { setViewTab(tab); if (tab !== 'details') setEditMode(false); }}
                                >{tab === 'details' ? 'Details' : tab === 'allocate' ? 'Issue Asset' : 'Movement History'}</button>
                            ))}
                        </div>

                        {viewTab === 'details' && (
                            <div>
                                {!editMode ? (
                                    <div>
                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '16px' }}>
                                            <div style={{ padding: '8px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Company</span><span style={{ fontWeight: 500 }}>{viewAssetData.company_name || 'N/A'}</span></div>
                                            <div style={{ padding: '8px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Category</span><span style={{ fontWeight: 500, textTransform: 'capitalize' }}>{viewAssetData.category}</span></div>
                                            <div style={{ padding: '8px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Serial Number</span><span style={{ fontWeight: 500 }}>{viewAssetData.serial_number || 'N/A'}</span></div>
                                            <div style={{ padding: '8px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Model Number</span><span style={{ fontWeight: 500 }}>{viewAssetData.model_number || 'N/A'}</span></div>
                                            <div style={{ padding: '8px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Purchase Date</span><span style={{ fontWeight: 500 }}>{viewAssetData.purchase_date || 'N/A'}</span></div>
                                            <div style={{ padding: '8px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Cost</span><span style={{ fontWeight: 500 }}>{viewAssetData.purchase_cost ? `${viewAssetData.currency_code} ${viewAssetData.purchase_cost}` : 'N/A'}</span></div>
                                            <div style={{ padding: '8px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Base Cost</span><span style={{ fontWeight: 500 }}>{viewAssetData.base_currency_cost || 'N/A'}</span></div>
                                            <div style={{ padding: '8px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Status</span>{getStatusBadge(viewAssetData.status)}</div>
                                        </div>
                                        <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end', borderTop: '1px solid var(--border-gray)', paddingTop: '12px' }}>
                                            <button className="btn btn-secondary" style={{ borderColor: '#ef4444', color: '#ef4444', marginRight: 'auto', fontSize: '13px' }} onClick={() => handleDeleteAsset(viewAssetData.id)}>Delete</button>
                                            <button className="btn btn-primary" style={{ fontSize: '13px' }} onClick={() => setEditMode(true)}>Edit</button>
                                        </div>
                                    </div>
                                ) : (
                                    <form onSubmit={handleUpdateAsset}>
                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                                            <div className="form-group">
                                                <label className="form-label">Asset Name *</label>
                                                <input type="text" className="form-input" required value={editForm.name} onChange={e => setEditForm({...editForm, name: e.target.value})} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Category</label>
                                                <select className="form-input" value={editForm.category} onChange={e => setEditForm({...editForm, category: e.target.value})}>
                                                    <option value="laptop">Laptop</option>
                                                    <option value="mobile">Mobile</option>
                                                    <option value="hardware">Hardware</option>
                                                    <option value="software">Software</option>
                                                    <option value="furniture">Furniture</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                                            <div className="form-group">
                                                <label className="form-label">Serial Number</label>
                                                <input type="text" className="form-input" value={editForm.serial_number || ''} onChange={e => setEditForm({...editForm, serial_number: e.target.value})} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Model Number</label>
                                                <input type="text" className="form-input" value={editForm.model_number || ''} onChange={e => setEditForm({...editForm, model_number: e.target.value})} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Purchase Date</label>
                                                <DateInput value={editForm.purchase_date} onChange={val => setEditForm({...editForm, purchase_date: val})} />
                                            </div>
                                        </div>
                                        <div style={{ display: 'grid', gridTemplateColumns: '0.8fr 1fr 1fr', gap: '12px', marginBottom: '20px' }}>
                                            <div className="form-group">
                                                <label className="form-label">Currency</label>
                                                <input type="text" className="form-input" value={editForm.currency_code} onChange={e => setEditForm({...editForm, currency_code: e.target.value.toUpperCase()})} maxLength="3" />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Purchase Cost</label>
                                                <input type="number" className="form-input" value={editForm.purchase_cost || ''} onChange={e => setEditForm({...editForm, purchase_cost: e.target.value})} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Base Cost</label>
                                                <input type="number" className="form-input" value={editForm.base_currency_cost || ''} onChange={e => setEditForm({...editForm, base_currency_cost: e.target.value})} />
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                                            <button type="button" className="btn btn-secondary" onClick={() => { setEditMode(false); setEditForm(viewAssetData); }}>Cancel</button>
                                            <button type="submit" className="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        )}

                        {viewTab === 'allocate' && (
                            <div>
                                {viewAssetData.status === 'allocated' ? (
                                    <div>
                                        {(() => {
                                            const activeAlloc = (viewAssetData.history || []).find(h => h.status === 'active');
                                            if (!activeAlloc) return <div style={{ textAlign: 'center', padding: '32px', color: 'var(--text-secondary)' }}>No active allocation found.</div>;
                                            return (
                                                <div>
                                                    <div style={{ background: 'rgba(59, 130, 246, 0.06)', borderRadius: '10px', padding: '16px', marginBottom: '16px', border: '1px solid rgba(59, 130, 246, 0.15)' }}>
                                                        <div style={{ fontSize: '12px', color: 'var(--text-secondary)', marginBottom: '8px', fontWeight: 600, textTransform: 'uppercase', letterSpacing: '0.5px' }}>Currently Issued To</div>
                                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                                                            <div style={{ padding: '4px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Employee</span><span style={{ fontWeight: 600, fontSize: '14px' }}>{activeAlloc.first_name} {activeAlloc.last_name}</span></div>
                                                            <div style={{ padding: '4px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Allocation Date</span><span style={{ fontWeight: 500, fontSize: '14px' }}>{activeAlloc.allocation_date}</span></div>
                                                            <div style={{ padding: '4px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Expected Return</span><span style={{ fontWeight: 500, fontSize: '14px' }}>{activeAlloc.expected_return_date || 'Not specified'}</span></div>
                                                            <div style={{ padding: '4px 0' }}><span style={{ color: 'var(--text-secondary)', fontSize: '12px', display: 'block' }}>Attachment</span>{activeAlloc.attachment ? <a href={activeAlloc.attachment} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--primary-brand)', textDecoration: 'none', fontWeight: 500, fontSize: '14px' }}>View Document</a> : <span style={{ fontSize: '14px', color: 'var(--text-secondary)' }}>None</span>}</div>
                                                        </div>
                                                    </div>
                                                    <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                                        <button 
                                                            className="btn btn-secondary"
                                                            style={{ borderColor: '#ef4444', color: '#ef4444', fontSize: '13px' }}
                                                            onClick={() => handleDeallocateAsset(activeAlloc.id)}
                                                        >Return Asset</button>
                                                    </div>
                                                </div>
                                            );
                                        })()}
                                    </div>
                                ) : viewAssetData.status === 'available' ? (
                                    <form onSubmit={handleAllocateAsset}>
                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                                            <div className="form-group">
                                                <label className="form-label">Employee *</label>
                                                <select className="form-input" required value={allocationForm.employee_id} onChange={e => setAllocationForm({...allocationForm, employee_id: e.target.value})}>
                                                    <option value="">Select Employee</option>
                                                    {employees
                                                        .filter(emp => emp.primary_company_id == viewAssetData.company_id)
                                                        .map(emp => (
                                                        <option key={emp.id} value={emp.id}>{emp.first_name} {emp.last_name}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Allocation Date</label>
                                                <DateInput value={allocationForm.allocation_date} onChange={val => setAllocationForm({...allocationForm, allocation_date: val})} />
                                            </div>
                                        </div>
                                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                                            <div className="form-group">
                                                <label className="form-label">Expected Return Date</label>
                                                <DateInput value={allocationForm.expected_return_date} onChange={val => setAllocationForm({...allocationForm, expected_return_date: val})} />
                                            </div>
                                            <div className="form-group">
                                                <label className="form-label">Attachment</label>
                                                <input type="file" className="form-input" style={{ padding: '6px' }} onChange={e => setAllocationForm({...allocationForm, attachment: e.target.files[0]})} />
                                            </div>
                                        </div>
                                        <div style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                                            <button type="submit" className="btn btn-primary">Issue Asset</button>
                                        </div>
                                    </form>
                                ) : (
                                    <div style={{ textAlign: 'center', padding: '32px', color: 'var(--text-secondary)' }}>
                                        This asset is currently <strong>{viewAssetData.status}</strong> and cannot be issued.
                                    </div>
                                )}
                            </div>
                        )}

                        {viewTab === 'history' && (
                            <div>
                                {(!viewAssetData.history || viewAssetData.history.length === 0) ? (
                                    <div style={{ textAlign: 'center', padding: '32px', color: 'var(--text-secondary)' }}>No movement history found.</div>
                                ) : (
                                    <table className="data-table">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Allocated</th>
                                                <th>Expected Return</th>
                                                <th>Status</th>
                                                <th>Returned</th>
                                                <th>Attach.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {viewAssetData.history.map(hist => (
                                                <tr key={hist.id}>
                                                    <td style={{ fontWeight: 500 }}>{hist.first_name} {hist.last_name}</td>
                                                    <td>{hist.allocation_date}</td>
                                                    <td>{hist.expected_return_date || '-'}</td>
                                                    <td>
                                                        <span className={`badge ${hist.status === 'active' ? 'badge-primary' : 'badge-success'}`} style={{ fontSize: '11px' }}>
                                                            {hist.status}
                                                        </span>
                                                    </td>
                                                    <td>{hist.actual_return_date || '-'}</td>
                                                    <td>
                                                        {hist.attachment ? (
                                                            <a href={hist.attachment} target="_blank" rel="noopener noreferrer" style={{ color: 'var(--primary-brand)', textDecoration: 'none', fontSize: '12px' }}>View</a>
                                                        ) : '-'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
};

export default Assets;
