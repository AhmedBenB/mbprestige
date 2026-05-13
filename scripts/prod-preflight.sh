#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/mbprestige"
ENV_FILE="$APP_DIR/.env"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE"
  exit 1
fi

required_env=(
  APP_ENV
  APP_DEBUG
  APP_URL
  DB_CONNECTION
  DB_HOST
  DB_PORT
  DB_DATABASE
  DB_USERNAME
  DB_PASSWORD
  QUEUE_CONNECTION
  SESSION_DRIVER
  CACHE_STORE
  MAIL_MAILER
  MAIL_HOST
  MAIL_PORT
  MAIL_USERNAME
  MAIL_PASSWORD
  MAIL_FROM_ADDRESS
)

echo "Checking required environment keys..."
for key in "${required_env[@]}"; do
  if ! grep -qE "^${key}=" "$ENV_FILE"; then
    echo "Missing key: $key"
    exit 1
  fi
done

echo "Checking Laravel production flags..."
grep -q '^APP_ENV=production' "$ENV_FILE" || { echo "APP_ENV must be production"; exit 1; }
grep -q '^APP_DEBUG=false' "$ENV_FILE" || { echo "APP_DEBUG must be false"; exit 1; }

echo "Running app checks..."
cd "$APP_DIR"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan route:list >/dev/null

echo "Preflight OK."
