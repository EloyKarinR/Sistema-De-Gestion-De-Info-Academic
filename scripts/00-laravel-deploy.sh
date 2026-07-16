#!/usr/bin/env bash
set -e

echo "Instalando dependencias de Composer..."
composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

echo "Enlazando storage..."
php artisan storage:link || true

echo "Cacheando config, rutas y vistas..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Corriendo migraciones..."
php artisan migrate --force
