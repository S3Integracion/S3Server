(() => {
    const apiBase = '/api/v1';
    const form = document.getElementById('loginForm');
    const button = document.getElementById('loginButton');
    const statusNode = document.getElementById('statusMessage');

    let csrfToken = '';

    const setStatus = (message) => {
        statusNode.textContent = message;
    };

    const resolveLoginErrorMessage = (payload, statusCode) => {
        const errorCode = payload?.error?.code || '';
        const retryAfter = Number(payload?.meta?.retry_after_seconds || 0);

        if (errorCode === 'invalid_credentials') {
            return 'No fue posible autenticarse. Verifique sus credenciales.';
        }

        if (errorCode === 'too_many_attempts') {
            if (Number.isFinite(retryAfter) && retryAfter > 0) {
                return `Demasiados intentos fallidos. Intente nuevamente en ${retryAfter} segundos.`;
            }
            return 'Demasiados intentos fallidos. Intente nuevamente mas tarde.';
        }

        if (errorCode === 'user_inactive') {
            return 'Su usuario esta inactivo. Contacte al administrador.';
        }

        if (errorCode === 'csrf_invalid' || errorCode === 'session_missing') {
            return 'La sesion de seguridad expiro. Intente nuevamente.';
        }

        if (errorCode === 'cors_origin_denied') {
            return 'El origen de la solicitud no esta autorizado.';
        }

        if (statusCode >= 500) {
            return 'Error interno del servidor. Intente nuevamente.';
        }

        const backendMessage = payload?.error?.message;
        if (typeof backendMessage === 'string' && backendMessage.trim() !== '') {
            return backendMessage;
        }

        return 'No fue posible autenticarse. Verifique sus credenciales.';
    };

    const fetchCsrfToken = async () => {
        const response = await fetch(`${apiBase}/csrf-token`, {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Accept': 'application/json'
            }
        });

        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error('No fue posible iniciar el flujo de seguridad.');
        }

        csrfToken = payload.data.csrf_token;
    };

    const handleLogin = async (event) => {
        event.preventDefault();

        const identifier = document.getElementById('identifier').value.trim();
        const password = document.getElementById('password').value;

        if (!identifier || !password) {
            setStatus('Complete usuario/correo y contrasena.');
            return;
        }

        button.disabled = true;
        setStatus('Validando credenciales...');

        try {
            if (!csrfToken) {
                await fetchCsrfToken();
            }

            const response = await fetch(`${apiBase}/auth/login`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ identifier, password })
            });

            let payload = null;
            try {
                payload = await response.json();
            } catch (jsonError) {
                payload = null;
            }

            if (!response.ok || !payload?.success) {
                setStatus(resolveLoginErrorMessage(payload, response.status));
                await fetchCsrfToken();
                return;
            }

            window.location.href = '/home';
        } catch (error) {
            setStatus('Error temporal de comunicacion con el servidor.');
        } finally {
            button.disabled = false;
        }
    };

    document.addEventListener('DOMContentLoaded', async () => {
        form.addEventListener('submit', handleLogin);

        try {
            await fetchCsrfToken();
        } catch (error) {
            setStatus('No se pudo inicializar el token de seguridad.');
        }
    });
})();
