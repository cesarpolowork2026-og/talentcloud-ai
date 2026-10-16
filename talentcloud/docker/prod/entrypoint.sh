#!/bin/sh
# Prepara el contenedor y ejecuta el comando pedido.
#   php-fpm                      -> servidor de la aplicación (queda como root; sus procesos usan www-data)
#   php artisan queue:work ...   -> cola, como www-data
#   php artisan schedule:work    -> programador, como www-data
set -e
cd /var/www/html

# Los volúmenes de Docker se montan como root: la aplicación necesita poder escribir en storage.
chown -R www-data:www-data storage bootstrap/cache

# Guarda en caché la configuración, las rutas, los eventos y las vistas (se lee el entorno de este contenedor).
runuser -u www-data -- php artisan optimize

if [ "$1" = "php-fpm" ]; then
    exec "$@"
fi

exec runuser -u www-data -- "$@"
