#!/bin/sh
# Pull the latest code and deploy it on the VPS (see docs/getting-started/vps.md).
# Run as the deploy user from anywhere: sh /var/www/content-machine/deploy/vps/update.sh

set -eu

cd /var/www/content-machine

php artisan down --retry=30 || true

git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan cm:deploy
php artisan optimize

php artisan up

# Workers keep old code in memory until they restart.
php artisan queue:restart
