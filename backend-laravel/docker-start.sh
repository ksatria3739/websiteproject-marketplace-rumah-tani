#!/bin/sh
set -e

PORT="${PORT:-8000}"

echo ">> Preparing Laravel application on port $PORT..."

# Ensure .env file exists for Laravel commands
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        touch .env
    fi
fi

# Generate APP_KEY if not provided
if [ -z "$APP_KEY" ]; then
    echo ">> Generating application key..."
    php artisan key:generate --force || true
fi

# Link storage
php artisan storage:link || true

# Run database migrations
if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ] && [ "$DB_HOST" != "localhost" ]; then
    echo ">> Running database migrations on cloud MySQL ($DB_HOST)..."
    php artisan migrate --force --seed || true
else
    echo ">> Running self-contained SQLite database..."
    mkdir -p database
    touch database/database.sqlite
    chmod 777 database/database.sqlite
    php artisan migrate --force --seed || true
fi

echo ">> Starting Laravel server on 0.0.0.0:$PORT..."
exec php artisan serve --host=0.0.0.0 --port="$PORT"
