/* admin-common.js — shared admin panel utilities */
(() => {
    'use strict';

    const API = '/api/v1';
    let _csrfToken = '';

    /* ── Core API fetch ──────────────────────────────────────────────────── */

    async function apiFetch(path, options = {}) {
        const method  = (options.method || 'GET').toUpperCase();
        const headers = { Accept: 'application/json', ...(options.headers || {}) };

        if (_csrfToken && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            headers['X-CSRF-Token'] = _csrfToken;
        }
        if (options.json !== undefined) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.json);
            delete options.json;
        }

        let res;
        try {
            res = await fetch(API + path, { credentials: 'include', ...options, headers });
        } catch (err) {
            throw new Error('Error de red: ' + err.message);
        }

        if (res.status === 401 || res.status === 403) {
            window.location.href = '/login';
            return null;
        }
        return res;
    }

    /* ── Init: verify session, mark active nav link ──────────────────────── */

    async function init() {
        const res = await apiFetch('/auth/me');
        if (!res) return null;

        if (!res.ok) {
            window.location.href = '/login';
            return null;
        }

        const payload = await res.json();
        const user    = payload.data || {};
        _csrfToken    = user.csrf_token || '';

        // Mark active nav link
        const current = window.location.pathname.replace(/\/$/, '') || '/admin';
        document.querySelectorAll('.admin-nav-list a').forEach(a => {
            const href = a.getAttribute('href').replace(/\/$/, '');
            if (href === current) a.classList.add('active');
        });

        // Wire logout button
        const logoutBtn = document.getElementById('adminLogout');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async () => {
                await apiFetch('/auth/logout', { method: 'POST' });
                window.location.href = '/login';
            });
        }

        return user;
    }

    /* ── Table builder ───────────────────────────────────────────────────── */

    function buildTable(tableEl, columns, rows) {
        tableEl.innerHTML = '';

        const thead = tableEl.createTHead();
        const hrow  = thead.insertRow();
        columns.forEach(col => {
            const th = document.createElement('th');
            th.textContent = col.label;
            if (col.width) th.style.width = col.width;
            hrow.appendChild(th);
        });

        const tbody = tableEl.createTBody();
        if (!rows || rows.length === 0) {
            const tr = tbody.insertRow();
            const td = tr.insertCell();
            td.colSpan = columns.length;
            td.innerHTML = '<div class="empty-state"><span class="empty-icon">📭</span><p>Sin resultados</p></div>';
            return;
        }

        rows.forEach(row => {
            const tr = tbody.insertRow();
            columns.forEach(col => {
                const td = tr.insertCell();
                if (col.html) {
                    td.innerHTML = col.html(row);
                } else if (col.render) {
                    td.textContent = col.render(row) ?? '';
                } else {
                    td.textContent = row[col.key] ?? '';
                }
                if (col.cls) td.className = col.cls;
            });
            if (typeof row._onClick === 'function') {
                tr.style.cursor = 'pointer';
                tr.addEventListener('click', row._onClick);
            }
        });
    }

    /* ── Pagination builder ──────────────────────────────────────────────── */

    function buildPaginator(containerEl, meta, onPageChange) {
        containerEl.innerHTML = '';
        if (!meta || meta.pages <= 1) return;

        const info = document.createElement('span');
        info.className   = 'page-info';
        info.textContent = `Página ${meta.page} de ${meta.pages}  (${meta.total} total)`;

        const prev = document.createElement('button');
        prev.className   = 'btn btn-ghost btn-sm';
        prev.textContent = '← Anterior';
        prev.disabled    = meta.page <= 1;
        prev.addEventListener('click', () => onPageChange(meta.page - 1));

        const next = document.createElement('button');
        next.className   = 'btn btn-ghost btn-sm';
        next.textContent = 'Siguiente →';
        next.disabled    = meta.page >= meta.pages;
        next.addEventListener('click', () => onPageChange(meta.page + 1));

        containerEl.appendChild(prev);
        containerEl.appendChild(info);
        containerEl.appendChild(next);
    }

    /* ── Modal helpers ───────────────────────────────────────────────────── */

    function openModal(dialogEl) {
        if (dialogEl && typeof dialogEl.showModal === 'function') {
            dialogEl.showModal();
        }
    }

    function closeModal(dialogEl) {
        if (dialogEl && typeof dialogEl.close === 'function') {
            dialogEl.close();
        }
    }

    /* ── Notification toast ──────────────────────────────────────────────── */

    function toast(message, type = 'success') {
        const el = document.createElement('div');
        el.className = `status-message ${type === 'error' ? 'status-error' : 'status-success'}`;
        el.textContent = message;
        el.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;max-width:340px;animation:fadeIn 0.3s ease';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    /* ── Badge helper ────────────────────────────────────────────────────── */

    function badge(text, type) {
        return `<span class="badge badge-${type}">${escHtml(String(text))}</span>`;
    }

    /* ── Escape HTML ─────────────────────────────────────────────────────── */

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ── Format datetime ─────────────────────────────────────────────────── */

    function fmtDate(val) {
        if (!val) return '—';
        const d = new Date(val);
        if (isNaN(d)) return val;
        return d.toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
    }

    /* ── Parse JSON safely ───────────────────────────────────────────────── */

    function parseJson(str) {
        try { return JSON.parse(str); } catch { return str; }
    }

    /* ── Accordion toggle ────────────────────────────────────────────────── */

    function wireAccordions(containerEl) {
        containerEl.querySelectorAll('.accordion-trigger').forEach(btn => {
            btn.addEventListener('click', () => {
                const body     = btn.nextElementSibling;
                const expanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', String(!expanded));
                body.classList.toggle('open', !expanded);
            });
        });
    }

    /* ── Expose public API ───────────────────────────────────────────────── */

    window.AdminCommon = {
        init,
        apiFetch,
        buildTable,
        buildPaginator,
        openModal,
        closeModal,
        toast,
        badge,
        escHtml,
        fmtDate,
        parseJson,
        wireAccordions,
        getCsrf: () => _csrfToken,
    };
})();
