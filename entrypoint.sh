#!/bin/bash
set -e

# Map Railway / platform database URLs to DATABASE_URL when not set explicitly.
if [ -z "$DATABASE_URL" ]; then
    if [ -n "$MYSQL_URL" ]; then
        export DATABASE_URL="$MYSQL_URL"
        echo "Exported DATABASE_URL from MYSQL_URL"
    elif [ -n "$MYSQL_PRIVATE_URL" ]; then
        export DATABASE_URL="$MYSQL_PRIVATE_URL"
        echo "Exported DATABASE_URL from MYSQL_PRIVATE_URL"
    elif [ -n "$MYSQLHOST" ]; then
        export DATABASE_URL="mysql://${MYSQLUSER}:${MYSQLPASSWORD}@${MYSQLHOST}:${MYSQLPORT:-3306}/${MYSQLDATABASE}?serverVersion=8.0.32&charset=utf8mb4"
        echo "Built DATABASE_URL from MYSQLHOST/MYSQLUSER/MYSQLDATABASE"
    fi
fi

if [ -z "$DATABASE_URL" ]; then
    echo "Warning: DATABASE_URL is not set. Link a MySQL service in Railway and add a reference variable, or set DATABASE_URL manually."
fi

# Lexik JWT expects file paths in these vars. The image ships with an empty
# .env, so they must be set before any console command that boots the kernel.
if [ -z "$JWT_SECRET_KEY" ]; then
    export JWT_SECRET_KEY="/app/config/jwt/private.pem"
fi
if [ -z "$JWT_PUBLIC_KEY" ]; then
    export JWT_PUBLIC_KEY="/app/config/jwt/public.pem"
fi
if [ -z "$JWT_PASSPHRASE" ]; then
    if [ -n "$APP_SECRET" ]; then
        export JWT_PASSPHRASE="$APP_SECRET"
    else
        export JWT_PASSPHRASE="$(openssl rand -hex 32)"
        echo "Warning: JWT_PASSPHRASE was generated at startup. Set JWT_PASSPHRASE in Railway so tokens survive redeploys."
    fi
fi

# Set a default `DEFAULT_URI` when not provided by the environment.
if [ -z "$DEFAULT_URI" ]; then
    export DEFAULT_URI="http://localhost"
fi

# Defaults for vars normally in .env (container ships with an empty .env file).
export CORS_ALLOW_ORIGIN="${CORS_ALLOW_ORIGIN:-^https?://.*}"
export MESSENGER_TRANSPORT_DSN="${MESSENGER_TRANSPORT_DSN:-doctrine://default?auto_setup=0}"
export MAILER_DSN="${MAILER_DSN:-null://null}"
export GOOGLE_CLIENT_ID="${GOOGLE_CLIENT_ID:-unused}"
export GOOGLE_CLIENT_SECRET="${GOOGLE_CLIENT_SECRET:-unused}"

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

# Generate JWT key files when missing (paths are in JWT_SECRET_KEY / JWT_PUBLIC_KEY).
if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT keys (private.pem missing)..."
    mkdir -p config/jwt
    php bin/console lexik:jwt:generate-keypair --no-interaction || echo "Warning: Failed to generate JWT keys"
else
    echo "JWT keys already present; skipping key generation."
fi

# Console runs as root; PHP-FPM runs as www-data and must read/write var/ and JWT keys.
chown -R www-data:www-data /app/var /app/config/jwt 2>/dev/null || true
chmod -R 775 /app/var 2>/dev/null || true

echo "Starting PHP-FPM..."
php-fpm -D

echo "Waiting for PHP-FPM to start..."
sleep 2

# Railway (and similar platforms) route traffic to $PORT, not 80.
PORT="${PORT:-80}"
echo "Configuring Nginx to listen on port ${PORT}..."
sed "s/PORT_PLACEHOLDER/${PORT}/g" /etc/nginx/conf.d/symfony.conf > /tmp/symfony.conf
mv /tmp/symfony.conf /etc/nginx/conf.d/symfony.conf

run_migrations_when_db_ready() {
    wait_for_db() {
        echo "Waiting for database availability..."
        local i=0
        local max=60
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

        if [ "${APP_ENV:-prod}" != "prod" ]; then
            echo "Loading fixtures (non-production environment)..."
            php bin/console doctrine:fixtures:load --append --no-interaction || true
        else
            echo "Skipping fixtures in production environment."
        fi
    else
        echo "Database not reachable after timeout; skipping migrations and fixtures."
    fi
}

# Run migrations in the background so Nginx can bind $PORT immediately (avoids 502 on Railway).
run_migrations_when_db_ready &

nginx -t
echo "Starting Nginx on 0.0.0.0:${PORT}..."
exec nginx -g "daemon off;"