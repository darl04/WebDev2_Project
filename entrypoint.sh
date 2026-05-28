#!/bin/bash
set -e

# Default PORT to 80 if Railway does not provide one
PORT="${PORT:-80}"

# Map MYSQL_URL to DATABASE_URL if DATABASE_URL is not set (common on Railway)
if [ -z "$DATABASE_URL" ] && [ -n "$MYSQL_URL" ]; then
    export DATABASE_URL="$MYSQL_URL"
    echo "Exported DATABASE_URL from MYSQL_URL"
fi

# Provide a default base URI for console commands that generate URLs.
if [ -z "$DEFAULT_URI" ]; then
    export DEFAULT_URI="http://localhost"
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

db_is_ready() {
    if [ -z "$DATABASE_URL" ]; then
        return 1
    fi

    php -r '
$databaseUrl = getenv("DATABASE_URL");
if ($databaseUrl === false || $databaseUrl === "") {
    exit(1);
}

$parts = parse_url($databaseUrl);
if ($parts === false || empty($parts["scheme"]) || empty($parts["host"])) {
    exit(1);
}

$scheme = $parts["scheme"] === "postgresql" ? "pgsql" : $parts["scheme"];
$host = $parts["host"];
$port = $parts["port"] ?? ($scheme === "pgsql" ? 5432 : 3306);
$database = isset($parts["path"]) ? ltrim($parts["path"], "/") : "";
$user = $parts["user"] ?? "";
$password = $parts["pass"] ?? "";

if ($database === "") {
    exit(1);
}

$dsn = $scheme === "pgsql"
    ? sprintf("pgsql:host=%s;port=%s;dbname=%s", $host, $port, $database)
    : sprintf("mysql:host=%s;port=%s;dbname=%s", $host, $port, $database);

try {
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    unset($pdo);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
'
}

DB_READY=0
if [ -n "$DATABASE_URL" ]; then
    for attempt in 1 2 3 4 5 6 7 8 9 10; do
        if db_is_ready; then
            DB_READY=1
            break
        fi

        echo "Database is not ready yet (attempt ${attempt}/10); retrying..."
        sleep 3
    done
else
    echo "DATABASE_URL is not set; skipping migrations and fixtures."
fi

if [ "$DB_READY" -eq 1 ]; then
    echo "Running migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true
else
    echo "Skipping migrations because the database is unavailable."
fi

if [ "$DB_READY" -eq 1 ] && php bin/console list --raw 2>/dev/null | grep -qx 'doctrine:fixtures:load'; then
    echo "Loading fixtures..."
    php bin/console doctrine:fixtures:load --append --no-interaction || true
else
    echo "Skipping fixtures because doctrine/fixtures-bundle is not available or the database is unavailable."
fi

echo "Substituting PORT_PLACEHOLDER with ${PORT} in nginx config..."
sed -i "s/PORT_PLACEHOLDER/${PORT}/g" /etc/nginx/conf.d/symfony.conf

echo "Testing nginx configuration..."
nginx -t

echo "Starting Nginx..."
exec nginx -g "daemon off;"