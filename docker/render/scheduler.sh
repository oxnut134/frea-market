#!/bin/sh
# Run the Laravel scheduler once a minute (like `php artisan schedule:work`).
#
# schedule:work prints "No scheduled commands are ready to run." every minute,
# which floods the logs. Drop only that line; everything else (the output of
# scheduled tasks and any errors) still goes to the container log.
cd /var/www

while true; do
    php artisan schedule:run 2>&1 | grep -v '^No scheduled commands are ready to run\.$'
    sleep $((60 - $(date +%s) % 60))
done
