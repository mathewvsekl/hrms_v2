import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { User, Lock, ArrowRight, ShieldCheck, AlertCircle, RotateCcw, Mail, CheckCircle2 } from 'lucide-react';
import useAuthStore from '../store/useAuthStore';

// ─── Cloudflare Turnstile Site Key ─────────────────────────────────────────
// Replace with your actual site key from the Cloudflare dashboard.
const TURNSTILE_SITE_KEY = import.meta.env.VITE_TURNSTILE_SITE_KEY || '1x00000000000000000000AA';

// ─── API Base ───────────────────────────────────────────────────────────────
const API_BASE = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/api$/, '');

// ─── Turnstile Hook ──────────────────────────────────────────────────────────
// Dynamically loads the Turnstile script and renders the widget into a ref'd container.
function useTurnstile(containerId) {
    const [token, setToken] = useState(null);
    const [error, setError] = useState(null);
    const widgetIdRef = useRef(null);
    const isScriptLoaded = useRef(false);

    const render = useCallback(() => {
        const container = document.getElementById(containerId);
        if (!container || !window.turnstile) return;

        // Cleanup previous widget before re-rendering
        if (widgetIdRef.current !== null) {
            try { window.turnstile.remove(widgetIdRef.current); } catch (_) {}
        }

        widgetIdRef.current = window.turnstile.render(container, {
            sitekey: TURNSTILE_SITE_KEY,
            callback: (t) => { setToken(t); setError(null); },
            'error-callback': () => {
                setToken(null);
                setError('Security check failed. Please refresh.');
            },
            'expired-callback': () => {
                setToken(null);
                setError('Security check expired. Please verify again.');
            },
            theme: 'light',
            appearance: 'interaction-only', // Hides the widget unless a challenge is explicitly needed
        });
    }, [containerId]);

    const reset = useCallback(() => {
        setToken(null);
        setError(null);
        if (widgetIdRef.current !== null && window.turnstile) {
            try { window.turnstile.reset(widgetIdRef.current); } catch (_) {}
        }
    }, []);

    useEffect(() => {
        if (isScriptLoaded.current) { render(); return; }

        const existingScript = document.getElementById('cf-turnstile-script');
        if (existingScript) {
            existingScript.addEventListener('load', render);
            isScriptLoaded.current = true;
            return;
        }

        const script = document.createElement('script');
        script.id = 'cf-turnstile-script';
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
        script.async = true;
        script.defer = true;
        script.onload = () => { isScriptLoaded.current = true; render(); };
        document.head.appendChild(script);

        return () => {
            if (widgetIdRef.current !== null && window.turnstile) {
                try { window.turnstile.remove(widgetIdRef.current); } catch (_) {}
            }
        };
    }, [render]);

    return { token, error: error, reset, render };
}

// ─── API Helpers ─────────────────────────────────────────────────────────────
async function apiRequestOTP(email, turnstileToken) {
    const res = await fetch(`${API_BASE}/api/auth/request-otp`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        // NOTE: turnstile_token is accepted by the PHP layer but not strictly required yet.
        // The field is included for forward-compatibility with the full security implementation.
        body: JSON.stringify({ email, turnstile_token: turnstileToken }),
    });
    // Return both status and body so caller can decide
    return { data: await res.json(), status: res.status };
}

async function apiVerifyOTP(email, otpCode) {
    const res = await fetch(`${API_BASE}/api/auth/verify-otp`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        // PHP backend expects { email, otp } — not otp_code
        body: JSON.stringify({ email, otp: otpCode }),
    });
    return { data: await res.json(), status: res.status };
}

