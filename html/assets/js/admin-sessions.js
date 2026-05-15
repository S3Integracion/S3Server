/* admin-sessions.js */
(() => {
    'use strict';
    const { init, apiFetch, buildTable, buildPaginator, toast, badge, escHtml, fmtDate } = window.AdminCommon;

    let currentPage = 1;

    async function loadSessions() {
        const search     = document.getElementById('sessionSearch')?.value.trim() || '';
        const activeOnly = document.getElementById('activeOnlyToggle')?.checked ? '1' : '0';
        const res = await apiFetch(
            `/admin/sessions?page=${currentPage}&per_page=20&search=${encodeURIComponent(search)}&active_only=${activeOnly}`
        );
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('sessionsTable'), [
            { label: 'Usuario', html: r => escHtml(r.username || `ID:${r.user_id}`) },
            { key: 'ip_address', label: 'IP' },
            { label: 'Última actividad', render: r => r.last_activity_human || fmtDate(r.last_activity) },
            { label: 'Creada', render: r => fmtDate(r.created_at) },
            { label: 'Estado', html: r => {
                if (r.revoked_at) return badge('Revocada', 'danger');
                return r.is_active ? badge('Activa', 'success') : badge('Expirada', 'warn');
            }},
            { label: 'Acciones', cls: 'actions', html: r =>
                r.revoked_at ? '—' :
                `<button class="btn btn-danger btn-xs" data-revoke="${escHtml(r.id)}">Revocar</button>`
            },
        ], data || []);

        document.querySelectorAll('[data-revoke]').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); revokeSession(btn.dataset.revoke); });
        });

        buildPaginator(document.getElementById('sessionsPagination'), meta, p => { currentPage = p; loadSessions(); });
    }

    async function revokeSession(sessionId) {
        if (!confirm('¿Revocar esta sesión?')) return;
        const res = await apiFetch(`/admin/sessions/${encodeURIComponent(sessionId)}`, { method: 'DELETE' });
        if (!res) return;
        toast(res.ok ? 'Sesión revocada' : 'No se pudo revocar', res.ok ? 'success' : 'error');
        loadSessions();
    }

    async function revokeAll() {
        if (!confirm('¿Revocar TODAS las sesiones activas? Los usuarios serán desconectados.')) return;
        const res = await apiFetch('/admin/sessions', { method: 'DELETE' });
        if (!res) return;
        if (res.ok) {
            const { data } = await res.json();
            toast(`${data.revoked_count} sesiones revocadas`);
        } else {
            toast('Error al revocar', 'error');
        }
        loadSessions();
    }

    async function setup() {
        await init();
        await loadSessions();

        document.getElementById('btnSearch')?.addEventListener('click', () => { currentPage = 1; loadSessions(); });
        document.getElementById('sessionSearch')?.addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadSessions(); } });
        document.getElementById('activeOnlyToggle')?.addEventListener('change', () => { currentPage = 1; loadSessions(); });
        document.getElementById('btnRevokeAll')?.addEventListener('click', revokeAll);
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
