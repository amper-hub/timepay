#!/bin/bash
set -e

php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
