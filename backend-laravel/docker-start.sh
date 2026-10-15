#!/bin/sh
set -e

PORT="${PORT:-8000}"

echo ">> Preparing Laravel application on port $PORT..."

# Generate APP_KEY if not provided
if [ -z "$APP_KEY" ]; then
    echo ">> Generating application key..."
    php artisan key:generate --force
fi

# Link storage
php artisan storage:link || true

# Run database migrations if DB is configured
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ] && [ "$DB_HOST" != "localhost" ]; then
    echo ">> Running database migrations on $DB_HOST..."
    php artisan migrate --force --seed || true
fi

echo ">> Starting Laravel server on 0.0.0.0:$PORT..."
exec php artisan serve --host=0.0.0.0 --port="$PORT"
