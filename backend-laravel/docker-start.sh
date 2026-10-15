#!/bin/sh
set -e

PORT="${PORT:-8000}"

echo ">> Preparing Laravel application on port $PORT..."

# Generate dynamic .env from environment variables so worker processes get full config
APP_KEY="${APP_KEY:-base64:6b39wI1yV/z785w1T/M6WJvR1k5Lz3m/2y4x3x5x6x7=}"
DB_CONNECTION="${DB_CONNECTION:-sqlite}"

cat <<EOF > /app/.env
APP_NAME=RumahTani
APP_ENV=${APP_ENV:-production}
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}

DB_CONNECTION=${DB_CONNECTION}
DB_HOST=${DB_HOST:-}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-test}
DB_USERNAME=${DB_USERNAME:-}
DB_PASSWORD=${DB_PASSWORD:-}

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
EOF

# Link storage
php artisan storage:link || true

# Clear any cached config
php artisan config:clear || true

# Ensure database directory exists
mkdir -p /app/database

# Attempt migration based on configured connection
MIGRATED=0

if [ "$DB_CONNECTION" = "mysql" ] && [ -n "$DB_HOST" ] && [ "$DB_HOST" != "127.0.0.1" ] && [ "$DB_HOST" != "localhost" ]; then
    echo ">> Attempting database migration on Cloud MySQL ($DB_HOST)..."
    if php artisan migrate --force --seed; then
        echo ">> Successfully connected and seeded Cloud MySQL!"
        MIGRATED=1
    else
        echo ">> Cloud MySQL connection failed! Falling back to built-in SQLite..."
    fi
fi

if [ "$MIGRATED" -eq 0 ]; then
    echo ">> Initializing and seeding built-in SQLite database..."
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' /app/.env
    sed -i 's|^DB_DATABASE=.*|DB_DATABASE=/app/database/database.sqlite|' /app/.env
    rm -f /app/database/database.sqlite
    touch /app/database/database.sqlite
    chmod 777 /app/database/database.sqlite
    php artisan config:clear || true
    php artisan migrate:fresh --force --seed
    echo ">> Built-in SQLite database successfully created and seeded!"
fi

echo ">> Starting Laravel server on 0.0.0.0:$PORT..."
exec php artisan serve --host=0.0.0.0 --port="$PORT"
