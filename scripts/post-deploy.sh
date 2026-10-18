#!/bin/sh
# Runs after every successful deploy (FlyNode "post-deploy command"). Safe to repeat.
set -e
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link --force || true
php artisan optimize:clear
