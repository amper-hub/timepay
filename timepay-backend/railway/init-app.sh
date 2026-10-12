#!/bin/sh
set -eu

# Railway runs this as a controlled pre-deploy step with the production DB vars.
# Never use migrate:fresh or db:wipe here; existing attendance and payroll data
# must remain intact across releases.
php artisan migrate --force
