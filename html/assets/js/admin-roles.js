/* admin-roles.js */
(() => {
    'use strict';
    const { init, apiFetch, buildTable, buildPaginator, openModal, closeModal, toast, badge, escHtml, fmtDate } = window.AdminCommon;

    let currentPage  = 1;
    const modal      = document.getElementById('roleModal');
    let allPerms     = [];

    async function loadPermissionsForGrid() {
        const res = await apiFetch('/admin/permissions?per_page=100');
        if (!res || !res.ok) return;
        const { data } = await res.json();
        allPerms = data || [];
    }

    function renderPermissionsGrid(assignedIds) {
        const grid = document.getElementById('permissionsGrid');
        if (!grid) return;
        grid.innerHTML = allPerms.map(p => `
            <label>
              <input type="checkbox" name="perm_ids" value="${p.id}"
                ${assignedIds.includes(p.id) ? 'checked' : ''}>
              ${escHtml(p.name)}
            </label>`).join('');
    }

    function getSelectedPermIds() {
        return Array.from(document.querySelectorAll('#permissionsGrid input[name="perm_ids"]:checked'))
            .map(cb => parseInt(cb.value, 10));
    }

    async function loadRoles() {
        const search = document.getElementById('roleSearch')?.value.trim() || '';
        const res = await apiFetch(`/admin/roles?page=${currentPage}&per_page=20&search=${encodeURIComponent(search)}`);
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('rolesTable'), [
            { key: 'id', label: 'ID', width: '50px' },
            { key: 'name', label: 'Nombre' },
            { key: 'description', label: 'Descripción' },
            { label: 'Estado', html: r => badge(r.is_active ? 'Activo' : 'Inactivo', r.is_active ? 'success' : 'neutral') },
            { label: 'Permisos', render: r => r.permission_count ?? (r.permissions?.length ?? '—') },
            { label: 'Creado', render: r => fmtDate(r.created_at) },
            { label: 'Acciones', cls: 'actions', html: r => `<button class="btn btn-ghost btn-xs" data-edit="${r.id}">Editar</button>` },
        ], (data || []).map(r => ({ ...r, _onClick: () => openEditModal(r.id) })));

        document.querySelectorAll('[data-edit]').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); openEditModal(parseInt(btn.dataset.edit, 10)); });
        });

        buildPaginator(document.getElementById('rolesPagination'), meta, p => { currentPage = p; loadRoles(); });
    }

    async function openCreateModal() {
        document.getElementById('roleModalTitle').textContent = 'Nuevo rol';
        document.getElementById('roleId').value = '';
        document.getElementById('roleName').value = '';
        document.getElementById('roleDescription').value = '';
        document.getElementById('roleIsActive').checked = true;
        renderPermissionsGrid([]);
        document.getElementById('roleFormError').style.display = 'none';
        openModal(modal);
    }

    async function openEditModal(roleId) {
        const res = await apiFetch(`/admin/roles/${roleId}`);
        if (!res || !res.ok) { toast('No se pudo cargar el rol', 'error'); return; }
        const { data } = await res.json();

        document.getElementById('roleModalTitle').textContent = 'Editar rol';
        document.getElementById('roleId').value = data.id;
        document.getElementById('roleName').value = data.name;
        document.getElementById('roleDescription').value = data.description || '';
        document.getElementById('roleIsActive').checked = !!data.is_active;
        renderPermissionsGrid((data.permission_ids || []).map(Number));
        document.getElementById('roleFormError').style.display = 'none';
        openModal(modal);
    }

    async function saveRole(e) {
        e.preventDefault();
        const errEl  = document.getElementById('roleFormError');
        errEl.style.display = 'none';

        const roleId = document.getElementById('roleId').value;
        const isNew  = !roleId;
        const body   = {
            name:        document.getElementById('roleName').value,
            description: document.getElementById('roleDescription').value,
            is_active:   document.getElementById('roleIsActive').checked,
        };

        const url    = isNew ? '/admin/roles' : `/admin/roles/${roleId}`;
        const method = isNew ? 'POST' : 'PUT';

        const res = await apiFetch(url, { method, json: body });
        if (!res) return;

        if (!res.ok) {
            const { message } = await res.json();
            errEl.textContent = message || 'Error al guardar';
            errEl.style.display = '';
            return;
        }

        const { data } = await res.json();
        const savedId  = data.id || parseInt(roleId, 10);
        const permIds  = getSelectedPermIds();
        await apiFetch(`/admin/roles/${savedId}/permissions`, { method: 'PUT', json: { permission_ids: permIds } });

        closeModal(modal);
        toast(isNew ? 'Rol creado' : 'Rol actualizado');
        currentPage = 1;
        loadRoles();
    }

    async function setup() {
        await init();
        await loadPermissionsForGrid();
        await loadRoles();

        document.getElementById('btnCreateRole')?.addEventListener('click', openCreateModal);
        document.getElementById('btnSearch')?.addEventListener('click', () => { currentPage = 1; loadRoles(); });
        document.getElementById('roleSearch')?.addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadRoles(); } });
        document.getElementById('roleForm')?.addEventListener('submit', saveRole);
        document.getElementById('btnCancelRole')?.addEventListener('click', () => closeModal(modal));
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
