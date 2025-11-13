# 🚀 Deployment Guide

Kompletní návod pro nasazení aplikace na produkční server.

## 📋 Předpoklady

### Serverové požadavky

- **PHP:** 8.2 nebo novější
- **MySQL:** 8.0 nebo novější
- **Web server:** Apache 2.4+ nebo Nginx 1.18+
- **SSL certifikát:** Pro HTTPS (povinné)
- **Přístup k Keycloak:** OpenID Connect server (login.cmi.cz)

### PHP rozšíření

```bash
# Zkontroluj nainstalovaná rozšíření
php -m | grep -E 'pdo|mysql|mbstring|curl|json|openssl'
```

Požadovaná rozšíření:
- `pdo`
- `pdo_mysql`
- `mbstring`
- `curl`
- `json`
- `openssl`

## 📦 Příprava produkčního prostředí

### 1. Vytvoření databáze

```bash
# Připoj se k MySQL
mysql -u root -p

# Vytvoř databázi
CREATE DATABASE cmi_inflace CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;

# Vytvoř uživatele
CREATE USER 'cmi_user'@'localhost' IDENTIFIED BY 'SecureP@ssw0rd!123';
GRANT ALL PRIVILEGES ON cmi_inflace.* TO 'cmi_user'@'localhost';
FLUSH PRIVILEGES;

EXIT;
```

### 2. Import databáze

Použij export z `export_phpmyadmin/` složky:

```bash
# phpMyAdmin import (doporučeno)
# 1. Nahraj CMI_INFLACE_EXPORT.zip
# 2. Rozbal
# 3. NEJDŘÍV importuj: cmi_inflace_PROCEDURY.sql (SQL záložka)
# 4. PAK importuj: cmi_inflace_STRUKTURA_DATA.sql (Import záložka)
```

**Nebo MySQL command line:**

```bash
cd export_phpmyadmin
mysql -u cmi_user -p cmi_inflace < cmi_inflace_PROCEDURY.sql
mysql -u cmi_user -p cmi_inflace < cmi_inflace_STRUKTURA_DATA.sql
```

### 3. Naklonuj repozitář

```bash
# SSH přístup (doporučeno)
cd /var/www
git clone git@github.com:Jarda25Work/cmi-inflace.git
cd cmi-inflace

# Nebo HTTPS
git clone https://github.com/Jarda25Work/cmi-inflace.git
cd cmi-inflace
```

### 4. Nastav oprávnění souborů

```bash
# Nastav vlastníka (Apache/Nginx user)
sudo chown -R www-data:www-data /var/www/cmi-inflace

# Složky: 755, Soubory: 644
sudo find /var/www/cmi-inflace -type d -exec chmod 755 {} \;
sudo find /var/www/cmi-inflace -type f -exec chmod 644 {} \;

# Config soubor jen pro vlastníka
sudo chmod 600 /var/www/cmi-inflace/web/includes/config.php
```

### 5. Vytvoř config.php

```bash
cd /var/www/cmi-inflace/web/includes
cp config.example.php config.php
nano config.php
```

**Produkční config.php:**

```php
<?php
/**
 * PRODUKČNÍ KONFIGURACE
 */

// Error reporting - VYPNOUT v produkci
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php/cmi-inflace-errors.log');

// Kódování
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Databáze
define('DB_HOST', 'localhost');
define('DB_NAME', 'cmi_inflace');
define('DB_USER', 'cmi_user');
define('DB_PASS', 'SecureP@ssw0rd!123');
define('DB_CHARSET', 'utf8mb4');

// OpenID Connect
define('OIDC_ISSUER', 'https://login.cmi.cz/auth/realms/CMI/');
define('OIDC_CLIENT_ID', 'production_client_id');
define('OIDC_CLIENT_SECRET', '');  // Pro public client
define('OIDC_REDIRECT_URI', 'https://meridla.cmi.cz/oidc_callback.php');
define('OIDC_SCOPES', 'openid profile email');

// Aplikační nastavení
define('APP_NAME', 'CMI Systém kalibrace měřidel');
define('ITEMS_PER_PAGE', 20);
define('CURRENT_YEAR', date('Y'));

// Session security - HTTPS POVINNÉ
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);  // HTTPS only
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);

// Časová zóna
date_default_timezone_set('Europe/Prague');

// PDO Connection
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Chyba připojení k databázi. Kontaktujte administrátora.");
        }
    }
    
    return $pdo;
}
?>
```

