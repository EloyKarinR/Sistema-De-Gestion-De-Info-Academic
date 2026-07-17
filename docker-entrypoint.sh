#!/usr/bin/env bash
set -e

cd /var/www/html

php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

exec /start.sh
