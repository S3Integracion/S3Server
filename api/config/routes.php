<?php
declare(strict_types=1);

use App\Core\Router;

/**
 * @param array<string, object> $controllers
 */
function registerApiRoutes(Router $router, array $controllers): void
{
    $router->add('GET', '/api/v1/health', [$controllers['health'], 'show'], [
        'requires_auth' => false,
        'requires_csrf' => false,
        'required_permission' => null,
        'expects_json' => false,
    ]);

    $router->add('GET', '/api/v1/csrf-token', [$controllers['auth'], 'csrfToken'], [
        'requires_auth' => false,
        'requires_csrf' => false,
        'required_permission' => null,
        'expects_json' => false,
    ]);

    $router->add('POST', '/api/v1/auth/login', [$controllers['auth'], 'login'], [
        'requires_auth' => false,
        'requires_csrf' => true,
        'required_permission' => null,
        'expects_json' => true,
    ]);

    $router->add('POST', '/api/v1/auth/logout', [$controllers['auth'], 'logout'], [
        'requires_auth' => true,
        'requires_csrf' => true,
        'required_permission' => null,
        'expects_json' => false,
    ]);

    $router->add('GET', '/api/v1/auth/me', [$controllers['auth'], 'me'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => null,
        'expects_json' => false,
    ]);

    $router->add('GET', '/api/v1/admin/users', [$controllers['admin_users'], 'index'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => 'users.read',
        'expects_json' => false,
    ]);

    $router->add('POST', '/api/v1/admin/users', [$controllers['admin_users'], 'create'], [
        'requires_auth' => true,
        'requires_csrf' => true,
        'required_permission' => 'users.create',
        'expects_json' => true,
    ]);

    $router->add('GET', '/api/v1/admin/users/{id}', [$controllers['admin_users'], 'show'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => 'users.read',
        'expects_json' => false,
    ]);

    $router->add('PUT', '/api/v1/admin/users/{id}', [$controllers['admin_users'], 'update'], [
        'requires_auth' => true,
        'requires_csrf' => true,
        'required_permission' => 'users.update',
        'expects_json' => true,
    ]);

    $router->add('PATCH', '/api/v1/admin/users/{id}', [$controllers['admin_users'], 'update'], [
        'requires_auth' => true,
        'requires_csrf' => true,
        'required_permission' => 'users.update',
        'expects_json' => true,
    ]);

    $router->add('PUT', '/api/v1/admin/users/{id}/roles', [$controllers['admin_users'], 'assignRoles'], [
        'requires_auth' => true,
        'requires_csrf' => true,
        'required_permission' => 'users.roles.assign',
        'expects_json' => true,
    ]);

    $router->add('GET', '/api/v1/admin/roles', [$controllers['admin_roles'], 'index'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => 'roles.read',
        'expects_json' => false,
    ]);

    $router->add('POST', '/api/v1/admin/roles', [$controllers['admin_roles'], 'create'], [
        'requires_auth' => true,
        'requires_csrf' => true,
        'required_permission' => 'roles.create',
        'expects_json' => true,
    ]);

    $router->add('GET', '/api/v1/admin/roles/{id}', [$controllers['admin_roles'], 'show'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => 'roles.read',
        'expects_json' => false,
    ]);

    $router->add('PUT', '/api/v1/admin/roles/{id}', [$controllers['admin_roles'], 'update'], [
        'requires_auth' => true,
        'requires_csrf' => true,
        'required_permission' => 'roles.update',
        'expects_json' => true,
    ]);

    $router->add('PATCH', '/api/v1/admin/roles/{id}', [$controllers['admin_roles'], 'update'], [
        'requires_auth' => true,
        'requires_csrf' => true,
        'required_permission' => 'roles.update',
        'expects_json' => true,
    ]);

    $router->add('PUT', '/api/v1/admin/roles/{id}/permissions', [$controllers['admin_roles'], 'assignPermissions'], [
        'requires_auth' => true,
        'requires_csrf' => true,
        'required_permission' => 'roles.permissions.assign',
        'expects_json' => true,
    ]);

    $router->add('GET', '/api/v1/admin/permissions', [$controllers['admin_permissions'], 'index'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => 'permissions.read',
        'expects_json' => false,
    ]);

    // ── Admin Dashboard ──────────────────────────────────────────────────────
    $router->add('GET', '/api/v1/admin/dashboard/stats', [$controllers['admin_dashboard'], 'stats'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'admin.dashboard', 'expects_json' => false,
    ]);

    // ── Sessions ─────────────────────────────────────────────────────────────
    $router->add('GET', '/api/v1/admin/sessions', [$controllers['admin_sessions'], 'index'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'sessions.read', 'expects_json' => false,
    ]);
    $router->add('DELETE', '/api/v1/admin/sessions/{id}', [$controllers['admin_sessions'], 'revoke'], [
        'requires_auth' => true, 'requires_csrf' => true,
        'required_permission' => 'sessions.revoke', 'expects_json' => false,
    ]);
    $router->add('DELETE', '/api/v1/admin/sessions', [$controllers['admin_sessions'], 'revokeAll'], [
        'requires_auth' => true, 'requires_csrf' => true,
        'required_permission' => 'sessions.revoke', 'expects_json' => false,
    ]);

    // ── Audit Log ─────────────────────────────────────────────────────────────
    $router->add('GET', '/api/v1/admin/audit', [$controllers['admin_audit'], 'index'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'audit.read', 'expects_json' => false,
    ]);
    $router->add('GET', '/api/v1/admin/audit/{id}', [$controllers['admin_audit'], 'show'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'audit.read', 'expects_json' => false,
    ]);

    // ── Database Browser ──────────────────────────────────────────────────────
    $router->add('GET', '/api/v1/admin/database/tables', [$controllers['admin_database'], 'tables'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'database.browse', 'expects_json' => false,
    ]);
    // columns route must be before browseTable to avoid {table} capturing "columns"
    $router->add('GET', '/api/v1/admin/database/tables/{db}/{table}/columns', [$controllers['admin_database'], 'columns'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'database.browse', 'expects_json' => false,
    ]);
    $router->add('GET', '/api/v1/admin/database/tables/{db}/{table}', [$controllers['admin_database'], 'browseTable'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'database.browse', 'expects_json' => false,
    ]);

    // ── Orders — marketplaces BEFORE {id} ────────────────────────────────────
    $router->add('GET', '/api/v1/admin/orders/marketplaces', [$controllers['admin_orders'], 'marketplaces'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'orders.read', 'expects_json' => false,
    ]);
    $router->add('GET', '/api/v1/admin/orders', [$controllers['admin_orders'], 'index'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'orders.read', 'expects_json' => false,
    ]);
    $router->add('GET', '/api/v1/admin/orders/{id}', [$controllers['admin_orders'], 'show'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'orders.read', 'expects_json' => false,
    ]);

    // ── Imports ───────────────────────────────────────────────────────────────
    $router->add('GET', '/api/v1/admin/imports', [$controllers['admin_imports'], 'index'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'imports.read', 'expects_json' => false,
    ]);
    $router->add('GET', '/api/v1/admin/imports/{id}/failures', [$controllers['admin_imports'], 'failures'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'imports.read', 'expects_json' => false,
    ]);
    $router->add('GET', '/api/v1/admin/imports/{id}', [$controllers['admin_imports'], 'show'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'imports.read', 'expects_json' => false,
    ]);

    // ── API Tokens ────────────────────────────────────────────────────────────
    $router->add('GET', '/api/v1/admin/tokens', [$controllers['admin_tokens'], 'index'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'tokens.read', 'expects_json' => false,
    ]);
    $router->add('DELETE', '/api/v1/admin/tokens/{id}', [$controllers['admin_tokens'], 'revoke'], [
        'requires_auth' => true, 'requires_csrf' => true,
        'required_permission' => 'tokens.revoke', 'expects_json' => false,
    ]);

    // ── Security ──────────────────────────────────────────────────────────────
    $router->add('GET', '/api/v1/admin/security/login-attempts', [$controllers['admin_security'], 'loginAttempts'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'security.read', 'expects_json' => false,
    ]);
    $router->add('GET', '/api/v1/admin/security/stats', [$controllers['admin_security'], 'stats'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'security.read', 'expects_json' => false,
    ]);

    // Chrome extension endpoints (Bearer auth, no cookies, no CSRF).
    $router->add('POST', '/api/v1/extension/auth/login', [$controllers['extension'], 'login'], [
        'requires_auth' => false,
        'requires_csrf' => false,
        'required_permission' => null,
        'expects_json' => true,
        'auth_mode' => 'bearer',
    ]);

    $router->add('POST', '/api/v1/extension/auth/logout', [$controllers['extension'], 'logout'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => null,
        'expects_json' => false,
        'auth_mode' => 'bearer',
    ]);

    $router->add('GET', '/api/v1/extension/auth/me', [$controllers['extension'], 'me'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => null,
        'expects_json' => false,
        'auth_mode' => 'bearer',
    ]);

    $router->add('POST', '/api/v1/extension/orders/import', [$controllers['extension'], 'import'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => 'order_details.import',
        'expects_json' => true,
        'auth_mode' => 'bearer',
    ]);

    $router->add('POST', '/api/v1/extension/orders/not-registered', [$controllers['extension'], 'submitNotRegistered'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => 'order_details.import',
        'expects_json' => true,
        'auth_mode' => 'bearer',
    ]);

    $router->add('POST', '/api/v1/extension/orders/not-delivered', [$controllers['extension'], 'submitNotDelivered'], [
        'requires_auth' => true,
        'requires_csrf' => false,
        'required_permission' => 'order_details.import',
        'expects_json' => true,
        'auth_mode' => 'bearer',
    ]);

    // ── Pending Orders — Admin ────────────────────────────────────────────────
    $router->add('GET', '/api/v1/admin/orders/not-registered', [$controllers['admin_order_pending'], 'notRegistered'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'orders.read', 'expects_json' => false,
    ]);

    $router->add('GET', '/api/v1/admin/orders/not-delivered', [$controllers['admin_order_pending'], 'notDelivered'], [
        'requires_auth' => true, 'requires_csrf' => false,
        'required_permission' => 'orders.read', 'expects_json' => false,
    ]);
}
