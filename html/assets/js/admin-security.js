/* admin-security.js */
(() => {
    'use strict';
    const { init, apiFetch, buildTable, buildPaginator, badge, escHtml, fmtDate } = window.AdminCommon;

    let currentPage = 1;

    async function loadStats() {
        const res = await apiFetch('/admin/security/stats');
        if (!res || !res.ok) return;
        const { data } = await res.json();

        const s = data.last_24h || {};
        setText('stat24Total',   s.total);
        setText('stat24Success', s.succeeded);
        setText('stat24Failed',  s.failed, s.failed > 0);
        setText('stat24Ips',     s.unique_ips);

        // Locked identifiers
        const locked = data.locked_identifiers || [];
        buildTable(document.getElementById('lockedTable'), [
            { key: 'identifier', label: 'Identifier' },
            { key: 'ip_address', label: 'IP' },
            { label: 'Intentos fallidos', html: r => `<span class="badge badge-danger">${escHtml(String(r.failures))}</span>` },
            { label: 'Último intento', render: r => r.last_attempt ? fmtDate(r.last_attempt) : '—' },
        ], locked);
    }

    function setText(id, val, danger) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = val ?? '—';
        if (danger) el.style.color = 'var(--danger-main)';
    }

    async function loadAttempts() {
        const params = new URLSearchParams({ page: currentPage, per_page: 25 });
        const ident  = document.getElementById('secIdentifier')?.value.trim();
        const ip     = document.getElementById('secIp')?.value.trim();
        const succ   = document.getElementById('secSuccessful')?.value;
        const from   = document.getElementById('secFrom')?.value;
        const to     = document.getElementById('secTo')?.value;
        if (ident) params.set('identifier', ident);
        if (ip)    params.set('ip_address', ip);
        if (succ !== '') params.set('successful', succ);
        if (from)  params.set('date_from', from);
        if (to)    params.set('date_to', to);

        const res = await apiFetch('/admin/security/login-attempts?' + params);
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('attemptsTable'), [
            { key: 'identifier', label: 'Identifier' },
            { key: 'ip_address', label: 'IP' },
            { label: 'Usuario', html: r => escHtml(r.username || (r.user_id ? `#${r.user_id}` : '—')) },
            { label: 'Resultado', html: r => r.successful == 1 ? badge('Exitoso', 'success') : badge('Fallido', 'danger') },
            { label: 'Razón', html: r => escHtml(r.reason || '—') },
            { label: 'Fecha/hora', render: r => r.attempted_at_human ? fmtDate(r.attempted_at_human) : '—' },
        ], data || []);

        buildPaginator(document.getElementById('attemptsPagination'), meta, p => { currentPage = p; loadAttempts(); });
    }

    async function setup() {
        await init();
        await Promise.all([loadStats(), loadAttempts()]);

        document.getElementById('btnSearch')?.addEventListener('click', () => { currentPage = 1; loadAttempts(); });
        ['secIdentifier', 'secIp'].forEach(id => {
            document.getElementById(id)?.addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadAttempts(); } });
        });
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