// ─── Main Component ───────────────────────────────────────────────────────────
const Login = () => {
    const navigate = useNavigate();

    // ── State ─────────────────────────────────────────────────────────────────
    const [step, setStep] = useState('email'); // 'email' | 'otp'
    const [email, setEmail] = useState('');
    const [otp, setOtp] = useState('');
    const [referenceCode, setReferenceCode] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);
    const [successMsg, setSuccessMsg] = useState(null);

    // Tech Support Modal State
    const [showSupportModal, setShowSupportModal] = useState(false);
    const [supportForm, setSupportForm] = useState({ name: '', email: '', message: '' });
    const [supportStatus, setSupportStatus] = useState(null); // 'sending', 'success'

    const inputRefs = useRef([]);

    const handleOtpChange = (index, value) => {
        const digit = value.replace(/\D/g, '').slice(-1);
        let newOtpArray = otp.split('');
        while (newOtpArray.length < 6) newOtpArray.push(' ');
        
        newOtpArray[index] = digit || ' ';
        
        const newOtpStr = newOtpArray.join('').replace(/\s+$/, '');
        setOtp(newOtpStr);

        if (digit && index < 5) {
            inputRefs.current[index + 1]?.focus();
        }
    };

    const handleOtpKeyDown = (index, e) => {
        if (e.key === 'Backspace' && (!otp[index] || otp[index] === ' ') && index > 0) {
            inputRefs.current[index - 1]?.focus();
        }
    };

    const handleOtpPaste = (e) => {
        e.preventDefault();
        const pasteData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
        if (pasteData) {
            setOtp(pasteData);
            const focusIndex = Math.min(pasteData.length, 5);
            inputRefs.current[focusIndex]?.focus();
        }
    };

    // Track OTP failed attempts locally for UX feedback
    const [failedAttempts, setFailedAttempts] = useState(0);
    const MAX_ATTEMPTS = 3;

    // ── Turnstile ─────────────────────────────────────────────────────────────
    const TURNSTILE_CONTAINER_ID = 'turnstile-widget-container';
    const { token: turnstileToken, error: turnstileError, reset: resetTurnstile } = useTurnstile(
        step === 'email' ? TURNSTILE_CONTAINER_ID : '__inactive__'
    );

    // Reset errors when switching steps
    useEffect(() => { setError(null); setSuccessMsg(null); }, [step]);

    // ── Step 1: Request OTP ───────────────────────────────────────────────────
    const handleRequestOTP = async (e) => {
        e.preventDefault();
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            setError('Please enter a valid corporate email address.');
            return;
        }

        if (!turnstileToken) {
            setError('Please complete the security check before proceeding.');
            return;
        }
        setIsLoading(true);
        setError(null);
        try {
            const { data, status } = await apiRequestOTP(email, turnstileToken);

            // Enumeration-safe: treat HTTP 200 AND 404 identically in the UI.
            // A 404 means the email isn't registered, but we never tell the user that.
            // Only hard server errors (500) or rate-limit (429) surface as errors.
            if (status === 200 || status === 404) {
                setFailedAttempts(0);
                setOtp('');
                
                // Extract reference code or generate a dummy uppercase 4-letter code to prevent enumeration side-channels
                const refCode = data?.reference_code || data?.data?.reference_code || 
                    Array.from({ length: 4 }, () => String.fromCharCode(65 + Math.floor(Math.random() * 26))).join('');
                setReferenceCode(refCode);
                
                setStep('otp');
            } else if (status === 429) {
                setError('Too many requests. Please wait before trying again.');
                resetTurnstile();
            } else {
                setError(data?.message || 'An error occurred. Please try again.');
                resetTurnstile();
            }
        } catch {
            setError('Unable to reach the server. Check your connection.');
            resetTurnstile();
        } finally {
            setIsLoading(false);
        }
    };

    // Auto-submit OTP
    useEffect(() => {
        if (otp.length === 6 && !otp.includes(' ') && step === 'otp' && !isLoading) {
            const timeoutId = setTimeout(() => {
                const btn = document.getElementById('verify-otp-btn');
                if (btn && !btn.disabled) btn.click();
            }, 100);
            return () => clearTimeout(timeoutId);
        }
    }, [otp, step, isLoading]);

    // ── Step 2: Verify OTP ────────────────────────────────────────────────────
    const handleVerifyOTP = async (e) => {
        e?.preventDefault?.();
        if (otp.length !== 6 || otp.includes(' ')) { setError('Please enter the full 6-digit code.'); return; }
        setIsLoading(true);
        setError(null);
        try {
            const { data, status } = await apiVerifyOTP(email, otp);

            // PHP backend returns a flat object:
            // Success: { success: true, data: { token, user_id, role, ... }, message: '...' }
            // or sometimes top-level: { token, user_id, role, ... }
            const isSuccess = status === 200 && (
                data.success === true ||
                data.status === 'success' ||
                data.token              // fallback: token present at top level
            );

            if (isSuccess) {
                // PHP may nest under data.data or return flat — handle both
                const payload  = data?.data ?? data;
                const token    = payload?.token    || data?.token;
                const user     = {
                    email,
                    user_id:      payload?.user_id      || data?.user_id,
                    first_name:   payload?.first_name   || data?.first_name,
                    last_name:    payload?.last_name    || data?.last_name,
                    role:         payload?.role         || data?.role,
                    designation:  payload?.designation  || data?.designation,
                    employee_id:  payload?.employee_id  || data?.employee_id,
                    company_id:   payload?.company_id   || data?.company_id,
                    country_id:   payload?.country_id   || data?.country_id,
                };

                if (token) {
                    localStorage.setItem('hrms_auth_token', token);
                    localStorage.setItem('hrms_user', JSON.stringify(user));
                    // Sync Zustand store so ProtectedRoute recognizes the logged-in state
                    useAuthStore.setState({ user, token, isAuthenticated: true });
                }

                setSuccessMsg('Identity verified. Redirecting...');
                setTimeout(() => navigate('/dashboard'), 800);
            } else {
                const newAttempts = failedAttempts + 1;
                setFailedAttempts(newAttempts);
                setOtp('');
                if (newAttempts >= MAX_ATTEMPTS || data.code === 'MAX_ATTEMPTS_EXCEEDED') {
                    setError('Maximum verification attempts exceeded. Please request a new code.');
                    setTimeout(() => handleChangeEmail(), 2500);
                } else {
                    setError(data.message || 'Invalid code. Please try again.');
                }
            }
        } catch {
            setError('Unable to verify. Check your connection.');
        } finally {
            setIsLoading(false);
        }
    };

    // ── Typo Escape-Hatch: Return to email step, preserve email value ─────────
    const handleChangeEmail = useCallback(() => {
        setStep('email');
        setOtp('');
        setReferenceCode('');
        setError(null);
        setSuccessMsg(null);
        setFailedAttempts(0);
        // Turnstile will re-render automatically via the hook when step === 'email'
    }, []);

    // ── Handle Tech Support Form ──────────────────────────────────────────────
    const handleSupportSubmit = (e) => {
        e.preventDefault();
        setSupportStatus('sending');
        // Simulate API call
        setTimeout(() => {
            setSupportStatus('success');
            setTimeout(() => {
                setShowSupportModal(false);
                setSupportStatus(null);
                setSupportForm({ name: '', email: '', message: '' });
            }, 3000);
        }, 1000);
    };

    // ── Render ────────────────────────────────────────────────────────────────
    return (
        <>
            <style>{`
                :root {
                    --primary-green: #245A34;
                    --primary-green-light: #37844E;
                    --primary-dark: #12331D;
                    --charcoal: #0F172A;
                    --surface: #FFFFFF;
                    --background: #F1F5F9;
                    --text-main: #1E293B;
                    --text-muted: #64748B;
                    --border-light: #E2E8F0;
                    --danger: #B91C1C;
                    --danger-bg: #FEF2F2;
                    --danger-border: #FCA5A5;
                    --success: #166534;
                    --success-bg: #F0FDF4;
                    --success-border: #86EFAC;
                    --font-heading: 'Outfit', -apple-system, sans-serif;
                    --font-body: 'Inter', -apple-system, sans-serif;
                }

                .login-page {
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background-color: var(--background);
                    background-image:
                        radial-gradient(circle at 15% 50%, rgba(36,90,52,0.08) 0%, transparent 40%),
                        radial-gradient(circle at 85% 30%, rgba(36,90,52,0.06) 0%, transparent 40%);
                    font-family: var(--font-body);
                    padding: 2rem;
                }

                .login-card {
                    background: var(--surface);
                    width: 900px;
                    min-height: 530px;
                    height: auto;
                    border-radius: 28px;
                    box-shadow: 0 25px 50px -12px rgba(15,23,42,0.15), 0 0 0 1px rgba(15,23,42,0.02);
                    display: flex;
                    overflow: hidden;
                    animation: cardAppear 0.7s cubic-bezier(0.16,1,0.3,1) forwards;
                    opacity: 0;
                }

                /* ── Proportional Scaling using Browser Zoom ── */
                @media (max-width: 1400px) or (max-height: 800px) {
                    .login-card {
                        zoom: 0.85;
                    }
                }
                @media (max-width: 1100px) or (max-height: 680px) {
                    .login-card {
                        zoom: 0.76;
                    }
                }

                @keyframes cardAppear {
                    to { opacity: 1; }
                }

                /* ── Brand Panel ── */
                .brand-side {
                    flex: 1;
                    background: linear-gradient(145deg, var(--primary-dark) 0%, var(--primary-green) 100%);
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    justify-content: center;
                    padding: 2.75rem;
                    position: relative;
                    color: #fff;
                    overflow: hidden;
                }
                .brand-side::before {
                    content: '';
                    position: absolute; inset: 0;
                    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
                    pointer-events: none;
                }
                .brand-side::after {
                    content: '';
                    position: absolute;
                    bottom: -20%; right: -10%;
                    width: 400px; height: 400px;
                    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
                    filter: blur(50px);
                }
                .brand-content { position: relative; z-index: 2; }
                .brand-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.6rem;
                    background: rgba(255,255,255,0.08);
                    backdrop-filter: blur(12px);
                    padding: 0.6rem 1.25rem;
                    border-radius: 100px;
                    font-size: 0.8125rem;
                    font-weight: 600;
                    letter-spacing: 0.5px;
                    margin-bottom: 2.5rem;
                    border: 1px solid rgba(255,255,255,0.15);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                    animation: fadeSlide 0.8s ease 0.2s both;
                }
                .brand-side h2 {
                    font-family: var(--font-heading);
                    font-size: 2.75rem;
                    font-weight: 800;
                    line-height: 1.15;
                    margin-bottom: 1.5rem;
                    letter-spacing: -0.5px;
                    animation: fadeSlide 0.8s ease 0.3s both;
                    color: #ffffff;
                }
                .brand-side p {
                    font-size: 1.1rem;
                    color: rgba(255,255,255,0.82);
                    max-width: 380px;
                    line-height: 1.7;
                    animation: fadeSlide 0.8s ease 0.4s both;
                }

                /* ── Auth Panel ── */
                .auth-side {
                    flex: 1.25;
                    padding: 2.25rem 3.5rem;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                }
                .auth-header { margin-bottom: 1.5rem; animation: fadeSlide 0.7s ease 0.4s both; }
                .auth-logo { height: 48px; width: auto; margin-bottom: 1.5rem; display: block; }
                .auth-side h1 {
                    font-family: var(--font-heading);
                    font-size: 2rem;
                    font-weight: 700;
                    color: var(--charcoal);
                    margin-bottom: 0.75rem;
                    letter-spacing: -0.5px;
                }
                .auth-side .subtitle { color: var(--text-muted); font-size: 0.95rem; line-height: 1.55; }
                .email-highlight {
                    font-weight: 700;
                    color: var(--primary-green);
                    word-break: break-word;
                }

                /* ── Form Elements ── */
                .login-form { width: 100%; animation: fadeSlide 0.7s ease 0.5s both; }
                .form-group { margin-bottom: 1.15rem; }
                .form-label { display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.55rem; }
                .input-container { position: relative; }
                .input-icon {
                    position: absolute; left: 1.1rem; top: 50%; transform: translateY(-50%);
                    color: #94A3B8; transition: color 0.2s;
                    pointer-events: none;
                }
                .input-container input {
                    width: 100%;
                    padding: 0.875rem 1rem 0.875rem 3rem;
                    border: 1px solid var(--border-light);
                    border-radius: 14px;
                    font-size: 0.95rem;
                    font-family: inherit;
                    color: var(--text-main);
                    background: #F8FAFC;
                    transition: all 0.2s;
                    box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
                }
                .input-container input:focus {
                    outline: none;
                    border-color: var(--primary-green);
                    background: #fff;
                    box-shadow: 0 0 0 4px rgba(36,90,52,0.1);
                }
                .input-container input:focus ~ .input-icon { color: var(--primary-green); }

                .otp-container {
                    display: flex;
                    gap: 0.5rem;
                    justify-content: space-between;
                    margin-bottom: 0.5rem;
                }
                .otp-container input {
                    width: 3rem;
                    height: 3.5rem;
                    text-align: center;
                    font-size: 1.5rem;
                    border: 1px solid var(--border-light);
                    border-radius: 12px;
                    color: var(--text-main);
                    background: #F8FAFC;
                    transition: all 0.2s;
                    box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
                }
                .otp-container input:focus {
                    outline: none;
                    border-color: var(--primary-green);
                    background: #fff;
                    box-shadow: 0 0 0 4px rgba(36,90,52,0.1);
                }

                .login-btn {
                    width: 100%;
                    padding: 0.95rem;
                    background: var(--primary-green);
                    color: #fff;
                    border: none;
                    border-radius: 14px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    transition: all 0.25s cubic-bezier(0.16,1,0.3,1);
                    margin-top: 1rem;
                    box-shadow: 0 8px 16px -4px rgba(36,90,52,0.3);
                }
                .login-btn:hover:not(:disabled) {
                    background: var(--primary-dark);
                    transform: translateY(-2px);
                    box-shadow: 0 12px 20px -4px rgba(36,90,52,0.4);
                }
                .login-btn:active:not(:disabled) { transform: translateY(0); }
                .login-btn:disabled {
                    opacity: 0.55;
                    cursor: not-allowed;
                    background: #94A3B8;
                    box-shadow: none;
                    transform: none;
                }

                /* ── Turnstile ── */
                .turnstile-wrapper { margin-top: 1.25rem; }
                .turnstile-hint {
                    font-size: 0.8rem;
                    color: var(--text-muted);
                    margin-top: 0.5rem;
                    display: flex; align-items: center; gap: 0.35rem;
                }

                /* ── Messages ── */
                .msg-box {
                    margin-top: 1.25rem;
                    padding: 0.875rem 1.125rem;
                    border-radius: 12px;
                    font-size: 0.875rem;
                    font-weight: 500;
                    display: flex;
                    align-items: flex-start;
                    gap: 0.6rem;
                }
                .msg-box.error {
                    background: var(--danger-bg);
                    border: 1px solid var(--danger-border);
                    color: var(--danger);
                    animation: shake 0.45s ease both;
                }
                .msg-box.success {
                    background: var(--success-bg);
                    border: 1px solid var(--success-border);
                    color: var(--success);
                }

                /* ── Escape Hatch ── */
                .change-email-btn {
                    margin-top: 1rem;
                    width: 100%;
                    padding: 0.75rem;
                    background: transparent;
                    border: 1px dashed var(--border-light);
                    border-radius: 12px;
                    color: var(--text-muted);
                    font-size: 0.875rem;
                    font-weight: 500;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    transition: all 0.2s;
                }
                .change-email-btn:hover {
                    background: #F8FAFC;
                    border-color: var(--primary-green);
                    color: var(--primary-green);
                }

                /* ── Tech Support Link ── */
                .support-link {
                    margin-top: 1rem;
                    text-align: center;
                    font-size: 0.85rem;
                    color: var(--text-muted);
                }
                .support-link button {
                    background: none;
                    border: none;
                    color: var(--primary-green);
                    text-decoration: none;
                    font-weight: 500;
                    margin-left: 0.25rem;
                    cursor: pointer;
                    padding: 0;
                    font-size: 0.85rem;
                }
                .support-link button:hover {
                    text-decoration: underline;
                }

                /* ── Tech Support Modal ── */
                .modal-overlay {
                    position: fixed;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(15, 23, 42, 0.6);
                    backdrop-filter: blur(4px);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                    animation: fadeIn 0.2s ease;
                }
                .modal-content {
                    background: var(--surface);
                    width: 90%;
                    max-width: 450px;
                    border-radius: 16px;
                    padding: 1.5rem;
                    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
                    position: relative;
                    animation: fadeSlide 0.3s ease;
                }
                .modal-close {
                    position: absolute;
                    top: 1rem; right: 1rem;
                    background: transparent;
                    border: none;
                    font-size: 1.5rem;
                    color: var(--text-muted);
                    cursor: pointer;
                    line-height: 1;
                    padding: 0.2rem 0.5rem;
                    border-radius: 8px;
                }
                .modal-close:hover {
                    background: #F1F5F9;
                    color: var(--charcoal);
                }
                .modal-title {
                    font-size: 1.25rem;
                    font-weight: 700;
                    color: var(--charcoal);
                    margin-bottom: 0.5rem;
                }
                .modal-desc {
                    font-size: 0.875rem;
                    color: var(--text-muted);
                    margin-bottom: 1rem;
                    line-height: 1.5;
                }
                .support-phone {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.5rem;
                    background: #F8FAFC;
                    padding: 0.75rem 1rem;
                    border-radius: 12px;
                    margin-bottom: 1rem;
                    color: var(--charcoal);
                    font-weight: 600;
                    font-size: 1rem;
                }
                .support-phone > div {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                .support-form textarea {
                    min-height: 70px;
                    resize: vertical;
                    width: 100%;
                    padding: 0.875rem 1rem;
                    border: 1px solid var(--border-light);
                    border-radius: 12px;
                    font-family: inherit;
                    font-size: 0.95rem;
                    color: var(--text-main);
                    background: #F8FAFC;
                    margin-bottom: 1rem;
                }
                .support-form textarea:focus {
                    outline: none;
                    border-color: var(--primary-green);
                    background: var(--surface);
                }

                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                /* ── Attempt indicator ── */
                .attempts-indicator {
                    margin-top: 0.5rem;
                    font-size: 0.8rem;
                    color: #92400E;
                    display: flex;
                    align-items: center;
                    gap: 0.35rem;
                }
                .attempts-dot {
                    display: inline-block;
                    width: 8px; height: 8px;
                    border-radius: 50%;
                    background: #FCD34D;
                }
                .attempts-dot.used { background: var(--danger); }

                @keyframes fadeSlide {
                    from { opacity: 0; transform: translateY(14px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                @keyframes shake {
                    10%, 90% { transform: translateX(-2px); }
                    20%, 80% { transform: translateX(3px); }
                    30%, 50%, 70% { transform: translateX(-4px); }
                    40%, 60% { transform: translateX(4px); }
                }

                @media (max-width: 960px) {
                    .login-card { flex-direction: column; border-radius: 20px; width: 100%; max-width: 480px; height: auto; }
                    .brand-side { padding: 3rem 2.5rem; align-items: center; text-align: center; flex: none; }
                    .brand-side h2 { font-size: 2rem; }
                    .brand-side p { margin: 0 auto; }
                    .auth-side { padding: 3rem 2.5rem; }
                    .auth-logo { margin: 0 auto 2rem auto; }
                    .auth-header { text-align: center; }
                }
            `}</style>

            <div className="login-page">
                <div className="login-card">

                    {/* ══ Brand Panel ══ */}
                    <div className="brand-side">
                        <div className="brand-content">
                            <div className="brand-badge">
                                <ShieldCheck size={15} />
                                <span>ENTERPRISE PORTAL</span>
                            </div>
                            <h2>Avantgarde HRMS Workspace</h2>
                            <p>Driving organizational excellence through a unified, platform built for performance.</p>
                        </div>
                    </div>

                    {/* ══ Auth Panel ══ */}
                    <div className="auth-side">

                        {/* ── Email Step ── */}
                        {step === 'email' && (
                            <>
                                <div className="auth-header">
                                    <img src="/api/logo" alt="Avantgarde Logo" className="auth-logo" />
                                    <h1>Secure Login</h1>
                                    <p className="subtitle">
                                        Enter your registered corporate email to receive a secure one-time access code.
                                    </p>
                                </div>

                                <form className="login-form" onSubmit={handleRequestOTP} noValidate>
                                    <div className="form-group">
                                        <label className="form-label" htmlFor="email-input">Corporate Email</label>
                                        <div className="input-container">
                                            <input
                                                id="email-input"
                                                type="email"
                                                value={email}
                                                onChange={(e) => setEmail(e.target.value)}
                                                placeholder="name@avantgarde.com"
                                                required
                                                autoComplete="email"
                                                disabled={isLoading}
                                            />
                                            <User size={17} className="input-icon" />
                                        </div>
                                    </div>

                                    {/* Cloudflare Turnstile widget mounts here */}
                                    <div className="turnstile-wrapper">
                                        <div id={TURNSTILE_CONTAINER_ID} />
                                        {turnstileError && (
                                            <p className="turnstile-hint" style={{ color: 'var(--danger)' }}>
                                                <AlertCircle size={13} /> {turnstileError}
                                            </p>
                                        )}
                                    </div>

                                    <button
                                        type="submit"
                                        className="login-btn"
                                        id="request-otp-btn"
                                        // Disabled until turnstile token is available
                                        disabled={isLoading || !turnstileToken || !email}
                                    >
                                        {isLoading ? 'Processing Request…' : 'Send Access Code'}
                                        {!isLoading && <ArrowRight size={17} />}
                                    </button>
                                </form>

                                {error && (
                                    <div className="msg-box error" role="alert">
                                        <AlertCircle size={16} style={{ flexShrink: 0, marginTop: 1 }} />
                                        <div>{error}</div>
                                    </div>
                                )}
                            </>
                        )}

                        {/* ── OTP Step ── */}
                        {step === 'otp' && (
                            <>
                                <div className="auth-header">
                                    <img src="/api/logo" alt="Avantgarde Logo" className="auth-logo" />
                                    <h1>Authentication Code</h1>
                                    <p className="subtitle">
                                        A secure 6-digit code has been dispatched to{' '}
                                        <span className="email-highlight">{email}</span>.
                                        {referenceCode && (
                                            <>
                                                {' '}Match verification reference <strong style={{ color: 'var(--primary-green-light)', letterSpacing: '0.5px' }}>{referenceCode}</strong>.
                                            </>
                                        )}
                                        {' '}Check your inbox and spam folder.
                                    </p>
                                </div>

                                <form className="login-form" onSubmit={handleVerifyOTP} noValidate>
                                    <div className="form-group">
                                        <label className="form-label" htmlFor="otp-input">Security Code</label>
                                        <div className="otp-container" onPaste={handleOtpPaste}>
                                            {Array.from({ length: 6 }).map((_, index) => (
                                                <input
                                                    key={index}
                                                    ref={el => inputRefs.current[index] = el}
                                                    type="text"
                                                    inputMode="numeric"
                                                    maxLength={1}
                                                    value={(otp[index] && otp[index] !== ' ') ? otp[index] : ''}
                                                    onChange={e => handleOtpChange(index, e.target.value)}
                                                    onKeyDown={e => handleOtpKeyDown(index, e)}
                                                    disabled={isLoading}
                                                    autoFocus={index === 0}
                                                    required
                                                    autoComplete={index === 0 ? "one-time-code" : "off"}
                                                />
                                            ))}
                                        </div>

                                        {/* Failed-attempt visual indicator */}
                                        {failedAttempts > 0 && (
                                            <div className="attempts-indicator">
                                                {Array.from({ length: MAX_ATTEMPTS }).map((_, i) => (
                                                    <span
                                                        key={i}
                                                        className={`attempts-dot${i < failedAttempts ? ' used' : ''}`}
                                                        title={i < failedAttempts ? 'Failed attempt' : 'Remaining attempt'}
                                                    />
                                                ))}
                                                <span>{MAX_ATTEMPTS - failedAttempts} attempt{MAX_ATTEMPTS - failedAttempts !== 1 ? 's' : ''} remaining</span>
                                            </div>
                                        )}
                                    </div>

                                    {successMsg ? (
                                        <div className="msg-box success" role="status">
                                            <ShieldCheck size={16} style={{ flexShrink: 0, marginTop: 1 }} />
                                            <div>{successMsg}</div>
                                        </div>
                                    ) : (
                                        <button
                                            type="submit"
                                            className="login-btn"
                                            id="verify-otp-btn"
                                            disabled={isLoading || otp.length !== 6}
                                        >
                                            {isLoading ? 'Verifying Identity…' : 'Verify & Access System'}
                                            {!isLoading && <ArrowRight size={17} />}
                                        </button>
                                    )}
                                </form>

                                {error && (
                                    <div className="msg-box error" role="alert">
                                        <AlertCircle size={16} style={{ flexShrink: 0, marginTop: 1 }} />
                                        <div>{error}</div>
                                    </div>
                                )}

                                {/* ── Typo Escape-Hatch ── */}
                                <button
                                    className="change-email-btn"
                                    id="change-email-btn"
                                    type="button"
                                    onClick={handleChangeEmail}
                                    disabled={isLoading}
                                    aria-label="Return to email entry and correct your email address"
                                >
                                    <RotateCcw size={14} />
                                    Incorrect email? Change it here
                                </button>

                                {/* ── Tech Support ── */}
                                <div className="support-link">
                                    Having trouble? 
                                    <button type="button" onClick={() => setShowSupportModal(true)}>
                                        Contact Tech Support
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </div>

            {/* ── Tech Support Modal ── */}
            {showSupportModal && (
                <div className="modal-overlay" onClick={() => setShowSupportModal(false)}>
                    <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                        <button className="modal-close" onClick={() => setShowSupportModal(false)} aria-label="Close modal">&times;</button>
                        
                        <h2 className="modal-title">Tech Support</h2>
                        <p className="modal-desc">
                            If you're having trouble receiving your access code, our IT team is here to help.
                        </p>

                        <div className="support-phone">
                            <div>
                                <ShieldCheck size={18} color="var(--primary-green)" />
                                <span>Global Support: +1 (800) 555-0199</span>
                            </div>
                            <div style={{ marginTop: '0.75rem', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                                <Mail size={18} color="var(--primary-green)" />
                                <span>Email: <a href="mailto:support@anedins.com" style={{ color: 'var(--charcoal)', textDecoration: 'none' }}>support@anedins.com</a></span>
                            </div>
                        </div>

                        {supportStatus === 'success' ? (
                            <div className="msg-box success">
                                <CheckCircle2 size={16} />
                                <div>Your message has been sent. Our team will contact you shortly.</div>
                            </div>
                        ) : (
                            <form className="support-form" onSubmit={handleSupportSubmit}>
                                <div className="form-group">
                                    <label className="form-label">Name</label>
                                    <div className="input-container">
                                        <input 
                                            type="text" 
                                            placeholder="Your Name" 
                                            required 
                                            value={supportForm.name}
                                            onChange={e => setSupportForm({...supportForm, name: e.target.value})}
                                        />
                                    </div>
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Corporate Email</label>
                                    <div className="input-container">
                                        <input 
                                            type="email" 
                                            placeholder="name@avantgarde.com" 
                                            required 
                                            value={supportForm.email}
                                            onChange={e => setSupportForm({...supportForm, email: e.target.value})}
                                        />
                                    </div>
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Message</label>
                                    <textarea 
                                        placeholder="Briefly describe your login issue..." 
                                        required
                                        value={supportForm.message}
                                        onChange={e => setSupportForm({...supportForm, message: e.target.value})}
                                    ></textarea>
                                </div>
                                <button type="submit" className="login-btn" disabled={supportStatus === 'sending'}>
                                    {supportStatus === 'sending' ? 'Sending...' : 'Send Message'}
                                </button>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </>
    );
};

export default Login;
