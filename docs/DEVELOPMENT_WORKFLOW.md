# Development Workflow — S3Server

Guía operativa para que el equipo trabaje sobre `S3Integracion/S3Server` con la
infraestructura de CI/CD actual: `main` protegida, PR obligatorios, deploy
automático tras merge, SQL fuera del pipeline.

## 1. Roles y accesos

| Rol | Permisos GitHub | Permisos servidor |
|---|---|---|
| Lead / DBA / Ops | Admin del repo | Acceso `developer` (sudo), gestión de BD bajo `/srv/s3server-private/sql` o sesión MySQL directa |
| Desarrollador backend/frontend | Write (puede crear ramas y abrir PRs, no merge directo) | Solo lectura vía Tailscale opcional; no toca servidor en producción |
| Reviewer | Triage/Write con derecho de approval | — |
| GitHub Actions runner | Token efímero | Usuario `deploy` (sin SQL, sin sudo) |

**Regla**: ningún humano hace deploy a mano. Producción solo se mueve por merge
a `main`.

## 2. Onboarding (~15 minutos)

```powershell
git clone https://github.com/S3Integracion/S3Server.git
cd S3Server
git config core.hooksPath .githooks      # activa pre-commit antiSQL
cp .env.example .env                     # rellena valores locales, no los de prod
```

Checklist al primer día:

- [ ] Acceso al repo con tu cuenta GitHub
- [ ] Tu llave SSH pública agregada a tu cuenta GitHub
- [ ] Tailscale instalado solo si necesitas leer/depurar el servidor
- [ ] Entorno PHP local que coincida con producción
- [ ] Base de datos local con esquema actual (DBA te pasa el dump fuera del repo)
- [ ] Leer `docs/security_checklist.md` y `docs/ubuntu_apache_deploy.md`

## 3. Ciclo estándar de feature

```
issue/ticket -> rama -> commits -> PR -> CI verde -> review -> merge -> deploy automático -> verificación
```

### 3.1 Crear la rama

```powershell
git checkout main
git pull origin main
git checkout -b feature/auth-token-rotation
```

Convenciones de nombre:

- `feature/<descripcion-corta>` — funcionalidad nueva
- `fix/<bug-id-o-descripcion>` — corrección
- `refactor/<area>` — sin cambio de comportamiento
- `docs/<area>` — solo markdown bajo `docs/`
- `chore/<tarea>` — config, deps, tooling
- `hotfix/<descripcion>` — emergencia en producción (§5)

### 3.2 Commits

- Un commit por cambio lógico. Evita "WIP" mezclado.
- Mensaje imperativo en presente: `Add token rotation endpoint`, no `Added` ni `Adding`.
- Si tocas varias capas, separa: `Add tokens table accessor` + `Wire rotation in AuthService` + `Expose POST /tokens/rotate`.

### 3.3 Pull Request

```powershell
git push -u origin feature/auth-token-rotation
gh pr create --base main --title "Add token rotation endpoint" --body "..."
```

La descripción del PR debe responder:

- **Qué** cambia (una frase)
- **Por qué** (link a issue o motivación)
- **Cómo probarlo** (pasos manuales si la prueba automatizada no cubre)
- **Riesgo**: bajo/medio/alto, qué se rompe si sale mal
- **Cambios de BD**: sí/no — si sí, link al ticket de DBA (§6)

### 3.4 CI

`Block SQL files` y `Validate PHP files` corren en cada push. Si fallan, arregla
antes de pedir revisión. Ambos checks son obligatorios para merge y no se pueden
bypassear desde el botón estándar de GitHub.

### 3.5 Code review

El reviewer chequea:

- [ ] Ningún `.sql`, `.env` real ni credenciales hardcodeadas
- [ ] Lógica PHP correcta (el CI solo valida sintaxis)
- [ ] Cambios en `api/` no rompen contratos públicos sin versionar
- [ ] HTML/JS no carga assets externos no autorizados
- [ ] Cambios a middleware de auth o permisos: doble par de ojos
- [ ] Cambios a routing: paths nuevos cubiertos por permisos
- [ ] PR pequeño (< ~400 LOC). Si excede, justificarlo.

### 3.6 Merge

Squash por defecto: colapsa los commits de la rama en uno solo sobre `main` con
el título del PR como mensaje. Mantiene `main` limpio.

### 3.7 Deploy automático

Tras el merge:

