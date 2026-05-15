/* admin-database.js */
(() => {
    'use strict';
    const { init, apiFetch, buildPaginator, escHtml } = window.AdminCommon;

    let activeDb    = null;
    let activeTable = null;
    let currentPage = 1;

    async function loadTables() {
        const res = await apiFetch('/admin/database/tables');
        if (!res || !res.ok) return;
        const { data } = await res.json();

        const listEl = document.getElementById('dbTableList');
        listEl.innerHTML = '';

        const grouped = data || {};
        Object.entries(grouped).forEach(([dbName, tables]) => {
            const label = document.createElement('div');
            label.className   = 'db-group-label';
            label.textContent = dbName;
            listEl.appendChild(label);

            (tables || []).forEach(tbl => {
                const btn = document.createElement('button');
                btn.className = 'db-table-btn';
                const typeLabel = tbl.table_type === 'VIEW' ? 'Vista' : 'Tabla';
                const rows = tbl.approx_rows != null ? Number(tbl.approx_rows).toLocaleString('es-MX') : '?';
                btn.innerHTML = `<span class="tbl-name">${escHtml(tbl.table_name)}</span>
                    <span class="tbl-type">${typeLabel} · ${rows}</span>`;
                btn.addEventListener('click', () => {
                    listEl.querySelectorAll('.db-table-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    activeDb    = dbName;
                    activeTable = tbl.table_name;
                    currentPage = 1;
                    loadTableData();
                });
                listEl.appendChild(btn);
            });
        });
    }

    async function loadTableData() {
        if (!activeDb || !activeTable) return;

        const search = document.getElementById('dbSearch')?.value.trim() || '';
        const params = new URLSearchParams({ page: currentPage, per_page: 20 });
        if (search) params.set('search', search);

        const panel = document.getElementById('dbContentPanel');
        panel.innerHTML = '<div class="admin-card" style="min-height:200px;display:flex;align-items:center;justify-content:center"><p style="color:var(--text-dim)">Cargando…</p></div>';

        const res = await apiFetch(`/admin/database/tables/${encodeURIComponent(activeDb)}/${encodeURIComponent(activeTable)}?${params}`);
        if (!res || !res.ok) {
            panel.innerHTML = '<div class="admin-card"><p style="color:var(--danger-main)">Error al cargar datos.</p></div>';
            return;
        }
        const { data } = await res.json();
        const { rows, columns, meta } = data;

        // Build panel HTML
        panel.innerHTML = `
            <div class="admin-card" style="flex:1;overflow:hidden;display:flex;flex-direction:column;margin-bottom:0">
              <div class="admin-card-header">
                <h2>${escHtml(activeDb)}.<strong>${escHtml(activeTable)}</strong></h2>
                <div style="display:flex;gap:.5rem;align-items:center">
                  <input type="search" id="dbSearch" placeholder="Buscar…" style="padding:.4rem .7rem;border-radius:var(--radius-sm);border:1px solid var(--panel-border);font-size:.88rem" value="${escHtml(search)}">
                  <button id="btnDbSearch" class="btn btn-ghost btn-sm">Buscar</button>
                  <button id="btnShowCols" class="btn btn-ghost btn-sm">Columnas</button>
                </div>
              </div>
              <div id="colsPanel" style="display:none;margin-bottom:.75rem"></div>
              <div class="admin-table-wrap" style="flex:1;overflow:auto">
                <table class="admin-table" id="dbDataTable"><thead></thead><tbody></tbody></table>
              </div>
              <div class="pagination" id="dbPagination"></div>
            </div>`;

        const table = panel.querySelector('#dbDataTable');
        const thead = table.querySelector('thead');
        const tbody = table.querySelector('tbody');

        // Headers
        const hrow = thead.insertRow();
        if (columns && columns.length) {
            columns.forEach(col => {
                const th = document.createElement('th');
                th.textContent = col.COLUMN_NAME;
                th.title = col.COLUMN_TYPE + (col.COLUMN_KEY ? ' [' + col.COLUMN_KEY + ']' : '');
                hrow.appendChild(th);
            });
        } else if (rows && rows.length) {
            Object.keys(rows[0]).forEach(k => {
                const th = document.createElement('th');
                th.textContent = k;
                hrow.appendChild(th);
            });
        }

        // Rows
        if (!rows || rows.length === 0) {
            const tr = tbody.insertRow();
            const td = tr.insertCell();
            td.colSpan = hrow.cells.length || 1;
            td.innerHTML = '<div class="empty-state"><span class="empty-icon">📭</span><p>Sin registros</p></div>';
        } else {
            const keys = columns && columns.length ? columns.map(c => c.COLUMN_NAME) : Object.keys(rows[0]);
            rows.forEach(row => {
                const tr = tbody.insertRow();
                keys.forEach(k => {
                    const td = tr.insertCell();
                    const val = row[k];
                    td.textContent = val === null ? 'NULL' : String(val);
                    if (val === null) td.style.color = 'var(--text-dim)';
                });
            });
        }

        buildPaginator(panel.querySelector('#dbPagination'), meta, p => { currentPage = p; loadTableData(); });

        panel.querySelector('#btnDbSearch')?.addEventListener('click', () => { currentPage = 1; loadTableData(); });
        panel.querySelector('#dbSearch')?.addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadTableData(); } });

        panel.querySelector('#btnShowCols')?.addEventListener('click', async () => {
            const colsPanel = panel.querySelector('#colsPanel');
            if (colsPanel.style.display === 'none') {
                if (!colsPanel.innerHTML) await loadColumnsPanel(colsPanel);
                colsPanel.style.display = '';
            } else {
                colsPanel.style.display = 'none';
            }
        });
    }

    async function loadColumnsPanel(colsPanel) {
        const res = await apiFetch(`/admin/database/tables/${encodeURIComponent(activeDb)}/${encodeURIComponent(activeTable)}/columns`);
        if (!res || !res.ok) return;
        const { data } = await res.json();

        let html = '<div class="admin-table-wrap" style="max-height:220px"><table class="admin-table"><thead><tr><th>#</th><th>Columna</th><th>Tipo</th><th>Nulo</th><th>Llave</th><th>Extra</th></tr></thead><tbody>';
        (data || []).forEach(col => {
            html += `<tr>
              <td>${escHtml(String(col.ORDINAL_POSITION))}</td>
              <td><strong>${escHtml(col.COLUMN_NAME)}</strong></td>
              <td><code style="font-size:.78rem">${escHtml(col.COLUMN_TYPE)}</code></td>
              <td>${col.IS_NULLABLE === 'YES' ? 'Sí' : 'No'}</td>
              <td>${escHtml(col.COLUMN_KEY || '—')}</td>
              <td>${escHtml(col.EXTRA || '—')}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        colsPanel.innerHTML = html;
    }

    async function setup() {
        await init();
        await loadTables();
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
