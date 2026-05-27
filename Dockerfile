# Multi-stage build: builder installs dependencies, prepares Symfony assets, and warms the production cache.
FROM php:8.3-fpm AS builder

# Set the working directory for all following commands.
WORKDIR /app

# Install required tools for Composer, Git, and frontend build assets.
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    nodejs \
    npm \
    libpq-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally so Composer commands are available.
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Allow Composer to run as root in the container.
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy dependency manifests first to leverage Docker caching.
COPY composer.json composer.lock ./

# Install PHP dependencies including redis-messenger
RUN composer install --no-interaction --no-scripts --optimize-autoloader && \
    composer require symfony/redis-messenger --no-interaction

# Copy the application source after dependencies are cached.
COPY . .

# If a `.env` file exists in the repository it would be copied into the
# image above. To avoid baking local credentials into the image, remove
# any copied `.env` and replace it with an empty file that the Symfony
# Runtime can read without throwing a PathException.
RUN if [ -f /app/.env ]; then rm -f /app/.env; fi && \
    if [ -f /app/.env.local.php ]; then rm -f /app/.env.local.php; fi && \
    touch /app/.env && chmod 644 /app/.env

# # Install frontend dependencies and build assets
# RUN npm install && npm run build

# Do not create a `.env` file at build time — runtime environment
# variables must be used by the container. Creating `.env` during
# image build bakes configuration (like `DATABASE_URL`) into the
# cached Symfony config which can cause connection issues in hosts
# like Railway where credentials are provided at runtime.

# Reinstall dependencies and optimize the autoloader for production.
# We include redis-messenger here and ignore platform reqs to ensure it installs even if the extension check is finicky in the container.
RUN composer require symfony/redis-messenger --no-interaction --ignore-platform-reqs && \
    composer install --no-interaction --optimize-autoloader --no-ansi || true

# Do not warm Symfony cache at build time. Cache must be warmed at
# container start using runtime environment variables so that
# resolved configuration (like `DATABASE_URL`) uses the correct
# credentials provided by Railway at runtime.


FROM php:8.3-fpm AS runtime

# Set the working directory inside the runtime container.
WORKDIR /app

# Install nginx and curl for request handling and health checks.
RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Copy the prepared application from the builder stage.
COPY --from=builder /app /app

# Safely extract extensions from builder
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# Create runtime directories and fix permissions for the web server user.
RUN mkdir -p /app/var && \
    chown -R www-data:www-data /app && \
    chmod -R 755 /app && \
    chmod -R 775 /app/var

# Use the main nginx configuration file for the Symfony app.
COPY nginx-main.conf /etc/nginx/nginx.conf

# Remove default nginx site configs and add the Symfony site configuration.
RUN rm -rf /etc/nginx/conf.d/* /etc/nginx/sites-enabled /etc/nginx/sites-available
COPY nginx.conf /etc/nginx/conf.d/symfony.conf

# Copy and enable the container entrypoint script.
COPY entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Healthcheck verifies the app is serving HTTP correctly.
HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=3 \
    CMD sh -c 'curl -f "http://127.0.0.1:${PORT:-80}/" || exit 1'

# Expose HTTP port 80 from the container.
EXPOSE 80

# Start the container using the custom entrypoint.
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]