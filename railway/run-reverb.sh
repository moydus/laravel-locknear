#!/bin/bash
set -e

echo "==> Starting Reverb WebSocket server..."
# PORT is injected by Railway automatically
php artisan reverb:start \
    --host=0.0.0.0 \
    --port="${PORT:-8080}" \
    --no-interaction
