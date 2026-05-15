# Test Plan (v1)

## Auth

1. Login correcto con usuario activo -> `200`, `success=true`.
2. Login con password incorrecto -> `401`.
3. Login con usuario inactivo -> `403`.
4. `GET /auth/me` con sesion activa -> `200`.
5. `GET /auth/me` sin sesion -> `401`.
6. Logout con sesion activa -> `200` y cookie invalidada.

## Security

1. POST sin `X-CSRF-Token` -> `403`.
2. Token CSRF invalido -> `403`.
3. Multiples fallos de login hasta umbral -> `429` con `Retry-After`.
4. Origin no autorizado con cabecera `Origin` -> `403`.
5. Verificar headers: CSP, X-Frame-Options, nosniff, Referrer-Policy.

## RBAC

1. Usuario sin `users.read` en `GET /admin/users` -> `403`.
2. Usuario con permiso correcto -> `200`.
3. Cambiar roles/permisos y repetir solicitud -> cambio inmediato.

## API contract

1. Endpoints inexistentes -> `404`.
2. Metodo no permitido -> `405` + `Allow`.
3. Cuerpo invalido JSON -> `400`.
4. Content-Type incorrecto en endpoint JSON -> `415`.

## Auditoria

1. Confirmar registro de login exitoso/fallido.
2. Confirmar registro de logout.
3. Confirmar eventos de creacion/actualizacion de users/roles y asignaciones.
4. Validar escritura en tabla `audit_logs` y archivo `api/storage/logs/audit.log`.

## Extension Endpoints (Bearer auth)

Variables: `TOKEN` = bearer obtenido por `POST /extension/auth/login`. `BASE` = `https://cybercomander.net/api/v1`.

### Login

```bash
curl -s -X POST "$BASE/extension/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"identifier":"admin","password":"...","device":"S3 Seller Extractor 3.1.0"}'
```

Esperado: `200` con `data.access_token`, `data.expires_at`, `data.user`, `data.permissions[]` que incluye `order_details.import`.

### POST /extension/orders/import

1. Happy path con 1 orden (`run.run_uuid`, `orders[0].order_id`, `delivery_status`, `has_refund`, `refund_amount`, `items[]`, `packages[]`) -> `200`, `data.inserted=1` (o `data.updated=1` si ya existe).
2. Re-enviar el mismo payload -> `data.updated=1` por orden (idempotente).
3. Payload con `orders=[]` -> `422` validation_error.
4. Payload con `delivery_status` invalido (no en ENUM) -> el repo lo deja en NULL, sin error.
5. Sin Authorization header -> `401`.
6. Sin permiso `order_details.import` -> `403`.

### POST /extension/orders/not-registered

1. Happy: `order_id` que no existe en `orders` -> `inserted=1`.
2. `order_id` que ya existe en `orders` -> `skipped=1` (ningun INSERT en `order_not_registered`).
3. Lote de 5 entradas mezcladas -> contadores correctos.
4. Re-enviar el mismo `order_id` -> `updated=1` (UPSERT del extracted_at y tracking_id).

### POST /extension/orders/not-delivered

1. Happy: `order_id` existe en `orders` con `delivery_status='Tránsito'` -> `inserted=1`.
2. `order_id` existe pero `delivery_status='Entregado'` -> `skipped=1`.
3. `order_id` que no existe en `orders` -> `skipped=1`.
4. Re-enviar el mismo `order_id` mientras `delivery_status` siga siendo no-final -> `updated=1`.

## Admin Orders (session auth)

Variables: `SID` = cookie `S3SESSID` de un usuario con `orders.read`.

### GET /admin/orders con filtros

```bash
curl -G "$BASE/admin/orders" \
  -H "Cookie: S3SESSID=$SID" \
  --data-urlencode "delivery_status=Tránsito" \
  --data-urlencode "has_refund=Sí"
```

Esperado: `data[]` solo con filas que cumplen ambos filtros.
`meta.total` debe coincidir con:

```sql
SELECT COUNT(DISTINCT order_id) FROM v_order_tracking_summary
 WHERE delivery_status='Tránsito' AND has_refund='Sí';
```

### GET /admin/orders/{id}

`data.delivery_status`, `data.has_refund`, `data.refund_amount` presentes y consistentes con la fila en `orders`.
`data.items[]` y `data.packages[]` poblados.

### GET /admin/orders/not-registered y /admin/orders/not-delivered

