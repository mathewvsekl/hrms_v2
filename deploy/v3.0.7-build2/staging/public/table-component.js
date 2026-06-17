/* ============================================================
   HRMS V2 — Reusable Table & Tab Components (Atlas Engine)
   Auto-generates sortable data tables, tab systems, and modals.
   ============================================================ */

/**
 * DataTable — Renders a sortable, paginated, searchable table inside a container.
 * Usage:
 *   const table = new DataTable('#container', {
 *     columns: [ { key: 'name', label: 'Name', sortable: true }, ... ],
 *     data: [ { name: 'John', ... }, ... ],
 *     pageSize: 10,
 *     onRowClick: (row) => { ... },
 *     renderCell: { status: (val) => `<span class="badge badge-green">${val}</span>` }
 *   });
 */
class DataTable {
    constructor(containerSelector, options = {}) {
        this.container = document.querySelector(containerSelector);
        this.columns = options.columns || [];
        this.allData = options.data || [];
        this.filteredData = [...this.allData];
        this.pageSize = options.pageSize || 10;
        this.currentPage = 1;
        this.sortKey = null;
        this.sortAsc = true;
        this.onRowClick = options.onRowClick || null;
        this.renderCell = options.renderCell || {};
        this.searchKeys = options.searchKeys || this.columns.map(c => c.key);
        this.render();
    }

    setData(data) {
        this.allData = data;
        this.filteredData = [...data];
        this.currentPage = 1;
        this.render();
    }

    search(query) {
        const q = query.toLowerCase().trim();
        if (!q) {
            this.filteredData = [...this.allData];
        } else {
            this.filteredData = this.allData.filter(row =>
                this.searchKeys.some(key => {
                    const val = row[key];
                    return val && String(val).toLowerCase().includes(q);
                })
            );
        }
        this.currentPage = 1;
        this.render();
    }

    sort(key) {
        if (this.sortKey === key) {
            this.sortAsc = !this.sortAsc;
        } else {
            this.sortKey = key;
            this.sortAsc = true;
        }
        this.filteredData.sort((a, b) => {
            const aVal = a[key] ?? '';
            const bVal = b[key] ?? '';
            if (typeof aVal === 'number' && typeof bVal === 'number') {
                return this.sortAsc ? aVal - bVal : bVal - aVal;
            }
            return this.sortAsc
                ? String(aVal).localeCompare(String(bVal))
                : String(bVal).localeCompare(String(aVal));
        });
        this.render();
    }

    get pagedData() {
        const start = (this.currentPage - 1) * this.pageSize;
        return this.filteredData.slice(start, start + this.pageSize);
    }

    get totalPages() {
        return Math.max(1, Math.ceil(this.filteredData.length / this.pageSize));
    }

    render() {
        const html = `
            <table class="data-table">
                <thead><tr>${this.renderHead()}</tr></thead>
                <tbody>${this.renderBody()}</tbody>
            </table>
            ${this.renderPagination()}
        `;
        this.container.innerHTML = html;
        this.bindEvents();
    }

    renderHead() {
        return this.columns.map(col => {
            const sorted = this.sortKey === col.key;
            const arrow = sorted ? (this.sortAsc ? '↑' : '↓') : '↕';
            const cls = col.sortable ? `sortable${sorted ? ' sorted' : ''}` : '';
            return `<th class="${cls}" data-sort="${col.sortable ? col.key : ''}">${col.label}${col.sortable ? ` <span class="sort-icon">${arrow}</span>` : ''}</th>`;
        }).join('');
    }

    renderBody() {
        if (this.pagedData.length === 0) {
            return `<tr><td colspan="${this.columns.length}" style="text-align:center; padding:32px; color:var(--text-muted);">No records found</td></tr>`;
        }
        return this.pagedData.map((row, idx) => {
            const cells = this.columns.map(col => {
                const val = row[col.key] ?? '';
                const rendered = this.renderCell[col.key] ? this.renderCell[col.key](val, row) : this.escapeHtml(String(val));
                const cls = col.primary ? ' class="cell-primary"' : '';
                return `<td${cls}>${rendered}</td>`;
            }).join('');
            const clickCls = this.onRowClick ? ' class="clickable"' : '';
            return `<tr${clickCls} data-idx="${idx}">${cells}</tr>`;
        }).join('');
    }

    renderPagination() {
        if (this.filteredData.length <= this.pageSize) return '';
        const start = (this.currentPage - 1) * this.pageSize + 1;
        const end = Math.min(this.currentPage * this.pageSize, this.filteredData.length);
        let buttons = '';
        buttons += `<button class="pagination-btn" data-page="prev" ${this.currentPage === 1 ? 'disabled' : ''}><i class="ri-arrow-left-s-line"></i></button>`;
        for (let i = 1; i <= this.totalPages; i++) {
            if (this.totalPages > 7 && i > 3 && i < this.totalPages - 2 && Math.abs(i - this.currentPage) > 1) {
                if (i === 4 || i === this.totalPages - 3) buttons += `<span style="padding:0 4px;color:var(--text-muted)">…</span>`;
                continue;
            }
            buttons += `<button class="pagination-btn${i === this.currentPage ? ' active' : ''}" data-page="${i}">${i}</button>`;
        }
        buttons += `<button class="pagination-btn" data-page="next" ${this.currentPage === this.totalPages ? 'disabled' : ''}><i class="ri-arrow-right-s-line"></i></button>`;
        return `<div class="pagination"><span class="pagination-info">Showing ${start}–${end} of ${this.filteredData.length}</span><div class="pagination-controls">${buttons}</div></div>`;
    }

