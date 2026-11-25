#!/bin/sh
set -eu

# Run Laravel's minute scheduler alongside the API server. Railway restarts
# the container if the main API process exits.
(
    while true; do
        php artisan schedule:run --no-interaction || true
        sleep 60
    done
) &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
