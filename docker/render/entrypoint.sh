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

# Reset the demo account's data on every start (the scheduler also runs this daily).
# This runs before seeding: the seeder puts the demo profile back to its initial
# image, and after that demo:reset no longer knows which uploaded file to delete,
# so the file would stay on the disk forever. On an empty database (first start)
# there is no demo account yet and nothing is reset.
if ! php artisan demo:reset; then
    echo "WARNING: demo:reset failed. Starting without resetting demo data." >&2
fi

# A failed seeding does not stop the start: the site can run without re-seeding
if ! php artisan db:seed --force; then
    echo "WARNING: db:seed failed. Starting without seeding." >&2
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# The commands above run as root; let php-fpm (www-data) write uploaded images,
# compiled views and caches
chown -R www-data:www-data storage bootstrap/cache

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
