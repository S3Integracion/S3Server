/* admin-order-not-delivered.js */
(() => {
    'use strict';
    const { init, apiFetch, escHtml, fmtDate, buildPaginator } = window.AdminCommon;

    let currentPage = 1;

    async function loadRows() {
        const params = new URLSearchParams({ page: currentPage, per_page: 50 });

        const res = await apiFetch('/admin/orders/not-delivered?' + params);
        if (!res || !res.ok) return;
        const json = await res.json();

        const rows = json.data?.data || [];
        const pagination = json.data?.pagination || {};

        const totalEl = document.getElementById('notDeliveredTotal');
        if (totalEl) totalEl.textContent = `Total: ${pagination.total ?? rows.length}`;

        const tbody = document.querySelector('#notDeliveredTable tbody');
        tbody.innerHTML = '';

        if (rows.length === 0) {
            const tr = tbody.insertRow();
            tr.innerHTML = `<td colspan="5" style="text-align:center;color:var(--text-dim)">Sin órdenes en tránsito.</td>`;
        } else {
            rows.forEach(r => {
                const tr = tbody.insertRow();
                tr.innerHTML = `
                    <td>${escHtml(r.order_id || '—')}</td>
                    <td>${escHtml(r.tracking_id || '—')}</td>
                    <td>${fmtDate(r.extracted_at)}</td>
                    <td>${fmtDate(r.created_at)}</td>
                    <td>${fmtDate(r.updated_at)}</td>`;
            });
        }

        buildPaginator(
            document.getElementById('notDeliveredPagination'),
            {
                page:  pagination.current_page ?? currentPage,
                pages: pagination.last_page   ?? 1,
                total: pagination.total       ?? rows.length,
            },
            p => { currentPage = p; loadRows(); }
        );
    }

    async function setup() {
        await init();
        await loadRows();
        document.getElementById('btnRefreshNotDelivered')?.addEventListener('click', () => { currentPage = 1; loadRows(); });
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
