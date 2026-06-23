#!/bin/bash
set -e

echo "==> Starting Laravel scheduler loop..."
while true; do
    php artisan schedule:run --no-interaction
    sleep 60
done
