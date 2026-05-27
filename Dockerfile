# =========================
# Builder Stage
# =========================
FROM php:8.3-fpm AS builder

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    nodejs \
    npm \
    libpq-dev \
    default-libmysqlclient-dev \
    libzip-dev \
    zlib1g-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

# Allow Composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copy composer files first for caching
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader

# Copy application files
COPY . .

# Create default .env if missing
RUN if [ ! -f /app/.env ]; then \
    DB_URL=${DATABASE_URL:-${MYSQL_URL:-mysql://root@127.0.0.1:3306/app_db?serverVersion=8.0}}; \
    echo "APP_ENV=prod\n\
APP_DEBUG=false\n\
APP_SECRET=ChangeMe\n\
DEFAULT_URI=http://localhost\n\
DATABASE_URL=$DB_URL\n\
MAILER_DSN=null://null\n\
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0\n" > /app/.env; \
    fi

# Install Symfony Redis Messenger
RUN composer require symfony/redis-messenger --no-interaction

# Optimize autoloader
RUN composer install \
    --no-interaction \
    --optimize-autoloader \
    --no-ansi

# Warm Symfony cache
RUN php bin/console cache:warmup --env=prod --no-debug


# =========================
# Runtime Stage
# =========================
FROM php:8.3-fpm AS runtime

# Set working directory
WORKDIR /app

# Install runtime dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    build-essential \
    default-libmysqlclient-dev \
    libzip-dev \
    zlib1g-dev \
    && docker-php-ext-install pdo pdo_mysql bcmath zip \
    && rm -rf /var/lib/apt/lists/*

# Copy app from builder
COPY --from=builder /app /app

# Copy PHP extensions/config
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/

# Set permissions
RUN mkdir -p /app/var && \
    chown -R www-data:www-data /app && \
    chmod -R 755 /app && \
    chmod -R 775 /app/var

# Configure Nginx
COPY nginx-main.conf /etc/nginx/nginx.conf

RUN rm -rf /etc/nginx/conf.d/* \
    /etc/nginx/sites-enabled \
    /etc/nginx/sites-available

COPY nginx.conf /etc/nginx/conf.d/symfony.conf

# Copy entrypoint
COPY entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Healthcheck
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port
EXPOSE 80

# Start container
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]