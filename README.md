# S3Server

Base productiva para servidor empresarial en PHP:
- Frontend publico en `html/` (welcome, login, home autenticado)
- API REST endurecida en `api/` con rutas `/api/v1`
- Sesion en DB, CSRF estricto, RBAC admin, auditoria DB + archivo

## Estructura principal

- `html/`: recursos web publicos
- `api/public`: unico entrypoint HTTP de la API
- `api/app`: Core, Middleware, Controllers, Services, Repositories, Validation, Security
- `api/config`: bootstrap y rutas
- `api/storage`: logs y temporales no publicos
- `docs/`: despliegue, esquema esperado y pruebas

## Endpoints v1

- `GET /api/v1/health`
- `GET /api/v1/csrf-token`
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `GET /api/v1/auth/me`
- `GET|POST /api/v1/admin/users`
- `GET|PUT|PATCH /api/v1/admin/users/{id}`
- `PUT /api/v1/admin/users/{id}/roles`
- `GET|POST /api/v1/admin/roles`
- `GET|PUT|PATCH /api/v1/admin/roles/{id}`
- `PUT /api/v1/admin/roles/{id}/permissions`
- `GET /api/v1/admin/permissions`

## Configuracion

1. Copiar `.env.example` a `.env` y completar valores reales.
2. Ejecutar script SQL en MySQL Workbench: `docs/sql/mysql_workbench_setup.sql`.
3. Si prefieres revisar estructura primero, consulta `docs/database_schema_reference.md`.
4. Configurar Apache2 para:
   - `DocumentRoot` publico en `.../html`
   - Alias `/api` a `.../api/public`
5. Forzar HTTPS en produccion.

## Notas

- No se usan credenciales en frontend.
- No se expone MySQL a internet.
- Todos los cambios criticos quedan auditados.
