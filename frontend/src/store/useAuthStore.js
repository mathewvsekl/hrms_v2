import { create } from 'zustand';
import api from '../services/api';

const useAuthStore = create((set, get) => ({
    user: JSON.parse(localStorage.getItem('hrms_user')) || null,
    isAuthenticated: !!localStorage.getItem('hrms_auth_token'),
    token: localStorage.getItem('hrms_auth_token') || null,
    isLoading: false,
    error: null,

    syncUser: async () => {
        try {
            const response = await api.get('/auth/me');
            if (response.data && response.data.data) {
                const user = { ...response.data.data, email: response.data.data.username };
                localStorage.setItem('hrms_user', JSON.stringify(user));
                set({ user });
            }
        } catch (err) {
            console.error("Failed to sync user data:", err);
        }
    },

    hasPermission: (module, action) => {
        const user = get().user;
        if (!user) return false;
        
        let perms = user.permissions || [];
        if (!Array.isArray(perms)) {
            perms = Object.values(perms);
        }

        // Super Admins, Admins, Country Managers get '*' which means all access
        if (perms.includes('*')) return true;

        return perms.includes(`${module.toLowerCase()}:${action.toLowerCase()}`);
    },

    hasModuleAccess: (module) => {
        const user = get().user;
        if (!user) return false;
        
        let perms = user.permissions || [];
        if (!Array.isArray(perms)) {
            perms = Object.values(perms);
        }

        if (perms.includes('*')) return true;

        return perms.some(p => typeof p === 'string' && p.startsWith(`${module.toLowerCase()}:`));
    },

    login: async (email, password) => {
        set({ isLoading: true, error: null });
        try {
            // PHP backend expects POST /api/login with username & password
            const response = await api.post('/login', { username: email, password });

            const resData = response.data;
            const token = resData?.data?.token || resData?.token;
            const userResponse = resData?.data || resData;
            const user = { ...userResponse, email: userResponse.email || email };

            if (token && user) {
                localStorage.setItem('hrms_auth_token', token);
                localStorage.setItem('hrms_user', JSON.stringify(user));

                set({ user, token, isAuthenticated: true, isLoading: false });
                return { success: true };
            } else {
                set({ error: "Invalid response format", isLoading: false });
                return { success: false };
            }
        } catch (err) {
            const errorMsg = err.response?.data?.message || err.message || 'Login failed';
            set({ error: errorMsg, isLoading: false });
            return { success: false, error: errorMsg };
        }
    },

    requestOTP: async (email) => {
        set({ isLoading: true, error: null });
        try {
            await api.post('/auth/request-otp', { email });
            set({ isLoading: false });
            return { success: true };
        } catch (err) {
            const errorMsg = err.response?.data?.message || err.message || 'Failed to send OTP';
            set({ error: errorMsg, isLoading: false });
            return { success: false, error: errorMsg };
        }
    },

    verifyOTP: async (email, otp) => {
        set({ isLoading: true, error: null });
        try {
            const response = await api.post('/auth/verify-otp', { email, otp });
            const resData = response.data;
            const token = resData?.data?.token || resData?.token;
            const userResponse = resData?.data || resData;
            const user = { ...userResponse, email: userResponse.email || email };

            if (token && user) {
                localStorage.setItem('hrms_auth_token', token);
                localStorage.setItem('hrms_user', JSON.stringify(user));

                set({ user, token, isAuthenticated: true, isLoading: false });
                return { success: true };
            } else {
                set({ error: "Invalid response format", isLoading: false });
                return { success: false };
            }
        } catch (err) {
            const errorMsg = err.response?.data?.message || err.message || 'Verification failed';
            set({ error: errorMsg, isLoading: false });
            return { success: false, error: errorMsg };
        }
    },

    logout: async () => {
        try {
            await api.post('/logout');
        } catch (err) {
            console.error("Logout API failed", err);
        } finally {
            localStorage.removeItem('hrms_auth_token');
            localStorage.removeItem('hrms_user');
            set({ user: null, token: null, isAuthenticated: false });
            window.location.href = '/login';
        }
    }
}));

export default useAuthStore;
