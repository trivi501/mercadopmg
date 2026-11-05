#!/usr/bin/env bash
# Prepara un servidor Ubuntu 24.04 limpio para mercados.gpe.gob.mx.
# Uso (una sola vez, como usuario con sudo):  sudo bash deploy/instalar-servidor.sh
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "Ejecute con sudo." >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

echo "==> Paquetes base"
apt-get update
apt-get install -y nginx mysql-server supervisor git unzip curl acl certbot python3-certbot-nginx

echo "==> PHP 8.3 y extensiones"
apt-get install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-bcmath \
    php8.3-intl php8.3-gd php8.3-zip php8.3-curl php8.3-sqlite3 php8.3-opcache

echo "==> Composer"
if ! command -v composer >/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Node.js 22"
if ! command -v node >/dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
    apt-get install -y nodejs
fi

echo "==> Ajustes de PHP para producción"
cat > /etc/php/8.3/fpm/conf.d/99-mercadopmg.ini <<'INI'
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 120
expose_php = Off
date.timezone = America/Mexico_City
opcache.enable = 1
opcache.memory_consumption = 192
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
INI
cp /etc/php/8.3/fpm/conf.d/99-mercadopmg.ini /etc/php/8.3/cli/conf.d/99-mercadopmg.ini
sed -i 's/^opcache.validate_timestamps = 0/opcache.validate_timestamps = 1/' /etc/php/8.3/cli/conf.d/99-mercadopmg.ini
systemctl restart php8.3-fpm

echo "==> Firewall"
if command -v ufw >/dev/null; then
    ufw allow OpenSSH
    ufw allow 'Nginx Full'
    ufw --force enable
fi

echo "==> Directorio de la aplicación"
mkdir -p /var/www/mercadopmg /var/backups/mercadopmg
chown "${SUDO_USER:-root}":www-data /var/www/mercadopmg

echo
echo "Servidor listo. Versiones instaladas:"
php -v | head -1
composer --version
node -v
mysql --version