    bindEvents() {
        // Sort
        this.container.querySelectorAll('th[data-sort]').forEach(th => {
            const key = th.dataset.sort;
            if (key) th.addEventListener('click', () => this.sort(key));
        });
        // Row click
        if (this.onRowClick) {
            this.container.querySelectorAll('tr[data-idx]').forEach(tr => {
                tr.addEventListener('click', () => {
                    const idx = parseInt(tr.dataset.idx);
                    this.onRowClick(this.pagedData[idx]);
                });
            });
        }
        // Pagination
        this.container.querySelectorAll('.pagination-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const p = btn.dataset.page;
                if (p === 'prev') this.currentPage = Math.max(1, this.currentPage - 1);
                else if (p === 'next') this.currentPage = Math.min(this.totalPages, this.currentPage + 1);
                else this.currentPage = parseInt(p);
                this.render();
            });
        });
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

/**
 * TabSystem — Activates a tab-based navigation system.
 * Usage: new TabSystem('#tab-container');
 * Expects .tab-item[data-tab] and .tab-panel[data-tab] in the container.
 */
class TabSystem {
    constructor(containerSelector) {
        this.container = document.querySelector(containerSelector);
        if (!this.container) return;
        this.tabs = this.container.querySelectorAll('.tab-item');
        this.panels = this.container.querySelectorAll('.tab-panel');
        this.tabs.forEach(tab => {
            tab.addEventListener('click', () => this.activate(tab.dataset.tab));
        });
    }

    activate(tabId) {
        this.tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === tabId));
        this.panels.forEach(p => p.classList.toggle('active', p.dataset.tab === tabId));
    }
}

/**
 * ModalManager — Open/close modals.
 * Usage:
 *   ModalManager.open('my-modal');
 *   ModalManager.close('my-modal');
 */
const ModalManager = {
    open(id) {
        const overlay = document.getElementById(id);
        if (overlay) overlay.classList.add('active');
    },
    close(id) {
        const overlay = document.getElementById(id);
        if (overlay) overlay.classList.remove('active');
    },
    init() {
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => {
                const overlay = btn.closest('.modal-overlay');
                if (overlay) overlay.classList.remove('active');
            });
        });
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.remove('active');
            });
        });
    }
};

/**
 * StatusBadge — Returns HTML for a styled status badge.
 */
function statusBadge(status) {
    const map = {
        'active': 'badge-green', 'present': 'badge-green', 'approved': 'badge-green',
        'completed': 'badge-green', 'paid': 'badge-green',
        'inactive': 'badge-gray', 'absent': 'badge-red', 'rejected': 'badge-red',
        'cancelled': 'badge-gray', 'offboarding': 'badge-orange',
        'pending': 'badge-orange', 'draft': 'badge-gray', 'processing': 'badge-blue',
        'half_day': 'badge-orange', 'late': 'badge-warning',
        'on_leave': 'badge-purple', 'onboarding': 'badge-blue',
        'full_time': 'badge-green', 'part_time': 'badge-blue', 'contractor': 'badge-purple',
        'public_holiday': 'badge-info', 'weekend': 'badge-gray',
    };
    const cls = map[status] || 'badge-gray';
    const label = status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    return `<span class="badge ${cls}">${label}</span>`;
}

/**
 * API helper for fetch calls
 */
const API = {
    baseUrl: '',
    
    showLoader() {
        let container = document.querySelector('.main-content, .content-wrapper, .page-content') || document.body;
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
        this.activeRequests = (this.activeRequests || 0) + 1;
    },

    hideLoader() {
        this.activeRequests = Math.max(0, (this.activeRequests || 0) - 1);
        if (this.activeRequests === 0) {
            const loader = document.getElementById('globalLoader');
            if (loader) loader.classList.remove('active');
        }
    },

    async get(endpoint) {
        try {
            const token = localStorage.getItem('api_token');
            const res = await fetch(this.baseUrl + endpoint, {
                headers: { 'Authorization': token ? `Bearer ${token}` : '' }
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return await res.json();
        } catch (e) {
            console.error('API GET error:', e);
            return { error: true, message: e.message };
        }
    },

    async post(endpoint, data) {
        this.showLoader();
        try {
            const token = localStorage.getItem('api_token');
            const res = await fetch(this.baseUrl + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': token ? `Bearer ${token}` : ''
                },
                body: JSON.stringify(data)
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return await res.json();
        } catch (e) {
            console.error('API POST error:', e);
            return { error: true, message: e.message };
        } finally {
            this.hideLoader();
        }
    },

    async put(endpoint, data) {
        this.showLoader();
        try {
            const token = localStorage.getItem('api_token');
            const res = await fetch(this.baseUrl + endpoint, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': token ? `Bearer ${token}` : ''
                },
                body: JSON.stringify(data)
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return await res.json();
        } catch (e) {
            console.error('API PUT error:', e);
            return { error: true, message: e.message };
        } finally {
            this.hideLoader();
        }
    },

    async delete(endpoint) {
        this.showLoader();
        try {
            const token = localStorage.getItem('api_token');
            const res = await fetch(this.baseUrl + endpoint, {
                method: 'DELETE',
                headers: { 'Authorization': token ? `Bearer ${token}` : '' }
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return await res.json();
        } catch (e) {
            console.error('API DELETE error:', e);
            return { error: true, message: e.message };
        } finally {
            this.hideLoader();
        }
    }
};

// Auto-init modals on DOM ready
document.addEventListener('DOMContentLoaded', () => ModalManager.init());
