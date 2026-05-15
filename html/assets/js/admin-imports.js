/* admin-imports.js */
(() => {
    'use strict';
    const { init, apiFetch, buildTable, buildPaginator, badge, escHtml, fmtDate } = window.AdminCommon;

    let currentPage        = 1;
    let failuresPage       = 1;
    let activeImportRunId  = null;

    const STATUS_BADGE = {
        pending:    ['Pendiente', 'neutral'],
        processing: ['Procesando', 'info'],
        completed:  ['Completado', 'success'],
        failed:     ['Fallido', 'danger'],
    };

    async function loadImports() {
        const params = new URLSearchParams({ page: currentPage, per_page: 20 });
        const status = document.getElementById('importStatus')?.value;
        const userId = document.getElementById('importUserId')?.value.trim();
        const from   = document.getElementById('importFrom')?.value;
        const to     = document.getElementById('importTo')?.value;
        if (status) params.set('status', status);
        if (userId) params.set('user_id', userId);
        if (from)   params.set('date_from', from);
        if (to)     params.set('date_to', to);

        const res = await apiFetch('/admin/imports?' + params);
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('importsTable'), [
            { key: 'id', label: 'ID', width: '60px' },
            { label: 'UUID', render: r => (r.run_uuid || '—').slice(0, 8) + '…' },
            { label: 'Usuario', html: r => escHtml(String(r.user_id || '—')) },
            { label: 'Modo', html: r => `<span class="badge badge-neutral">${escHtml(r.extraction_mode || '—')}</span>` },
            { key: 'row_count', label: 'Filas' },
            { key: 'inserted_count', label: 'Insertados' },
            { key: 'rejected_count', label: 'Rechazados' },
            { label: 'Estado', html: r => {
                const [label, type] = STATUS_BADGE[r.status] || [r.status, 'neutral'];
                return badge(label, type);
            }},
            { label: 'Fecha', render: r => fmtDate(r.created_at) },
        ], (data || []).map(r => ({ ...r, _onClick: () => loadImportDetail(r.id) })));

        buildPaginator(document.getElementById('importsPagination'), meta, p => { currentPage = p; loadImports(); });
    }

    async function loadImportDetail(importId) {
        const res = await apiFetch(`/admin/imports/${importId}`);
        if (!res || !res.ok) return;
        const { data } = await res.json();

        activeImportRunId = importId;
        failuresPage      = 1;

        const [label, type] = STATUS_BADGE[data.status] || [data.status, 'neutral'];

        const infoPanel = document.getElementById('importInfoPanel');
        infoPanel.innerHTML = `
            <h3>Corrida #${data.id} — ${badge(label, type)}</h3>
            <dl style="display:grid;grid-template-columns:160px 1fr;gap:.4rem .75rem;font-size:.9rem;margin-top:.75rem">
              <dt style="font-weight:700;color:var(--text-dim)">UUID</dt><dd><code style="font-size:.8rem">${escHtml(data.run_uuid || '—')}</code></dd>
              <dt style="font-weight:700;color:var(--text-dim)">Usuario ID</dt><dd>${escHtml(String(data.user_id || '—'))}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Marketplace</dt><dd>${escHtml(data.marketplace_country || String(data.marketplace_id || '—'))}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Modo</dt><dd>${escHtml(data.extraction_mode || '—')}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Versión extensión</dt><dd>${escHtml(data.extension_version || '—')}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Filas</dt><dd>${data.row_count || 0} total · ${data.inserted_count || 0} insertados · ${data.updated_count || 0} actualizados · ${data.rejected_count || 0} rechazados</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Errores</dt><dd>${escHtml(data.error_summary || '—')}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">IP cliente</dt><dd>${escHtml(data.client_ip || '—')}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Extraído</dt><dd>${fmtDate(data.extracted_at)}</dd>
              <dt style="font-weight:700;color:var(--text-dim)">Subido</dt><dd>${fmtDate(data.uploaded_at)}</dd>
            </dl>`;

        document.getElementById('importDetailSection').style.display = '';
        document.getElementById('importDetailTitle').textContent = `Corrida #${data.id}`;

        if (data.failure_count > 0) {
            await loadFailures();
        } else {
            const failTbody = document.querySelector('#failuresTable tbody');
            if (failTbody) {
                failTbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><span class="empty-icon">✅</span><p>Sin registros fallidos</p></div></td></tr>';
            }
        }

        document.getElementById('importDetailSection').scrollIntoView({ behavior: 'smooth' });
    }

    async function loadFailures() {
        if (!activeImportRunId) return;
        const res = await apiFetch(`/admin/imports/${activeImportRunId}/failures?page=${failuresPage}&per_page=20`);
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('failuresTable'), [
            { key: 'row_number', label: 'Fila' },
            { key: 'row_number', label: '#', render: (_,i) => (failuresPage - 1) * 20 + i + 1 },
            { key: 'order_id', label: 'Orden ID' },
            { key: 'error_code', label: 'Código error' },
            { key: 'error_message', label: 'Mensaje', cls: 'wrap' },
        ], data || []);

        buildPaginator(document.getElementById('failuresPagination'), meta, p => { failuresPage = p; loadFailures(); });
    }

    async function setup() {
        await init();
        await loadImports();

        document.getElementById('btnSearch')?.addEventListener('click', () => { currentPage = 1; loadImports(); });
        document.getElementById('btnCloseImportDetail')?.addEventListener('click', () => {
            document.getElementById('importDetailSection').style.display = 'none';
            activeImportRunId = null;
        });
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
