import React, { useState, useEffect } from 'react';
import { Calendar } from 'lucide-react';

/**
 * DateInput Component
 * Allows manual entry in DD/MM/YYYY format while managing state in YYYY-MM-DD.
 * Includes a native date picker fallback via a hidden input overlaying the icon.
 */
const DateInput = ({ value, onChange, placeholder = 'DD/MM/YYYY', className = '', style = {}, min = null, max = null, ...props }) => {
    const [displayText, setDisplayText] = useState('');
    const dateInputRef = React.useRef(null);

    // Sync external value (YYYY-MM-DD) to internal display (DD/MM/YYYY)
    useEffect(() => {
        if (value && value.includes('-')) {
            const [y, m, d] = value.split('-');
            if (y && m && d) {
                setDisplayText(`${d}/${m}/${y}`);
                return;
            }
        }
        // Only clear display if external value is explicitly empty
        if (value === '' || value === null) {
            setDisplayText('');
        }
    }, [value]);

    const handleTextChange = (e) => {
        const input = e.target.value;
        const numbers = input.replace(/\D/g, ''); // numbers only
        
        let formatted = numbers;
        if (numbers.length > 2) {
            formatted = numbers.slice(0, 2) + '/' + numbers.slice(2);
        }
        if (numbers.length > 4) {
            formatted = formatted.slice(0, 5) + '/' + formatted.slice(5, 9);
        }

        setDisplayText(formatted);

        // If complete (DD/MM/YYYY = 8 digits), trigger onChange with YYYY-MM-DD
        if (numbers.length === 8) {
            const d = numbers.slice(0, 2);
            const m = numbers.slice(2, 4);
            const y = numbers.slice(4, 8);
            const isoValue = `${y}-${m}-${d}`;
            
            // Basic validation
            const dateObj = new Date(isoValue);
            if (!isNaN(dateObj.getTime()) && y.length === 4) {
                // Check against min/max if provided
                if (max && isoValue > max) return;
                if (min && isoValue < min) return;
                
                onChange(isoValue);
                // Attempt to open the calendar picker if supported to show the user the result
                if (dateInputRef.current && dateInputRef.current.showPicker) {
                    try {
                        dateInputRef.current.showPicker();
                    } catch (err) {
                        console.warn('showPicker failed:', err);
                    }
                }
            }
        } else {
            // If incomplete, send empty to keep consistency with calendar icon
            if (value !== '') {
                onChange('');
            }
        }
    };

    return (
        <div style={{ position: 'relative', display: 'flex', alignItems: 'center', width: '100%', ...style }}>
            <input
                type="text"
                value={displayText}
                onChange={handleTextChange}
                placeholder={placeholder}
                className={`form-input ${className}`}
                style={{ paddingRight: '40px', width: '100%', ...style }}
                maxLength={10}
                {...props}
            />
            {/* Native date picker fallback - sits invisibly over the icon area */}
            <div style={{
                position: 'absolute',
                right: '8px',
                width: '24px',
                height: '24px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: 'pointer'
            }}>
                <Calendar size={18} style={{ color: 'var(--text-secondary)', pointerEvents: 'none' }} />
                <input 
                    type="date"
                    ref={dateInputRef}
                    value={value || ''}
                    disabled={props.readOnly}
                    min={min}
                    max={max}
                    onChange={(e) => onChange(e.target.value)}
                    title={props.readOnly ? "Read Only" : "Open Calendar"}
                    style={{
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        width: '100%',
                        height: '100%',
                        opacity: 0,
                        cursor: props.readOnly ? 'default' : 'pointer'
                    }}
                />
            </div>
        </div>
    );
};

export default DateInput;
