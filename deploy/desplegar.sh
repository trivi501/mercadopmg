#!/usr/bin/env bash
# Publica la última versión de la rama main en https://mercados.gpe.gob.mx.
# Uso (como el usuario dueño del código, desde cualquier carpeta):
#   bash /var/www/mercadopmg/deploy/desplegar.sh
set -euo pipefail

APP_DIR=/var/www/mercadopmg
BRANCH=${BRANCH:-main}

cd "$APP_DIR"

trap 'echo; echo "ERROR: el despliegue falló. El sitio sigue en mantenimiento."; echo "Corrija el problema y ejecute: php artisan up"' ERR

echo "==> Modo mantenimiento"
php artisan down --retry=15 || true

echo "==> Código ($BRANCH)"
git fetch origin "$BRANCH"
git reset --hard "origin/$BRANCH"

echo "==> Dependencias PHP"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> Frontend"
# Sin caché de rutas: Wayfinder debe ver las rutas nuevas, no las del despliegue anterior.
php artisan optimize:clear
php artisan wayfinder:generate --with-form
npm ci --no-audit --no-fund
npm run build

echo "==> Base de datos"
php artisan migrate --force
# Permisos nuevos de cada versión (idempotente: sólo sincroniza roles y permisos).
php artisan db:seed --class=RolesAndPermissionsSeeder --force

echo "==> Caché de configuración, rutas, vistas y eventos"
php artisan optimize:clear
php artisan optimize
php artisan storage:link 2>/dev/null || true

echo "==> Permisos"
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache

echo "==> Reiniciar PHP y la cola"
sudo systemctl reload php8.3-fpm
php artisan queue:restart

php artisan up
trap - ERR

echo
echo "Desplegado: $(git log -1 --format='%h %s')"
curl -fsS -o /dev/null -w "Verificación https://mercados.gpe.gob.mx/up -> HTTP %{http_code}\n" https://mercados.gpe.gob.mx/up || echo "Aviso: /up no respondió; revise storage/logs."
