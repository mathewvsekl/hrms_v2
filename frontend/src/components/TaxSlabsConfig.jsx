import React, { useState, useEffect } from 'react';
import api from '../services/api';
import { Plus, Trash2, Edit2, Loader, ChevronDown, ChevronRight } from 'lucide-react';
import { formatDate } from '../utils/dateUtils';

export default function TaxSlabsConfig({ showAlert, components = [], companyId }) {
    const [slabs, setSlabs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [isAdding, setIsAdding] = useState(false);
    
    const [form, setForm] = useState({
        component_id: '',
        effective_date: '',
        personal_relief: 0,
        brackets: [{ id: null, min_amount: 0, max_amount: '', tax_type: 'PERCENTAGE', percentage: 0, fixed_amount: 0 }]
    });

    const [expandedComponents, setExpandedComponents] = useState({});

    const toggleComponent = (compId) => {
        setExpandedComponents(prev => ({ ...prev, [compId]: !prev[compId] }));
    };

    useEffect(() => {
        fetchSlabs();
    }, [companyId]);

    const fetchSlabs = async () => {
        try {
            setLoading(true);
            const res = await api.get(`/tax-slabs?company_id=${companyId || ''}`);
            if (res.data.status === 'success') {
                setSlabs(res.data.data);
            }
        } catch (error) {
            showAlert('Error', 'Failed to fetch tax slabs: ' + (error.response?.data?.message || error.message), 'error');
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const handleSave = async () => {
        try {
            setSaving(true);
            if (form.component_id === '') {
                showAlert('Error', 'Please select a Tax Head', 'error');
                setSaving(false);
                return;
            }

            if (form.effective_date === '') {
                showAlert('Error', 'Please select an Effective Date From', 'error');
                setSaving(false);
                return;
            }

            const componentIdVal = parseInt(form.component_id);

            // Validation: Ensure no blank fields
            for (let i = 0; i < form.brackets.length; i++) {
                const b = form.brackets[i];
                if (b.min_amount === '' || b.min_amount === null) {
                    showAlert('Error', `Row ${i + 1}: Min Amount is required`, 'error');
                    setSaving(false); return;
                }
                if (i < form.brackets.length - 1 && (b.max_amount === '' || b.max_amount === null)) {
                    showAlert('Error', `Row ${i + 1}: Max Amount is required (only the last row can be blank)`, 'error');
                    setSaving(false); return;
                }
                if (b.max_amount !== '' && b.max_amount !== null && parseFloat(b.max_amount) <= parseFloat(b.min_amount)) {
                    showAlert('Error', `Row ${i + 1}: Max Amount must be strictly greater than Min Amount`, 'error');
                    setSaving(false); return;
                }
                if (b.tax_type === 'FIXED' && (b.fixed_amount === '' || b.fixed_amount === null)) {
                    showAlert('Error', `Row ${i + 1}: Fixed Amount is required`, 'error');
                    setSaving(false); return;
                }
                if (b.tax_type === 'PERCENTAGE' && (b.percentage === '' || b.percentage === null)) {
                    showAlert('Error', `Row ${i + 1}: Percentage is required`, 'error');
                    setSaving(false); return;
                }
            }
            
            const payload = {
                company_id: companyId,
                component_id: componentIdVal,
                effective_date: form.effective_date,
                personal_relief: form.personal_relief || 0,
                brackets: form.brackets.map(bracket => ({
                    min_amount: bracket.min_amount,
                    max_amount: bracket.max_amount === '' ? null : bracket.max_amount,
                    tax_type: bracket.tax_type || 'PERCENTAGE',
                    percentage: bracket.percentage || 0,
                    fixed_amount: bracket.fixed_amount || 0
                }))
            };

            await api.post('/tax-slabs/bulk', payload);

            showAlert('Success', 'Tax slabs saved successfully', 'success');
            setIsAdding(false);
            setForm({
                component_id: '',
                effective_date: '',
                personal_relief: 0,
                brackets: [{ id: null, min_amount: 0, max_amount: '', percentage: 0 }]
            });
            fetchSlabs();
        } catch (error) {
            showAlert('Error', error.response?.data?.message || 'Failed to save tax slabs', 'error');
        } finally {
            setSaving(false);
        }
    };

    const handleEdit = (slab) => {
        const componentSlabs = slabs.filter(s => s.component_id === slab.component_id);
        setForm({
            component_id: slab.component_id || '',
            effective_date: componentSlabs[0]?.effective_date || '',
            personal_relief: componentSlabs[0]?.personal_relief || 0,
            brackets: componentSlabs.map(s => ({
                id: s.id,
                min_amount: s.min_amount,
                max_amount: s.max_amount || '',
                tax_type: s.tax_type || 'PERCENTAGE',
                percentage: s.percentage,
                fixed_amount: s.fixed_amount || 0
            }))
        });
        setIsAdding(true);
    };

    const addBracketRow = () => {
        const lastBracket = form.brackets[form.brackets.length - 1];
        let nextMin = 0;
        if (lastBracket && lastBracket.max_amount !== '') {
            const maxVal = parseFloat(lastBracket.max_amount);
            if (!isNaN(maxVal)) {
                nextMin = maxVal + 1;
            }
        }

        setForm({
            ...form,
            brackets: [...form.brackets, { id: null, min_amount: nextMin, max_amount: '', tax_type: 'PERCENTAGE', percentage: 0, fixed_amount: 0 }]
        });
    };

    const removeBracketRow = (index) => {
        const newBrackets = [...form.brackets];
        newBrackets.splice(index, 1);
        setForm({ ...form, brackets: newBrackets });
    };

    const updateBracket = (index, field, value) => {
        const newBrackets = [...form.brackets];
        newBrackets[index][field] = value;
        
        // Auto-populate the next row's min_amount if max_amount is updated
        if (field === 'max_amount') {
            const maxVal = parseFloat(value);
            const minVal = parseFloat(newBrackets[index].min_amount);
            if (!isNaN(maxVal) && !isNaN(minVal) && maxVal > minVal) {
                if (index < newBrackets.length - 1) {
                    newBrackets[index + 1].min_amount = maxVal + 1;
                } else {
                    // Auto-add next bracket
                    newBrackets.push({ id: null, min_amount: maxVal + 1, max_amount: '', tax_type: 'PERCENTAGE', percentage: 0, fixed_amount: 0 });
                }
            } else if (index < newBrackets.length - 1) {
                // If it becomes invalid (or empty) while there's already a next row, you might want to reset or just leave it.
                // For now, let's leave it, or maybe set to 0. We'll just ignore unless it's valid.
            }
        }
        
        setForm({ ...form, brackets: newBrackets });
    };

    if (loading) {
        return <div style={{ display: 'flex', justifyContent: 'center', padding: '40px' }}><Loader className="spin" size={24} /></div>;
    }

    const groupedSlabs = slabs.reduce((acc, slab) => {
        if (!acc[slab.component_id]) acc[slab.component_id] = { id: slab.component_id, name: slab.component_name, effective_date: slab.effective_date, slabs: [] };
        acc[slab.component_id].slabs.push(slab);
        return acc;
    }, {});

    return (
        <div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                <p style={{ margin: 0, color: '#64748b', fontSize: '14px' }}>Configure the marginal tax brackets (slabs) for calculations.</p>
                {!isAdding && (
                    <button className="btn btn-primary" onClick={() => setIsAdding(true)}>
                        <Plus size={16} style={{ marginRight: '8px' }} /> Add Slab(s)
                    </button>
                )}
            </div>

            {isAdding && (
                <div className="card" style={{ padding: '20px', marginBottom: '24px', background: '#f8fafc', border: '1px solid #e2e8f0' }}>
                    <h3 style={{ margin: '0 0 16px 0', fontSize: '16px' }}>Add/Edit Tax Slabs</h3>
                    
                    <div style={{ display: 'flex', gap: '20px', marginBottom: '20px', maxWidth: '600px' }}>
                        <div style={{ flex: 1 }}>
                            <label className="form-label" style={{ fontSize: '12px' }}>Tax Head (Component)</label>
                            <select className="form-input" value={form.component_id} onChange={e => setForm({...form, component_id: e.target.value})}>
                                <option value="" disabled>Select Tax Head...</option>
                                {components
                                    .filter(comp => comp.type === 'DEDUCTION' && comp.computation_type === 'FORMULA' && (!groupedSlabs[comp.id] || comp.id === parseInt(form.component_id)))
                                    .map(comp => (
                                    <option key={comp.id} value={comp.id}>{comp.name} {comp.is_statutory == 1 ? '(Statutory)' : ''}</option>
                                ))}
                            </select>
                        </div>
                        <div style={{ flex: 1 }}>
                            <label className="form-label" style={{ fontSize: '12px' }}>Effective Date From</label>
                            <input type="date" className="form-input" value={form.effective_date} onChange={e => setForm({...form, effective_date: e.target.value})} />
                        </div>
                        <div style={{ flex: 1 }}>
                            <label className="form-label" style={{ fontSize: '12px' }}>Personal Relief (Monthly)</label>
                            <input type="number" step="0.01" className="form-input" value={form.personal_relief} onChange={e => setForm({...form, personal_relief: e.target.value})} placeholder="0.00" />
                        </div>
                    </div>

                    <div style={{ marginBottom: '20px' }}>
                        {form.brackets.map((bracket, index) => (
                            <div key={index} style={{ display: 'flex', gap: '16px', alignItems: 'flex-end', marginBottom: '12px' }}>
                                <div style={{ flex: 1 }}>
                                    <label className="form-label" style={{ fontSize: '12px' }}>Min Amount</label>
                                    <input type="number" step="0.01" className="form-input" value={bracket.min_amount} onChange={e => updateBracket(index, 'min_amount', e.target.value)} />
                                </div>
                                <div style={{ flex: 1 }}>
                                    <label className="form-label" style={{ fontSize: '12px' }}>Max Amount (Leave blank for infinity)</label>
                                    <input type="number" step="0.01" className="form-input" value={bracket.max_amount} onChange={e => updateBracket(index, 'max_amount', e.target.value)} placeholder="Infinity" />
                                </div>
                                <div style={{ flex: 1, maxWidth: '120px' }}>
                                    <label className="form-label" style={{ fontSize: '12px' }}>Type</label>
                                    <select className="form-input" value={bracket.tax_type || 'PERCENTAGE'} onChange={e => updateBracket(index, 'tax_type', e.target.value)}>
                                        <option value="PERCENTAGE">Percentage</option>
                                        <option value="FIXED">Fixed Amt</option>
                                    </select>
                                </div>
                                <div style={{ flex: 1 }}>
                                    <label className="form-label" style={{ fontSize: '12px' }}>
                                        {(!bracket.tax_type || bracket.tax_type === 'PERCENTAGE') ? 'Percentage (%)' : 'Amount'}
                                    </label>
                                    <input 
                                        type="number" step="0.01" className="form-input" 
                                        value={(!bracket.tax_type || bracket.tax_type === 'PERCENTAGE') ? bracket.percentage : bracket.fixed_amount} 
                                        onChange={e => updateBracket(index, (!bracket.tax_type || bracket.tax_type === 'PERCENTAGE') ? 'percentage' : 'fixed_amount', e.target.value)} 
                                    />
                                </div>
                                {form.brackets.length > 1 && index === form.brackets.length - 1 && (
                                    <button className="btn-icon" style={{ color: '#ef4444', marginBottom: '8px' }} onClick={() => removeBracketRow(index)}>
                                        <Trash2 size={18} />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <button className="btn btn-secondary" style={{ display: 'flex', alignItems: 'center', gap: '6px' }} onClick={addBracketRow}>
                            <Plus size={16} /> Add Bracket Row
                        </button>
                        <div style={{ display: 'flex', gap: '12px' }}>
                            <button className="btn btn-primary" onClick={handleSave} disabled={saving}>
                                {saving ? <Loader size={14} className="spin" /> : 'Save All'}
                            </button>
                            <button className="btn btn-secondary" onClick={() => {
                                setIsAdding(false);
                                setForm({
                                    component_id: '',
                                    effective_date: '',
                                    brackets: [{ id: null, min_amount: 0, max_amount: '', tax_type: 'PERCENTAGE', percentage: 0, fixed_amount: 0 }]
                                });
                            }} disabled={saving}>
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <div className="table-container">
                <table className="table">
                    <thead>
                        <tr>
                            <th>Tax Head</th>
                            <th>Min Amount</th>
                            <th>Max Amount</th>
                            <th>Rate / Amount</th>
                            <th>Max Tax in Bracket</th>
                            <th style={{ textAlign: 'center' }}>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {Object.values(groupedSlabs).length === 0 ? (
                            <tr>
                                <td colSpan="6" style={{ textAlign: 'center', padding: '20px', color: '#64748b' }}>No tax slabs configured</td>
                            </tr>
                        ) : Object.values(groupedSlabs).map(group => (
                            <React.Fragment key={group.id}>
                                <tr style={{ backgroundColor: '#f8fafc', cursor: 'pointer' }} onClick={() => toggleComponent(group.id)}>
                                    <td colSpan="5" style={{ fontWeight: '600' }}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                            {expandedComponents[group.id] ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
                                            {group.name} 
                                            <span style={{ fontSize: '12px', color: '#64748b', fontWeight: 'normal', marginLeft: '8px' }}>
                                                (Effective: {group.effective_date ? formatDate(group.effective_date) : 'N/A'} {group.slabs[0]?.personal_relief > 0 ? `| Relief: ${Number(group.slabs[0]?.personal_relief).toLocaleString()}` : ''})
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style={{ display: 'flex', gap: '8px', justifyContent: 'center' }}>
                                            <button className="btn-icon" title="Edit Component Slabs" onClick={(e) => { e.stopPropagation(); handleEdit(group.slabs[0]); }}>
                                                <Edit2 size={16} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                {expandedComponents[group.id] && group.slabs.map(slab => {
                                    const minAmt = parseFloat(slab.min_amount);
                                    const maxAmt = slab.max_amount ? parseFloat(slab.max_amount) : null;
                                    const isFixed = slab.tax_type === 'FIXED';
                                    const maxTax = isFixed ? parseFloat(slab.fixed_amount) : (maxAmt ? (maxAmt - minAmt) * (parseFloat(slab.percentage) / 100) : null);

                                    return (
                                        <tr key={slab.id} style={{ backgroundColor: '#ffffff' }}>
                                            <td style={{ paddingLeft: '40px', color: '#64748b' }}>Slab bracket</td>
                                            <td>{minAmt.toLocaleString()}</td>
                                            <td>{maxAmt ? maxAmt.toLocaleString() : 'Infinity'}</td>
                                            <td>{isFixed ? Number(slab.fixed_amount).toLocaleString() : `${Number(slab.percentage)}%`}</td>
                                            <td>{maxTax !== null ? maxTax.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '-'}</td>
                                            <td></td>
                                        </tr>
                                    );
                                })}
                            </React.Fragment>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
