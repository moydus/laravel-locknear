#!/bin/sh
set -e

cd /var/www/app

php artisan optimize
php artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisord.conf
