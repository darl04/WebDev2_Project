#!/bin/bash
set -e

# Default PORT to 80 if Railway does not provide one
PORT="${PORT:-80}"

# Map MYSQL_URL to DATABASE_URL if DATABASE_URL is not set (common on Railway)
if [ -z "$DATABASE_URL" ] && [ -n "$MYSQL_URL" ]; then
    export DATABASE_URL="$MYSQL_URL"
    echo "Exported DATABASE_URL from MYSQL_URL"
fi

echo "Starting PHP-FPM..."
php-fpm -D

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

echo "Substituting PORT_PLACEHOLDER with ${PORT} in nginx config..."
sed -i "s/PORT_PLACEHOLDER/${PORT}/g" /etc/nginx/conf.d/symfony.conf

echo "Testing nginx configuration..."
nginx -t

echo "Starting Nginx..."
exec nginx -g "daemon off;"