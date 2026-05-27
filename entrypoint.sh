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
php bin/console cache:clear --env=prod --no-debug || true
php bin/console cache:warmup --env=prod --no-debug || true

echo "Starting PHP-FPM..."
php-fpm -F &
PHP_PID=$!

echo "Waiting for PHP-FPM to start..."
sleep 2

# Ensure JWT keys exist
if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT keys..."
    mkdir -p config/jwt
    php bin/console lexik:jwt:generate-keypair --no-interaction || echo "Warning: Failed to generate JWT keys"
fi

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

echo "Loading fixtures..."
php bin/console doctrine:fixtures:load --append --no-interaction || true

echo "Starting Nginx..."
nginx -g "daemon off;"

wait $PHP_PID