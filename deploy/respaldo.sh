#!/bin/sh
# Copia de seguridad de la base (pg_dump) y de los CV subidos. Conserva los últimos 7 días.
#
#   ./respaldo.sh                       -> deja los archivos en ~/respaldos
#   DESTINO=/mnt/disco ./respaldo.sh    -> en otra carpeta
#
# Programarlo cada noche con cron (crontab -e):
#   30 2 * * * /home/<usuario>/talentcloud/deploy/respaldo.sh >> /home/<usuario>/respaldo.log 2>&1
set -eu

cd "$(dirname "$0")"
DESTINO="${DESTINO:-$HOME/respaldos}"
FECHA="$(date +%F)"
COMPOSE="docker compose -f docker-compose.prod.yml"

mkdir -p "$DESTINO"

# Base de datos (las variables POSTGRES_* ya están dentro del contenedor).
$COMPOSE exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB"' | gzip > "$DESTINO/base-$FECHA.sql.gz"

# CV: viven en el volumen de storage, no en la base. Si todavía no hay ninguno, no hay nada que copiar.
$COMPOSE exec -T app sh -c 'cd /var/www/html/storage/app/private && [ -d cvs ] && tar -cz cvs || true' > "$DESTINO/cvs-$FECHA.tar.gz"

# Un archivo vacío no es una copia: avisar en vez de guardarlo.
if ! gzip -t "$DESTINO/base-$FECHA.sql.gz" 2>/dev/null || [ "$(wc -c < "$DESTINO/base-$FECHA.sql.gz")" -lt 1000 ]; then
    echo "ERROR: la copia de la base de datos salió vacía o dañada" >&2
    exit 1
fi

find "$DESTINO" -name 'base-*.sql.gz' -mtime +7 -delete
find "$DESTINO" -name 'cvs-*.tar.gz' -mtime +7 -delete

echo "Copia lista en $DESTINO ($FECHA)"
