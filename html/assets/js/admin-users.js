/* admin-users.js */
(() => {
    'use strict';
    const { init, apiFetch, buildTable, buildPaginator, openModal, closeModal, toast, badge, escHtml, fmtDate } = window.AdminCommon;

    let currentPage = 1;
    const modal     = document.getElementById('userModal');
    let allRoles    = [];

    async function loadRolesForSelect() {
        const res = await apiFetch('/admin/roles?per_page=100');
        if (!res || !res.ok) return;
        const { data } = await res.json();
        allRoles = data || [];
        renderRolesGrid([]);
    }

    function renderRolesGrid(assignedIds) {
        const grid = document.getElementById('rolesGrid');
        if (!grid) return;
        grid.innerHTML = allRoles.map(r => `
            <label>
              <input type="checkbox" name="role_ids" value="${r.id}"
                ${assignedIds.includes(r.id) ? 'checked' : ''}>
              ${escHtml(r.name)}
            </label>`).join('');
    }

    function getSelectedRoleIds() {
        return Array.from(document.querySelectorAll('#rolesGrid input[name="role_ids"]:checked'))
            .map(cb => parseInt(cb.value, 10));
    }

    async function loadUsers() {
        const search = document.getElementById('userSearch')?.value.trim() || '';
        const res = await apiFetch(`/admin/users?page=${currentPage}&per_page=20&search=${encodeURIComponent(search)}`);
        if (!res || !res.ok) return;
        const { data, meta } = await res.json();

        buildTable(document.getElementById('usersTable'), [
            { key: 'id', label: 'ID', width: '50px' },
            { key: 'username', label: 'Usuario' },
            { key: 'email', label: 'Email' },
            { label: 'Estado', html: r => badge(r.is_active ? 'Activo' : 'Inactivo', r.is_active ? 'success' : 'neutral') },
            { label: 'Roles', html: r => (r.roles || []).map(n => `<span class="badge badge-info">${escHtml(n)}</span>`).join(' ') || '—' },
            { label: 'Último login', render: r => fmtDate(r.last_login_at) },
            { label: 'Acciones', cls: 'actions', html: r => `
                <button class="btn btn-ghost btn-xs" data-edit="${r.id}">Editar</button>` },
        ], (data || []).map(r => ({
            ...r,
            _onClick: () => openEditModal(r.id),
        })));

        // Wire edit buttons
        document.querySelectorAll('[data-edit]').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); openEditModal(parseInt(btn.dataset.edit, 10)); });
        });

        buildPaginator(document.getElementById('usersPagination'), meta, p => { currentPage = p; loadUsers(); });
    }

    async function openCreateModal() {
        document.getElementById('userModalTitle').textContent = 'Nuevo usuario';
        document.getElementById('userId').value = '';
        document.getElementById('userUsername').value = '';
        document.getElementById('userEmail').value = '';
        document.getElementById('userPassword').value = '';
        document.getElementById('userIsActive').checked = true;
        document.getElementById('passwordHint').style.display = 'none';
        renderRolesGrid([]);
        document.getElementById('userFormError').style.display = 'none';
        openModal(modal);
    }

    async function openEditModal(userId) {
        const res = await apiFetch(`/admin/users/${userId}`);
        if (!res || !res.ok) { toast('No se pudo cargar el usuario', 'error'); return; }
        const { data } = await res.json();

        document.getElementById('userModalTitle').textContent = 'Editar usuario';
        document.getElementById('userId').value = data.id;
        document.getElementById('userUsername').value = data.username;
        document.getElementById('userEmail').value = data.email;
        document.getElementById('userPassword').value = '';
        document.getElementById('userIsActive').checked = !!data.is_active;
        document.getElementById('passwordHint').style.display = '';
        renderRolesGrid((data.role_ids || []).map(Number));
        document.getElementById('userFormError').style.display = 'none';
        openModal(modal);
    }

    async function saveUser(e) {
        e.preventDefault();
        const errEl    = document.getElementById('userFormError');
        errEl.style.display = 'none';

        const userId   = document.getElementById('userId').value;
        const isNew    = !userId;
        const body = {
            username:  document.getElementById('userUsername').value,
            email:     document.getElementById('userEmail').value,
            is_active: document.getElementById('userIsActive').checked,
        };
        const pass = document.getElementById('userPassword').value;
        if (pass || isNew) body.password = pass;

        const url    = isNew ? '/admin/users' : `/admin/users/${userId}`;
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
        const savedId = data.id || parseInt(userId, 10);

        // Assign roles
        const roleIds = getSelectedRoleIds();
        await apiFetch(`/admin/users/${savedId}/roles`, { method: 'PUT', json: { role_ids: roleIds } });

        closeModal(modal);
        toast(isNew ? 'Usuario creado' : 'Usuario actualizado');
        currentPage = 1;
        loadUsers();
    }

    async function setup() {
        await init();
        await loadRolesForSelect();
        await loadUsers();

        document.getElementById('btnCreateUser')?.addEventListener('click', openCreateModal);
        document.getElementById('btnSearch')?.addEventListener('click', () => { currentPage = 1; loadUsers(); });
        document.getElementById('userSearch')?.addEventListener('keydown', e => { if (e.key === 'Enter') { currentPage = 1; loadUsers(); } });
        document.getElementById('userForm')?.addEventListener('submit', saveUser);
        document.getElementById('btnCancelUser')?.addEventListener('click', () => closeModal(modal));
    }

    document.addEventListener('DOMContentLoaded', setup);
})();
