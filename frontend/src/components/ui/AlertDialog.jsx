import React, { useState, useEffect } from 'react';
import { CheckCircle2, AlertCircle, AlertTriangle, Info, HelpCircle } from 'lucide-react';
import useNotificationStore from '../../store/useNotificationStore';

const AlertDialog = () => {
    const { alert, confirm, prompt, closeAlert, closeConfirm, closePrompt } = useNotificationStore();
    const [promptValue, setPromptValue] = useState('');

    useEffect(() => {
        if (prompt) setPromptValue(prompt.defaultValue || '');
    }, [prompt]);

    if (!alert && !confirm && !prompt) return null;

    const active = alert || confirm || prompt;
    const isConfirm = !!confirm;
    const isPrompt = !!prompt;

    const getIcon = () => {
        if (isPrompt) return <HelpCircle size={32} />;
        const type = active.type || 'info';
        switch (type) {
            case 'success': return <CheckCircle2 size={32} />;
            case 'error': return <AlertCircle size={32} />;
            case 'warning': return <AlertTriangle size={32} />;
            default: return <Info size={32} />;
        }
    };

    const handleConfirm = () => {
        if (isPrompt) {
            if (active.onConfirm) active.onConfirm(promptValue);
            closePrompt();
        } else {
            if (active.onConfirm) active.onConfirm();
            isConfirm ? closeConfirm() : closeAlert();
        }
    };

    const handleCancel = () => {
        if (isPrompt) {
            if (prompt.onCancel) prompt.onCancel();
            closePrompt();
        } else {
            if (confirm?.onCancel) confirm.onCancel();
            closeConfirm();
        }
    };

    return (
        <div className="alert-overlay" onClick={(isConfirm || isPrompt) ? undefined : handleConfirm}>
            <div className="alert-dialog" onClick={e => e.stopPropagation()}>
                <div className={`alert-icon ${isPrompt ? 'info' : (active.type || 'info')}`}>
                    {getIcon()}
                </div>
                <h3 className="alert-title">{active.title}</h3>
                <p className="alert-message">{active.message}</p>
                
                {isPrompt && (
                    <div style={{ marginBottom: '24px' }}>
                        <input 
                            type="text" 
                            className="form-input" 
                            value={promptValue} 
                            onChange={e => setPromptValue(e.target.value)}
                            autoFocus
                            onKeyDown={e => e.key === 'Enter' && handleConfirm()}
                        />
                    </div>
                )}

                <div className="alert-actions">
                    {(isConfirm || isPrompt) && (
                        <button className="btn btn-secondary" onClick={handleCancel}>
                            Cancel
                        </button>
                    )}
                    <button className="btn btn-primary" onClick={handleConfirm}>
                        {isConfirm || isPrompt ? 'Confirm' : 'OK'}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default AlertDialog;
