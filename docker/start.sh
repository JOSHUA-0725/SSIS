#!/bin/sh
set -e
cd /var/www/html

# Render tells the container which port to listen on.
PORT="${PORT:-10000}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Laravel needs a 32-byte key. Build a stable one from APP_SECRET when APP_KEY is not set.
if [ -z "$APP_KEY" ]; then
    if [ -n "$APP_SECRET" ]; then
        APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", getenv("APP_SECRET"), true));')"
    else
        APP_KEY="$(php artisan key:generate --show)"
    fi
    export APP_KEY
fi

if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

export DB_CONNECTION="${DB_CONNECTION:-sqlite}"

if [ "$DB_CONNECTION" = "sqlite" ]; then
    # The free plan has no permanent disk, so the demo database is rebuilt on every start.
    export DB_DATABASE="${DB_DATABASE:-/var/www/html/storage/app/ssis.sqlite}"
    rm -f "$DB_DATABASE"
    touch "$DB_DATABASE"
    php artisan migrate:fresh --seed --force
else
    php artisan migrate --force
    if [ "$SEED_ON_START" = "true" ]; then
        php artisan db:seed --force
    fi
fi

php artisan config:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
