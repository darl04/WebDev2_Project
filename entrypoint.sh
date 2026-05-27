#!/bin/bash
set -e

# Map MYSQL_URL to DATABASE_URL if DATABASE_URL is not set (common on Railway)
if [ -z "$DATABASE_URL" ] && [ -n "$MYSQL_URL" ]; then
    export DATABASE_URL="$MYSQL_URL"
    echo "Exported DATABASE_URL from MYSQL_URL"
fi

# Set a default `DEFAULT_URI` when not provided by the environment.
if [ -z "$DEFAULT_URI" ]; then
    export DEFAULT_URI="http://localhost"
fi

# Ensure Symfony cache is (re)built using the runtime environment
# variables that Railway provides. Warming at runtime prevents the
# image from containing baked-in DB credentials from build time.
echo "Clearing and warming Symfony cache with runtime env vars..."
# Note: we must not remove `/app/.env` here because the Symfony Runtime
# attempts to read it very early during process bootstrap. Even an empty
# `.env` file prevents a PathException when the platform doesn't provide
# one. The image build will create an empty `.env` file instead of
# baking secrets into it.
php bin/console cache:clear --env=prod --no-debug || true
php bin/console cache:warmup --env=prod --no-debug || true

echo "Starting PHP-FPM..."
php-fpm -F &
PHP_PID=$!

echo "Waiting for PHP-FPM to start..."
sleep 2

# Ensure JWT keys exist
# Ensure JWT keys exist — only attempt if keys are missing and we're not
# relying on a JWT secret environment variable. If your deployment uses
# `JWT_SECRET_KEY` or other env-based configuration, set those in Railway
# instead of forcing key generation here.
if [ ! -f config/jwt/private.pem ] && [ -z "$JWT_SECRET_KEY" ]; then
        echo "Generating JWT keys (private.pem missing and JWT_SECRET_KEY not set)..."
        mkdir -p config/jwt
        php bin/console lexik:jwt:generate-keypair --no-interaction || echo "Warning: Failed to generate JWT keys"
else
        echo "Skipping JWT key generation (keys exist or JWT_SECRET_KEY provided)."
fi

# Wait for the database to become available before running migrations or
# loading fixtures. This prevents connection-refused errors during startup
# when the DB provisioned by Railway isn't immediately ready.
wait_for_db() {
    echo "Waiting for database availability..."
    local i=0
    local max=30
    while [ $i -lt $max ]; do
        if php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1; then
            echo "Database is available."
            return 0
        fi
        i=$((i+1))
        sleep 2
    done
    return 1
}

if wait_for_db; then
    echo "Running migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

    # Only load fixtures in non-production environments to avoid destructive
    # operations on production data. Use an explicit deploy job if you need
    # to seed production data.
    if [ "${APP_ENV:-prod}" != "prod" ]; then
        echo "Loading fixtures (non-production environment)..."
        php bin/console doctrine:fixtures:load --append --no-interaction || true
    else
        echo "Skipping fixtures in production environment."
    fi
else
    echo "Database not reachable after timeout; skipping migrations and fixtures."
fi

echo "Starting Nginx..."
nginx -g "daemon off;"

wait $PHP_PID