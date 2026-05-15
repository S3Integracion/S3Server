/* admin-tokens.js */
(() => {
    'use strict';
    const { init, apiFetch, buildTable, buildPaginator, toast, badge, escHtml, fmtDate } = window.AdminCommon;

    let currentPage = 1;

    async function loadTokens() {
        const params = new URLSearchParams({ page: currentPage, per_page: 20 });
        const userId     = document.getElementById('tokenUserId')?.value.trim();
        const activeOnly = document.getElementById('activeOnlyToggle')?.checked;
        if (userId)     params.set('user_id', userId);
        if (activeOnly) params.set('active_only', '1');

        const res = await apiFetch('/admin/tokens?' + params);
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('tokensTable'), [
            { key: 'id', label: 'ID', width: '50px' },
            { label: 'Usuario', html: r => escHtml(String(r.user_id || '—')) },
            { label: 'Dispositivo', html: r => escHtml(r.device_label || '—') },
            { label: 'Creado', render: r => fmtDate(r.created_at) },
            { label: 'Expira', render: r => fmtDate(r.expires_at) },
            { label: 'Último uso', render: r => r.last_used_at ? fmtDate(r.last_used_at) : '—' },
            { label: 'Estado', html: r => {
                if (r.status === 'revoked') return badge('Revocado', 'danger');
                if (r.status === 'expired') return badge('Expirado', 'warn');
                return badge('Activo', 'success');
            }},
            { label: 'Acciones', cls: 'actions', html: r =>
                r.status !== 'active'
                    ? '—'
                    : `<button class="btn btn-danger btn-xs" data-revoke="${r.id}">Revocar</button>`
            },
        ], data || []);

        document.querySelectorAll('[data-revoke]').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); revokeToken(parseInt(btn.dataset.revoke, 10)); });
        });

        buildPaginator(document.getElementById('tokensPagination'), meta, p => { currentPage = p; loadTokens(); });
    }

    async function revokeToken(tokenId) {
        if (!confirm('¿Revocar este token de API?')) return;
        const res = await apiFetch(`/admin/tokens/${tokenId}`, { method: 'DELETE' });
        if (!res) return;
        toast(res.ok ? 'Token revocado' : 'No se pudo revocar', res.ok ? 'success' : 'error');
        if (res.ok) loadTokens();
    }

    async function setup() {
        await init();
        await loadTokens();

        document.getElementById('btnSearch')?.addEventListener('click', () => { currentPage = 1; loadTokens(); });
        document.getElementById('tokenUserId')?.addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadTokens(); } });
        document.getElementById('activeOnlyToggle')?.addEventListener('change', () => { currentPage = 1; loadTokens(); });
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
