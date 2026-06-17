import axios from 'axios';

// The base URL for the PHP API. Depending on environment, we might want to change this.
const API_URL = import.meta.env.VITE_API_BASE_URL || '/api';

const api = axios.create({
    baseURL: API_URL,
    headers: {
        'Content-Type': 'application/json',
    },
    withCredentials: true
});

let activeRequests = 0;
let loaderTimeout;

function showLoader() {
    let container = document.querySelector('.main-content') || document.body;
    let loader = document.getElementById('globalLoader');
    
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'globalLoader';
        loader.className = 'global-loader-overlay';
        loader.innerHTML = `
            <div class="loader-content">
                <div class="loader-spinner"></div>
                <div class="loader-text">SYNCING DATA...</div>
            </div>
        `;
        container.appendChild(loader);
    }
    
    loader.classList.add('active');
    activeRequests++;
}

function hideLoader() {
    activeRequests = Math.max(0, activeRequests - 1);
    if (activeRequests === 0) {
        const loader = document.getElementById('globalLoader');
        if (loader) loader.classList.remove('active');
    }
}

// Request Interceptor: Add Auth Token if applicable
api.interceptors.request.use(
    (config) => {
        const url = config.url || '';
        // Exclude background polling requests from resetting the session timeout
        if (!url.includes('/notifications')) {
            window.dispatchEvent(new Event('api_activity'));
        }
        
        const method = (config.method || 'get').toLowerCase();
        if (method !== 'get') {
            showLoader();
        }
        const token = localStorage.getItem('hrms_auth_token');
        if (token) {
            config.headers['Authorization'] = `Bearer ${token}`;
        }
        
        // Context-Aware Security: Inject current view mode to inform backend of intent
        const viewMode = localStorage.getItem('adminViewMode') || 'admin';
        config.headers['X-View-Mode'] = viewMode;

        return config;
    },
    (error) => {
        hideLoader();
        return Promise.reject(error);
    }
);

// Helper for CDN rewriting
function replaceAssetPaths(obj, assetUrl) {
    if (obj === null || obj === undefined) return obj;
    if (typeof obj === 'string') {
        if (obj.startsWith('/public/')) {
            return assetUrl + obj.substring(7); // replace /public with assetUrl (which has no trailing slash)
        }
        return obj;
    }
    if (Array.isArray(obj)) {
        for (let i = 0; i < obj.length; i++) {
            obj[i] = replaceAssetPaths(obj[i], assetUrl);
        }
    } else if (typeof obj === 'object') {
        for (let key in obj) {
            if (Object.prototype.hasOwnProperty.call(obj, key)) {
                obj[key] = replaceAssetPaths(obj[key], assetUrl);
            }
        }
    }
    return obj;
}

// Response Interceptor: Handle CDN rewriting and 401 Unauthorized globally
api.interceptors.response.use(
    (response) => {
        hideLoader();
        const assetUrl = import.meta.env.VITE_ASSET_BASE_URL;
        if (assetUrl && response.data) {
            const cleanAssetUrl = assetUrl.replace(/\/$/, '');
            response.data = replaceAssetPaths(response.data, cleanAssetUrl);
        }
        return response;
    },
    (error) => {
        hideLoader();
        if (error.response && error.response.status === 401) {
            // Clear local auth State and redirect to login
            localStorage.removeItem('hrms_auth_token');
            localStorage.removeItem('hrms_user');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default api;
