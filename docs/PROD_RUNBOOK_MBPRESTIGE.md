# MBPRESTIGE - Runbook Production (Hetzner + Laravel + Cloudflare + Brevo)

Ce document est le plan d'execution officiel pour passer en production.

## 0) Ce que le repo fournit deja

- Template Nginx: `deploy/nginx/mbprestige.conf`
- Template Supervisor workers: `deploy/supervisor/mbprestige-worker.conf`
- Template cron scheduler: `deploy/cron/mbprestige-scheduler.cron`
- Script de preflight prod: `scripts/prod-preflight.sh`
- Script post-deploiement: `scripts/prod-post-deploy.sh`
- Variables de prod documentees dans `.env.example`

## 1) Serveur Hetzner

Recommande:
- Ubuntu 24.04
- CPX21 minimum (CPX31 ideal)
- cle SSH obligatoire

Bootstrap:
```bash
apt update && apt upgrade -y
adduser mbprestige
usermod -aG sudo mbprestige
```

## 2) Stack Laravel

```bash
apt install -y nginx mysql-server redis-server git unzip curl supervisor cron
apt install -y php php-cli php-fpm php-mysql php-sqlite3 php-xml php-mbstring php-curl php-zip php-bcmath php-intl php-gd
```

Composer:
```bash
cd /tmp
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
composer --version
```

## 3) Deploiement projet

```bash
cd /var/www
git clone <URL_GIT> mbprestige
cd mbprestige
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
chown -R www-data:www-data /var/www/mbprestige
chmod -R 775 storage bootstrap/cache
```

## 4) Base MySQL

```sql
CREATE DATABASE mbprestige CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mbprestige_user'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE_FORT';
GRANT ALL PRIVILEGES ON mbprestige.* TO 'mbprestige_user'@'localhost';
FLUSH PRIVILEGES;
```

Puis:
```bash
php artisan migrate --force
php artisan db:seed --force
```

## 5) Nginx

Copier le template:
```bash
cp deploy/nginx/mbprestige.conf /etc/nginx/sites-available/mbprestige
ln -s /etc/nginx/sites-available/mbprestige /etc/nginx/sites-enabled/mbprestige
nginx -t
systemctl reload nginx
```

Adapter la socket PHP si necessaire (`fastcgi_pass`).

## 6) Domaine + Cloudflare

- Passer les nameservers du domaine vers Cloudflare.
- DNS:
  - `A @ -> IP serveur (Proxied)`
  - `CNAME www -> @ (Proxied)`
- SSL/TLS:
  - `Full (strict)` quand certificat origine en place.
  - `Always Use HTTPS` ON.

## 7) Option Tunnel Cloudflare

Si tunnel:
- Installer `cloudflared` sur le serveur.
- Creer tunnel `mbprestige-prod` dans le dashboard Cloudflare.
- Mapper:
  - `ton-domaine.fr -> http://localhost:80`
  - `www.ton-domaine.fr -> http://localhost:80`

## 8) Scheduler + Queue workers

Scheduler:
```bash
crontab -e
```
Ajouter la ligne de `deploy/cron/mbprestige-scheduler.cron`.

Workers Supervisor:
```bash
cp deploy/supervisor/mbprestige-worker.conf /etc/supervisor/conf.d/
supervisorctl reread
supervisorctl update
supervisorctl start mbprestige-worker:*
```

## 9) Brevo SMTP

Dans `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=TON_LOGIN_SMTP_BREVO
MAIL_PASSWORD=TA_CLE_SMTP_BREVO
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=contact@ton-domaine.fr
MAIL_FROM_NAME="MBPRESTIGE"
```

Attention: utiliser la **cle SMTP Brevo**, pas la cle API REST.

## 10) Hardening minimum prod

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
apt install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban
```

Si tunnel Cloudflare uniquement, limiter encore plus les ports entrants.

## 11) Procedure de release

Preflight:
```bash
bash scripts/prod-preflight.sh
```

Deploy:
```bash
bash scripts/prod-post-deploy.sh
```

## 12) Check-list go-live

- `APP_ENV=production` et `APP_DEBUG=false`
- HTTPS force
- Scheduler actif
- Workers actifs
- SMTP Brevo teste
- Backup BDD quotidien configure
- Mot de passe admin temporaire remplace
- Stripe en test valide (webhook compris), puis passage live
