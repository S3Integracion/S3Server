/* admin-permissions.js */
(() => {
    'use strict';
    const { init, apiFetch, buildTable, buildPaginator, badge, escHtml } = window.AdminCommon;

    let currentPage = 1;
    let allRoles    = [];

    async function loadRoles() {
        const res = await apiFetch('/admin/roles?per_page=100');
        if (!res || !res.ok) return;
        const { data } = await res.json();
        allRoles = data || [];
    }

    function rolesWithPermission(permName) {
        return allRoles.filter(r => (r.permission_names || []).includes(permName));
    }

    async function loadPermissions() {
        const search = document.getElementById('permSearch')?.value.trim() || '';
        const res = await apiFetch(`/admin/permissions?page=${currentPage}&per_page=25&search=${encodeURIComponent(search)}`);
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('permissionsTable'), [
            { key: 'id', label: 'ID', width: '50px' },
            { key: 'name', label: 'Nombre', html: r => `<code style="font-size:.82rem">${escHtml(r.name)}</code>` },
            { key: 'description', label: 'Descripción' },
            { label: 'Estado', html: r => badge(r.is_active ? 'Activo' : 'Inactivo', r.is_active ? 'success' : 'neutral') },
            { label: 'Roles', html: r => {
                const roles = rolesWithPermission(r.name);
                if (!roles.length) return '<span style="color:var(--text-dim)">—</span>';
                return roles.map(rr => `<span class="badge badge-info">${escHtml(rr.name)}</span>`).join(' ');
            }},
        ], data || []);

        buildPaginator(document.getElementById('permPagination'), meta, p => { currentPage = p; loadPermissions(); });
    }

    async function setup() {
        await init();
        await loadRoles();
        await loadPermissions();

        document.getElementById('btnSearch')?.addEventListener('click', () => { currentPage = 1; loadPermissions(); });
        document.getElementById('permSearch')?.addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadPermissions(); } });
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
