import { useState, useRef, useEffect } from 'react';
import { ChevronDown, Search, X } from 'lucide-react';

export default function SearchableSelect({ options, value, onChange, placeholder = "Select...", disabled = false, isMulti = false }) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const wrapperRef = useRef(null);

    // Find the currently selected options
    const selectedValues = isMulti ? (Array.isArray(value) ? value : (value ? String(value).split(', ').filter(Boolean) : [])) : [];
    const selectedOption = !isMulti ? options.find(opt => opt.value === value) : null;
    const selectedOptionsList = isMulti ? options.filter(opt => selectedValues.includes(opt.value)) : [];

    useEffect(() => {
        function handleClickOutside(event) {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        }
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const filteredOptions = options.filter(opt => 
        opt.label.toLowerCase().includes(searchTerm.toLowerCase())
    );

    const toggleMultiValue = (optValue, e) => {
        if (e) e.stopPropagation();
        let newVals;
        if (selectedValues.includes(optValue)) {
            newVals = selectedValues.filter(v => v !== optValue);
        } else {
            newVals = [...selectedValues, optValue];
        }
        onChange(newVals);
    };

    return (
        <div ref={wrapperRef} style={{ position: 'relative', width: '100%' }}>
            <div 
                className={`form-input ${disabled ? 'disabled' : ''}`}
                style={{ 
                    display: 'flex', 
                    justifyContent: 'space-between', 
                    alignItems: 'center', 
                    cursor: disabled ? 'not-allowed' : 'pointer',
                    backgroundColor: disabled ? '#f3f4f6' : 'white',
                    color: (!isMulti && selectedOption) || (isMulti && selectedOptionsList.length > 0) ? 'inherit' : '#9ca3af',
                    userSelect: 'none',
                    minHeight: '42px',
                    height: 'auto',
                    padding: '4px 12px'
                }}
                onClick={() => !disabled && setIsOpen(!isOpen)}
            >
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px', overflow: 'hidden', alignItems: 'center' }}>
                    {isMulti ? (
                        selectedOptionsList.length > 0 ? (
                            selectedOptionsList.map(opt => (
                                <span key={opt.value} style={{ display: 'flex', alignItems: 'center', gap: '4px', backgroundColor: '#f3f4f6', padding: '2px 8px', borderRadius: '4px', fontSize: '13px', color: '#374151' }}>
                                    {opt.label}
                                    {!disabled && (
                                        <X size={12} style={{ cursor: 'pointer' }} onClick={(e) => toggleMultiValue(opt.value, e)} />
                                    )}
                                </span>
                            ))
                        ) : (
                            <span>{placeholder}</span>
                        )
                    ) : (
                        <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {selectedOption ? selectedOption.label : placeholder}
                        </span>
                    )}
                </div>
                <ChevronDown size={16} color="#6b7280" style={{ transform: isOpen ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s', flexShrink: 0 }} />
            </div>

            {isOpen && (
                <div style={{
                    position: 'absolute',
                    top: '100%',
                    left: 0,
                    right: 0,
                    marginTop: '4px',
                    backgroundColor: 'white',
                    border: '1px solid var(--color-border)',
                    borderRadius: '8px',
                    boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
                    zIndex: 50,
                    maxHeight: '250px',
                    display: 'flex',
                    flexDirection: 'column'
                }}>
                    <div style={{ padding: '8px', borderBottom: '1px solid var(--color-border)', display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <Search size={14} color="#9ca3af" />
                        <input
                            type="text"
                            placeholder="Search..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            onClick={(e) => e.stopPropagation()}
                            autoFocus
                            style={{
                                border: 'none',
                                outline: 'none',
                                width: '100%',
                                fontSize: '13px'
                            }}
                        />
                    </div>
                    <div style={{ overflowY: 'auto', flex: 1, padding: '4px' }}>
                        {filteredOptions.length > 0 ? (
                            filteredOptions.map((opt) => {
                                const isSelected = isMulti ? selectedValues.includes(opt.value) : opt.value === value;
                                return (
                                    <div
                                        key={opt.value}
                                        style={{
                                            padding: '8px 12px',
                                            cursor: 'pointer',
                                            borderRadius: '4px',
                                            backgroundColor: isSelected ? 'var(--color-rose-gold)' : 'transparent',
                                            color: isSelected ? 'var(--color-primary)' : 'inherit',
                                            fontSize: '13px',
                                            fontWeight: isSelected ? '600' : '400',
                                            display: 'flex',
                                            justifyContent: 'space-between',
                                            alignItems: 'center'
                                        }}
                                        onMouseEnter={(e) => {
                                            if (!isSelected) e.currentTarget.style.backgroundColor = '#f9fafb';
                                        }}
                                        onMouseLeave={(e) => {
                                            if (!isSelected) e.currentTarget.style.backgroundColor = 'transparent';
                                        }}
                                        onClick={() => {
                                            if (isMulti) {
                                                toggleMultiValue(opt.value);
                                            } else {
                                                onChange(opt.value);
                                                setIsOpen(false);
                                                setSearchTerm('');
                                            }
                                        }}
                                    >
                                        {opt.label}
                                        {isMulti && isSelected && <X size={14} />}
                                    </div>
                                );
                            })
                        ) : (
                            <div style={{ padding: '8px 12px', color: '#9ca3af', fontSize: '13px', textAlign: 'center' }}>
                                No results found
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
