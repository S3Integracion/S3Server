(() => {
    const apiBase = '/api/v1';
    const statusNode = document.getElementById('homeStatus');
    const logoutButton = document.getElementById('logoutButton');

    let csrfToken = '';

    const setStatus = (message) => {
        statusNode.textContent = message;
    };

    const redirectToLogin = () => {
        window.location.href = '/login';
    };

    const fillPills = (nodeId, values) => {
        const node = document.getElementById(nodeId);
        node.innerHTML = '';

        if (!Array.isArray(values) || values.length === 0) {
            const li = document.createElement('li');
            li.textContent = 'Sin elementos asignados';
            node.appendChild(li);
            return;
        }

        values.forEach((value) => {
            const li = document.createElement('li');
            li.textContent = value;
            node.appendChild(li);
        });
    };

    const loadSession = async () => {
        const response = await fetch(`${apiBase}/auth/me`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json'
            }
        });

        const payload = await response.json();

        if (response.status === 401 || response.status === 403) {
            redirectToLogin();
            return;
        }

        if (!response.ok || !payload.success) {
            throw new Error('No se pudo obtener la sesion.');
        }

        const user = payload.data.user;
        document.getElementById('profileId').textContent = user.id;
        document.getElementById('profileUsername').textContent = user.username;
        document.getElementById('profileEmail').textContent = user.email;
        document.getElementById('profileLastLogin').textContent = user.last_login_at || 'Sin registro';

        fillPills('permissionsList', payload.data.permissions || []);
        fillPills('rolesList', payload.data.roles || []);

        csrfToken = payload.data.csrf_token || '';

        // Show admin panel link if user has admin.dashboard permission
        const permissions = payload.data.permissions || [];
        if (permissions.includes('admin.dashboard')) {
            const adminSection = document.getElementById('adminAccessSection');
            if (adminSection) adminSection.style.display = '';
        }
    };

    const logout = async () => {
        logoutButton.disabled = true;
        setStatus('Cerrando sesion...');

        try {
            if (!csrfToken) {
                await loadSession();
            }

            const response = await fetch(`${apiBase}/auth/logout`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                }
            });

            const payload = await response.json();
            if (!response.ok || !payload.success) {
                setStatus('No fue posible cerrar sesion. Intente nuevamente.');
                logoutButton.disabled = false;
                return;
            }

            redirectToLogin();
        } catch (error) {
            setStatus('Error temporal al cerrar sesion.');
            logoutButton.disabled = false;
        }
    };

    document.addEventListener('DOMContentLoaded', async () => {
        logoutButton.addEventListener('click', logout);

        try {
            await loadSession();
            setStatus('Sesion validada.');
        } catch (error) {
            setStatus('No fue posible cargar el perfil autenticado.');
        }
    });
})();
