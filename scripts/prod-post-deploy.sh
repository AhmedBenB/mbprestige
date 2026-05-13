#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/mbprestige"

echo "[1/8] Pull latest code"
cd "$APP_DIR"
git pull --ff-only

echo "[2/8] Install PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "[3/8] Run migrations"
php artisan migrate --force

echo "[4/8] Clear old caches"
php artisan optimize:clear

echo "[5/8] Build production caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[6/8] Restart queue workers"
php artisan queue:restart

echo "[7/8] Reload nginx"
sudo systemctl reload nginx

echo "[8/8] Quick health check"
php artisan about

echo "Deployment finished."
