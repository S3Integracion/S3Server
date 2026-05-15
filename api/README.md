# API Enterprise PHP (v1)

## Routing

Toda solicitud debe entrar por `api/public/index.php`.
Se requiere `mod_rewrite` activo para enrutar al front controller.

## Seguridad incluida

- PDO con consultas preparadas y `utf8mb4`
- Sesion persistida en DB
- CSRF obligatorio para mutaciones
- Rate limit de login por `identifier + ip`
- RBAC por permiso en cada endpoint admin
- CORS por lista blanca
- Headers de seguridad HTTP
- Auditoria en DB y en `api/storage/logs/audit.log`

## Dependencias

- PHP 8.1+
- Extensiones: `pdo`, `pdo_mysql`, `session`, `json`
