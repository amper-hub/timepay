#!/bin/sh
set -eu

npm run build

mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
