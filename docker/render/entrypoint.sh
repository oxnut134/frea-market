#!/bin/sh
set -e

PORT="${PORT:-10000}"
sed -i "s/__PORT__/${PORT}/g" /etc/nginx/sites-available/default

# APP_KEY must come from the environment. Generating one here would change it
# on every start and invalidate all cookies and sessions.
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Run 'php artisan key:generate --show' locally and set the value as an environment variable." >&2
    exit 1
fi

# Expose storage/app/public as public/storage (used while IMAGE_DISK=public)
php artisan storage:link

# A failed migration stops the start: do not serve with a mismatched schema
php artisan migrate --force

# A failed seeding does not: the site can run without re-seeding
if ! php artisan db:seed --force; then
    echo "WARNING: db:seed failed. Starting without seeding." >&2
fi

# Reset the demo account's data on every start (the scheduler also runs this daily)
if ! php artisan demo:reset; then
    echo "WARNING: demo:reset failed. Starting without resetting demo data." >&2
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# The commands above run as root; let php-fpm (www-data) write uploaded images,
# compiled views and caches
chown -R www-data:www-data storage bootstrap/cache

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
