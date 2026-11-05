# Despliegue en producción — https://mercados.gpe.gob.mx

Servidor: **Ubuntu 24.04** · Nginx · PHP-FPM 8.3 · MySQL 8 · Supervisor · Node 22.
Proyecto: **mercadopmg** en `/var/www/mercadopmg`, base de datos y usuario MySQL `mercadopmg`.

Archivos de apoyo en [`deploy/`](../deploy):

| Archivo                             | Destino en el servidor                    |
| ----------------------------------- | ----------------------------------------- |
| `instalar-servidor.sh`              | se ejecuta una vez (paquetes del sistema) |
| `.env.production.example`           | `/var/www/mercadopmg/.env`                |
| `nginx/mercadopmg.conf`             | `/etc/nginx/sites-available/mercadopmg`   |
| `supervisor/mercadopmg-worker.conf` | `/etc/supervisor/conf.d/`                 |
| `cron/mercadopmg`                   | `/etc/cron.d/mercadopmg`                  |
| `desplegar.sh`                      | actualizaciones posteriores               |
| `respaldo.sh`                       | respaldo diario de MySQL (lo llama cron)  |

## 0. Antes de empezar

- El DNS de `mercados.gpe.gob.mx` debe apuntar (registro A) a la IP pública del servidor, y los puertos 80 y 443 deben estar abiertos. Sin esto Certbot no puede emitir el certificado.
- Todos los cambios deben estar en GitHub (`git push origin main`): el servidor descarga el código desde ahí.

## 1. Preparar el servidor (una sola vez)

```bash
ssh usuario@IP_DEL_SERVIDOR
sudo apt-get update && sudo apt-get install -y git
git clone https://github.com/trivi501/mercadopmg.git /tmp/mercadopmg-setup
sudo bash /tmp/mercadopmg-setup/deploy/instalar-servidor.sh
```

> El repositorio es privado: use una _deploy key_ (paso 3) o un token personal de GitHub para este primer `clone`.

## 2. Base de datos

```bash
sudo mysql_secure_installation
DB_PASS=$(openssl rand -base64 24 | tr -d '/+=')
echo "Contraseña MySQL de mercadopmg: $DB_PASS"   # guárdela, va en el .env
sudo mysql <<SQL
CREATE DATABASE mercadopmg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mercadopmg'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON mercadopmg.* TO 'mercadopmg'@'localhost';
FLUSH PRIVILEGES;
SQL
```

## 3. Código

Llave de despliegue (solo lectura) para que el servidor descargue de GitHub:

```bash
ssh-keygen -t ed25519 -C "deploy@mercados.gpe.gob.mx" -f ~/.ssh/mercadopmg -N ""
cat ~/.ssh/mercadopmg.pub
```

Agregue la llave pública en GitHub → _trivi501/mercadopmg_ → **Settings → Deploy keys → Add deploy key** (sin permiso de escritura). Luego:

```bash
cat >> ~/.ssh/config <<'EOF'
Host github.com
    IdentityFile ~/.ssh/mercadopmg
    IdentitiesOnly yes
EOF
git clone git@github.com:trivi501/mercadopmg.git /var/www/mercadopmg
cd /var/www/mercadopmg
```

## 4. Configuración (.env)

```bash
cp deploy/.env.production.example .env
nano .env        # DB_PASSWORD, ADMIN_EMAIL, ADMIN_PASSWORD y datos SMTP (todo lo marcado CAMBIAR)
chmod 640 .env && sudo chgrp www-data .env
```

## 5. Instalación de la aplicación

```bash
cd /var/www/mercadopmg
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force          # roles, permisos, catálogos base y administrador (sin datos demo)
php artisan storage:link
sudo chgrp -R www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

> Si dejó `ADMIN_PASSWORD` vacío, `db:seed` muestra **una sola vez** la contraseña generada para el usuario `admin`. Anótela.

## 6. Importar el padrón 2026

Desde su computadora, suba el Excel al servidor:

```bash
scp "PADRON COMERCIO DE LA VIA PUBLICA ( VIA PUBLICA,TIANGUIS Y JARDIN JUAREZ) 2026.xlsx" usuario@IP_DEL_SERVIDOR:/tmp/padron-2026.xlsx
```

En el servidor:

```bash
cd /var/www/mercadopmg
php artisan padron:importar /tmp/padron-2026.xlsx --dry-run    # revisa hojas y renglones sin guardar
php artisan padron:importar /tmp/padron-2026.xlsx              # importa (1,246 comerciantes)

