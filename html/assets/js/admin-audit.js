/* admin-audit.js */
(() => {
    'use strict';
    const { init, apiFetch, buildTable, buildPaginator, escHtml, fmtDate, parseJson } = window.AdminCommon;

    let currentPage = 1;

    async function loadAudit() {
        const params = new URLSearchParams({
            page: currentPage,
            per_page: 25,
        });
        const search = document.getElementById('auditSearch')?.value.trim();
        const event  = document.getElementById('auditEvent')?.value.trim();
        const userId = document.getElementById('auditUserId')?.value.trim();
        const from   = document.getElementById('auditFrom')?.value;
        const to     = document.getElementById('auditTo')?.value;
        if (search)  params.set('search', search);
        if (event)   params.set('event', event);
        if (userId)  params.set('user_id', userId);
        if (from)    params.set('date_from', from);
        if (to)      params.set('date_to', to);

        const res = await apiFetch('/admin/audit?' + params.toString());
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('auditTable'), [
            { key: 'id', label: 'ID', width: '60px' },
            { label: 'Usuario', html: r => escHtml(r.username || (r.user_id ? `#${r.user_id}` : 'Sistema')) },
            { label: 'Evento', html: r => `<code style="font-size:.78rem">${escHtml(r.event || '—')}</code>` },
            { key: 'resource', label: 'Recurso' },
            { key: 'action', label: 'Acción' },
            { key: 'ip_address', label: 'IP' },
            { label: 'Fecha', render: r => fmtDate(r.created_at) },
            { label: '+', width: '50px', cls: 'actions', html: r =>
                `<button class="btn btn-ghost btn-xs" data-detail="${r.id}">Ver</button>` },
        ], data || []);

        document.querySelectorAll('[data-detail]').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); loadDetail(parseInt(btn.dataset.detail, 10)); });
        });

        buildPaginator(document.getElementById('auditPagination'), meta, p => { currentPage = p; loadAudit(); });
    }

    async function loadDetail(id) {
        const res = await apiFetch(`/admin/audit/${id}`);
        if (!res || !res.ok) return;
        const { data } = await res.json();

        document.getElementById('dAuditId').textContent      = data.id;
        document.getElementById('dAuditUser').textContent    = data.username || (data.user_id ? `#${data.user_id}` : 'Sistema');
        document.getElementById('dAuditEvent').textContent   = data.event || '—';
        document.getElementById('dAuditResource').textContent = data.resource || '—';
        document.getElementById('dAuditAction').textContent  = data.action || '—';
        document.getElementById('dAuditIp').textContent      = data.ip_address || '—';
        document.getElementById('dAuditUa').textContent      = data.user_agent || '—';
        document.getElementById('dAuditDate').textContent    = fmtDate(data.created_at);

        const meta = data.meta_json;
        const metaEl = document.getElementById('dAuditMeta');
        if (meta) {
            const parsed = parseJson(meta);
            metaEl.textContent = typeof parsed === 'object'
                ? JSON.stringify(parsed, null, 2)
                : meta;
        } else {
            metaEl.textContent = '—';
        }

        const panel = document.getElementById('auditDetailPanel');
        panel.style.display = '';
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    async function setup() {
        await init();
        await loadAudit();

        document.getElementById('btnSearch')?.addEventListener('click', () => { currentPage = 1; loadAudit(); });
        document.getElementById('btnCloseDetail')?.addEventListener('click', () => {
            document.getElementById('auditDetailPanel').style.display = 'none';
        });
        ['auditSearch', 'auditEvent', 'auditUserId'].forEach(id => {
            document.getElementById(id)?.addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadAudit(); } });
        });
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
