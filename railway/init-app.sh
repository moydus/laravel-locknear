#!/bin/bash
set -e

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Caching config + routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Syncing Meilisearch schema (if driver is meilisearch)..."
if [ "$SCOUT_DRIVER" = "meilisearch" ]; then
    php artisan locknear:setup-meilisearch || true
fi

echo "==> Init complete."