# Quita los catálogos de ejemplo del seeder ("Tianguis Municipal", "Ruta 01") si quedaron sin comerciantes
php artisan tinker --execute="Auth::setUser(App\Models\User::where('username','admin')->first()); foreach ([App\Models\Tianguis::where('name','Tianguis Municipal')->first(), App\Models\MarketRoute::where('name','Ruta 01')->first()] as \$m) if (\$m && \$m->merchants()->doesntExist()) { \$m->delete(); echo 'Eliminado: '.\$m->name.PHP_EOL; }"

rm /tmp/padron-2026.xlsx
```

El comando no duplica comerciantes si se ejecuta dos veces.

## 7. Nginx y HTTPS

```bash
sudo cp deploy/nginx/mercadopmg.conf /etc/nginx/sites-available/mercadopmg
sudo ln -sf /etc/nginx/sites-available/mercadopmg /etc/nginx/sites-enabled/mercadopmg
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

sudo certbot --nginx -d mercados.gpe.gob.mx --redirect --agree-tos -m CORREO_RESPONSABLE@gpe.gob.mx
```

Certbot renueva el certificado solo (`systemctl list-timers | grep certbot`).

## 8. Cola, tareas programadas y respaldos

```bash
sudo cp deploy/supervisor/mercadopmg-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status mercadopmg-worker:*

sudo cp deploy/cron/mercadopmg /etc/cron.d/mercadopmg
sudo chmod 644 /etc/cron.d/mercadopmg
sudo bash deploy/respaldo.sh        # primer respaldo de prueba → /var/backups/mercadopmg
```

## 9. Cachés y verificación

```bash
php artisan optimize
curl -I https://mercados.gpe.gob.mx/up          # debe responder 200
```

Abra https://mercados.gpe.gob.mx, inicie sesión con `admin` y:

1. Cambie la contraseña del administrador.
2. En **Configuración** escriba el nombre del municipio (aparece en tickets y reportes).
3. Cree los usuarios de recaudadores, inspectores y capturistas en **Usuarios**.

## Actualizaciones posteriores

Después de hacer `git push` a `main`:

```bash
ssh usuario@IP_DEL_SERVIDOR
bash /var/www/mercadopmg/deploy/desplegar.sh
```

El script pone el sitio en mantenimiento, descarga `main`, instala dependencias, compila el frontend, migra, regenera cachés, reinicia la cola y verifica `/up`. Descarta cualquier cambio hecho a mano en el código del servidor (el `.env` y `storage/` no se tocan).

## Operación

| Tarea                        | Comando                                                                                 |
| ---------------------------- | --------------------------------------------------------------------------------------- |
| Ver errores de la aplicación | `tail -f /var/www/mercadopmg/storage/logs/laravel-$(date +%F).log`                      |
| Ver errores de Nginx         | `sudo tail -f /var/log/nginx/mercadopmg.error.log`                                      |
| Estado de la cola            | `sudo supervisorctl status mercadopmg-worker:*`                                         |
| Respaldo manual              | `sudo bash /var/www/mercadopmg/deploy/respaldo.sh`                                      |
| Restaurar un respaldo        | `gunzip -c /var/backups/mercadopmg/ARCHIVO.sql.gz \| mysql -u mercadopmg -p mercadopmg` |
| Anualidades de un ejercicio  | `php artisan padron:anualidades 2027` (cron lo hace solo cada 1 de enero)               |
| Mantenimiento manual         | `php artisan down` / `php artisan up`                                                   |

Copie periódicamente `/var/backups/mercadopmg` fuera del servidor: un respaldo en el mismo disco no protege contra la pérdida del servidor.
