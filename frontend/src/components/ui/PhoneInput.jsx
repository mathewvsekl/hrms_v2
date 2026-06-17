import React, { useState, useEffect, useRef } from 'react';

const COUNTRIES = [
    { name: 'United Arab Emirates', code: 'ae', dial_code: '+971' },
    { name: 'India', code: 'in', dial_code: '+91' },
    { name: 'Qatar', code: 'qa', dial_code: '+974' },
    { name: 'Saudi Arabia', code: 'sa', dial_code: '+966' },
    { name: 'Oman', code: 'om', dial_code: '+968' },
    { name: 'Bahrain', code: 'bh', dial_code: '+973' },
    { name: 'Kuwait', code: 'kw', dial_code: '+965' },
    { name: 'United Kingdom', code: 'gb', dial_code: '+44' },
    { name: 'United States', code: 'us', dial_code: '+1' },
    { name: 'Canada', code: 'ca', dial_code: '+1' },
    { name: 'Singapore', code: 'sg', dial_code: '+65' },
    { name: 'Australia', code: 'au', dial_code: '+61' },
    { name: 'Kenya', code: 'ke', dial_code: '+254' },
    { name: 'Uganda', code: 'ug', dial_code: '+256' },
    { name: 'Tanzania', code: 'tz', dial_code: '+255' },
    { name: 'Rwanda', code: 'rw', dial_code: '+250' },
    { name: 'Nigeria', code: 'ng', dial_code: '+234' },
    { name: 'South Africa', code: 'za', dial_code: '+27' },
    { name: 'Germany', code: 'de', dial_code: '+49' },
    { name: 'France', code: 'fr', dial_code: '+33' }
];

const lookupCountryCode = (identifier) => {
    if (!identifier) return 'ae';
    const id = identifier.toLowerCase().trim();
    
    // Map ISO-3 to ISO-2
    const ISO3_TO_ISO2 = {
        'are': 'ae', 'ind': 'in', 'qat': 'qa', 'sau': 'sa', 'omn': 'om', 'bhr': 'bh', 'kwt': 'kw',
        'gbr': 'gb', 'usa': 'us', 'can': 'ca', 'sgp': 'sg', 'aus': 'au', 'ken': 'ke', 'uga': 'ug',
        'tza': 'tz', 'rwa': 'rw', 'nga': 'ng', 'zaf': 'za', 'deu': 'de', 'fra': 'fr'
    };

    if (ISO3_TO_ISO2[id]) return ISO3_TO_ISO2[id];

    const found = COUNTRIES.find(c => 
        c.code.toLowerCase() === id || 
        c.name.toLowerCase() === id || 
        c.dial_code.toLowerCase() === id
    );
    return found ? found.code : 'ae';
};