- `Block SQL files` y `Validate PHP files` corren otra vez contra `main`
- `Deploy production` se ejecuta en el self-hosted runner del servidor
- Tiempo total típico: ~30–60 s

### 3.8 Verificación post-deploy

```powershell
# desde tu máquina si tienes Tailscale
ssh -i $env:USERPROFILE\.ssh\s3server_github_actions deploy@100.122.100.14 'tail -50 /var/www/api/storage/logs/app.log'

# vía GitHub
gh run view --repo S3Integracion/S3Server
```

Recomendado: tener un endpoint `/api/health` que devuelva versión + timestamp y
consumirlo después de cada deploy.

## 4. Mapa: qué cambio va por dónde

| Tipo de cambio | Carpeta | Va por pipeline | Notas |
|---|---|---|---|
| Endpoint REST nuevo | `api/app/` | Sí | Documentar en `docs/` |
| Lógica de negocio | `api/app/Services/` | Sí | Con tests si existen |
| Cambio de routing | `api/config/routes.php` | Sí | Verificar middleware/permisos |
| HTML/CSS/JS | `html/` | Sí | Probar en navegador antes del PR |
| Documentación | `docs/*.md` | Sí | Markdown puro, sin SQL ejecutable |
| Cambio de esquema BD | NO va al repo | No — proceso aparte (§6) | |
| Nuevos secretos | NO al repo | `.env` editado a mano en servidor | Sincronizar **claves** en `.env.example` |
| Migrar contraseña/token | NO al repo | Mismo: editar `.env` del servidor | |
| Cambio de Apache/PHP config | Documentar en `docs/ubuntu_apache_deploy.md`, cambio se hace a mano en servidor | No | Considerar Ansible si se repite |

## 5. Hotfix en producción

Cuando algo está roto en `main` y no puedes esperar a un PR normal:

```powershell
git checkout main && git pull
git checkout -b hotfix/login-500-error
# arregla
git commit -m "Fix null deref in AuthService when session missing"
git push -u origin hotfix/login-500-error
gh pr create --base main --title "Hotfix: login 500 error" --body "..."
```

Como admin con bypass de approvals, puedes hacer merge inmediato cuando el CI
esté verde. Los checks (`Block SQL files`, `Validate PHP files`) **no son
bypasseables** — siempre deben pasar.

Si en un caso muy raro necesitas saltar también los checks, usa "Merge without
waiting for requirements" en GitHub UI y documenta por qué en el PR.

## 6. Cambios de base de datos (fuera del pipeline)

`.sql` está prohibido en el repo y en el pipeline. Las migraciones se manejan
así:

### 6.1 Documenta el cambio (Markdown, no SQL)

En `docs/db-changes/YYYY-MM-DD-<descripcion>.md` describe:

- Tablas/columnas/índices afectados
- Por qué
- Plan de rollback
- Quién aplica y cuándo

### 6.2 Aplica el SQL a mano en el servidor

DBA o lead Ops:

```bash
ssh developer@100.122.100.14
sudo mkdir -p /srv/s3server-private/sql
sudo nano /srv/s3server-private/sql/2026-05-15-add-tokens-table.sql
sudo mysql s3server_db < /srv/s3server-private/sql/2026-05-15-add-tokens-table.sql
```

Permisos del SQL privado: `chown root:root /srv/s3server-private/sql`,
`chmod 700 /srv/s3server-private/sql`. Nadie excepto root lo lee, ni siquiera el
runner.

### 6.3 Coordina con el deploy

Regla: el cambio de esquema va **antes** del código que lo necesita, salvo que
el código sea retrocompatible.

Orden típico para evolución segura:

1. DBA agrega columna nueva nullable
2. Code change que escribe en la columna nueva (rama → PR → merge → deploy)
3. Backfill de datos viejos (manual, en servidor)
4. Code change que lee de la columna nueva
5. DBA hace NOT NULL si aplica

Permite rollback de código sin tocar BD.

### 6.4 Backups antes de tocar BD

```bash
sudo mysqldump --single-transaction s3server_db | sudo tee /srv/s3server-private/sql/backups/$(date +%F-%H%M)-pre-migration.sql >/dev/null
```

Estos backups quedan fuera de `/var/www` y son inaccesibles para el runner.

## 7. Cambios de configuración (`.env`)

`.env` real solo vive en el servidor (`/var/www/.env`). Para agregar una
variable:

