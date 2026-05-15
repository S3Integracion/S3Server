# Ubuntu + Apache2 Deployment Guide

## 1) Prerequisitos

- Ubuntu Server actualizado
- Apache2, PHP 8.1+ y extensiones `pdo_mysql`, `mbstring`, `json`
- MySQL accesible solo localmente (`127.0.0.1`)

## 2) Ubicacion sugerida

- Repo en: `/var/www/S3Server`
- Webroot publico: `/var/www/S3Server/html`
- API publica: `/var/www/S3Server/api/public`

## 3) VirtualHost ejemplo

```apache
<VirtualHost *:443>
    ServerName server.company.com
    DocumentRoot /var/www/S3Server/html

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/server.company.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/server.company.com/privkey.pem

    <Directory /var/www/S3Server/html>
        AllowOverride None
        Require all granted
        Options -Indexes
    </Directory>

    Alias /api /var/www/S3Server/api/public
    <Directory /var/www/S3Server/api/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    <Directory /var/www/S3Server/api>
        <FilesMatch "\.env|\.log|\.sql|\.ini|\.yaml|\.yml$">
            Require all denied
        </FilesMatch>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/s3server-error.log
    CustomLog ${APACHE_LOG_DIR}/s3server-access.log combined
</VirtualHost>
```

## 4) Permisos

```bash
sudo chown -R www-data:www-data /var/www/S3Server
sudo find /var/www/S3Server -type d -exec chmod 750 {} \;
sudo find /var/www/S3Server -type f -exec chmod 640 {} \;
sudo chmod -R 750 /var/www/S3Server/api/storage
```

## 5) Endurecimiento adicional

- Forzar redireccion HTTP -> HTTPS
- Deshabilitar listado de directorios
- Confirmar `display_errors=Off` y `log_errors=On`
- Confirmar firewall con `3306` cerrado al exterior

## 6) Activacion

```bash
sudo a2enmod rewrite headers ssl
sudo a2ensite s3server.conf
sudo systemctl reload apache2
```
