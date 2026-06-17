import { create } from 'zustand';
import api from '../services/api';

const useNotificationStore = create((set, get) => ({
    // --- UI Alert/Confirm/Prompt State ---
    alert: null, // { title, message, type, onConfirm }
    confirm: null, // { title, message, onConfirm, onCancel }
    prompt: null, // { title, message, defaultValue, onConfirm, onCancel }

    showAlert: (title, message, type = 'info', onConfirm = null) => {
        set({ alert: { title, message, type, onConfirm } });
    },

    showConfirm: (title, message, onConfirm, onCancel = null) => {
        set({ confirm: { title, message, onConfirm, onCancel } });
    },

    showPrompt: (title, message, onConfirm, defaultValue = '', onCancel = null) => {
        set({ prompt: { title, message, defaultValue, onConfirm, onCancel } });
    },

    closeAlert: () => set({ alert: null }),
    closeConfirm: () => set({ confirm: null }),
    closePrompt: () => set({ prompt: null }),

    // --- System Notifications State (Bell Icon) ---
    notifications: [],
    unreadCount: 0,
    isAdminView: false,
    loading: false,

    fetchNotifications: async () => {
        set({ loading: true });
        try {
            const res = await api.get('/notifications');
            const data = res.data?.data || res.data || {};
            set({ 
                notifications: data.notifications || [],
                unreadCount: data.unread_count || 0,
                isAdminView: !!data.is_admin_view
            });
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        } finally {
            set({ loading: false });
        }
    },

    markAsRead: async (id) => {
        try {
            await api.post('/notifications/mark-read', { id });
            // Local update for responsiveness
            const { notifications, unreadCount } = get();
            const updated = notifications.map(n => n.id === id ? { ...n, is_read: 1 } : n);
            set({ 
                notifications: updated,
                unreadCount: Math.max(0, unreadCount - 1)
            });
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    },

    markAllAsRead: async () => {
        try {
            await api.post('/notifications/mark-all-read');
            const { notifications } = get();
            const updated = notifications.map(n => ({ ...n, is_read: 1 }));
            set({ 
                notifications: updated, 
                unreadCount: 0 
            });
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    },

    clearNotification: async (id) => {
        try {
            await api.delete(`/notifications/${id}`);
            const { notifications, unreadCount } = get();
            const n = notifications.find(x => x.id === id);
            const wasUnread = n && !n.is_read;
            const updated = notifications.filter(n => n.id !== id);
            set({ 
                notifications: updated,
                unreadCount: wasUnread ? Math.max(0, unreadCount - 1) : unreadCount
            });
        } catch (error) {
            console.error('Failed to delete notification:', error);
        }
    }
}));

export default useNotificationStore;