1. En tu PR, agrega la clave a `.env.example` con valor vacío
2. Tras el merge, lead Ops entra al servidor y pone el valor real:

   ```bash
   ssh developer@100.122.100.14
   sudo nano /var/www/.env
   ```

3. Reinicia PHP-FPM o Apache si el código no recarga automáticamente:

   ```bash
   sudo systemctl reload apache2
   ```

Nunca edites `.env` desde una rama. El pre-commit hook lo bloquea y de todos
modos sería un leak.

## 8. Rollback

Tres niveles, de menos a más invasivo:

### Nivel 1 — Revert del PR (preferido)

Desde GitHub UI: botón "Revert" en el PR ofensivo abre un nuevo PR de revert.

```powershell
gh pr view <numero>
git revert <sha>
git push origin main   # tras PR + checks + merge
```

Dispara otro deploy automático con el estado anterior.

### Nivel 2 — Restaurar backup de código del servidor

```bash
ssh developer@100.122.100.14
sudo ls -lh /home/deploy/deploy-backups/
sudo tar -xzf /home/deploy/deploy-backups/s3server-code-before-<SHA>.tgz -C /var/www
```

El backup no contiene SQL. Si el problema es de datos, ve a Nivel 3.

### Nivel 3 — Restaurar BD desde backup pre-migración

```bash
sudo mysql s3server_db < /srv/s3server-private/sql/backups/<fecha>-pre-migration.sql
```

Borra datos posteriores a ese backup. Coordina con stakeholders.

## 9. Trabajo concurrente entre devs

- Una persona, una rama, un cambio lógico. No compartas ramas.
- Si dos PRs tocan archivos cercanos, el segundo en mergear hace
  `git pull --rebase origin main` y resuelve antes de pedir review.
- Branch protection requiere "branches up to date" — el último PR antes de
  merge debe estar al día con `main`.
- Para evitar conflictos: divide responsabilidades por carpeta o por capa
  (controllers vs services vs repositorios).

## 10. Observabilidad recomendada

Backlog si no existe:

- Endpoint `/api/health` con versión, uptime, conexión a BD
- Log estructurado en `api/storage/logs/app.log` (JSON con nivel/timestamp/request-id)
- Rotación de logs con `logrotate` semanal
- Notificación de deploy a Slack/Discord/email vía paso extra del workflow
- Alerta si el runner se cae — `Restart=always` en systemd más monitoreo externo

## 11. Cheatsheet de comandos

```powershell
# Estado del runner
ssh -i $env:USERPROFILE\.ssh\s3server_github_actions deploy@100.122.100.14 'systemctl is-active actions.runner.S3Integracion-S3Server.s3integracionserver.service'

# Últimos deploys
gh run list --repo S3Integracion/S3Server --limit 5

# Logs del último deploy
gh run view --repo S3Integracion/S3Server --log-failed

# Ver secrets configurados (solo nombres)
gh secret list --repo S3Integracion/S3Server

# Auditoría local antiSQL
git ls-files | Select-String '\.sql$|\.env|backups'

# Forzar deploy manual del último commit en main
gh workflow run "CI and production deploy" --ref main --repo S3Integracion/S3Server
```

## 12. Riesgos y mitigaciones

| Riesgo | Mitigación actual | Mejora futura |
|---|---|---|
| Self-hosted runner cae → no se puede deployar | `Restart=always` en systemd | Monitoreo + alerta |
| Servidor único, sin staging | — | VM staging, job `deploy-staging` antes de prod |
| Sin tests automatizados | Solo `php -l` | PHPUnit para `app/Services` y `app/Repositories` |
| Sin migraciones versionadas en BD | Markdown manual | Phinx/Doctrine Migrations bajo `/srv/s3server-private/migrations/` |
| Una sola persona aprueba PRs | Admin bypass habilitado | Subir approvals a 2 cuando entren más devs |
| Pubkey de `developer` no rotada | — | Plan trimestral de rotación de llaves SSH |

## 13. Referencias internas

- `.github/workflows/deploy-production.yml` — definición del pipeline
- `.githooks/pre-commit` — bloqueo local de SQL/`.env`
- `docs/security_checklist.md` — checklist de seguridad de la aplicación
- `docs/ubuntu_apache_deploy.md` — configuración base del servidor
- `docs/database_schema_reference.md` — referencia del esquema (sin SQL ejecutable)
