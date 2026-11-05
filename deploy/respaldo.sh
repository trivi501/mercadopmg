#!/usr/bin/env bash
# Respaldo comprimido de la base de datos; conserva 30 días.
# Lo ejecuta cron a diario (deploy/cron/mercadopmg). Manual: sudo bash deploy/respaldo.sh
set -euo pipefail

APP_DIR=/var/www/mercadopmg
DEST=/var/backups/mercadopmg
DAYS=30

env_value() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

DB=$(env_value DB_DATABASE)
CNF=$(mktemp)
trap 'rm -f "$CNF"' EXIT
chmod 600 "$CNF"
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' \
    "$(env_value DB_USERNAME)" "$(env_value DB_PASSWORD)" "$(env_value DB_HOST)" "$(env_value DB_PORT)" > "$CNF"

mkdir -p "$DEST"
FILE="$DEST/${DB}-$(date +%Y%m%d-%H%M).sql.gz"

mysqldump --defaults-extra-file="$CNF" --single-transaction --routines --triggers --no-tablespaces "$DB" | gzip > "$FILE"
chmod 600 "$FILE"
find "$DEST" -name "${DB}-*.sql.gz" -mtime +"$DAYS" -delete

echo "$(date '+%F %T') respaldo creado: $FILE ($(du -h "$FILE" | cut -f1))"