const PhoneInput = ({ value = '', onChange, defaultCountry = 'ae', placeholder = 'Phone number', disabled = false, style = {}, ...props }) => {
    const [selectedCountry, setSelectedCountry] = useState('ae');
    const [localNumber, setLocalNumber] = useState('');
    const [isFocused, setIsFocused] = useState(false);
    
    // Track internal vs external changes to prevent infinite loops
    const lastPassedValue = useRef(value);

    // Initial and updates syncing
    useEffect(() => {
        const defaultCode = lookupCountryCode(defaultCountry);
        
        if (!value) {
            setSelectedCountry(defaultCode);
            setLocalNumber('');
            return;
        }

        const trimmed = value.trim();
        if (trimmed.startsWith('+')) {
            // Find longest dial_code match
            const sortedCountries = [...COUNTRIES].sort((a, b) => b.dial_code.length - a.dial_code.length);
            const found = sortedCountries.find(c => trimmed.startsWith(c.dial_code));
            if (found) {
                setSelectedCountry(found.code);
                setLocalNumber(trimmed.slice(found.dial_code.length).trim());
                return;
            }
        }

        // Fallback: entire value as local number, default country
        setSelectedCountry(defaultCode);
        setLocalNumber(trimmed);
    }, [value, defaultCountry]);

    const activeCountry = COUNTRIES.find(c => c.code === selectedCountry) || COUNTRIES[0];

    const handleCountryChange = (e) => {
        const countryCode = e.target.value;
        setSelectedCountry(countryCode);
        const country = COUNTRIES.find(c => c.code === countryCode);
        if (country) {
            const combined = `${country.dial_code} ${localNumber.trim()}`.trim();
            lastPassedValue.current = combined;
            onChange(combined);
        }
    };

    const handleNumberChange = (e) => {
        const inputVal = e.target.value.replace(/[^\d\s\-()]/g, ''); // digits, spaces, dashes, parens only
        setLocalNumber(inputVal);
        const combined = `${activeCountry.dial_code} ${inputVal.trim()}`.trim();
        lastPassedValue.current = combined;
        onChange(combined);
    };

    return (
        <div 
            style={{
                display: 'flex',
                alignItems: 'center',
                width: '100%',
                position: 'relative',
                border: isFocused ? '1px solid var(--color-rose-gold)' : '1px solid var(--color-border)',
                borderRadius: 'var(--radius-md)',
                backgroundColor: disabled ? 'var(--color-bg-secondary)' : 'var(--color-white)',
                boxShadow: isFocused ? '0 0 0 3px rgba(181, 148, 114, 0.1)' : 'none',
                transition: 'border-color 0.2s, box-shadow 0.2s',
                height: '42px',
                boxSizing: 'border-box',
                overflow: 'hidden',
                ...style
            }} 
            className="phone-input-container"
        >
            {/* Country Selector Visual Area */}
            <div style={{
                display: 'flex',
                alignItems: 'center',
                paddingLeft: '10px',
                paddingRight: '8px',
                borderRight: '1px solid var(--color-border)',
                height: '100%',
                backgroundColor: 'rgba(0,0,0,0.015)',
                position: 'relative',
                flexShrink: 0
            }}>
                <img 
                    src={`https://flagcdn.com/w40/${selectedCountry}.png`} 
                    alt={selectedCountry}
                    style={{ width: '20px', height: 'auto', borderRadius: '2px', marginRight: '6px', pointerEvents: 'none' }}
                    onError={(e) => { e.target.style.display = 'none'; }}
                />
                <span style={{ fontSize: '13px', color: 'var(--color-charcoal)', fontWeight: 600, marginRight: '4px', pointerEvents: 'none' }}>
                    {activeCountry.dial_code}
                </span>
                <span style={{ fontSize: '10px', color: 'var(--color-text-muted)', pointerEvents: 'none' }}>▼</span>
                
                {/* Native hidden select on top of standard elements */}
                <select
                    value={selectedCountry}
                    onChange={handleCountryChange}
                    disabled={disabled}
                    style={{
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        width: '100%',
                        height: '100%',
                        opacity: 0,
                        cursor: disabled ? 'default' : 'pointer',
                        zIndex: 2
                    }}
                >
                    {COUNTRIES.map(c => (
                        <option key={c.code} value={c.code}>
                            {c.name} ({c.dial_code})
                        </option>
                    ))}
                </select>
            </div>

            {/* Local Phone Number Text Input */}
            <input
                type="text"
                value={localNumber}
                onChange={handleNumberChange}
                disabled={disabled}
                placeholder={placeholder}
                onFocus={() => setIsFocused(true)}
                onBlur={() => setIsFocused(false)}
                style={{
                    border: 'none',
                    outline: 'none',
                    padding: '8px 12px',
                    fontSize: '14px',
                    fontFamily: 'inherit',
                    color: 'var(--color-charcoal)',
                    flex: 1,
                    background: 'transparent',
                    height: '100%'
                }}
                {...props}
            />
        </div>
    );
};

export default PhoneInput;
