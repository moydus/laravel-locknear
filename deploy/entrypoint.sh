#!/bin/sh
set -e

cd /var/www/app

php artisan optimize
php artisan vendor:publish --tag=filament-assets --force --quiet
php artisan migrate --force

exec /usr/bin/supervisord -c /etc/supervisord.conf