### 6. Vytvoř log složku

```bash
sudo mkdir -p /var/log/php
sudo chown www-data:www-data /var/log/php
sudo chmod 755 /var/log/php
```

## 🌐 Konfigurace webového serveru

### Apache Configuration

```bash
sudo nano /etc/apache2/sites-available/meridla.cmi.cz.conf
```

```apache
<VirtualHost *:80>
    ServerName meridla.cmi.cz
    Redirect permanent / https://meridla.cmi.cz/
</VirtualHost>

<VirtualHost *:443>
    ServerName meridla.cmi.cz
    ServerAdmin admin@cmi.cz
    
    DocumentRoot /var/www/cmi-inflace/web
    
    <Directory /var/www/cmi-inflace/web>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Zakáž přístup k includes/
    <Directory /var/www/cmi-inflace/web/includes>
        Require all denied
    </Directory>
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/meridla.cmi.cz.crt
    SSLCertificateKeyFile /etc/ssl/private/meridla.cmi.cz.key
    SSLCertificateChainFile /etc/ssl/certs/chain.crt
    
    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/meridla-error.log
    CustomLog ${APACHE_LOG_DIR}/meridla-access.log combined
</VirtualHost>
```

**Aktivuj konfiguraci:**

```bash
sudo a2ensite meridla.cmi.cz
sudo a2enmod ssl headers rewrite
sudo systemctl reload apache2
```

### Nginx Configuration

```bash
sudo nano /etc/nginx/sites-available/meridla.cmi.cz
```

```nginx
server {
    listen 80;
    server_name meridla.cmi.cz;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name meridla.cmi.cz;
    
    root /var/www/cmi-inflace/web;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /etc/ssl/certs/meridla.cmi.cz.crt;
    ssl_certificate_key /etc/ssl/private/meridla.cmi.cz.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    
    # Deny access to includes/
    location ~ ^/includes/ {
        deny all;
        return 404;
    }
    
    # PHP processing
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Logging
    access_log /var/log/nginx/meridla-access.log;
    error_log /var/log/nginx/meridla-error.log;
}
```

**Aktivuj konfiguraci:**

```bash
sudo ln -s /etc/nginx/sites-available/meridla.cmi.cz /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 🔐 Konfigurace Keycloak

### Přidání produkční Redirect URI

1. Přihlaš se do Keycloak admin konzole: https://login.cmi.cz/admin
2. Vyber realm: **CMI**
3. Naviguj: Clients → **publibtest**
4. Přidej do **Valid Redirect URIs:**
   ```
   https://meridla.cmi.cz/oidc_callback.php
   ```
5. Přidej do **Valid Post Logout Redirect URIs:**
   ```
   https://meridla.cmi.cz/
   ```
6. Ulož změny

### Vytvoření prvního admin uživatele

```bash
# Připoj se k databázi
mysql -u cmi_user -p cmi_inflace

# Vytvoř admin účet (přihlášení pouze přes OpenID)
INSERT INTO users (username, email, full_name, role, active)
VALUES ('admin', 'admin@cmi.cz', 'Administrator', 'admin', 1);
```

## 📊 Monitoring & Údržba

### Automatické zálohy

**Backup script** (`/var/scripts/backup_cmi_inflace.sh`):

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/cmi-inflace"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="cmi_inflace"
DB_USER="cmi_user"
DB_PASS="SecureP@ssw0rd!123"

# Vytvoř backup složku
mkdir -p $BACKUP_DIR

# Backup databáze
mysqldump -u $DB_USER -p$DB_PASS \
    --single-transaction \
    --routines \
    --triggers \
    $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Smaž backupy starší než 30 dní
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete

echo "Backup completed: db_$DATE.sql.gz"
```

**Nastavení cron:**

```bash
sudo chmod +x /var/scripts/backup_cmi_inflace.sh
sudo crontab -e

# Přidej řádek (každý den ve 2:00)
0 2 * * * /var/scripts/backup_cmi_inflace.sh >> /var/log/cmi-backup.log 2>&1
```

### Log rotation

