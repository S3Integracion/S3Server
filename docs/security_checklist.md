# Security Checklist Before Production

- [ ] MySQL escucha solo en `127.0.0.1`
- [ ] Puerto 3306 cerrado en firewall externo
- [ ] HTTPS activo con certificado valido
- [ ] `.env` no accesible por web
- [ ] Logs (`api/storage/logs`) no publicos
- [ ] `display_errors=Off` en produccion
- [ ] `APP_DEBUG=false`
- [ ] CORS limitado a origenes autorizados
- [ ] Cookies de sesion con `Secure`, `HttpOnly`, `SameSite=Strict`
- [ ] CSRF requerido para POST/PUT/PATCH/DELETE
- [ ] Rate limit de login verificado
- [ ] Roles/permisos asignados en DB segun politicas de la empresa
- [ ] Permisos de archivos endurecidos (`www-data`, 640/750)
