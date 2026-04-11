#!/bin/sh
set -e

# Cache config/routes/views for production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Run migrations
php artisan migrate --force

exec "$@"
