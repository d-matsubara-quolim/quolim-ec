#!/bin/sh
set -e

# ホストからマウントした storage 配下は、PHP（www-data）が書き込めるようにする
mkdir -p storage/logs storage/framework/sessions storage/framework/cache
chown -R www-data:www-data storage/logs storage/framework/sessions storage/framework/cache

exec "$@"
