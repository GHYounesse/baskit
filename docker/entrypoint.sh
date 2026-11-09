#!/bin/sh
# Container entrypoint. Runs as www-data, then hands over to the real command.
#
# Everything here depends on the runtime environment (the .env file is only
# available when the container starts)
set -eu

cd /var/www/html

# The storage volume starts empty on a new server.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs

if [ "${RUN_OPTIMIZE:-false}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan storage:link --force
fi

# The web container serves static files from a shared volume. Refresh it on every
# start so a new deploy never leaves stale hashed assets behind. Only the app
# service sets PUBLIC_SYNC_DIR.
if [ -n "${PUBLIC_SYNC_DIR:-}" ] && [ -d "$PUBLIC_SYNC_DIR" ]; then
    find "$PUBLIC_SYNC_DIR" -mindepth 1 -delete
    cp -a public/. "$PUBLIC_SYNC_DIR"/
    rm -f "$PUBLIC_SYNC_DIR/storage"
fi

exec "$@"