1. Sin permiso -> `403`.
2. Con `orders.read` -> `200` con paginacion (`meta.total`, `meta.page`, `meta.per_page`, `meta.pages`).
3. Filas ordenadas DESC por `extracted_at` (o por defecto del repo).

## Extension Manual Scenarios

Pre-requisitos: extension cargada en Chrome, sesion abierta en Seller Central, login en popup.

1. **Login popup**: ingresar credenciales -> badge superior muestra usuario y rol.
2. **Subir orden**: en pagina de orden detalle, click "Procesar JSON" -> ver fila en `/admin/orders` y nuevo registro en `import_runs`.
3. **Detectar no registradas**: en `Manage Orders`, click "Detectar no registradas (pagina actual)" -> filas aparecen en `order_not_registered`.
4. **Re-importar**: subir como `order_details` la orden detectada antes -> la fila desaparece de `order_not_registered`.
5. **Entrega en proceso**: importar orden con `delivery_status='Tránsito'` -> aparece fila en `order_not_delivered`.
6. **Entrega final**: re-importar la misma orden con `delivery_status='Entregado'` -> la fila desaparece de `order_not_delivered`.
7. **Re-verificar en transito**: click "Re-verificar pedidos en tránsito" -> contador en popup refleja la cola.

## Portal Manual Checklist

1. `/admin/orders`: filtros `delivery_status`, `has_refund` aislados y combinados; tabla y `meta.total` consistentes.
2. `/admin/orders`: detalle de orden con `has_refund=Sí` muestra `refund_amount` formateado.
3. `/admin/order-not-registered` y `/admin/order-not-delivered`: tabla, paginador, boton refresh.
4. URL canonica `/admin/order-not-registered` (sin `.html`) -> `200`.
5. Sidebar: enlaces a las dos paginas presentes en los 13 archivos `admin/*.html`.
6. Acceso sin permiso `orders.read` -> mensaje de error / redireccion a login.

## Database Assertions

Ejecutar despues de cada ronda de pruebas E2E:

```sql
-- 1. order_not_registered no debe tener overlap con orders.
SELECT n.order_id FROM order_not_registered n
  JOIN orders o ON o.order_id = n.order_id;
-- Esperado: 0 filas.

-- 2. order_not_delivered solo con status no-final.
SELECT n.order_id, o.delivery_status
  FROM order_not_delivered n
  JOIN orders o ON o.order_id = n.order_id
 WHERE o.delivery_status NOT IN ('Tránsito','Sin movimiento')
    OR o.delivery_status IS NULL;
-- Esperado: 0 filas.

-- 3. has_refund='Sí' implica refund_amount > 0.
SELECT order_id FROM orders
 WHERE has_refund='Sí' AND (refund_amount IS NULL OR refund_amount <= 0);
-- Esperado: 0 filas.

-- 4. Trigger de historial activo.
SELECT COUNT(*) FROM order_history
 WHERE delivery_status IS NOT NULL OR has_refund IS NOT NULL;
-- Esperado: > 0 tras cualquier UPDATE en orders.

-- 5. Grants requeridos para api_user.
SHOW GRANTS FOR 'api_user'@'127.0.0.1';
-- Debe contener:
--   GRANT DELETE ON `OrderDetails`.`order_not_registered` TO ...
--   GRANT DELETE ON `OrderDetails`.`order_not_delivered` TO ...
```

## Backup y rollback

```bash
mkdir -p /var/www/backups
TS=$(date +%Y%m%d_%H%M%S)
mysqldump --single-transaction --routines --triggers --events \
  -u root OrderDetails > /var/www/backups/OrderDetails_${TS}.sql
mysqldump --single-transaction --routines --triggers --events \
  -u root S3INTEGRACION > /var/www/backups/S3INTEGRACION_${TS}.sql
```

Restauracion en BD throwaway para validar:

```bash
mysql -u root -e "CREATE DATABASE OrderDetails_test;"
mysql -u root OrderDetails_test < /var/www/backups/OrderDetails_${TS}.sql
mysql -u root -e "DROP DATABASE OrderDetails_test;"
```

## Tests de extraccion (extension)

Runner standalone en `/var/www/extension/tests/run_extraction_tests.html`. Ver `extension/tests/README.md` para el procedimiento. Validar:

1. Todos los fixtures sinteticos pasan tras cualquier cambio en `extraction_shared.js`.
2. Agregar fixtures con HTML real de Seller Central conforme se obtienen muestras.
3. No introducir regresiones: si una regex se ajusta para cubrir un caso real, los fixtures previos deben seguir pasando.
