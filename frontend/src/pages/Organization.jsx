import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Building2, Save, Globe, Palette, ShieldCheck } from 'lucide-react';
import api from '../services/api';
import useLayoutStore from '../store/useLayoutStore';

const Organization = () => {
    const navigate = useNavigate();
    const isEmployeeView = localStorage.getItem('adminViewMode') === 'employee';

    useEffect(() => {
        if (isEmployeeView) {
            navigate('/employee-profile');
        }
    }, [isEmployeeView, navigate]);

    const [settings, setSettings] = useState({
        company_name: '',
        base_currency: 'KES',
        secondary_reporting_currency: 'USD',
        fiscal_year_start: '01-01'
    });
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState({ type: '', text: '' });
    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();

    useEffect(() => {
        setPageTitle("Global Settings");
        setPageSubtitle("Configure your enterprise branding and regional preferences");
        return () => resetPageHeader();
    }, []);

    useEffect(() => {
        fetchSettings();
    }, []);

    const fetchSettings = async () => {
        try {
            const res = await api.get('/organization/settings');
            const list = res.data?.data || res.data || [];
            const newSettings = { ...settings };
            list.forEach(item => {
                if (newSettings.hasOwnProperty(item.setting_key)) {
                    newSettings[item.setting_key] = item.setting_value;
                }
            });
            setSettings(newSettings);
        } catch (err) {
            console.error("Failed to fetch settings", err);
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async (e) => {
        e.preventDefault();
        setSaving(true);
        setMessage({ type: '', text: '' });
        try {
            await Promise.all([
                api.post('/organization/settings', {
                    setting_key: 'company_name',
                    setting_value: settings.company_name,
                    category: 'general'
                }),
                api.post('/organization/settings', {
                    setting_key: 'base_currency',
                    setting_value: settings.base_currency,
                    category: 'financial'
                }),
                api.post('/organization/settings', {
                    setting_key: 'secondary_reporting_currency',
                    setting_value: settings.secondary_reporting_currency,
                    category: 'financial'
                })
            ]);
            
            setMessage({ type: 'success', text: 'Settings updated successfully!' });
        } catch (err) {
            setMessage({ type: 'error', text: 'Failed to update settings. Please try again.' });
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <div className="p-8 text-center">Loading settings...</div>;

    return (
        <div style={{ maxWidth: '800px', margin: '0 auto' }}>

            {message.text && (
                <div style={{ 
                    padding: '12px 16px', 
                    borderRadius: '8px', 
                    marginBottom: '20px',
                    backgroundColor: message.type === 'success' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)',
                    color: message.type === 'success' ? '#059669' : '#dc2626',
                    border: `1px solid ${message.type === 'success' ? '#10b981' : '#ef4444'}`,
                    fontSize: '14px'
                }}>
                    {message.text}
                </div>
            )}

            <div className="card" style={{ padding: '0', overflow: 'hidden' }}>
                <form onSubmit={handleSave}>
                    <div style={{ padding: '24px', borderBottom: '1px solid var(--border-gray)' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '20px' }}>
                            <div style={{ padding: '8px', borderRadius: '8px', backgroundColor: 'rgba(40, 107, 62, 0.1)', color: 'var(--primary-brand)' }}>
                                <Palette size={20} />
                            </div>
                            <h3 style={{ margin: 0 }}>Branding & Identity</h3>
                        </div>

                        <div className="form-group" style={{ marginBottom: '20px' }}>
                            <label className="form-label">Company Name</label>
                            <input 
                                type="text" 
                                className="form-input" 
                                value={settings.company_name}
                                onChange={(e) => setSettings({...settings, company_name: e.target.value})}
                                placeholder="Enter legal company name"
                                required
                            />
                            <p style={{ fontSize: '12px', color: 'var(--text-secondary)', marginTop: '6px' }}>
                                This name appears in the sidebar, reports, and emails.
                            </p>
                        </div>

                        <div className="form-group" style={{ marginBottom: '0' }}>
                            <label className="form-label">Company Logo</label>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '16px', marginTop: '8px' }}>
                                <div style={{ 
                                    width: '64px', 
                                    height: '64px', 
                                    borderRadius: '8px', 
                                    border: '1px dashed var(--border-gray)',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    backgroundColor: '#fafafa'
                                }}>
                                    <Building2 size={24} color="#ccc" />
                                </div>
                                <button type="button" className="btn btn-outline" style={{ fontSize: '12px' }}>
                                    Change Logo
                                </button>
                                <span style={{ fontSize: '12px', color: 'var(--text-secondary)' }}>PNG, SVG or JPG. Max 2MB.</span>
                            </div>
                        </div>
                    </div>

                    <div style={{ padding: '24px', backgroundColor: '#fafafa' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '20px' }}>
                            <div style={{ padding: '8px', borderRadius: '8px', backgroundColor: 'rgba(59, 130, 246, 0.1)', color: '#3b82f6' }}>
                                <Globe size={20} />
                            </div>
                            <h3 style={{ margin: 0 }}>Regional Defaults</h3>
                        </div>

                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '20px' }}>
                            <div className="form-group">
                                <label className="form-label">Base Currency</label>
                                <select 
                                    className="form-input" 
                                    value={settings.base_currency}
                                    onChange={(e) => setSettings({...settings, base_currency: e.target.value})}
                                >
                                    <option value="KES">Kenyan Shilling (KES)</option>
                                    <option value="UGX">Ugandan Shilling (UGX)</option>
                                    <option value="AED">UAE Dirham (AED)</option>
                                    <option value="USD">US Dollar (USD)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                </select>
                            </div>
                            <div className="form-group">
                                <label className="form-label">Secondary Currency</label>
                                <select 
                                    className="form-input" 
                                    value={settings.secondary_reporting_currency}
                                    onChange={(e) => setSettings({...settings, secondary_reporting_currency: e.target.value})}
                                >
                                    <option value="USD">US Dollar (USD)</option>
                                    <option value="KES">Kenyan Shilling (KES)</option>
                                    <option value="EUR">Euro (EUR)</option>
                                    <option value="GBP">British Pound (GBP)</option>
                                </select>
                            </div>
                            <div className="form-group">
                                <label className="form-label">Fiscal Year Start</label>
                                <select 
                                    className="form-input"
                                    value={settings.fiscal_year_start}
                                    onChange={(e) => setSettings({...settings, fiscal_year_start: e.target.value})}
                                >
                                    <option value="01-01">January 1st</option>
                                    <option value="04-01">April 1st</option>
                                    <option value="07-01">July 1st</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style={{ padding: '16px 24px', backgroundColor: '#fff', borderTop: '1px solid var(--border-gray)', display: 'flex', justifyContent: 'flex-end', gap: '12px' }}>
                        <button type="button" className="btn btn-outline" onClick={fetchSettings} disabled={saving}>
                            Reset
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={saving}>
                            {saving ? 'Saving...' : <><Save size={18} /> Save Settings</>}
                        </button>
                    </div>
                </form>
            </div>

            <div className="card" style={{ marginTop: '24px', padding: '16px', display: 'flex', alignItems: 'center', gap: '12px', backgroundColor: '#f0fdf4', border: '1px solid #bbf7d0' }}>
                <ShieldCheck size={20} color="#15803d" />
                <span style={{ fontSize: '13px', color: '#166534' }}>
                    Changes to global settings are audited and logged for compliance purposes.
                </span>
            </div>
        </div>
    );
};

export default Organization;
