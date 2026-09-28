#!/usr/bin/env bash

set -e

echo "--- Instalando dependencias de composer ---"
composer install --no-dev --optimize-autoloader --no-interaction

echo "--- Aegurando la base de datos SQLITE ---"
mkdir -p database
touch database/database.sqlite

echo "--- Creando enlace simbolico ara imagenes de comprobantes---"
php artisan storage:link || true

echo "--- Ejecutando migraciones y seeders ---"
php artisan migrate --force

echo "--- Limpiando caches---"
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "--- Despliegue listo---"