```bash
sudo nano /etc/logrotate.d/cmi-inflace
```

```
/var/log/php/cmi-inflace-errors.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
}
```

### Monitoring audit logu

**Skript pro kontrolu podezřelé aktivity:**

```bash
#!/bin/bash
# /var/scripts/check_security_cmi.sh

# Zkontroluj neúspěšné pokusy o přihlášení
FAILED_LOGINS=$(mysql -u cmi_user -p$DB_PASS cmi_inflace -N -e \
    "SELECT COUNT(*) FROM audit_log WHERE akce='FAILED_LOGIN' AND created_at > NOW() - INTERVAL 1 HOUR")

if [ $FAILED_LOGINS -gt 10 ]; then
    echo "WARNING: $FAILED_LOGINS failed logins in last hour" | \
        mail -s "CMI Security Alert" admin@cmi.cz
fi
```

## 🔄 Aktualizace aplikace

### Standardní update

```bash
cd /var/www/cmi-inflace

# Záloha databáze před updatem
/var/scripts/backup_cmi_inflace.sh

# Pull changes z GitHubu
git fetch origin
git pull origin main

# Pokud jsou změny v DB schématu, aplikuj je
# mysql -u cmi_user -p cmi_inflace < sql/migrations/XXXX_update.sql

# Restartuj web server
sudo systemctl reload apache2  # nebo nginx
```

### Rollback

```bash
# Najdi předchozí commit
cd /var/www/cmi-inflace
git log --oneline -n 10

# Vrať se na předchozí verzi
git checkout <commit-hash>

# Obnov databázi ze zálohy
gunzip < /var/backups/cmi-inflace/db_XXXXXXXX_XXXXXX.sql.gz | \
    mysql -u cmi_user -p cmi_inflace
```

## ✅ Post-Deployment Checklist

- [ ] HTTPS funguje (force redirect z HTTP)
- [ ] SSL certifikát je platný
- [ ] Databáze je naimportovaná (všechny tabulky a procedury)
- [ ] `config.php` má produkční hodnoty
- [ ] Error reporting je vypnutý (`display_errors = 0`)
- [ ] Session security je zapnutá (`cookie_secure = 1`)
- [ ] Keycloak redirect URI je nastavená
- [ ] První admin účet je vytvořený
- [ ] OpenID přihlášení funguje
- [ ] Lze přidat/upravit/smazat měřidlo
- [ ] Automatické zálohy jsou nastavené (cron)
- [ ] Log rotation je nakonfigurovaná
- [ ] Security headers jsou aktivní (zkontroluj: securityheaders.com)
- [ ] File permissions jsou správné (755/644)
- [ ] `.git` složka není přístupná přes web
- [ ] Git nepublikuje `config.php`

## 🆘 Troubleshooting

### Chyba: "Database connection failed"

```bash
# Zkontroluj DB credentials v config.php
# Zkontroluj že MySQL běží
sudo systemctl status mysql

# Zkontroluj DB přístup
mysql -u cmi_user -p cmi_inflace
```

### Chyba: "OIDC login not working"

1. Zkontroluj Keycloak redirect URI
2. Ověř OIDC_ISSUER v config.php
3. Zkontroluj SSL certifikát
4. Zkontroluj firewall (port 443)

### Chyba: "#1305 - FUNCTION does not exist"

Importoval jsi v špatném pořadí. Řešení:
```bash
# Smaž databázi
mysql -u cmi_user -p -e "DROP DATABASE cmi_inflace;"
mysql -u cmi_user -p -e "CREATE DATABASE cmi_inflace CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;"

# NEJDŘÍV procedury
mysql -u cmi_user -p cmi_inflace < cmi_inflace_PROCEDURY.sql

# PAK data
mysql -u cmi_user -p cmi_inflace < cmi_inflace_STRUKTURA_DATA.sql
```

### Permission denied errors

```bash
sudo chown -R www-data:www-data /var/www/cmi-inflace
sudo find /var/www/cmi-inflace -type d -exec chmod 755 {} \;
sudo find /var/www/cmi-inflace -type f -exec chmod 644 {} \;
```

## 📞 Kontakt

V případě problémů:
- **GitHub Issues:** https://github.com/Jarda25Work/cmi-inflace/issues
- **Email:** admin@cmi.cz
