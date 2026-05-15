/* admin-dashboard.js */
(() => {
    'use strict';
    const { init, apiFetch, fmtDate, escHtml } = window.AdminCommon;

    async function loadDashboard() {
        const user = await init();
        if (!user) return;
        if (user.username) {
            const el = document.getElementById('dashboardUser');
            if (el) el.textContent = 'Hola, ' + user.username;
        }

        const res = await apiFetch('/admin/dashboard/stats');
        if (!res || !res.ok) return;

        const { data } = await res.json();
        const { stats, recent_audit } = data;

        function setCard(id, value, danger) {
            const card = document.getElementById(id);
            if (!card) return;
            const valEl = card.querySelector('.stat-value');
            if (valEl) {
                valEl.className = 'stat-value' + (danger && value > 0 ? ' ' : '');
                valEl.style.color = (danger && value > 0) ? 'var(--danger-main)' : '';
                valEl.textContent = Number(value).toLocaleString('es-MX');
            }
        }

        setCard('statUsers', stats.active_users);
        setCard('statSessions', stats.active_sessions);
        setCard('statOrders', stats.total_orders);
        setCard('statImports', stats.total_imports);
        setCard('statFailedImports', stats.failed_imports, true);
        setCard('statAudit', stats.total_audit);

        const tbody = document.querySelector('#recentAuditTable tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        if (!recent_audit || recent_audit.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><span class="empty-icon">📭</span><p>Sin registros recientes</p></div></td></tr>';
            return;
        }
        recent_audit.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escHtml(String(row.id))}</td>
                <td>${escHtml(row.username || '—')}</td>
                <td><code style="font-size:.8rem">${escHtml(row.event || '—')}</code></td>
                <td>${escHtml(row.resource || '—')}</td>
                <td>${escHtml(row.ip_address || '—')}</td>
                <td>${fmtDate(row.created_at)}</td>`;
            tbody.appendChild(tr);
        });
    }

    document.addEventListener('DOMContentLoaded', loadDashboard);
})();
