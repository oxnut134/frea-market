#!/bin/sh
set -e

PORT="${PORT:-10000}"
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/sites-available/default

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Expose storage/app/public as public/storage (used while IMAGE_DISK=public)
php artisan storage:link

php artisan migrate --force
php artisan db:seed --force

# The seeders run as root; let php-fpm (www-data) write uploaded images
chown -R www-data:www-data storage/app/public

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
