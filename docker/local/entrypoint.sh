#!/bin/sh
set -e

if [ -d /var/www/html/storage ]; then
    mkdir -p \
        /var/www/html/storage/framework/cache \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache
    chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache || true
fi

exec "$@"
