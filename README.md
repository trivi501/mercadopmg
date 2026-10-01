# Sistema Integral de Padrón de Comerciantes y Cobranza de Vía Pública

Sistema municipal para administrar el padrón de comerciantes en vía pública, tianguis y plazas: registro de comerciantes, ubicación, cobro de boletos y anualidades, emisión de tickets, reimpresión controlada, cancelación, reportes y auditoría.

Consta de:

- **Panel web** — Laravel 13 + Inertia + React + Tailwind (este repositorio).
- **API REST `/api/v1`** — consumida por la app Android de campo (Sanctum, tokens Bearer).
- **App Android** — Kotlin + Jetpack Compose (fase siguiente; ver `docs/API.md`).

Documentación:

- [`docs/ARQUITECTURA.md`](docs/ARQUITECTURA.md) — arquitectura, diagrama ER, reglas de negocio.
- [`docs/API.md`](docs/API.md) — referencia de la API v1 y protocolo de sincronización.

## Requisitos

- PHP 8.3+ con extensiones `pdo_mysql`, `gd`, `bcmath`, `intl`, `mbstring`, `zip`
- MySQL 8+
- Composer 2
- Node.js 20+ y npm

## Instalación (desarrollo)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure la base de datos en `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mercadopmg
DB_USERNAME=root
DB_PASSWORD=
APP_TIMEZONE=America/Mexico_City
APP_LOCALE=es
```

```bash
php artisan migrate --seed   # en local también carga datos de demostración
npm install
npm run build
composer run dev             # servidor + Vite + cola + logs
```

Usuarios de desarrollo (contraseña `password`): `admin`, `recaudador`, `inspector`, `capturista`.

## Pruebas y calidad

```bash
php artisan test      # pruebas (SQLite en memoria)
composer ci:check     # lint JS, TypeScript, Pint, PHPStan nivel 7 y pruebas
```

## Producción

1. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...` (HTTPS obligatorio: la app móvil envía tokens).
2. Defina `ADMIN_EMAIL` y `ADMIN_PASSWORD` antes de sembrar; si omite la contraseña se genera una aleatoria y se muestra una sola vez.
3. Instalación:

    ```bash
    composer install --no-dev --optimize-autoloader
    npm ci && npm run build
    php artisan migrate --force
    php artisan db:seed --force          # roles, permisos, catálogos base y admin (sin datos demo)
    php artisan config:cache && php artisan route:cache && php artisan view:cache
    ```

4. Programe el worker de colas (`php artisan queue:work`) con Supervisor.
5. Al inicio de cada ejercicio fiscal genere las anualidades pendientes:

    ```bash
    php artisan padron:anualidades 2027
    ```

    (También se generan solas al registrar un comerciante o consultar su estado de cuenta.)

6. Respalde la base de datos diariamente; recibos, reimpresiones y auditoría no se eliminan nunca.

## Roles

| Rol           | Puede                                                                        |
| ------------- | ---------------------------------------------------------------------------- |
| Administrador | Todo                                                                         |
| Recaudador    | Consultar padrón, cobrar, imprimir y reimprimir tickets, consultar historial |
| Inspector     | Consultar padrón, ubicación, situación y pagos                               |
| Capturista    | Registrar y modificar comerciantes, consultar padrón                         |

Los permisos se definen en `app/Enums/Permission.php` y se aplican con `php artisan db:seed --class=RolesAndPermissionsSeeder`.
